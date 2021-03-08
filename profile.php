<?php namespace ProcessWire;

if(!$user->isLoggedin()) throw new Wire404Exception(); 

$pcp = $modules->get("ProcessContactPages");

$pass_reset_form = "";
$base = wire("config")->urls;
$cssUrl = $base->InputfieldPassword."InputfieldPassword.css";
$pass_reset_form .= "<link rel='stylesheet' href='$cssUrl'>";

$jsUrls = array();
$jsUrls[] = $base->JqueryCore."JqueryCore.js";
$jsUrls[] = $base->InputfieldPassword."complexify/jquery.complexify.min.js";
$jsUrls[] = $base->InputfieldPassword."complexify/jquery.complexify.banlist.js";
$jsUrls[] = $base->JqueryCore."xregexp.min.js";
$jsUrls[] = $base->InputfieldPassword."InputfieldPassword.js";

foreach ($jsUrls as $jsUrl) {
	$pass_reset_form .= "<script src='$jsUrl'></script>";
}

// Password change form
$pass_reset_form .= "
 <h2>Change password</h2>
 <form action='./' method='post'>
 <ul class='Inputfields'><li class='Inputfield InputfieldPassword Inputfield_pass' id='wrap_Inputfield_pass' style=''>
 	<div class='InputfieldContent'><p class='description'>Minimum requirements: <span class='pass-require pass-require-minlength '>at least 6 characters long</span>, <span class='pass-require pass-require-letter '>letter</span>, <span class='pass-require pass-require-digit '>digit</span>.</p><p class='InputfieldPasswordRow'><label for='Inputfield_pass'>New password</label><input placeholder='New password' id='Inputfield_pass' class='FieldtypePassword InputfieldPasswordComplexify' name='pass' type='password' size='50' maxlength='128' autocomplete='new-password' data-banmode='loose' data-factor='0.7' data-minlength='6'> <span class='detail pass-scores' data-requirements='minlength letter digit'><span class='pass-fail'><i class='fa fa-fw fa-frown-o'></i>Not yet valid</span><span class='pass-invalid'><i class='fa fa-fw fa-frown-o'></i>Invalid</span><span class='pass-short'><i class='fa fa-fw fa-frown-o'></i>Too short</span><span class='pass-common'><i class='fa fa-fw fa-frown-o'></i>Too common</span><span class='pass-same'><i class='fa fa-fw fa-frown-o'></i>Same as old</span><span class='pass-weak'><i class='fa fa-fw fa-meh-o'></i>Weak</span><span class='pass-medium'><i class='fa fa-fw fa-meh-o'></i>Ok</span><span class='pass-good'><i class='fa fa-fw fa-smile-o'></i>Good</span><span class='pass-excellent'><i class='fa fa-fw fa-smile-o'></i>Excellent</span></span></p><p class='InputfieldPasswordRow'><label for='_Inputfield_pass'>Confirm</label><input placeholder='Confirm' class='InputfieldPasswordConfirm FieldtypePassword' type='password' size='50' id='_Inputfield_pass' name='_pass' value='' autocomplete='new-password' disabled='disabled'> <span class='pass-confirm detail'><span class='confirm-yes'><i class='fa fa-fw fa-smile-o'></i>Matches</span><span class='confirm-no'><i class='fa fa-fw fa-frown-o'></i>Does not match</span><span class='confirm-qty'><i class='fa fa-fw fa-meh-o'></i><span></span></span></span></p></div></li></ul>
 <input type='submit'>
 </form>
";

// Has user submitted a password change?
$pass = $input->post->pass; 
if($pass) {
 if(strlen($pass) < 6) {
   $page->body .= "<p>New password must be 6+ characters</p>";
   $page->body .= $pass_reset_form;
 } else if($pass !== $input->post->_pass) {
   $page->body .= "<p>Passwords do not match</p>";
   $page->body .= $pass_reset_form;
 } else {
   $user->of(false);
   $user->pass = $pass; 
   $user->save();
   $user->of(true);
   $page->body .= "<h2>Your password has been changed.</h2>
   <a href='/''>Continue</a>";
   // Remove registration form submission from contact system
   wire("log")->save("paul", "Password reset - removing submission");
   $pcp->activateAccount($user);
 }
} else {
	$page->body .= $pass_reset_form;
}
$page_template = $pcp->getPageTemplate();
$files->include($page_template);