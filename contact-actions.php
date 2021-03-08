<?php namespace ProcessWire;

if($config->ajax) {

	if ($session->CSRF->hasValidToken('pcp_token')) {

		$_input = file_get_contents("php://input");

		if($_input) {

			// Careful as user is NOT necessarily logged in
			$req = json_decode($_input);
			$pcp = wire("modules")->get("ProcessContactPages");

			if(property_exists($req, "params")) {

				$params = $req->params;
				$bot = isset($params->website);

				if($bot){
					// TODO: send email to system administrator (yes, me)?
					// Log bot activity
					$ipf = "UNAVAILABLE";
					if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
						$ipf = $_SERVER['HTTP_X_FORWARDED_FOR'];
					}
					$ipr = $_SERVER['REMOTE_ADDR'];
					$mssg = "Form appears to have been submitted by a bot with IP address details $ipf (HTTP_X_FORWARDED_FOR) and $ipr (REMOTE_ADDR)";
					wire("log")->save("bot-activity", $mssg);

					return json_encode(array("success"=>false, "error"=>"The form contained errors")); 
				}

				// No consent
				if(! isset($params->consent)) return json_encode(array("success"=>false, "error"=>"Please consent to the storage of your information so we can process your message"));

				if(property_exists($params, "submission_type")){
					$submission_type = $params->submission_type;
					unset($params->submission_type); // Don't want stored in submission field with other params. No need to sanitize as not user input
				} else {
					return json_encode(array("success"=>false, "error"=>"Unknown submission type"));
				}

				$sanitized = sanitizeSubmission($params, $sanitizer);

				if(gettype($sanitized) === "string") {
					// Error string returned
					return json_encode(array("success"=>false, "error"=>$sanitized));	
				}
				// Form santized and validated - pass to ProcessContactPages module for processing
				$submitted = $pcp->processSubmission($sanitized, $submission_type);

				if($submitted["error"]){
					return json_encode(array("success"=>false, "error"=>$submitted["error"]));
				}
				return json_encode(array("success"=>true, "message"=>"Thanks for your submission - we'll get back to you as soon as possible. Please make sure to check your spam folder if you don't hear from us.")); 
			} else {
				return json_encode(array("success"=>false, "error"=>"The form contained no data"));
			}
		} else {
			return json_encode(array("success"=>false, "error"=>"The form contained no input"));
		}
	}
	return json_encode(array("success"=>false, "error"=>"CSRF validation error"));
}
function sanitizeSubmission($data, $sanitizer) {

	$sanitized = array();
	$errors = array();

	foreach ($data as $field => $value) {

		switch ($field) {
	    	case 'fname':
	    	case 'lname':
		    	//TODO: $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
	    		$sanitized[$field] = $sanitizer->text($value, array("stripQuotes"=>true));
	    		break;

	    	case 'tel':
	    		$sanitized[$field] = $sanitizer->digits($value);
		      	if(strlen($sanitized[$field]) !== 11){
		      		$errors[] = "Invalid telephone number, please try again.";
		      	}
		      	break;

	    	case 'email':
	    		$sanitized[$field] = $sanitizer->email($value);
	    		// $sanitizer returns blank string if invalid
			  	if( ! strlen($sanitized[$field])){
			    	$errors[] = "Invalid email, please try again.";
			    }
	    		break;

	    	case 'username':
	    		// 2nd arg to santizer = beautify returned name https://processwire.com/api/ref/sanitizer/name/
	    		$sanitized[$field] = $sanitizer->name($value, true); 
	    		$exists = wire("users")->get($sanitized[$field])->id;
			  	if($exists){
			    	$errors[] = "Username unavailable - please try again.";
			    }
	    		break;

	    	case 'url':
	    		$sanitized[$field] = $sanitizer->url($value);
	    		break;

	    	case 'message':
	    		// Run $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
	    		$sanitized[$field] = $sanitizer->textarea($value);
	    		break;

	    	case 'address':
	    		// Run $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
	    		$sanitized[$field] = $sanitizer->textarea($value);
	    		break;

	    	case 'postcode':
	    		$sanitized[$field] = $sanitizer->alphanumeric($value);
	    		break;

	    	case 'consent':	    		
	        	$sanitized[$field] = $sanitizer->text($value);
	        	if($sanitized[$field] !== "granted") {
	        		$errors[] = "We need consent to store your data, please check the consent box and try again.";	
	        	}
	    		break;
	    	
	    	default:
	    		// Do nothing
	    		break;
	    }
	}
    return count($errors) ? implode(", ", $errors) : $sanitized;
}