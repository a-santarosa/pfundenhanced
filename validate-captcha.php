<?php 
if (!defined('ABSPATH')) {
		require_once dirname(__FILE__) . '/../../../wp-load.php';
	}
	require_once( ABSPATH . 'wp-admin/includes/post.php' );

	$captcha = $_POST['validateValue'];
	
	$returnArray = array( $_POST['validateId'], $_POST['validateError']);
	

if(isset($captcha)&& $captcha != "" && $_SESSION["code"] == $captcha)
{
$returnArray[] = "true";
}
else
{
$returnArray[] = "sda";
}

echo '{"jsonValidateReturn":'.json_encode( $returnArray ).'}';