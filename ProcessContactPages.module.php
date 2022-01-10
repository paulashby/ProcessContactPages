<?php namespace ProcessWire;

class ProcessContactPages extends Process {

  public static function getModuleinfo() {
    return [
      "title" => "Process Contact Pages",
      "summary" => "Handles contact form submissions with GDPR integrations.",
      "author" => "Paul Ashby, primitive.co",
      "version" => 1,
      "singular" => true,
      'autoload' => true,
      "installs" => ["PageMaker", "VersionControl"],
      "page" => [
        "name" => "contact",
        "title" => "Contact",
      ],
      "permission" => "contact-page-view"
    ];
  }
  public function ready() {
    $this->addHookBefore("Modules::saveConfig", $this, "customSaveConfig");
  }
  public function init() {
    
    // include supporting files (css, js)
    parent::init();

    $this->token_name = $this->session->CSRF->getTokenName("pcp_token");
    $this->token_value = $this->session->CSRF->getTokenValue("pcp_token");

    $this->addHookBefore("Modules::uninstall", $this, "customUninstall");
    $this->addHookAfter("InputfieldForm::render", $this, "customInputfieldFormRender");
    $this->addHookAfter("ProcessModule::executeEdit", $this, "nag");
    $this->addHookBefore('ProcessLogin::login', $this, "preventPendingAcctLogin");
  }
/**
 * Nag user to populate privacy policy
 *
 * @param  HookEvent $event
 */
  public function nag($event) {

    $this->checkPrivacyPolicy();
  }
/**
 * Prevent login by users whose accounts are not yet approved
 *
 * @param  HookEvent $event
 */
  protected function preventPendingAcctLogin ($event) {

    $sanitizer = wire('sanitizer');
    $name = $event->arguments(0);
    $prfx = $this["prfx"];
    $u = wire("users")->get($sanitizer->selectorValue($name));

    if($u["{$prfx}_pending"] === 1){

      // Prevent login
      $event->arguments(0, "account approval pending");
    }
  }
/**
 * Store info for created elements and pass to completeInstall function
 *
 * @param  HookEvent $event
 */
  public function customSaveConfig($event) {
    
    $class = $event->arguments(0);
    $page_path = $this->page->path();
    if($class !== $this->className || $page_path !== "/processwire/module/") return;
    
    // Config input
    $data = $event->arguments(1);
    $modules = $event->object;
    $page_maker = $this->modules->get("PageMaker");
    $configured = array_key_exists("configured", $data);

    if($configured) {

      $curr_config = $this->modules->getConfig($this->className);

      if ($this->approveConfig($curr_config, $data)) {

        // All good - config unchanged
        $event->arguments(1, $data);

      } else {

        // Show error for attempted changes and resubmit existing data.
        $this->session->error("You can only change these settings by reinstalling the module. But please be aware that this will mean losing the current contact data");

        $event->arguments(1, $curr_config);
      }
    } else {
     
      // Installing - fine to update config

      $data["configured"] = true; // Set flag

      // Where to install module pages
      $contact_root_id = $data["contact_root_location"];
      $contact_root = $this->pages->get($contact_root_id);

      if($contact_root->id) {
        $contact_root_path = $contact_root->path();
      } else {
        // Install in root if not provided 
        $contact_root = wire("pages")->get(1);
        $data["contact_root_location"] = 1;
        $contact_root_path = "/";
      }

      $prfx = $data["prfx"];

      // Create array of required pages containing three associative arrays whose member keys are the names of the respective elements
      $pgs = array(
        "fields" => array(
          "{$prfx}_markup" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Form markup", "config"=>array("markup")),
          "{$prfx}_document" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Document markup", "config"=>array("ck_editor")),
          "{$prfx}_email" => array("fieldtype"=>"FieldtypeEmail", "label"=>"Contact email address"),
          "{$prfx}_signature" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Email signature", "config"=>array("ck_editor", "markup")),
          "{$prfx}_ref" => array("fieldtype"=>"FieldtypeText", "label"=>"Contact reference code"),
          "{$prfx}_pending" => array("fieldtype"=>"FieldtypeCheckbox", "label"=>"Account awaiting approval"),
          "{$prfx}_submission" => array("fieldtype"=>"FieldtypeText", "label"=>"Contact submission", "config"=>array("html_ee")),
          "{$prfx}_status" => array("fieldtype"=>"FieldtypeText", "label"=>"Submission status"),
        ),
        "templates" => array(
          "{$prfx}-section" => array("t_parents" => array("{$prfx}-section")),
          "{$prfx}-section-active" => array("t_parents" => array("{$prfx}-section", "{$prfx}-section-active"), "t_children" => array("{$prfx}-section-active", "{$prfx}-message")),
          "{$prfx}-submitter" => array("t_parents" => array("{$prfx}-section-active"), "t_children" => array("{$prfx}-message"), "t_fields"=>array("{$prfx}_email")),
          "{$prfx}-registrations" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-message")),
          "{$prfx}-setting-forms" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-form")),
          "{$prfx}-setting-signature" => array("t_parents" => array("{$prfx}-section"), "t_fields"=>array("{$prfx}_signature")),
          "{$prfx}-form" => array("t_parents" => array("{$prfx}-setting-forms"), "t_fields"=>array("{$prfx}_markup")),
          "{$prfx}-setting-documents" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-document")),
          "{$prfx}-document" => array("t_parents" => array("{$prfx}-section-documents"), "t_fields"=>array("{$prfx}_document")),
          "{$prfx}-message" => array("t_parents" => array("{$prfx}-submitter"), "t_fields"=>array("{$prfx}_submission", "{$prfx}_status"))
        ),
        "pages" => array(
          "contact-pages" => array("template" => "{$prfx}-section", "parent"=>$contact_root_path, "title"=>"Contact Pages", "status"=>array("hidden")),
          "settings" => array("template" => "{$prfx}-section", "parent"=>"{$contact_root_path}contact-pages/", "title"=>"Settings"),
          "active" => array("template" => "{$prfx}-section", "parent"=>"{$contact_root_path}contact-pages/", "title"=>"Active"),
          "documents" => array("template" => "{$prfx}-setting-documents", "parent"=>"{$contact_root_path}contact-pages/settings/", "title"=>"Documents"),
          "forms" => array("template" => "{$prfx}-setting-forms", "parent"=>"{$contact_root_path}contact-pages/settings/", "title"=>"Forms"),
          "email-signature" => array("template" => "{$prfx}-setting-signature", "parent"=>"{$contact_root_path}contact-pages/settings/", "title"=>"Email Signature"),
          "contacts" => array("template" => "{$prfx}-section-active", "parent"=>"{$contact_root_path}contact-pages/active/", "title"=>"Contacts"),
          "registrations" => array("template" => "{$prfx}-registrations", "parent"=>"{$contact_root_path}contact-pages/active/", "title"=>"Registrations"),
          "privacy-policy" => array("template" => "{$prfx}-document", "parent"=>"{$contact_root_path}contact-pages/settings/documents/", "title"=>"Privacy Policy"),
          "tools" => array("template" => "{$prfx}-section", "parent"=>"{$contact_root_path}contact-pages/", "title"=>"Tools") 
        )
      );

      // t_access is a comma-separated list of roles with view access to contact pages
      $t_access = $data["t_access"];
      
      if(is_string($t_access) && strlen($t_access)) {
        $access_roles_array = explode(",", $t_access);
        $t_access = array("view"=>$access_roles_array);
        $t_name = "{$prfx}-section";
        $pgs["templates"][$t_name]["t_access"] = $t_access;
      }

      $made_pages = $page_maker->makePages("contact_pages", $pgs, true, true);

      if($made_pages === true) {

        // Activate version control on forms and documents that need to be tracked for gdpr
        $version_controlled = array(
          "fields" => array("{$prfx}_markup", "{$prfx}_document", "{$prfx}_status"),
          "templates" => array("{$prfx}-form", "{$prfx}-document", "{$prfx}-message")
        );
        
        $this->activateVersionControl($version_controlled);

        // Store paths to top level pages
        $data["paths"] = array(
          "forms" => $contact_root_path . "contact-pages/settings/forms",
          "documents" => $contact_root_path . "contact-pages/settings/documents",
          "contacts" => $contact_root_path . "contact-pages/active/contacts",
          "registrations" => $contact_root_path . "contact-pages/active/registrations"
        );

        // Store initial value of next_id for use when adding new users to the system (form submissions/registration requests)
        $data["next_id"] = "1";

        // Add ref (which will be associated with records of data deletion) and tmp_pw (temporary password used for initial pw reset) fields to user template - these will be populated only for users added via registration form
        //TODO: Does ref field belong in this module, or should it be part of the GDPR module?
        $usr_template = wire("templates")->get("user");
        $ufg = $usr_template->fieldgroup;
        $ref_f = wire("fields")->get("{$prfx}_ref");
        $ufg->add($ref_f);
        $submission_f = wire("fields")->get("{$prfx}_submission");
        $ufg->add($submission_f);
        $pending_f = wire("fields")->get("{$prfx}_pending");
        $ufg->add($pending_f);
        $ufg->save();

      } else {
        
        foreach ($made_pages as $e) {
          $this->error($e);
        }
        throw new WireException($e . ". Please uninstall the module then try again using a unique prefix.");
      } 
      $event->arguments(1, $data);
    }    
  }   
/**
 * Retrieve HTML markup for given document
 *
 * @param String $doc_title - title of the required document
 * @return String HTML markup
 */
  public function renderDocument($doc_title) {

    $sanitizer = wire('sanitizer');
    $prfx = $this["prfx"];

    $page = wire("pages")->get("template=" . $sanitizer->selectorValue("{$prfx}-document") . ", title=" . $sanitizer->selectorValue($doc_title));

    if($page->id){
      $data = $page["{$prfx}_document"];

      if($data){
        $purifier = wire("modules")->get('MarkupHTMLPurifier');
        return $purifier->purify($data);
      }
      throw new WireException("$doc_title contains no data");
    }
    throw new WireException("$doc_title does not exist"); 
  }  
/**
 * Generate HTML markup for multiple consecutive forms
 *
 * @param Array $options - contains options array for each required form [ "title"=>String (title to use when outputting form), "form"=>String (title of page with form markup field), "handler"=>String (path to js file)]
 * @return String HTML markup
 */
  public function renderForms($options){

    $handlers = array();
    $forms_out = "";

    foreach ($options as $form_options) {
      
      $markup = $this->renderForm($form_options["form"]);

      if($markup){
        // Add paths to javascript form handlers if not already in $handlers array
        $new_handler = array_key_exists("handler", $form_options) && ! in_array($form_options["handler"], $handlers);
        
        if($new_handler){
          $handlers[] = $form_options["handler"];
        }
        $title = $form_options["title"];
        $forms_out .= "<h2 class='form__title'>$title</h2>";

        if(array_key_exists("pre", $form_options)) {
          // Include any additional elements before form
          $pre = $form_options["pre"];
          $forms_out .= "<div class='pcp_pre'>$pre</div>";
        }

        $forms_out .= $markup; 

        if(array_key_exists("post", $form_options)) {
          // Include any additional elements after form
          $post = $form_options["post"];
          $forms_out .= "<div class='pcp_post'>$post</div>";
        }
      }
    }
    $out = "<div class='pcp_forms'>";

    foreach ($handlers as $handler_url) {
      $out .= "<script src='$handler_url'></script>";
    }
    $out .= $forms_out;
    $out .= "</div><!-- End pcp_forms -->";
    return $out;
  }  
/**
 * Generate HTML markup for given form
 *
 * @param String $form_title
 * @param String $handler_url - path to js file to handle form submission
 * @return String HTML markup
 */
  public function renderForm($form_title, $handler_url = false) {

    // Don't want to present a form to user if privacy policy has not been populated
    if($this->checkPrivacyPolicy()){

      $sanitizer = wire('sanitizer');
      $prfx = $this["prfx"];

      // Get page with form markup

      $form_page = wire("pages")->get("template=" . $sanitizer->selectorValue("{$prfx}-form") . ", title=" . $sanitizer->selectorValue($form_title));
      $markup = $form_page["{$prfx}_markup"];

      if(!$markup) return;

      // Include script tag for handler if provided
      $open = $handler_url ? "<script src='$handler_url'></script><div class='pcp_form'>" : "<div class='pcp_form'>";
      $close = "<p class='form__error form__error--submission'>No Error</p></div>";

      // Include token to mitigate CSRF
      $token_name = $this->token_name;
      $token_value = $this->token_value;

      $base = wire("config")->urls;
      $cssUrl = $base->InputfieldPassword."InputfieldPassword.css";

      // Array keys correspond to placeholder text in form markup. Value is the replacement string.
      $placeholders = array(
        "css-import-placeholder" => "<link rel='stylesheet' href='" . $base->InputfieldPassword . "InputfieldPassword.css'>",
        "jquery-import-placeholder" => "<script src='" . $base->JqueryCore . "JqueryCore.js'></script>",
        "complexify-import-placeholder" => "<script src='" . $base->InputfieldPassword . "complexify/jquery.complexify.min.js'></script>",
        "complexifybanlist-import-placeholder" => "<script src='" . $base->InputfieldPassword . "complexify/jquery.complexify.banlist.js'></script>",
        "xregexp-import-placeholder" => "<script src='" . $base->JqueryCore . "xregexp.min.js'></script>",
        "inputfieldpassword-import-placeholder" => "<script src='" . $base->InputfieldPassword . "InputfieldPassword.js'></script>",
        "csrf-token-placeholder" => "<input type='hidden' id='submission_token' name='$token_name' value='$token_value'>"
      );

      // Replace placeholders with live values
      foreach ($placeholders as $key => $value) {
        $markup = str_replace("<$key>", $value, $markup);
      }
      return $open . $markup . $close; 
    }
    throw new WireException("Privacy Policy must be populated before forms can be submitted");
  }
/**
 * Process form submission - create new entry in /contact-pages/active/contacts/submitter 
 *
 * @param Array $params - submitted with form - these MUST be pre-santized and validated
 * @param String $submission_type - "contact" or "registration"
 * @return JSON - success true with message or success false with conflated error message
 */
  public function processSubmission($params, $submission_type) {

    $sanitizer = wire('sanitizer');
    $email = $params["email"];
    $prfx = $this["prfx"];
    $submitter_tmplt = "{$prfx}-submitter";
    $submitter_parent = "{$submission_type}s";

    if(array_key_exists($submitter_parent, $this["paths"])){
      $parent_str = $this["paths"][$submitter_parent];
    } else {
      // Default to contacts if path not set
      $parent_str = $this["paths"]["contacts"];
    }
    $submitter = wire("pages")->get("parent=" . $sanitizer->selectorValue($parent_str) . ",{$prfx}_email=" . $sanitizer->selectorValue($email));
    $registration = $submission_type === "registration";

    if(array_key_exists("username", $params)){
      $username = $params["username"]; 
      $pass = $params["pass"];
    }
    
    $params["timestamp"] = $this->timestampNow();
    $exists = wire("users")->get("email=" . $sanitizer->selectorValue($email))->id;

    if($submitter->id){

      $submission_parent = $submitter;

      if($registration) {
        // Pending request exists for this email address
        $this->existingUserNotification($email, true);
        return true;
      }
    
    } else if($registration && $exists){
      // Email in use on existing account
      $this->existingUserNotification($email);
      return true;
    } else {

      $item_data = array(
        "title" => $this->getID(),
        "{$prfx}_email" => $email
      );
      // Make parent page for submissions from this email address
      $submission_parent = wire("pages")->add($submitter_tmplt, $parent_str, $item_data);
    }
    // Use number of current submissions from this email address to get numerical suffix
    $submissions_from_usr = $submission_parent->numChildren();
    $submissions_from_usr++;

    $title_sffx = "-$submissions_from_usr";
    $submitter_tmplt = "{$prfx}-message";
    
    /*
     * Registrations go straight to "Processed". So they show "Accepted"/"Rejected" buttons instead of a "Processed" button like Contacts.
     * This is because it turns out that 'backgound checks' for registrations only take a few mins, so the "Pending" to "Processed" stage is overkill
     */
    $submission_status = $registration ? "Processed" : "Pending";
    // Remove password from submission data
    unset($params["pass"]);
    unset($params["_pass"]);

    $encoded_submission = json_encode($params);

    $item_data = array(
      "title" => $submission_parent->title . $title_sffx,
      "{$prfx}_submission" => $encoded_submission,
      "{$prfx}_status" => $submission_status
    );
    $submission = wire("pages")->add($submitter_tmplt, $submission_parent->url, $item_data);

    if( ! $submission->id) return json_encode(array("error"=>"There was a problem submitting your message. Please try again later"));

    if($registration) {

      // fname is now shop name, lname is contact name
      $account_settings = array(
        "username" => $username,
        "display_name" => $params["lname"] . ", " . $params["fname"],
        "email" => $email,
        "pass" => $pass,
        "parent_title" => $submission->parent->title,
        "encoded_submission" => $encoded_submission
      );

      $customer_account = $this->createUserAccount($account_settings);

      if(gettype($customer_account) === "string") return json_encode(array("success"=>false, "error"=>$customer_account)); 
    }
    return true;  
  }  
/**
 * Get id string for submission from new contact
 *
 * @return String - the id
 */
  protected function getID() {
    $next_id = $this["next_id"];
    $id = ucfirst($this["prfx"]) . "-$next_id";
    $settings = wire("modules")->getConfig($this->className);
    $next_id++;
    $settings["next_id"] = $next_id;
    wire("modules")->saveConfig($this->className, $settings);
    return $id;
  }
/**
 * Retrieve historical contact form submission
 *
 * @param String $encoded_str - JSON string with HTML entity encoding
 * @param Boolean $parse_as_array - return array, else object
 * @return Array or Object
 */
  protected function getContactSubmission($encoded_str, $parse_as_array = false) {
    // This will be called when accessing historical submissions which are stored as JSON strings in textarea fields
    // If parse_as_array is true this will return an array, else an object
    $data = json_decode(wire("sanitizer")->unentities($encoded_str));
    return $parse_as_array ? get_object_vars($data) : $data;
  }
/**
 * Check if Privacy Policy is populated and display warning on back end if not.
 * Note: this only checks that the policy isn't completely empty
 *
 * @return Boolean
 */
  protected function checkPrivacyPolicy() {

    $sanitizer = wire('sanitizer');
    $contact_root = wire("pages")->get($this["contact_root_location"]);
    $system_exists = count($contact_root->children("name=contact-pages, include=hidden"));
    
    if( ! $system_exists) return true; // Don't throw exception if system has been removed
    
    $prfx = $this["prfx"];

    $page = wire("pages")->get("template=" . $sanitizer->selectorValue("{$prfx}-document") . ", title=Privacy Policy");

    if($page->id){
      $page_path = $page->path();
      if($page[$this["prfx"] . "_document"]) return true;
      $this->warning("Please populate your Privacy Policy at $page_path. Forms will throw errors until the policy is updated."); 
      return false;
    }
    throw new WireException("Privacy Policy page does not exist.");
  }
/**
 * Send notification email when registraton is submitted for existing user
 *
 * @param String $to - the recipient
 * @param Boolean $pending - true if an existing request is pending, else request is from user with active account
 */
  protected function existingUserNotification($to, $pending = false) {

    if($pending){
      $message = array("Hi,", "It looks like you submitted multiple registration requests on the Paper Bird site. To keep things simple, we'll only be able to process your initial request.", "Best wishes");
    } else {
      $message = array("Hi,", "It looks like you just tried to register on the Paper Bird website. The request wasn't processed as your email address is already in use on an existing account. If you've forgotten your password, you can reset it by clicking the 'Forgot Password?' link on the log in form.", "Best wishes");
    }

    $this->sendHTMLmail($to, "Registration Problem", $message);
  }
/**
 * Custom uninstall 
 * 
 * @param HookEvent $event
 */
  public function customUninstall($event) {

    $sanitizer = wire('sanitizer');
    $class = $event->arguments(0);
    if($class !== $this->className) return;

    $page_maker = $this->modules->get("PageMaker");
    $page_maker_config = $this->modules->getConfig("PageMaker"); 

    /* 
     * Warn if Contact Pages tree exists. There may be ongoing submissions in addition to
     * various custom forms, the privacy policy, email signature etc in the settings section
    */
    $prfx = $this["prfx"];
    $contact_pages = wire("pages")->get("template=" . $sanitizer->selectorValue("$prfx-section") . ", name=contact-pages");

    if($contact_pages->id){
      
      // There are active contacts - abort uninstall
      $this->error("The module could not be uninstalled as live data exists. If you want to proceed, you can remove all order data from the Admin/Contact page and try again.");
      $event->replace = true; // prevent uninstall
      $this->session->redirect("./edit?name=$class"); 

    } else {

      // Safe to proceed - remove ref and tmp_pw fields from user template
      $prfx = $this["prfx"];
      
      //TODO: Are we having ref field in this module or will it ultimately be part of GDPR module?

      // Remove custom fields from user fieldgroup - these will be themselves removed by the removeSet call to PageMaker module
      $usr_template = wire("templates")->get("user");
      $ufg = $usr_template->fieldgroup;

      $ufg->remove("{$prfx}_ref");
      $ufg->remove("{$prfx}_submission");
      $ufg->remove("{$prfx}_pending");
      $ufg->save();

      /*
      Remove the fields and templates of the contact system pages
      $report_pg_errs false as pages will already have been removed via the button on the Contact admin page
      */
      $page_maker->removeSet("contact_pages", false);

      parent::uninstall();
    } 
  }
/**
 * Apply field and template settings
 *
 * @param Array $init_settings Contains arrays of field and template names to initialise
 */
  protected function activateVersionControl($versionControlled) {

    $vc_data = wire("modules")->getConfig("VersionControl");

    foreach ($versionControlled["fields"] as $f_name) {

      $f = wire("fields")->get($f_name);

      // Activate version control
      $vc_data["enabled_fields"][] = $f->id;
    }
    foreach ($versionControlled["templates"] as $t_name) {

      // Activate version control
      $vc_data["enable_all_templates"] = false;
      $vc_data["enabled_templates"][] = wire("templates")->get($t_name)->id;
    }
    wire("modules")->saveModuleConfigData("VersionControl", $vc_data);
  }
/**
 * Check for changes to immutable array items
 * @param Array $curr_config Existing config array
 * @param Array $new_config New config array
 * @return Boolean false or the current config
 */
  protected function approveConfig($curr_config, $new_config) {

    $immutable = array(
      // Config entries that can't be changed after installation
      "contact_root_location",
      "prfx",
      "t_access",
      "reg_roles"
    );

    foreach ($new_config as $key => $val) {

      if( array_key_exists($key, $curr_config) &&
          $new_config[$key] !== $curr_config[$key] &&
          in_array($key, $immutable)){
        return false;
      }
    }
    return true;
  } 
/**
 * Remove top margin from button
 *
 * @param  HookEvent $event
 */
  public function customInputfieldFormRender($event) {

    if($this->page->path === '/processwire/contact/'){

      $return = $event->return;

      $event->return = str_replace(
        array("uk-margin-top", "ui-button ui-widget ui-state-default ui-corner-all"), 
        array("", "ui-button ui-widget ui-state-default ui-corner-all"), 
        $return);
    }

  }
  // Contact page
  public function ___execute() {

    if($this->input->post->submit) {

      // Process button clicks
      $sanitizer = wire('sanitizer');
      $form = $this->modules->get("InputfieldForm");
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $prfx = $this["prfx"];
        $operation = $this->sanitizer->text($this->input->post->submit); // Current status of the submission
        $submission = $this->sanitizer->text($this->input->post->submission); // Title of the submission page

        // Update submission status
        $submission_page = wire("pages")->get("name=" . $sanitizer->selectorValue($submission));

        if( ! $submission_page->id){

          $err_mssg = array("There was an error while updating status of $submission to $operation");

          $admin_email = $this["contact_admin_email"];

          $this->sendHTMLmail($admin_email, "Error - submission page could not be found", $err_mssg);
          wire("log")->save("contact-errors", implode(" ", $err_mssg));

          return $err_mssg;

        }
        $submission_page->setAndSave(["{$prfx}_status" => $operation]);

        switch ($operation) {
          case 'Accepted':
            $this->activateUserAccount($submission_page);
            break;

          case 'Rejected':
            $this->rejectRegistration($submission_page);
            break;

          case 'Resolved':
            $this->removeSubmission($submission_page, "Submission removed from system");
            break;
          
          default:
            // Do nothing
            break;
        }
      }
    }
    $this->checkPrivacyPolicy();

