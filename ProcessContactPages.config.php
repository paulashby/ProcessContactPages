<?php namespace ProcessWire;

$config = array(
	
	"contactRootlocation" => array(
	    "name" => "contact_root_location",
	    "type" => "PageListSelect",
	    "label" => "Where to install the contact system (can't be reset once submitted)",
	    "description" => "Please select a page for order storage - if no page is selected, the orders will be stored at '/contact/'", 
	    "required" => false
	),
	"contactPrefix" => array(
		"name"=> "prfx",
		"type" => "text", 
		"label" => "Prefix for fields and templates (can't be reset once submitted)",
		"description" => "Please enter a string to prepend to generated field and template names to avoid naming collisions", 
		"value" => "ctct", 
		"required" => true 
	),
	"contactAccess" => array(
		"name"=> "t_access",
		"type" => "text", 
		"label" => "Roles with view access to contact system pages (can't be reset once submitted)",
		"description" => "Please provide a comma-separated list of role names or IDs.",  
		"value" => "", 
		"required" => false 
	),
	"contactAutoReg" => array(
		"name"=> "autoreg",
		"type" => "checkbox", 
		"label" => "Automatically create accounts for approved registrations",
		"description" => "Created accounts will be given temporary passwords requiring user reset",  
		"notes" => "If this option is on, call ProcessContactPages->login(\$user) instead of \$session->login(\$user) when processing login form",
		"value" => "1", 
		"required" => false 
	),
	"contactRegRole" => array(
		"name"=> "reg_roles",
		"type" => "text", 
		"label" => "Roles for automatically created accounts (can't be reset once submitted)",
		"description" => "Please provide a comma-separated list of role names or IDs.",  
		"value" => "", 
		"requiredif" => "autoreg=1"
	),
	"contactHeadFoot" => array(
		"name"=> "hf_template",
		"type" => "text", 
		"label" => "Name of page template containing only your standard site header and footer",
		"description" => "Applies site styling to profile page used for password resets",  
		"value" => "", 
		"requiredif" => "autoreg=1",
		"showIf" => "autoreg=1" 
	)
);