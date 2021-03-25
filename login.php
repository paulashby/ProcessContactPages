<?php namespace ProcessWire;

if($user->isLoggedin()) $session->redirect("/"); 

$pcp = $modules->get("ProcessContactPages");

// present the login form
$headline = "Please login";

if($input->post->username && $input->post->pass) {

   $logged_in = $pcp->login($input->post->username, $input->post->pass);

   // Need to check $logged_in exists
   if(is_string($logged_in)){
      $headline = $logged_in;
   } else {
      $username = ucfirst($logged_in->name);
      $headline = "Hi $username";
   }
}

echo "
   <!DOCTYPE html>
      <html lang='en'>
      <head>
         <meta charset='UTF-8'>
         <meta name='viewport' content='width=device-width, initial-scale=1.0'>
         <title>Document</title>
      </head>
      <body>
         <h2>$headline</h2>
         <form action='./' method='post'>
            <p>
               <label>Username <input type='text' name='username'></label>
               <label>Password <input type='password' name='pass'></label>
            </p>
            <input type='submit'>
         </form>
         <p><a href='/reset-pass/'>Forgot your password?</a></p>
      </body>
   </html>
";