    // Display Contact and Regsitration tables
    $out =  $this->getTable("contacts");
    $out .= $this->getTable("registrations");
    
    /*
    $out .= "<br><br><small class='buttons remove-bttn'><a href='./confirm' class='ui-button ui-button--pop ui-button--remove ui-state-default '>Remove all contact data</a></small>";
    */
    return $out;
  }
  public function ___executeConfirm() {

    // Double check it's OK to delete order data
    return "<h4>WARNING: This will remove the entire contact system. Are you sure you want to delete your contact data along with all your custom forms and documents?</h4>
      <a href='./' class='ui-button ui-button--cancel ui-button--pop ui-button--cancel ui-state-default'>Cancel</a>
      <a href='./deletecontacts' class='ui-button ui-button--nuclear ui-state-default'>Yes, get on with it!</a>";
  }
  public function ___executeDeleteContacts() {

    // Delete contact data
    $contact_root = wire("pages")->get("contact-pages");

    if($contact_root->id) {

      $contact_root->delete(true);
      return "<h3>Contact data successfully removed</h3>
      <p>You can now uninstall the " . $this->className . " module</p>";

    } else {

      return "<h3>Something went wrong</h3>
      <p>The contact page could not be found</p>";

    }
  }
/**
 * Make a table showing orders for the provided steps
 *
 * @param String $submission_type "contacts" or "registrations"
 * @return Table markup
 */ 
  protected function getTable($submission_type) {

    $sanitizer = wire('sanitizer');
    $table = $this->modules->get("MarkupAdminDataTable");
    $table->setEncodeEntities(false); // Parse form HTML

    $prfx = $this["prfx"];
    $parent_str = $this["paths"][$submission_type];

    $submissions = wire("pages")->get($sanitizer->selectorValue($parent_str)); // This is the Contacts or Registrations page - its children represent individual submitters whose children in turn hold details of each submission from that email address.
    
    $records = array();

    $header_row_settings = array();

    foreach ($submissions->children() as $submitter) {

      foreach ($submitter->children() as $submission) {

        // Get associative array of submission data
        $submission_data = $this->getContactSubmission($submission["{$prfx}_submission"], true);
        $status = $submission["{$prfx}_status"];

        // Don't display "Accepted" or "Reminded" submissions as these are handled elsewhere
        $approved = $status === "Accepted" || $status === "Reminded";
        if($approved) continue;

        // Template for order of entries in table - any additional elements get added to end of array
        $record = array(
          "date" => "",
          "name" => "",
          "username" => "",
          "address" => "",
          "email" => "",
          "tel" => "",
          "url" => "",
          "message" => ""
        ); 

        if(array_key_exists("timestamp", $submission_data)){
          // Convert timestamp to formatted string
          $record["date"] = date('Y-m-d', $submission_data["timestamp"]);
           unset($submission_data["timestamp"]);
        }
        if(array_key_exists("fname", $submission_data)){
          // Concatonate first and last names
          if(array_key_exists("lname", $submission_data)){
            $record["name"] = $submission_data["lname"] . ", " . $submission_data["fname"];
            unset($submission_data["fname"]);
            unset($submission_data["lname"]);
          } else{
            throw new WireException("Name is required.");  
          }
        }
        if(array_key_exists("address", $submission_data)){
          // Concatonate address and postcode
          if(array_key_exists("postcode", $submission_data)){
            $record["address"] = $submission_data["address"] . "\n" .  $submission_data["postcode"];
            unset($submission_data["address"]);
            unset($submission_data["postcode"]);
          } else{
            throw new WireException("Postcode is required.");  
          }
        }
        // Add remaining submitted values to record
        foreach ($submission_data as $key => $value) {
          /*
           * Don't
           * - add consent to record (will have been granted for all successful submissions).
           * - attempt to add entries that may not exist (such as url on contact forms). 
           * - overwrite entries that have already been added to record (such as address)
           */
          $show_in_table = $key !== "consent" && (! array_key_exists($key, $record) || ! strlen($record[$key]));
          if($show_in_table){
            $record[$key] = $value;
          }
        }
        // Remove unpopulated items from record - these were included in the record template (above) but weren't submitted
        foreach ($record as $key => $value) {
          if( ! strlen($record[$key])){
            unset($record[$key]);
          }
        }
       
       $header_row_settings = array_unique(array_merge($header_row_settings, array_keys($record)));

       // Adding "status" to $header_row_settings only when all records have been processed to ensure it's placed in last column.
       $record["status"] = $status;
       $records[$submission->name] = $record;
      }
    }
    if(count($records)){
      ksort($header_row_settings);
      $header_row_settings[] = "status";
      $table->headerRow($header_row_settings);

      $table_rows = $this->getTableRows($records, $header_row_settings, $submission_type);

      foreach ($table_rows as $row_out) {
        $table->row($row_out);
      }
      $table_title = $submission_type === "contacts" ? "Contact form submissions" : ucfirst($submission_type);
      $out = "<h2>$table_title</h2>";
      $out .= $table->render();

      return $out;
    }
    return "No pending $submission_type.\n";
  }  
