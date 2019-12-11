<?php
/* modified 4/06/2010 to work with timestamps in device local time rather than UTC */
/* modified 1/26/2011 to work with new weather/site relationship */
/* modified 2016 to work with new station deployment */

require "DB.class.php";
$stime = microtime(true);

function AdjustToEastern($datestamp, $tz){
    // compute difference between US/Eastern and $tz in hours
    date_default_timezone_set('America/New_York');
    $dt1 = New DateTime();
    date_default_timezone_set($tz);
    $dt2 = New DateTime();
    $diff = strtotime($dt1->format('Y-m-d H:i')) - strtotime($dt2->format('Y-m-d H:i'));
    $diff = intval($diff /3600) % 24 ;
    $datestamp->modify($diff." hour");
    date_default_timezone_set('America/New_York');
    return New DateTime($datestamp->format('Y-m-d H:i'));
}

function getEasternTZDiff($tz){
    //return difference between  $tz and GMT in seconds
    date_default_timezone_set('America/New_York');
    $dt1 = New DateTime();
    date_default_timezone_set($tz);
    $dt2 = New DateTime();
    $diff = strtotime($dt1->format('Y-m-d H:m')) - strtotime($dt2->format('Y-m-d H:m'));
    date_default_timezone_set('America/New_York');
    return $diff;
}

function getTZOffsets() {
	global $mysqli, $tzoffset;
	$tzoffset = array();
	$sql = "SELECT DISTINCT timezone FROM vw_currentws_deployment ";
	$result = mysqli_query($mysqli, $sql);
	while ($row = mysqli_fetch_array($result)){
		$tzoffset[$row['timezone']] = getEasternTZDiff($row['timezone']);
	}
}

$debug = false;
if (isset($argc)) {
	$debug = $argc > 1 && $argv[1] == "debug";
}
$db = new DB;
$mysqli = $db->mysqli;
//$mailTo   = 'bioappeng@gmail.com,johnrpeterson23@gmail.com';
$mailTo   = 'bioappeng@gmail.com';
$mailCc   = 'cmeadow@dbtelligence.com';
$mailFrom = 'admin@bioappeng.com';

$tzoffset = array();
getTZOffsets();

// get comparison time in US/Eastern
date_default_timezone_set('America/New_York');
$nowts = new DateTime();
$now = strtotime("now");
$now_str = Date("Y-m-d H:i",$now);
/*
idsite	wsid	name	timezone	last_wx	code	wxsrc	pk
100	9001992	Arlington Park	America/Chicago	2018-11-26 01:18:00	AP	IP-100	AP|9001992
801	72759	Aqueduct	America/New_York	2019-04-07 21:40:00	AQU	CS	AQU|72759
801	9000976	Aqueduct	America/New_York	2019-04-07 21:31:00	AQU	IP-100	AQU|9000976
802	70127	Belmont Park	America/New_York	2019-04-07 21:40:00	BEL	CS	BEL|70127
102	77935	Churchill Downs	America/New_York	2019-04-07 21:35:00	CD	CS	CD|77935
102	9000975	Churchill Downs	America/New_York	2019-04-07 21:31:00	CD	IP-100	CD|9000975
107	30972	Del Mar	America/Los_Angeles	2019-04-07 18:30:00	DMR	CS	DMR|30972
106	9001985	Emerald Downs	America/Los_Angeles	2019-04-02 20:09:00	EMD	IP-100	EMD|9001985
103	62304	Fair Grounds Race Course	America/Chicago	2019-04-07 20:40:00	FG	CS	FG|62304
104	82367	Keeneland	America/New_York	2019-04-07 21:30:00	KEE	CS	KEE|82367
104	9001195	Keeneland	America/New_York	2019-04-07 21:31:00	KEE	IP-100	KEE|9001195
411	71834	Oaklawn Park	America/Chicago	2019-04-07 21:30:00	OP	CS	OP|71834
304	28416	Santa Anita	America/Los_Angeles	2019-04-03 05:40:00	SA	CS	SA|28416
*/
// get the latest weather for each station from the SOURCE table
$sql = "SELECT sd.idsite, sd.wsid, sd.name, sd.timezone, T.last_wx, sd.latency, sd.code, 'CS' AS wxsrc, 'campbell' AS type, CONCAT(sd.code,'|',sd.wsid) AS pk
	FROM vw_currentws_deployment sd
	INNER JOIN
		(SELECT wsid, MAX(TIMESTAMP) AS last_wx FROM minuteweather GROUP BY wsid) T
	ON sd.wsid = T.wsid
	WHERE sd.tablename = 'minuteweather'  AND sd.clone = 0
	-- above gets Campbell Stations
