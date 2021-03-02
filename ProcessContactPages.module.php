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

    // Associate file with profile template
    if($this["prfx"]){
      $prfx = $this["prfx"];
      $profile_t = $this->templates->get("{$prfx}_profile");
      if(! $profile_t) return;
      $profile_t->filename = wire("config")->paths->root . 'site/modules/ProcessContactPages/profile.php';
      $profile_t->save();
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

      if ($this->configValueChanged($curr_config, $data)) {

        // Show error for attempted changes and resubmit existing data.
        $this->session->error("Unable to change settings for this module after installation. If you really need to make changes, you can reinstall the module, but be aware that this will mean losing the data currently in the system");

        $event->arguments(1, $curr_config);

      } else {

        // All good - config unchanged
        $event->arguments(1, $data);
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
        //TODO: Might be better to abort if root not provided - that  way, won't auto install anything until properly configured - so we need to remove the configured flag here and return if root hasn't been provided
        $contact_root_path = "/";
        $contact_root = $this->pages->get("/");
      }

      $prfx = $data["prfx"];

      // Make profile template for password resets
      $profile_t = new Template();
      $profile_t->name = "{$prfx}_profile";
      $profile_t->fieldgroup = $this->templates->get("basic-page")->fieldgroup;
      $profile_t->compile = 0;
      $profile_t->noPrependTemplateFile = true;
      $profile_t->noAppendTemplateFile = true; 
      $profile_t->save();

      // Create array of required pages containing three associative arrays whose member keys are the names of the respective elements
      $pgs = array(
        "fields" => array(
          "{$prfx}_markup" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Form markup"),
          "{$prfx}_document" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Document markup"),
          "{$prfx}_email" => array("fieldtype"=>"FieldtypeEmail", "label"=>"Contact email address"),
          "{$prfx}_ref" => array("fieldtype"=>"FieldtypeText", "label"=>"Contact reference code"),
          "{$prfx}_tmp_pass" => array("fieldtype"=>"FieldtypeText", "label"=>"Temporary password"),          
          "{$prfx}_submission" => array("fieldtype"=>"FieldtypeText", "label"=>"Contact submission"),
          "{$prfx}_status" => array("fieldtype"=>"FieldtypeText", "label"=>"Submission status"),
        ),
        "templates" => array(
          "{$prfx}-section" => array("t_parents" => array("{$prfx}-section")),
          "{$prfx}-section-active" => array("t_parents" => array("{$prfx}-section", "{$prfx}-section-active"), "t_children" => array("{$prfx}-section-active", "{$prfx}-message")),
          "{$prfx}-submitter" => array("t_parents" => array("{$prfx}-section-active"), "t_children" => array("{$prfx}-message"), "t_fields"=>array("{$prfx}_email")),
          "{$prfx}-registrations" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-message")),
          "{$prfx}-setting-forms" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-form")),
          "{$prfx}-form" => array("t_parents" => array("{$prfx}-setting-forms"), "t_fields"=>array("{$prfx}_markup")),
          "{$prfx}-setting-documents" => array("t_parents" => array("{$prfx}-section"), "t_children" => array("{$prfx}-document")),
          "{$prfx}-document" => array("t_parents" => array("{$prfx}-section-documents"), "t_fields"=>array("{$prfx}_document")),
          "{$prfx}-message" => array("t_parents" => array("{$prfx}-submitter"), "t_fields"=>array("{$prfx}_submission", "{$prfx}_status"))
        ),
        "pages" => array(
          "contact-pages" => array("template" => "{$prfx}-section", "parent"=>$contact_root_path, "title"=>"Contact Pages"),
          "settings" => array("template" => "{$prfx}-section", "parent"=>"{$contact_root_path}contact-pages/", "title"=>"Settings"),
          "active" => array("template" => "{$prfx}-section", "parent"=>"{$contact_root_path}contact-pages/", "title"=>"Active"),
          "documents" => array("template" => "{$prfx}-setting-documents", "parent"=>"{$contact_root_path}contact-pages/settings/", "title"=>"Documents"),
          "forms" => array("template" => "{$prfx}-setting-forms", "parent"=>"{$contact_root_path}contact-pages/settings/", "title"=>"Forms"),
          "contacts" => array("template" => "{$prfx}-section-active", "parent"=>"{$contact_root_path}contact-pages/active/", "title"=>"Contacts"),
          "registrations" => array("template" => "{$prfx}-registrations", "parent"=>"{$contact_root_path}contact-pages/active/", "title"=>"Registrations"),
          "privacy-policy" => array("template" => "{$prfx}-document", "parent"=>"{$contact_root_path}contact-pages/settings/documents/", "title"=>"Privacy Policy"),
          "tools" => array("template" => "{$prfx}-section", "parent"=>"{$contact_root_path}contact-pages/", "title"=>"Tools"),
          "profile" => array("template" => "{$prfx}_profile", "parent"=>"{$contact_root_path}", "title"=>"Profile") 
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

        // Additional settings for fields and templates
        $init_settings = array(
          "fields" => array(
            "ck_editor" => array("{$prfx}_document"), 
            "html_ee" => array("{$prfx}_submission"),
            "markup" => array("{$prfx}_markup"),
            "version_controlled" => array("{$prfx}_markup", "{$prfx}_document", "{$prfx}_status")
          ),
          "vc_templates" => array("{$prfx}-form", "{$prfx}-document", "{$prfx}-message")
        );
        // Apply additional settings to fields and templates
        $this->initPages($pgs, $init_settings);

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
        $temp_pw_f = wire("fields")->get("{$prfx}_tmp_pass");
        $ufg->add($temp_pw_f);
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

     $prfx = $this["prfx"];
     $page = wire("pages")->get("template={$prfx}-document, title=$doc_title");

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
      // Add paths to javascript form hanlders if not already in $handlers array
      if($form_options["handler"] && ! in_array($form_options["handler"], $handlers)){
        $handlers[] = $form_options["handler"];
      
      }
      $title = $form_options["title"];
      $forms_out .= "<h2>$title</h2>";
      $forms_out .= $this->renderForm($form_options["form"]);
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
 * @param String $form - title of the required form
 * @param String $handler_url - path to js file to handle form submission
 * @return String HTML markup
 */
  public function renderForm($form_title, $handler_url = false) {

    // Don't want to present a form to user if privacy policy has not been populated
    if($this->privacyPolicyExists()){
      $prfx = $this["prfx"];

      // Include script tag for handler if provided
      $open = $handler_url ? "<script src='$handler_url'></script><div class='pcp_form'>" : "<div class='pcp_form'>";
      $close = "<p class='form__error form__error--submission'>No Error</p></div>";

      // Include token to mitigate CSRF
      $token_name = $this->token_name;
      $token_value = $this->token_value;

      // Get page with form markup
      $form_page = wire("pages")->get("template={$prfx}-form, title=$form_title");
      $markup = $form_page["{$prfx}_markup"];

      // Array keys correspond to placeholder text in form markup. Value is the replacement string.
      $placeholders = array(
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
 * @param String $submission_type - "contact" or "register"
 * @return JSON - success true with message or success false with conflated error message
 */
   public function processSubmission($params, $submission_type) {

    $date = date_create();
    $params["timestamp"] = date_timestamp_get($date);
    $email = $params["email"];
    $prfx = $this["prfx"];
    $submitter_tmplt = "{$prfx}-submitter";
    $submitter_parent = "{$submission_type}s";
    $parent_str = $this["paths"][$submitter_parent];
    $submitter = wire("pages")->get("parent=$parent_str,{$prfx}_email=$email");
    
    if($submitter->id){

      $submission_parent = $submitter;
      if($submission_type === "registration") return array("error"=>"A registration request for this email address is already being processed");
    
    } else {

      if($submission_type === "registration"){

        // Check for existing account
        $existing_user = wire("users")->get("email=$email")->id;
        if($existing_user) return array("error"=>"An account already exists for this email address");
      }

      $item_data = array(
        "title" => $this->getID(),
        "{$prfx}_email" => $email
      );
      // Make parent page for submissions form this email address
      $submission_parent = wire("pages")->add($submitter_tmplt, $parent_str, $item_data);
    }
    // Use number of current submissions from this email address to get numerical suffix
    $submissions_from_usr = $submission_parent->numChildren();
    $submissions_from_usr++;

    $title_sffx = "-$submissions_from_usr";
    $submitter_tmplt = "{$prfx}-message";

    /*
     * Registrations go straight to "Processed". So they show "Accepted"/"Rejected" buttons instead of a "Processed" button like Contacts.
     * This is because it turns out that 'backgound checks' for regsitrations only take a few mins, so the "Pending" to "Processed" stage is overkill
     */
    $submission_status = $submission_type === "contact" ? "Pending" : "Processed";
    
    $item_data = array(
      "title" => $submission_parent->title . $title_sffx,
      "{$prfx}_email" => $params["email"], //TODO: Is email required here? It's already on parent page!
      "{$prfx}_submission" => json_encode($params),
      "{$prfx}_status" => $submission_status
    );
    $submission = wire("pages")->add($submitter_tmplt, $submission_parent->url, $item_data);

    if( ! $submission->id) return array("error"=>"There was a problem submitting your message. Please try again later");
    
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
 * Check for existence of Privacy Policy
 *
 * @return Boolean
 */
  protected function privacyPolicyExists() {
    $prfx = $this["prfx"];
    $page = wire("pages")->get("template={$prfx}-document, title=Privacy Policy");
    return $page->id && $page[$this["prfx"] . "_document"];
  }
/**
 * Custom uninstall 
 * 
 * @param HookEvent $event
 */
  public function customUninstall($event) {

    $class = $event->arguments(0);
    if($class !== $this->className) return;

    $page_maker = $this->modules->get("PageMaker");
    $page_maker_config = $this->modules->getConfig("PageMaker"); 
    
    // Check for active contacts before uninstalling
    if($this->inUse(array_keys($this["paths"]))){
      
      // There are active contacts - abort uninstall
      $this->error("The module could not be uninstalled as live data exists. If you want to proceed, you can remove all order data from the Admin/Contact page and try again.");
      $event->replace = true; // prevent uninstall
      $this->session->redirect("./edit?name=$class"); 

    } else {

      // Safe to proceed - remove ref and tmp_pw fields from user template
      $prfx = $this["prfx"];
      //TODO: Are we having ref field in this module?
      $this->removeFields(array("{$prfx}_ref", "{$prfx}_tmp_pass"));

      /*
      Remove the fields and templates of the contact system pages
      $report_pg_errs false as pages as will already have been removed via the button on the Contact admin page
      */
      $page_maker->removeSet("contact_pages", false);

      // Remove the ajax template that was installed by init()
      $prfx = $this["prfx"];
      $profile_t = $this->templates->get("{$prfx}_profile");
      if($profile_t) {
        wire('templates')->delete($profile_t);
      }

      parent::uninstall();
    } 
  }
/**
 * Remove field from system
 *
 * @param Array $f_names Names of fields to be removed
 */
  protected function removeFields($f_names){

    // Traverse array of field names
    foreach ($f_names as $f_name) {
      $rm_fld = wire("fields")->get($f_name);
      if($rm_fld !== null) {
        $f_groups = $rm_fld->getFieldgroups();

        // Remove from all fieldgroups
        foreach ($f_groups as $fg) {
          $fg->remove($rm_fld);
          $fg->save();
        }
        wire("fields")->delete($rm_fld);
      }
    }
  }
/**
 * Apply field and template settings
 *
 * @param Array $init_settings Contains arrays of field and template names to initialise
 */
  protected function initPages($pgs, $init_settings) {

    $vc_data = wire("modules")->getConfig("VersionControl");

    foreach ($pgs["fields"] as $field => $spec) {

      $f = wire("fields")->get($field);

      if(in_array($field, $init_settings["fields"]["ck_editor"])){

        // Set Inputfield Type to CKEditor
        $f->set('inputfieldClass', 'InputfieldCKEditor');
        $f->save();
      }
      if(in_array($field, $init_settings["fields"]["html_ee"])){

        // Set Text Formatter to HTML Entity Encoder
        $f->set("textformatters", array("TextformatterEntities"));
        $f->save();
      }      
      if(in_array($field, $init_settings["fields"]["markup"])){

        // Set Content Type to Markup/HTML
        $f->set("contentType", 1);
        $f->save();
      }
      if(in_array($field, $init_settings["fields"]["version_controlled"])){

        // Activate version control
        $vc_data["enabled_fields"][] = $f->id;
      }
    }
    foreach ($init_settings["vc_templates"] as $t_name) {

      // Activate version control
      $vc_data["enabled_templates"][] = wire("templates")->get($t_name)->id;
    }
    wire("modules")->saveModuleConfigData("VersionControl", $vc_data);
  }
/**
 * Check it's safe to delete provided pages
 *
 * @param array $ps Names of pages to check
 * @return boolean true if pages are in use
 */
  protected function inUse($contact_parents) {

    // Check for ongoing contacts
    foreach ($contact_parents as $pg) {
      $selector = 'name=' . $pg;
      $curr_p = $this->pages->findOne($selector);

      if($curr_p->id > 0){
        return true; 
      }
    }
    return false;
  }
/**
 * Check for changes to module config
 *
 * @param Array $new_config New config array to check
 * @return Boolean false or the current config
 */
  protected function configValueChanged ($curr_config, $new_config) {

   foreach ($new_config as $key => $val) {

    if( array_key_exists($key, $curr_config) &&
      $new_config[$key] !== $curr_config[$key]){
        return true;
      }
    }
    return false;
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

      $form = $this->modules->get("InputfieldForm");
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $prfx = $this["prfx"];
        $operation = $this->sanitizer->text($this->input->post->submit); // Current status of the submission
        $submission = $this->sanitizer->text($this->input->post->submission); // Title of the submission page

        // Update submission status
        $submission_page = wire("pages")->get("name=$submission");
        $submission_page->setAndSave(["{$prfx}_status" => $operation]);

        switch ($operation) {
          case 'Accepted':
            $this->createUserAccount($submission_page);
            break;

          case 'Rejected':
            $this->rejectRegistration($submission_page);
            break;

          case 'Completed':
            //TODO: Implement this
            bd("Completed - remove data from Contacts section - could display notice");
            break;
          
          default:
            // Do nothing
            break;
        }
      }
    }
    // Display Contact and Regsitration tables
    $out =  $this->getTable("contacts");
    $out .= $this->getTable("registrations");

    // Add data removal button
    $out .= "<br><br><small class='buttons remove-bttn'><a href='./confirm' class='ui-button ui-button--pop ui-button--remove ui-state-default '>Remove all contact data</a></small>";
    return $out;
  }
  public function ___executeConfirm() {

    // Double check it's OK to delete order data
    return "<h4>WARNING: This will remove the entire contact system. Are you sure you want to delete your contact data?</h4>
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

    $table = $this->modules->get("MarkupAdminDataTable");
    $table->setEncodeEntities(false); // Parse form HTML

    $prfx = $this["prfx"];
    $parent_str = $this["paths"][$submission_type];
    $submissions = wire("pages")->get($parent_str); // This is the Contacts or Registrations page - its children represent individual submitters whose children in turn hold details of each submission from that email address.
    
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

        $record = array(
          // Concatenate name fields
          "name" => $submission_data["fname"] . " " . $submission_data["lname"]
        ); 
        if(array_key_exists("timestamp", $submission_data)){
          
          // Convert timestamp to formatted string
          $submission_data["date"] = date('Y-m-d', $submission_data["timestamp"]);
        }
        foreach ($submission_data as $key => $value) {
          // Exclude items already formatted and added
          $should_display = $key !== "fname" && $key !== "lname" && $key !== "consent" && $key !== "timestamp";

          if($should_display){
            $record[$key] = $value;
          } 
        }
        ksort($record);
        $record["status"] = $status;
        $header_row_settings = array_unique(array_merge($header_row_settings, array_keys($record)));
        $records[$submission->name] = $record;
      }
    }
    if(count($records)){
      ksort($header_row_settings);
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
    return "No pending $submission_type";
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
    return "Completed";
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
 * @param String $status - "Pending", "Processed", "Completed", Accepted", "Reminded"
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
 * Create new user for approved registration request
 *
 * @param Page $submission - the submission page
 * @return User
 */
  protected function rejectRegistration($submission){
    
    $prfx = $this["prfx"];
    $submission_data = $this->getContactSubmission($submission["{$prfx}_submission"], true);
    $email = $submission_data["email"];

    $message = "This is a message from Paper Bird to inform you that we are unable to provide you with a user account at this time";
    $message .= "\nBest regards,\nThe Paper Bird team.";
    //TODO: Add these settings to the config?
    //TODO: Add an image to signature
    $signature = "\n\nPaper Bird Publishing\n020 8613 8085\n07766 164 807\nwww.paperbirdpublishing.co.uk";
    $message .= $signature;

    $http_host = wire("config")->httpHost;
    mail($email, "Paper Bird Registration Request", $message, "From: noreply@$http_host"); 

    $submission_parent = $submission->parent;
    wire("pages")->delete($submission_parent, true); // Delete submission and its parent
    wire("notices")->message("A rejection message has been sent to the email provided");
  }
/**
 * Create new user for approved registration request
 *
 * @param Page $submission - the submission page
 * @return User
 */
  protected function createUserAccount($submission){
    
    $prfx = $this["prfx"];
    $submission_data = $this->getContactSubmission($submission["{$prfx}_submission"], true);
    $email = $submission_data["email"];

    if($email) {
      // Make sure account doesn't already exist for this email
      $u = wire("users")->get("email=$email");
      if($u->id) throw new WireException($e . "An account exists for this email address.");

      // Make sure account doesn't already exist for this username
      $un = $submission_data["username"];
      $u = wire("users")->get("name=$un");
      if($u->id) throw new WireException($e . "An account exists for this user name.");

      // Make new user
      $u = wire("users")->add($un);

      // Get roles for new account from module config
      $u_roles = explode(",", $this["reg_roles"]);
      foreach ($u_roles as $u_role){
        $u->of(false);
        $u->addRole($u_role);
        $u->save();
      }
      // generate a random, temporary password - see https://processwire.com/talk/topic/1716-integrating-a-member-visitor-login-form/
      $pass = '';
      $chars = 'abcdefghjkmnopqrstuvwxyz23456789!@£$%^&*'; 
      $length = mt_rand(9,12); // password between 9 and 12 characters
      for($n = 0; $n < $length; $n++) $pass .= $chars[mt_rand(0, strlen($chars)-1)];
        bd($pass, "password");
      $u->of(false);
      $u["email"] = $email;
      $u["{$prfx}_tmp_pass"] = $pass; // populate a temporary pass to their profile
      $u["{$prfx}_ref"] = $submission->parent->title;
      $u->save();
      $u->of(true); 
      $message = "Your registration request was successful and your temporary password on the Paper Bird site is: $pass\n";
      $message .= "Please change it after you login.";
      //TODO: Add these settings to the config?
      //TODO: Add an image to signature
      $signature = "\n\nPaper Bird Publishing\n020 8613 8085\n07766 164 807\nwww.paperbirdpublishing.co.uk";
      $message .= $signature;
      $http_host = wire("config")->httpHost;
      mail($u->email, "Password reset", $message, "From: noreply@$http_host"); 

      return wire("notices")->message("Customer account successfully created");
    }
  }
/**
 * Login user - may have temporary password
 * This is intended to be used to add password reset to a login endpoint
 * @param Page $user
 * @param String $username
 * @param String $pass
 * @return Logged in user or redirect to reset password
 */
  public function login($user, $username, $pass){
    
    $prfx = $this["prfx"];

    if($user->id && $user["{$prfx}_tmp_pass"] && $user["{$prfx}_tmp_pass"] === $pass) {
     
      // user logging in with tmp_pass, so change it to be their real pass
      $tmp_pass = true;
      $user->of(false);
      $user->pass = $user["{$prfx}_tmp_pass"];
      $user->save();
      $user->of(true);
    }
    $user = wire("session")->login($username, $pass); 
    
    if($user) {
      if($tmp_pass){
        // user is logged in, get rid of tmp_pass
        $user->of(false);
        $user["{$prfx}_tmp_pass"] = "";
        $user->save();
        // Redirect to the profile edit page so password can be reset
        $profile_page = wire("pages")->get("template={$prfx}_profile")->url;
        wire("session")->redirect($profile_page);        
      } 
      return $user;
    }
  }
}