/**
 * Add records as table rows 
 *
 * @param Array $records - contains arrays of user-submitted data as name value pairs ("email=>"paul@primitive.co" etc) 
 * @param Array $column_keys - list of column heading strings
 * @param String $submission_type - Needed for call to getStatusForm
 * @return Array of table rows
 */
protected function getTableRows($records, $column_keys, $submission_type){

    $table_rows = array();

    foreach ($records as $page_name => $record) {

      $button_value = $this->getButtonValue($record["status"], $submission_type);
      $table_rows[] = $this->getTableRow($column_keys, $page_name, $record, $button_value);
    }    
    return $table_rows;
  } 
/**
 * Get value of button based on current status
 *
 * @param String $status - current status of submission
 * @param String $submission_type - "contacts", registrations"
 * @return String
 */ 
  protected function getButtonValue($status, $submission_type){

    if($status === "Pending") return "Processed";
    if($submission_type === "registrations") return "Rejected";
    return "Resolved";
  }
/**
 * Get single table row
 *
 * @param Array $column_keys - list of column heading strings
 * @param String $page_name - name of submission page
 * @param Array $record - user-submitted data as name value pairs ("email=>"paul@primitive.co" etc)
 * @param String $button_value - for status button 
 * @return table row
 */ 
  protected function getTableRow($column_keys, $page_name, $record, $button_value) {

      $table_row = array();

      foreach ($column_keys as $record_item) {

        if($record_item === "status"){
          $table_row[] = $this->getStatusForm($record[$record_item], $page_name, $button_value);
        } else {
          // "Not provided" when $record_item not in record. 
          $table_row[] = array_key_exists($record_item, $record) ? wire("sanitizer")->entities($record[$record_item]) : "Not provided";
        }
      }
      return $table_row;
  }