UNION
	SELECT sd.idsite, sd.wsid, sd.name, sd.timezone, T.last_wx, sd.latency, sd.code, 'RW' AS wxsrc, 'ip-100' AS type, CONCAT(sd.code,'|',sd.wsid) AS pk
	FROM vw_currentws_deployment sd
	INNER JOIN
			(SELECT mac, MAX(TIMESTAMP) AS last_wx FROM minuteweatherrw GROUP BY mac) T
	ON sd.mac = T.mac
	-- RAINWISE - a WHERE clause is irrelevant because of join on MAX
UNION
	SELECT sd.idsite, sd.wsid, sd.name, sd.timezone, T.last_wx, sd.latency, sd.code, 'CS' AS wxsrc, 'campbell' AS type, CONCAT(sd.code,'|',sd.wsid) AS pk
	FROM vw_currentws_deployment sd
	INNER JOIN
		(SELECT wsid, MAX(TIMESTAMP) AS last_wx FROM minuteweatherfg GROUP BY wsid) T
	ON sd.wsid = T.wsid
	WHERE sd.tablename = 'minuteweatherfg'
	-- FG and KEE: two rf gauges
UNION
	SELECT sd.idsite, sd.wsid, sd.name, sd.timezone, T.last_wx, sd.latency, sd.code, 'CS' AS wxsrc, 'campbell' AS type, CONCAT(sd.code,'|',sd.wsid) AS pk
	FROM vw_currentws_deployment sd
	INNER JOIN
		(SELECT wsid, MAX(TIMESTAMP) AS last_wx FROM wx_singlesensor1m GROUP BY wsid) T
	ON sd.wsid = T.wsid
	WHERE sd.tablename = 'wx_singlesensor1m'
	-- @@AQU SPECIAL CASE
ORDER BY pk;
";
$lastminuteweather = $db->query($sql, false, 'pk');
if ($debug) {
	echo "\nLAST MINUTE WEATHER\n",print_r($lastminuteweather, TRUE);
}
$sql = "SELECT DISTINCT sc.wsid, lastcheck, status, status_changed
        FROM station_check sc INNER JOIN
                ( SELECT wsid, MAX(checked) AS lastcheck
                        FROM station_check GROUP BY wsid
                ) T
        ON sc.wsid = T.wsid AND sc.checked = T.lastcheck
		INNER JOIN vw_currentws_deployment sd
		ON sc.wsid = sd.wsid;";
$laststatus = $db->query($sql,false,'wsid');
if ($debug) {
	echo "\nLAST STATUS\n",print_r($laststatus, TRUE);
}

