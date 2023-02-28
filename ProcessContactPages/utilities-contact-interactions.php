<?php namespace ProcessWire;

if( ! $config->ajax) {
	throw new Wire404Exception();
}

if ($session->CSRF->hasValidToken('pcp_token')) {

	$_input = file_get_contents("php://input");

	if( ! $_input){
		return json_encode(array("success"=>false, "error"=>"The form contained no input"));
	}

	// Careful as user is NOT necessarily logged in
	$req = json_decode($_input);
	$pcp = wire("modules")->get("ProcessContactPages");

	if( ! property_exists($req, "params")){
		return json_encode(array("success"=>false, "error"=>"The form contained no data"));
	}

	$params = $req->params;
	$bot = isset($params->website);

	if($bot){
		// Log bot activity
		$ipf = "UNAVAILABLE";
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
			$ipf = $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
		$ipr = $_SERVER['REMOTE_ADDR'];
		$mssg = "Form appears to have been submitted by a bot with IP address details $ipf (HTTP_X_FORWARDED_FOR) and $ipr (REMOTE_ADDR)";
		wire("log")->save("bot-activity", $mssg);

		$admin_email = $pcp->getAdminEmail();
		$pcp->sendHTMLmail($admin_email, "Suspected Bot Activity", array($mssg));

		return json_encode(array("success"=>false, "error"=>"The form contained errors")); 
	}

	// Check consent
	if( ! isset($params->consent)){
		return json_encode(array("success"=>false, "error"=>"Please consent to the storage of your information so we can process your message"));
	}

	// Check submission type
	if( ! property_exists($params, "submission_type")){
		return json_encode(array("success"=>false, "error"=>"Unknown submission type"));
	}

	$submission_type = $params->submission_type;
	unset($params->submission_type); // Don't want stored in submission field with other params. No need to sanitize as not user input

	$sanitized = sanitizeSubmission($params, $submission_type, $sanitizer);

	// Error string returned
	if(gettype($sanitized) === "string"){
		return json_encode(array("success"=>false, "error"=>$sanitized));
	}

	// Validate password if present
	if(property_exists($params, "pass")){

		// Check password confirmation also exists
		if( ! property_exists($params, "_pass")){
			return json_encode(array("success"=>false, "error"=>"Password confirmation required"));
		}
			
		$pass_params = array("pass" => $params->pass, "_pass" => $params->_pass);
		$p = new WireInputData($pass_params); 

		$inputfield_pass = $modules->get("InputfieldPassword"); // load the inputfield module
		$inputfield_pass->attr("name","pass"); // set the name
		$inputfield_pass->processInput($p); // process and validate the field

		if($inputfield_pass->getErrors()){
			return json_encode(array("success"=>false, "error"=>"Invalid password: " . implode(", ", $inputfield_pass->getErrors(true))));
		}

		// Include validated password
		$sanitized["pass"] = $params->pass;

	}
	
	// Form santized and validated - pass to ProcessContactPages module for processing
	$submitted = $pcp->processSubmission($sanitized, $submission_type);

	if(is_string($submitted)){
		return $submitted;
	}
	
	$confirmation_message = array(
		"contact" => "Thanks for your submission - we'll get back to you as soon as possible. Please make sure to check your spam folder if you don't hear from us.",
		"registration" => "Thanks for your account request - we'll get back to you as soon as possible. Please make sure to check your spam folder if you don't hear from us.",
		"catalogue" => "Message received - a Paper Bird catalogue will be winging its way to you very soon!"
	);
	
	return json_encode(array("success"=>true, "message"=>$confirmation_message[$submission_type])); 

}
return json_encode(array("success"=>false, "error"=>"CSRF validation error"));

