<?php namespace ProcessWire;

$config = array(
	
	"contactRootlocation" => array(
	    "name" => "contact_root_location",
	    "type" => "PageListSelect",
	    "label" => "Where to install the contact system (can't be reset once submitted)",
	    "description" => "Please select a page for order storage - if no page is selected, the orders will be stored at '/contact/'", 
	    "required" => false
	),
	"adminEmail" => array(
		"name"=> "contact_admin_email",
		"type" => "email", 
		"label" => "Admin email",
		"description" => "For status updates and notifications.", 
		"value" => "", 
		"required" => true 
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
	"contactRegRole" => array(
		"name"=> "reg_roles",
		"type" => "text", 
		"label" => "Roles for customer accounts (can't be reset once submitted)",
		"description" => "Please provide a comma-separated list of role names or IDs.",  
		"value" => "", 
		"requiredif" => "autoreg=1"
	),
	"tsAndCs" => array(
		"name"=> "ts_and_cs",
		"type" => "text",
	    "label" => "Terms and Condtions page",
	    "description" => "Optionally, enter a link for inclusion at bottom of welcome email.", 
	    "required" => false
	)
);