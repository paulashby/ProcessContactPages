<?php namespace ProcessWire;

if($user->isLoggedin()) $session->redirect("/internal/profile/"); 

$pcp = $modules->get("ProcessContactPages");
$pcpc = $modules->getConfig("ProcessContactPages");

if($input->post->username && $input->post->pass) {
   $username = $sanitizer->username($input->post->username);
   $pass = $input->post->pass;
   $u = $users->get($username);
   $pcp->login($u, $username, $pass);
}

// present the login form
$headline = $input->post->username ? "Login failed" : "Please login";
$page->body = "
   <h2>$headline</h2>
   <form action='./' method='post'>
      <p>
         <label>Username <input type='text' name='username'></label>
         <label>Password <input type='password' name='pass'></label>
      </p>
      <input type='submit'>
   </form>
   <p><a href='/reset-pass/'>Forgot your password?</a></p>
";

$hf_template = $pcpc["hf_template"];

// Use head/foot template if provided in module config
if($hf_template){
   include $config->paths->templates . $hf_template;
} else {
   // Use generic page furniture
   echo "
   <!DOCTYPE html>
      <html lang='en'>
      <head>
         <meta charset='UTF-8'>
         <meta name='viewport' content='width=device-width, initial-scale=1.0'>
         <title>Document</title>
      </head>
      <body>
         {$page->body}
      </body>
   </html>
";
}