/**
 * Assembles form with status button for Contact page listings
 *
 * @param String $status - "Pending", "Processed", "Resolved", Accepted", "Reminded"
 * @param String $page_name - Needed for execute when identifying target of $input->post operations
 * @param String $button value
 * @return Form with appropriate button
 */ 
  protected function getStatusForm($status, $page_name, $button_value){

    $form = $this->modules->get("InputfieldForm");
    $form->action = "./";
    $form->method = "post";

    $field = $this->modules->get("InputfieldHidden");
    $field->attr("name", "submission");
    
    $status_string = strtolower($status);
    $form->addClass("uk-form--$status_string");

    $field->set("value", $page_name);
    $form->add($field);

    $button = $this->modules->get("InputfieldSubmit");
    $button->value = $button_value;
    $button->addClass("ui-button--$status_string");

    if($button_value === "Rejected"){
      
      // We also need "Accepted" button
      $button->addClass("ui-button--rejected"); 
      $accepted_button = $this->modules->get("InputfieldSubmit");
      $accepted_button->value = "Accepted";
      $accepted_button->addClass("ui-button--accepted"); 
      $form->add($accepted_button); 
    }
    $form->add($button);

    return $form->render();
  }
/**
 * get header/footer template set by user in module configuration
 *
 * @return String - path to template
 */
  public function getPageTemplate(){
    $tmplt = $this["hf_template"];
    return wire("config")->paths->templates . $tmplt;
  }
