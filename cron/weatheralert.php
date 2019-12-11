<?php
require("DB.class.php");

function windAlert($id, $wspd){
	// speeds are m/s
	$mph = round(2.23694 * $wspd,0);
	$mailCc   = 'cmeadow@roadrunner.com';
	$mailFrom = 'weatheralerts@bioappeng.com';
	if (!isset($_GET['debug'])) {
		$mailTo = 'cmeadow@dbtelligence.com, nlawsonis@gmail.com, mlpeterson23@gmail.com';
	}  else {
		$mailTo = 'cmeadow@dbtelligence.com';
	}
	$headers = 'From: '.$mailFrom."\r\n".
		'Cc: '.$mailCc."\r\n" .
		'X-Mailer: PHP/' . phpversion(). "\r\n" ;
	$subject = 'Wind Speed Alert';
	$now = Date('Y-m-d H:i:s');
	$msg = "Weather station {$id} has detected a wind gust of {$mph} mph at {$now} Eastern Time.\n";
	mail($mailTo, $subject, $msg, $headers);
	echo 1;
}

date_default_timezone_set("US/Eastern");

$debug = isset($_GET['debug']);
$db = New DB;
$db->debug = false;

$wsid = isset($_GET['id']) ? $_GET['id'] : false;
if (!$wsid) {
	echo 0;
	exit;
}

foreach ($_GET as $var=>$val) {
	switch($var) {
		case 'id': break;
		case 'windspeed' :
			windAlert($wsid,$val);
			break;
	}
}


?>