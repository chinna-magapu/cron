<?php
require "DB.class.php";
$debug = false;
date_default_timezone_set("America/New_York");

function mailMsg($subject, $body, $delay=false){
	$args = array('script'=>'daily_station_report','subject'=>$subject,'body'=>$body,'debug'=>'0', 'mailfr'=>'admin@bioappeng.com');
    if ($delay !== false) {
        $args['delay'] = $delay;
    }
	$uri  = 'http://bioappeng.us/track/alert_mailer.php';
	$uri  = 'http://www.bioappeng.com/track/alert_mailer.php';

	$opts = array('http'=>array('method'=>'POST', 'header'=>'Content-Type: application/x-www-form-urlencoded', 'content'=>http_build_query($args)));
	$context = stream_context_create($opts);
    $result = file_get_contents($uri, false, $context);
	return $result;
}

function station_phrase($cnt) {
	return $cnt == 1 ?  "STATION IS" : "STATIONS ARE";
}
$db = new DB;
$mysqli = $db->mysqli;
$mailTo   = 'bioappeng@gmail.com,kalebdempsey.rstl@gmail.com,paultangel.rstl@gmail.com,rstl.services@gmail.com';
$mailCc   = 'cmeadow@dbtelligence.com';
$mailFrom = 'weather_admin@bioappeng.com';

$wsid_sql = "SELECT sc.`wsid`, sc.`type`, `status_changed`, `checked`, `last_wx`, `last_expected`, `status`, s.`timezone`,
	s.`code`, s.`idsite`, TIMEDIFF(last_expected, last_wx) AS timediff, st.rw_weatherpage
	FROM `station_check` sc INNER JOIN
		(SELECT MAX(checked) AS lastcheck, wsid FROM station_check GROUP BY wsid) T
	ON sc.wsid = T.wsid AND sc.checked = T.lastcheck
	INNER JOIN vw_currentws_deployment s ON sc.wsid = s.wsid
        left join station st on s.mac = st.rwmac
	ORDER BY sc.status, last_wx DESC, code";

$data = $db->query($wsid_sql);
//print_r($data); // die;
$okcnt = 0;
$wsout = "Weather Station Check (All Times Adjusted to US Eastern):\r\n\r\n";
$notok['W'] = $notok['O'] = $ok = Array();
foreach ($data as $row) {
	if ($row['status'] == "OK") {
		$ok[] = $row;
		$okcnt++;
	} else {
		$notok[substr($row['status'],0,1)][] = $row;
	}
}
$cnt = count($notok['O']);
if ($cnt > 0) {
	$wsout .= "\r\n{$cnt} ".station_phrase($cnt)." OFFLINE:\r\n";
	foreach ($notok['O'] as $row) {
		$hrsoff = substr($row['timediff'],0,2);
		$minoff = substr($row['timediff'],3,2);
		$code = substr($row['code'].'  ',0,4);
		$at = ($row['type'] == 'ip-100' ? '  ' : ' ').$code;
		$wsout .= "    {$row['type']} {$row['wsid']} {$at} Last Weather: {$row['last_wx']}; offline for ".substr($row['timediff'],0,-3)."\r\n";
	}
}
$cnt = count($notok['W']);
if ($cnt > 0) {
	$wsout .= "\r\n{$cnt} ".station_phrase($cnt)." IN WARNING STATUS:\r\n";
	foreach ($notok['W'] as $row) {
		$hrsoff = substr($row['timediff'],0,2);
		$minoff = substr($row['timediff'],3,2);
		$code = substr($row['code'].'  ',0,4);
		$at = ($row['type'] == 'ip-100' ? '  ' : ' ').$code;
		$wsout .= "    {$row['type']} {$row['wsid']} {$at} Last Weather: {$row['last_wx']}; offline for ".substr($row['timediff'],0,-3)."\r\n";
	}
}

//print_r($ok);
$wsout .= "\r\n{$okcnt} ".station_phrase($okcnt). " ONLINE AND UP-TO-DATE:\r\n";
if ($okcnt > 0) {
	foreach ($ok as $row) {
		$code = substr($row['code'].'  ',0,4);
		$hrsoff = substr($row['timediff'],0,2);
		$minoff = substr($row['timediff'],3,2);
		$code = substr($row['code'].'  ',0,4);
		$at = ($row['type'] == 'ip-100' ? '  ' : ' ').$code;
		$wsout .= "    {$row['type']} {$row['wsid']} {$at} Last Weather: {$row['last_wx']}\r\n";
	}
}
//echo $wsout; die;
$now = date("Y-m-d H:i");
$headers = 'From: '.$mailFrom."\r\n".
   	'Cc: '.$mailCc."\r\n"
    .'X-Mailer: PHP/' . phpversion(). "\r\n" ;
$subject = 'BAE Weather Station Daily Report ';
$msgbody = "Weather station status check at ".$now."\r\n\r\n".$wsout."\r\n\r\n";
/*
$delay = false;
echo mailMsg($subject, $msgbody, $delay);
*/
$mailsent = mail($mailTo, $subject, $msgbody, $headers);
if (!$mailsent) {
	echo "\nMail Send failed.\n";
} else {
	//echo "\nMail Send succeeded.\n";
}
$mach = php_uname("n");
$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('daily_station_report','Daily Station Report','{$mach}')";
$ok = $db->exec($sql);
$db = null;
?>