/**
 * Remove submission pages for approved registration request
 *
 * @param Page $submission - the submission page
 * @param String $message - message to display on success
 * @return Notice
 */
  protected function removeSubmission($submission, $message = false){
    
    $siblings = $submission->siblings(false);

    // Remove parent too if there are no other submissions from this email address
    $rmv_page = count($siblings) ? $submission : $submission->parent;

    wire("pages")->delete($rmv_page, true);
    if($message) return wire("notices")->message($message);
  }
/**
 * Get signature from contact-pages/settings/email-signature/
 *
 * @return String signature
 */
  protected function getSignature(){

    $sig_pg = wire("pages")->get("email-signature");
    $prfx = $this["prfx"];
    return $sig_pg["{$prfx}_signature"];
  }  
/**
 * Get privacy policy from contact-pages/settings/documents/privacy-policy/
 *
 * @return String signature
 */
  public function getPrivacyPolicy(){

    $privacy_pg = wire("pages")->get("privacy-policy");
    $prfx = $this["prfx"];
    return $privacy_pg["{$prfx}_document"];
  }
/**
 * Reject registration request
 *
 * @param Page $submission - the submission page
 * @return Notice
 */
  protected function rejectRegistration($submission){
    
    $sanitizer = wire('sanitizer');
    $prfx = $this["prfx"];
    $submission_data = $this->getContactSubmission($submission["{$prfx}_submission"], true);
    $u_name = $submission_data["username"];
    $fname =  ucfirst($submission_data["fname"]);
    $email = $submission_data["email"];
    $message = array(
      "Dear $fname,", "We are sorry to inform you that we are unable to provide you with a customer account at this time.", "Best wishes"
    );
    $this->sendHTMLmail($email, "Your Registration Request", $message);

    // Remove user - make sure $u_name is a valid string as we don't want to delete the wrong user!

    if(is_string($u_name) && strlen($u_name)){
      $rejected = wire("users")->get($sanitizer->selectorValue($u_name));

      if($rejected->id){
        wire("users")->delete($rejected);
      }     
    }

    return $this->removeSubmission($submission, "A rejection message has been sent to the email provided");
  }
