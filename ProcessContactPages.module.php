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
      $contact_root_id = $data["contact_root_location"];
      $contact_root = $this->pages->get($contact_root_id);

      if($contact_root->id) {
        $contact_root_path = $contact_root->path();
      } else {
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

        $init_settings = array(
          "fields" => array(
            "ck_editor" => array("{$prfx}_document"), 
            "html_ee" => array("{$prfx}_submission"),
            "markup" => array("{$prfx}_markup"),
            "version_controlled" => array("{$prfx}_markup", "{$prfx}_document", "{$prfx}_status")
          ),
          "vc_templates" => array("{$prfx}-form", "{$prfx}-document", "{$prfx}-message")
        );

        $this->initPages($pgs, $init_settings);
        $data["paths"] = array(
          "forms" => $contact_root_path . "contact-pages/settings/forms",
          "documents" => $contact_root_path . "contact-pages/settings/documents",
          "contacts" => $contact_root_path . "contact-pages/active/contacts",
          "registrations" => $contact_root_path . "contact-pages/active/registrations"
        );

        // Store titles of parent pages of live contact data pages
        $data["contact_parents"] = array("forms", "documents", "contacts", "registrations");

        // Store initial value of next_id for use when adding new users to the system (form submissions/registration requests)
        $data["next_id"] = "1";

        // Add ref and tmp_pw fields to user template - these will be populated only for users added via registration form
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
 * Generate HTML markup for given form
 *
 * @param String $form - title of the required form
 * @param String $handler_url - path to js file to handle form submission
 * @return String HTML markup
 */
  public function renderForm($form_title, $handler_url) {

    if($this->privacyPolicyExists()){
      $prfx = $this["prfx"];
      $open = "<script src='$handler_url'></script><div class='contact'>";
      $close = "<p class='form__error form__error--submission'>No Error</p></div>";

      $token_name = $this->token_name;
      $token_value = $this->token_value;

      $form_page = wire("pages")->get("template={$prfx}-form, title=$form_title");
      $markup = $form_page["{$prfx}_markup"];

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
      $submission_parent = wire("pages")->add($submitter_tmplt, $parent_str, $item_data);
    }
    $submissions_from_usr = $submission_parent->numChildren();
    $submissions_from_usr++;

    $title_sffx = "-$submissions_from_usr";
    $submitter_tmplt = "{$prfx}-message";
    
    $item_data = array(
      "title" => $submission_parent->title . $title_sffx,
      "{$prfx}_email" => $params["email"],
      "{$prfx}_submission" => json_encode($params),
      "{$prfx}_status" => "Pending"
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
    $contact_system_pages = $page_maker_config["page_sets"]["contact_pages"]["setup"]["pages"];

    // Check for active contacts before uninstalling
    if($this->inUse($this["contact_parents"])) { 
      
      // There are active contacts - abort uninstall
      $this->error("The module could not be uninstalled as live data exists. If you want to proceed, you can remove all order data from the Admin/Contact page and try again.");
      $event->replace = true; // prevent uninstall
      $this->session->redirect("./edit?name=$class"); 

    } else {

      // Safe to proceed - remove ref and tmp_pw fields from user template
      $prfx = $this["prfx"];
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
        $f->set('inputfieldClass', 'InputfieldCKEditor');
        $f->save();
      }
      if(in_array($field, $init_settings["fields"]["html_ee"])){
        $f->set("textformatters", array("TextformatterEntities"));
        $f->save();
      }      
      if(in_array($field, $init_settings["fields"]["markup"])){
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
 * Adjust appearance of form field
 *
 * @param  HookEvent $event
 */
  public function customInputfieldFormRender($event) {

    if($this->page->path === '/processwire/contact/'){

      $return = $event->return;

      if (strpos($return, "active-form") !== false) {
        $class_suffix = "--pending";
      } else {
        $class_suffix = "--processed";
      }
      
       $class_suffix = "--pending";

      // Add class suffix for css to remove top margin and set button colour according to status
      $event->return = str_replace(
        array("uk-margin-top", "ui-button ui-widget ui-state-default ui-corner-all"), 
        array("", "ui-button ui-button$class_suffix ui-widget ui-state-default ui-corner-all"), $return);
    }

  }
  // Contact page
  public function ___execute() {
    if($this->input->post->submit) {

      // Value of submit is the current status of the order
      bd($this->input->post->submit, "post->submit");

      // Value of submission is the title of the submission page
      bd($this->input->post->submission, "post->submission");

    /*
     * We deal with this action by updating the value of the status field as follows:
     * 
     * submit val            updated field value
     * -----------           --------------------
     * "Processed"           "Processed"
     * "Accepted"            "Accepted"
     *                       Create user account for customer and email with password reset link
     * 
     *  We don't set the field value for the following:
     * "Rejected"            Send email to the rejected customer and remove their data
     * "Completed"           Remove data - should we warn?
     * 
     */

      // Update submission status
      $form = $this->modules->get("InputfieldForm");
      $form->processInput($this->input->post);

      if($form->getErrors()) {
        $out .= $form->render();
      } else {
        $prfx = $this["prfx"];
        $operation = $this->sanitizer->text($this->input->post->submit);
        $submission = $this->sanitizer->text($this->input->post->submission);
        $submission_page = wire("pages")->get("name=$submission");
        $submission_page->setAndSave(["{$prfx}_status" => $operation]);

        switch ($operation) {
          case 'Accepted':
            bd("Accepted - create user account, send pw reset email");
            $this->createUserAccount($submission_page);
            break;

          case 'Rejected':
            bd("Rejected - send email to customer and delete data");
            break;

          case 'Completed':
            bd("Completed - remove data from Contacts section - could display notice");
            break;
          
          default:
            // Do nothing
            break;
        }
      }
    }
    return $this->getTable("registrations");
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
    $table_rows = array();

    $prfx = $this["prfx"];
    $submitter_tmplt = "{$prfx}-submitter";
    $submitter_parent = $submission_type;
    $parent_str = $this["paths"][$submission_type];
    $submissions = wire("pages")->get($parent_str); // This is the Contacts page - children should be the pages with email field
    
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
          $date = date_create();
          $submission_data["date"] = date('Y-m-d', $submission_data["timestamp"]);
        }
        foreach ($submission_data as $key => $value) {
          
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

      foreach ($this->getTableRows($records, $header_row_settings, $submission_type) as $row_out) {
        $table->row($row_out);
      }
      $out = $table->render();
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
 * @return array of table rows
 */ 
  protected function getTableRows($records, $column_keys, $submission_type) {

    $table_rows = array();

    foreach ($records as $page_name => $record) {

      $table_row = array();

      foreach ($column_keys as $record_item) {

        if($record_item === "status"){
          $table_row[] = $this->getStatusForm($record[$record_item], $page_name, $submission_type);
        } else {
          // "Not provided" when $record_item not in record
          $table_row[] = array_key_exists($record_item, $record) ? wire("sanitizer")->entities($record[$record_item]) : "Not provided";
        }
      }
      $table_rows[] = $table_row;
    }
    // bd($table_rows, "table rows");
    return $table_rows;
  }
/**
 * Assembles form with status button for Contact page listings
 *
 * @param String $status - "Pending", "Processed", "Completed", Accepted", "Reminded"
 * @param String $page_name - Needed for execute when identifying target of $input->post operations
 * @param String $submission_type - Needed to determine correct button value
 * @return Form with appropriate button
 */ 
  protected function getStatusForm($status, $page_name, $submission_type){

    $form = $this->modules->get("InputfieldForm");
    $form->action = "./";
    $form->method = "post";

    $form->attr("id+name","{$status}-form"); //Name differs from POP

    $field = $this->modules->get("InputfieldHidden");
    $field->attr("id+name", "submission"); // Name differs from POP

    //TODO: Change the colour of the status button in customInputfieldFormRender() - just got to work out the logic of checking for "active-form" on #437
    $field->set("value", $page_name);
    $form->add($field);

    $button = $this->modules->get("InputfieldSubmit");

    if($status === "Pending") {
      // For both registration and regular forms, next step is the same
      $button->value= "Processed";
    } else {

      if($submission_type === "registrations") {
        // In this case we need both "Accepted" and "Rejected" buttons, one of which is clicked when assessment of prospective customercompleted
        $button->value = "Rejected";
        $accepted_button = $this->modules->get("InputfieldSubmit");
        $accepted_button->value = "Accepted";
        $form->add($accepted_button);
      } else {
        // Clicked when customer replies to our initial response
        $button->value = "Completed";
      }
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
 * Create new user for approved account registration request
 *
 * @param String $submission - title of submission page
 * @return User
 */
  protected function createUserAccount($submission){
    $prfx = $this["prfx"];
    $submission_data = $this->getContactSubmission($submission["{$prfx}_submission"], true);
    $email = $submission_data["email"];

    if($email) {
      // We don't have such a user yet
      $u = wire("users")->get("email=$email");
      if($u->id) throw new WireException($e . "An account exists for this email address.");

      $un = $submission_data["username"];
      $u = wire("users")->get("name=$un");
      if($u->id) throw new WireException($e . "An account exists for this user name.");

      // Make new user
      $u = wire("users")->add($un);
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
      $u->of(false);
      $u["email"] = $email;
      $u["{$prfx}_tmp_pass"] = $pass; // populate a temporary pass to their profile
      $u["{$prfx}_ref"] = $submission->parent->title;
      $u->save();
      $u->of(true); 
      $message = "Your registration request was successful and your temporary password on our web site is: $pass\n";
      $message .= "Please change it after you login.";
      $http_host = wire("config")->httpHost;
      mail($u->email, "Password reset", $message, "From: noreply@$http_host"); 
    }
  }
/**
 * Login user - may have temporary password
 *
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
