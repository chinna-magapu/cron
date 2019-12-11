<?php
$url = 'http://bioappeng.com/cron/load_weather.php';
$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
$data = file_get_contents($url,false,$context);
echo "<pre>{$data}</pre>";
?>
