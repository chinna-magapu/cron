<?php
function roundToQuarterHour($ts) {
	/*	round down if 5 or less only ex:
		2016-01-22 15:48:23 -> return 2016-01-22 15:45:00
		2016-01-22 15:51:23 -> return 2016-01-22 15:51:00
     */
    $minutes = date('i', strtotime($ts));
	if ($minutes % 15 <= 6) {
		$minutes = $minutes - $minutes % 15;
	    return Date('Y-m-d H:i:00',strtotime(substr($ts,0,14).$minutes.':00'));
	} else {
	    return Date('Y-m-d H:i:00',strtotime($ts));
	}
}

if (PHP_SAPI != "cli") {
    exit;
}
if ($argc < 3) {
	echo "\nusage: php relay-check sitecode altid\n";
}
/*
	relay check - get 24 and 72 hour rainfall for master webcontrol
*/
require("DB.class.php");
$db = new DB;

date_default_timezone_set("America/New_York");
$sql = "INSERT INTO alertcheck (script, timestamp, comment, machine) VALUES ('relay-precip-check','".date('Y-m-d H:i:s')."','Started','VPS')";
$db->exec($sql);

$code = $argv[1];
$altid = $argv[2];
$sql = "SELECT idsite, timezone FROM site WHERE code=?";
$params = Array("s",$code);
$siterow = $db->fetchrow_prepared($sql,$params);
if (empty($siterow)) {
	echo "\nInvalid sitecode {$sitecode}\n";
}
$idsite = $siterow['idsite'];
$tzone  = $siterow['timezone'];
date_default_timezone_set($tzone);
$now = date("Y-m-d H:i:00");
// since we run at 02, 17, 32 and 47 normally, round DOWN to nearest 15
$now = roundToQuarterHour($now);
$checkstatus_due = "Status check due ".date("Y-m-d H:i:00", strtotime($now)+300);
$nowsecs = strtotime($now);
$look24 = Date('Y-m-d H:i:s',$nowsecs -  86340);  //86400
$look72 = Date('Y-m-d H:i:s',$nowsecs - 345540); //345600
$qsql = "SELECT 24 AS hrs, sum(RF) as TotRF FROM weather WHERE idsite={$idsite} AND timestamp BETWEEN '{$look24}' AND '{$now}'
UNION SELECT 72 AS hrs, sum(RF) as TotRF FROM weather WHERE idsite={$idsite} AND timestamp BETWEEN  '{$look72}' AND '{$now}'";

$rf = $db->query($qsql);
$rf24 = $rf72 = -1.0;
	//echo $qsql;
	//print_r($rf);
foreach ($rf as $k=>$data){
	if ($data['hrs'] == 24) $rf24 = $data['TotRF'] * 0.01;
	if ($data['hrs'] == 72) $rf72 = $data['TotRF'] * 0.01;
}
$isql = "REPLACE  INTO relaylog(`altid`, `code`, `timestamp`, `precip24`, `precip72`, `relay1`, `relay2`,`r1status`,`r2status`) VALUES
	('{$altid}','FG','{$now}',{$rf24},{$rf72},'Unk','Unk','{$checkstatus_due}','{$checkstatus_due}')";
echo $isql;
$db->exec($isql);

$db = null;
//echo "DONE!\n";
?>
