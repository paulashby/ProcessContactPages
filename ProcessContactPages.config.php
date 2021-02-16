<?php namespace ProcessWire;

$config = array(
	
	"contactRootlocation" => array(
	    "name" => "contact_root_location",
	    "type" => "PageListSelect",
	    "label" => "Where to install the contact system (can't be reset after installation)",
	    "description" => "Please select a page for order storage - if no page is selected, the orders will be stored at '/contact/'", 
	    "required" => false
	),
	"contactPrefix" => array(
		"name"=> "prfx",
		"type" => "text", 
		"label" => "Prefix for fields and templates",
		"description" => "Please enter a string to prepend to generated field and template names to avoid naming collisions", 
		"value" => "ctct", 
		"required" => true 
	),
	"contactAccess" => array(
		"name"=> "t_access",
		"type" => "text", 
		"label" => "Roles with view access to contact system pages (can't be reset after installation)",
		"description" => "Please provide a comma-separated list of role names or IDs.",  
		"value" => "", 
		"required" => false 
	)
);