<?php namespace ProcessWire;

if(!$user->isLoggedin()) throw new Wire404Exception(); 

// Has user submitted a password change?
$pass = $input->post->pass; 
if($pass) {
 if(strlen($pass) < 6) {
   $page->body .= "<p>New password must be 6+ characters</p>";
 } else if($pass !== $input->post->pass_confirm) {
   $page->body .= "<p>Passwords do not match</p>";
 } else {
   $user->of(false);
   $user->pass = $pass; 
   $user->save();
   $user->of(true);
   $page->body .= "<p>Your password has been changed.</p>";
 }
}
// Password change form
$page->body .= "
 <h2>Change password</h2>
 <form action='./' method='post'>
 <p>
 <label>New Password <input type='password' name='pass'></label><br>
 <label>New Password (confirm) <input type='password' name='pass_confirm'></label>
 </p>
 <input type='submit'>
 </form>
 <p><a href='/logout/'>Logout</a></p>
";

$pcp = $modules->get("ProcessContactPages");
$page_template = $pcp->getPageTemplate();
$files->include($page_template);