function sanitizeSubmission($data, $submission_type, $sanitizer) {

	if($submission_type === "catalogue") {
		$data->message = "CATALOGUE REQUEST";
	}

	$requirements = array(
		"contact" => array(
			"fname"=>"first name", 
			"lname"=>"last name", 
			"email"=>"email", 
			"message"=>"message", 
			"consent"=>"consent checkbox"
		),
		"registration" => array(
			"fname"=>"first name", 
			"lname"=>"last name", 
			"username"=>"user name", 
			"pass"=>"password", 
			"_pass"=>"password confirmation",
			"email"=>"email", 
			"address"=>"address", 
			"postcode"=>"postcode", 
			"url"=>"website address", 
			"consent"=>"consent checkbox"
		),
		"catalogue" => array(
			"fname"=>"first name", 
			"lname"=>"last name", 
			"email"=>"email", 
			"message"=>"message", 
			"address"=>"address", 
			"postcode"=>"postcode", 
			"consent"=>"consent checkbox"
		)
	);
	$sanitized = array();
	$errors = array();

	foreach ($requirements[$submission_type] as $required_field => $label) {
		if( ! property_exists($data, $required_field)){
			$errors[] = $label;
		}
	}

	// Required fields missing
	if(count($errors)) return "Please fill in the following fields: " . implode(", ", $errors);

	foreach ($data as $field => $value) {

		switch ($field) {
	    	case 'fname':
	    	case 'lname':
		    	//TODO: $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
	    		$sanitized[$field] = $sanitizer->text($value, array("stripQuotes"=>true));
	    		if( ! preg_match("/^[A-Za-z]+$/", $sanitized[$field])) $errors[] = "Please enter a name using letters only";
	    		break;

	    	case 'tel':
	    		$sanitized[$field] = $sanitizer->digits($value);
		      	if(strlen($sanitized[$field]) < 9) $errors[] = "Telephone number should have at least 9 digits";
		      	break;

	    	case 'email':
	    		$sanitized[$field] = $sanitizer->email($value);
	    		// $sanitizer returns blank string if invalid
	    		if($submission_type === "registration"){
	    			$exists = wire("users")->get("email=" . $sanitizer->selectorValue($sanitized[$field]))->id;
	    			if($exists) $errors[] = "An account already exists for this email address";
	    		}
	    		if( ! preg_match("/^([\w\-\.]+)@((\[([0-9]{1,3}\.){3}[0-9]{1,3}\])|(([\w\-]+\.)+)([a-zA-Z]{2,4}))$/", $sanitized[$field])){
	    			$errors[] = "Please enter a valid email";
	    		}
		      	break;

	    	case 'username':
	    		// 2nd arg to santizer = beautify returned name https://processwire.com/api/ref/sanitizer/name/
	    		$sanitized[$field] = $sanitizer->name($value, true); 
	    		$exists = wire("users")->get($sanitized[$field])->id;
				if($exists){
					$errors[] = "Username unavailable - please try again";
				}
				if( ! preg_match("/^[A-Za-z0-9]+(?:[_-][A-Za-z0-9]+)*$/", $sanitized[$field])){
					$errors[] = "Please enter a username using only Letters, numbers, hyphens and underscores";
				}
		      	break;

	    	case 'url':
	    		$sanitized[$field] = $sanitizer->url($value);
	    		if( ! preg_match("/^(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]+\.[^\s]{2,}|www\.[a-zA-Z0-9]+\.[^\s]{2,})$/", $sanitized[$field])){
	    			$errors[] = "Please enter a valid website address";
	    		}
				break;

	    	case 'message':
	    		// Run $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
	    		$sanitized[$field] = $sanitizer->textarea($value);
	    		break;

	    	case 'address':
	    		// Run $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
	    		$sanitized[$field] = $sanitizer->textarea($value);
	    		if( ! preg_match("/^[A-Za-z\d,. -]+$/m", $sanitized[$field])){
	    			$errors[] = "Please enter an address consisting only of Letters, numbers and spaces";
	    		}
		      	break;

	    	case 'postcode':
	    		$sanitized[$field] = $sanitizer->alphanumeric($value);
	    		if( ! preg_match("/^(([A-Za-z][0-9]{1,2})|(([A-Za-z][A-HJ-Ya-hj-ya][0-9]{1,2})|(([A-Za-z][0-9][A-Za-z])|([A-Za-z][A-HJ-Ya-hj-y][0-9]?[A-Za-z]))))[\s]*[0-9][A-Za-z]{2}$/", $sanitized[$field])){
	    			$errors[] = "Please enter a valid postcode";
	    		}
	    		break;

	    	case 'consent':	    		
	        	$sanitized[$field] = $sanitizer->text($value);
	        	if($sanitized[$field] !== "granted") {
	        		$errors[] = "We need consent to store your data, please check the consent box and try again";	
	        	}
	    		break;
	    	
	    	default:
	    		// Do nothing
	    		break;
	    }
	}
	return count($errors) ? implode(", ", $errors) : $sanitized;
}