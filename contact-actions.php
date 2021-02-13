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
					$ipf = "UNAVAILABLE";
					if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
						$ipf = $_SERVER['HTTP_X_FORWARDED_FOR'];
					}
					$ipr = $_SERVER['REMOTE_ADDR'];
					$mssg = "Form appears to have been submitted by a bot with IP address details $ipf (HTTP_X_FORWARDED_FOR) and $ipr (REMOTE_ADDR)";
					wire("log")->save("bot-activity", $mssg);

					return json_encode(array("success"=>false, "error"=>"The form contained erros")); 
				}

				if(! isset($params->consent)) return json_encode(array("success"=>false, "error"=>"Please consent to the storage of your information so we can process your message"));

				$sanitized = sanitizeSubmission($params, $sanitizer);

				if(gettype($sanitized) === "string") {
					return json_encode(array("success"=>false, "error"=>$sanitized));	
				}
				$submitted = $pcp->processContactSubmission($sanitized);

				if($submitted["error"]){
					return json_encode(array("success"=>false, "error"=>$submitted["error"]));
				}
				return json_encode(array("success"=>true, "message"=>"Thanks for your submission - we'll get back to you as soon as possible")); 
			}
		} else {
			return json_encode(array("success"=>false, "error"=>"The form contained no input"));
		}
	}
	return json_encode(array("success"=>false, "error"=>"CSRF validation error"));
}
function sanitizeSubmission($data, $sanitizer) {

	$sanitized = array();

	foreach ($data as $field => $value) {

		//TODO: Do we need to sanitize our checkboxes to make sure they ARE checkboxes?
		if($field === 'fname' || $field === 'lname'){
			//TODO: Run $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
			$sanitized[$field] = $sanitizer->text($value, array("stripQuotes"=>true));
		} else if($field === 'tel'){
			$sanitized[$field] = $sanitizer->digits($value);
	      	if(strlen($sanitized[$field]) !== 11){
	      		$errors[] = "Invalid telephone number, please try again.";
	      	}
	      } else if($field === 'email'){
	      	$sanitized[$field] = $sanitizer->email($value);
		  	if( ! strlen($sanitized[$field])){
		    	$errors[] = "Invalid email, please try again.";
		    }
		} else if($field === 'message'){
        	//TODO: Run $sanitizer->entities on this when outputting - see https://processwire.com/api/ref/sanitizer/text/
        	$sanitized[$field] = $sanitizer->textarea($value);
        }
    }
    return count($errors) ? implode(", ", $errors) : $sanitized;
}