$changedtoOFF = $changedToOK = Array();
$stations_offline = Array();
foreach ($lastminuteweather AS $pk => $row) {
    /* adjust device times to Eastern for comparison */
    $eastern  = AdjustToEastern(new DateTime($row['last_wx']),$row['timezone']);
    $lastrcvd = strtotime($eastern->format('Y-m-d H:i'));
	$last_rx_east = $eastern->format('Y-m-d H:i');
	$type = $row['type'];
	$code = $row['code'];
	$wsid = $row['wsid'];
	$wssrc = $row['wxsrc'];
	$idsite = $row['idsite'];
	$tzone  = $row['timezone'];
	/*	latency is the time needed to get new data, and we'll allow a 25% margin
	 *	for OK, e.g. with a five minute latency, 75 * 5 = 385 secs
	*/
	$okdiff   = $row['latency'] *  75;  // allow for 1.25 * latency
	$warndiff = $row['latency'] * 240;  // 4 * latency
	$secsdiff = $now - $lastrcvd;
	if ($debug) {
		echo "\n{$code} TZ:{$row['timezone']} NOW EAST ",Date('Y-m-d H:i',$now),"  LastWX EAST ",($eastern->format('Y-m-d H:i')),"\n";
		print_r($row);
	}

	$status = "OK";
	if ($secsdiff > $warndiff) {
		$status = "OFFLINE";
		$stations_offline[$wsid] = "{$type} {$wsid} at {$code} {$row['name']}";
	} elseif ($secsdiff > $okdiff ) {
		$status = "WARNING";
	}
	$laststat = isset($laststatus[$wsid]) ? $laststatus[$wsid]['status'] : "";
	$laststatchange = isset($laststatus[$wsid]) ? $laststatus[$wsid]['status_changed'] : "";
	if ($debug) {
		echo "STATUS: {$status} LAST ST: {$laststat} DIFF secs: {$secsdiff} OK_IF <= {$okdiff} WARN_IF <= {$warndiff}\n";
	}
	$expected = Date('Y-m-d H:i', $now - $okdiff);
	/* interpretation changed. last_wx is Eastern time and last_minutewx is local */
	if ($status != $laststat || $laststatchange == "") {
		$sql = "INSERT INTO station_check (`wsid`,`type`, `status_changed`, `checked`, `last_wx`, `last_localwx`,
			`last_expected`, `status`, `timezone`, `code`, `idsite`) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
		$params = array("isssssssssi",$wsid, $type, $now_str, $now_str, $last_rx_east, $row['last_wx'], $expected, $status, $tzone, $code, $idsite);
		$debug_sql = "INSERT INTO station_check ( `wsid`,`type`, `status_changed`, `checked`, `last_wx`, `last_localwx`,\n"
					."`last_expected`, `status`, `timezone`, `code`, `idsite`) VALUES \n"
					."({$wsid},'{$type}','{$now_str}','{$now_str}','{$last_rx_east}','{$row['last_wx']}',\n"
					."'{$expected}','{$status}','{$tzone}','{$code}','{$idsite}')";
		if ($debug)	echo "\nDEBUG SQL {$debug_sql}";
		$ok = $db->exec_prepared($sql, $params);
		if ($db->error) {
			echo $sql,"\n",print_r($params);
			echo "\n!!ERROR ",$db->error,"\n";
		}
		if ($laststat == "OFFLINE" && $status == "OK") {
			$changedToOK[] = "{$type} Station {$wsid} at {$code} is now online again. Last data received was {$last_rx_east}.";
		}
		if ($laststat != "OFFLINE" && $status == "OFFLINE"){
			$changedtoOFF[] = "{$type} Station {$wsid} at {$code} has gone offline. Last data received was {$last_rx_east} (expected by {$expected})";
		}
	} else {
        $sql = "UPDATE station_check SET checked=?, last_wx=?,  `last_localwx`=?, last_expected=? WHERE wsid=? and status_changed=?";
		$params = array("ssssis", $now_str,  $last_rx_east, $row['last_wx'], $expected, $wsid, $laststatchange);
		if ($debug) {
	        $debug_sql = "UPDATE station_check SET checked='{$now_str}', last_wx='{$last_rx_east}', `last_localwx`='{$row['last_wx']}', last_expected='{$expected}' WHERE wsid='{$wsid}' and status_changed='{$laststatchange}'";
			echo "debug sql\n{$debug_sql}\n";
		}
		$ok = $db->exec_prepared($sql, $params);
		if ($db->error) {
			echo $sql,"\n",print_r($params);
			echo "\n!!ERROR ",$db->error,"\n";
		}
	}
}

//insert the check record
$etime = microtime(true);
$runtime = round(($etime - $stime),2);
$hostname = php_uname("n");
$comment = "check_devices finished; runtime {$runtime} seconds\n";
$comment = count($stations_offline)." stations offline: ";
foreach ($stations_offline as $station) {
	$comment .= "\n{$station}";
}
$sql = "INSERT INTO alertcheck(script,comment, machine) VALUES ('check_devices',?,?)";
$params = array("ss",$comment,$hostname);
if (!$debug) {
	$ok = $db->exec_prepared($sql,$params);
}
$db = null;
$now = new DateTime();
$send_change_alert = false;
if ($send_change_alert) {
//send mail
	if (count($changedtoOFF) > 0 || count($changedToOK) > 0) {
		// mail setup
		$headers = 'From: '.$mailFrom."\r\n".
	    	'Cc: '.$mailCc."\r\n" .
		    'X-Mailer: PHP/' . phpversion(). "\r\n" ;
		$subject = 'Weather Station(s) Status Changed ';
		$msgbody = "Weather station status check at ".$now->format("m/d/Y H:i")."\r\n\r\n";
		if (count($changedtoOFF) > 0) {
		    foreach($changedtoOFF as $msgline){
	    	    $msgbody .= $msgline."\r\n\r\n";
			}
		}
		if (count($changedToOK) > 0) {
		    foreach($changedToOK as $msgline){
	    	    $msgbody .= $msgline."\r\n\r\n";
			}
		}
		$msgbody .= "\n\n";
		if ($debug) {
			echo $msgbody;
		} else {
		   mail($mailTo, $subject, $msgbody, $headers);
		}
	}
}

?>