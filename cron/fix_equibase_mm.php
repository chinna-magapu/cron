<?php
require_once('DB.class.php');
require("simplestats.php");
date_default_timezone_set("America/New_York");
$db = new DB;
$sql = "SELECT url, workout FROM equibase_workouts WHERE wmax IS NULL or wmin IS NULL";
$fixers = $db->query($sql);
foreach ($fixers as $k=>$data) {
	$sql = "SELECT times_json FROM equibase_workout_data WHERE url='{$data['url']}' AND workout={$data['workout']}";
	$json_data = $db->getScalar($sql);
	$times = json_decode($json_data);
	$wmax = max($times);
	$wmin = min($times);
	$sql = "UPDATE  equibase_workouts SET wmin={$wmin}, wmax={$wmax} WHERE url='{$data['url']}' AND workout={$data['workout']}\n";
	echo $sql;
	$rc = $db->exec($sql);
}

$db = null;
date_default_timezone_set("America/New_York");



?>