/**
 * Create new user for approved registration request
 *
 * @param Array $options - [String $username, String $email, String $pass, String $title, JSON endoded String $encoded_submission]
 * @return User or error String
 */
  protected function createUserAccount($options){

    $sanitizer = wire('sanitizer');
    $username = $options["username"];
    $display_name = $options["display_name"];
    $email = $options["email"];
    $pass = $options["pass"];
    $parent_title = $options["parent_title"];
    $encoded_submission = $options["encoded_submission"];
    $prfx = $this["prfx"];
    $errors = array();

    if(count($errors)) return array("error"=>implode(". ", $errors));

    // Make new user
    $u = wire("users")->add($username);

    // Get roles for new account from module config
    $u_roles = explode(",", $this["reg_roles"]);
    $u->of(false);
    foreach ($u_roles as $u_role){
      $u->addRole($u_role);
    }

    if($this->modules->isInstalled("ProcessOrderPages")) {
      $pop_data = $this->modules->getConfig("ProcessOrderPages");
      $pop_prfx = $pop_data["prfx"];      
    }

    $u["{$pop_prfx}_display_name"] = $display_name;
    $u["{$prfx}_pending"] = 1;
    $u->email = $email;
    $u->pass = $pass;
    $u["{$prfx}_submission"] = $encoded_submission;
    $u["{$prfx}_ref"] = $parent_title;
    $u->save();
    $u->of(true); 
    $message = array(
      "Thank you for your registration request.","We'll send a confirmation email as soon as it's all been set up for you. In the meantime please make sure to keep your log in details somewhere safe."
    );
    $this->sendHTMLmail($u->email, "Your account registration", $message); 

    return $u;
  }
