<?php
$args = array ('subject'=>'testing alert mailer by POS', 'body'=>'this is a test',"debug"=>1, 'script'=>'test-alert_mailer.php');
$args = array ('body'=>'this is a test',"debug"=>1, 'script'=>'test-alert_mailer.php');
$uri = 'http://bioappeng.com/track/alert_mailer.php';
$uri = 'http://www.bioappeng.com/track/alert_mailer.php';
$opts = array('http'=>array('method'=>'POST', 'header'=>'Content-Type: application/x-www-form-urlencoded', 'content'=>http_build_query($args)));
$context = stream_context_create($opts);
$result = file_get_contents($uri, false, $context);
print $result;
?>