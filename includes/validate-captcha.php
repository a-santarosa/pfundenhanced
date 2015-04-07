<?php 
session_start();
	
	$captcha = $_POST['captcha'];

if(isset($captcha)&& $captcha != "" && $_SESSION["code"] == $captcha)
{
echo "right";
exit;
}
else
{
echo "wrong";
exit;
}