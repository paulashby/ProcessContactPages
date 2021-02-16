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

      // Create array of required pages containing three associative arrays whose member keys are the names of the respective elements
      $pgs = array(
        "fields" => array(
          "{$prfx}_markup" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Form markup"),
          "{$prfx}_document" => array("fieldtype"=>"FieldtypeTextarea", "label"=>"Document markup"),
          "{$prfx}_email" => array("fieldtype"=>"FieldtypeEmail", "label"=>"Contact email address"),
          "{$prfx}_ref" => array("fieldtype"=>"FieldtypeInteger", "label"=>"Contact reference number"),
          "{$prfx}_submission" => array("fieldtype"=>"FieldtypeText", "label"=>"Contact submission")
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
          "{$prfx}-message" => array("t_parents" => array("{$prfx}-submitter"), "t_fields"=>array("{$prfx}_submission"))
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
            "version_controlled" => array("{$prfx}_markup", "{$prfx}_document")
          ),
          "vc_templates" => array("{$prfx}-form", "{$prfx}-document")
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

        // Add ref field to user template - this will be populated only for users added via registration form
        $usr_template = wire("templates")->get("user");
        $ufg = $usr_template->fieldgroup;
        $f = wire("templates")->get("{$prfx}_ref");
        $ufg->add($f);
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
 * @return String HTML markup
 */
  public function renderForm($form_title) {

    if($this->privacyPolicyExists()){
      $prfx = $this["prfx"];
      $form_script_url = $this->config->urls->site . "modules/ProcessContactPages/processcontactpages.js";
      $open = "<script src='$form_script_url'></script><div class='contact'>";
      $close = "<p class='form__error form__error--submission'>No Error</p></div>";

      $token_name = $this->token_name;
      $token_value = $this->token_value;

      $form_page = wire("pages")->get("template={$prfx}-form, title=$form_title");
      $markup = $form_page["{$prfx}_markup"];

      $placeholders = array(
        "csrf-token-placeholder" => "<input type='hidden' id='contact_token' name='$token_name' value='$token_value'>"
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
 * @param Array $fparams - submitted with form - these MUST be pre-santized and validated
 * @return JSON - success true with message or success false with conflated error message
 */
   public function processContactSubmission($params) {

    $date = date_create();
    $params["timestamp"] = date_timestamp_get($date);
    $email = $params["email"];
    $prfx = $this["prfx"];
    $submitter_tmplt = "{$prfx}-submitter";
    $submitter = wire("pages")->get("template=$submitter_tmplt,{$prfx}_email=$email");
    bd("template=$submitter_tmplt,{$prfx}_email=$email", "selector");
    $submitter_exists = $submitter->id;
    bd($submitter_exists, "submitter_exists");
    if($submitter_exists){
      $submission_parent = $submitter;
    } else {
      $item_data = array(
        "title" => $this->getID(),
        "{$prfx}_email" => $email
      );
      $submission_parent = wire("pages")->add($submitter_tmplt, $this["paths"]["contacts"], $item_data);
    }
    $submissions_from_usr = $submission_parent->numChildren();
    $submissions_from_usr++;

    $title_sffx = "-$submissions_from_usr";
    $submitter_tmplt = "{$prfx}-message";
    
    $item_data = array(
      "title" => $submission_parent->title . $title_sffx,
      "{$prfx}_email" => $params["email"],
      "{$prfx}_submission" => json_encode($params)
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
 * @param Boolean $parse_as_array - return array, else object
 * @return Array or Object
 */
  protected function getContactSubmission($parse_as_array = false) {
    // This will be called when accessing historical submissions which are stored as JSON strings in textarea fields
    
    // If parse_as_array is true this will return an array, else an object
    $submission = json_decode($sanitizer->unentities($json), $parse_as_array);
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

      /*
      Safe to proceed - remove the fields and templates of the contact system pages
      $report_pg_errs false as pages as will already have been removed via the button on the Contact admin page
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

  // Contact page
  public function ___execute() {
    return "Hello world";
  }
}