/**
 * Activate approved account
 *
 * @param Page $submission - the submission page
 */
  protected function activateUserAccount($submission){

    $sanitizer = wire('sanitizer');
    $prfx = $this["prfx"];
    $submission_data = $this->getContactSubmission($submission["{$prfx}_submission"], true);
    $un = $submission_data["username"];

    $u = wire("users")->get("name=" . $sanitizer->selectorValue($un));

    if($u->id){
      $u->of(false);
      $u["{$prfx}_pending"] = 0;
      $u->save();
      $u->of(true); 

      $name = ucfirst($submission_data["fname"]);

      $message = array(
        "Hi $name,",
        "Great news - your account request has been approved and you can now log in using the email and password you provided when you registered."
      );

      $tc_link = $this["ts_and_cs"];
      if(strlen($tc_link)) {
        $message[] = "<small>Please see <a href='$tc_link'>terms and conditions</a> for minimum order and carriage information.</small>";
      }
      $this->sendHTMLmail($u->email, "Your new account", $message);
      $this->removeSubmission($submission);
    } else {
      wire("log")->save("registration-errors", "No user found with username $un");
    }
  }
/**
 * Get timestamp for current time
 * @return Integer
 */
  protected function timestampNow(){
    // Time now
    $date = date_create();
    return date_timestamp_get($date);
  }
  /**
 * Send HTML email
 *
 * @param String $to - email address of recipient
 * @param String $subject
 * @param Array $message - array of strings - one per para
 */
  public function sendHTMLmail($to, $subject, $message){

    $message_markup = "<p>" . implode("</p><p>", $message) . "</p>";
    $message_markup .= $this->getSignature();

    $content = "
    <!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
    <html xmlns='http://www.w3.org/1999/xhtml' lang='en-GB'>
    <head>
      <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
      <title>Email Test</title>
      <meta name='viewport' content='width=device-width, initial-scale=1.0'/>

      <style type='text/css'>
        a[x-apple-data-detectors] {color: inherit !important;}
      </style>

    </head>
    <body>
    $message_markup
    </body>
    </html>
    ";
    $http_host = wire("config")->httpHost;
    $headers = "From: noreply@$http_host\n";
    $headers .= "MIME-Version: 1.0\n";
    $headers .= "Content-Type: text/html; charset=utf-8\n";
    mail($to, $subject, $content, $headers);
  }
/**
 * Get admin email from module config
 *
 * @return String - email address
 */
  public function getAdminEmail(){
    return $this["contact_admin_email"];
  }
  /**
 * Login a user unless account is pending approval
 *
 * @param String $username - unsanitized name submitted in login form
 * @param String $pass - unsanitized (of course) password submitted in login form
 * @return User or error string of user account is still pending approval
 */
  public function login($username, $pass){

    $sanitizer = wire("sanitizer");
    $username = $sanitizer->username($username);
    $user = wire("users")->get("name=" . $sanitizer->selectorValue($username));

    if(!$user->id) return "Unknown user.";

    $prfx = $this["prfx"];

    if($user["{$prfx}_pending"] === 1){
      return "Account awaiting approval.";
    }
    return wire("session")->login($username, $pass);

  }
}