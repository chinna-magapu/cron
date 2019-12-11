<?php
/* this is now obsolete and replaced by check_devices_minutewx.php, col last_minutewx changed to last_localwx
	DELETE AFTER THIS DEPLOYMENT
modified 4/06/2010 to work with timestamps in device local time rather than UTC
modified 1/26/2011 to work with new weather/site relationship
modified 2016 to work with new station deployment
*/

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
	$sql = "select distinct timezone from site where wsid is not null
			UNION
			select distinct timezone from site s inner join wx_singlesensor ss on s.code = ss.code";
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
idsite	wsid	name	timezone	lastwx	code	wxsrc	pk
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
$sql = "SELECT s.idsite, sd.wsid, s.name, s.timezone, T.lastwx, sd.latency, s.code, 'CS' AS wxsrc, CONCAT(s.code,'|',sd.wsid) AS pk
	FROM site s	INNER JOIN vw_currentws_deployment sd
		ON s.code = sd.code
	INNER JOIN
		(SELECT wsid, MAX(TIMESTAMP) AS lastwx FROM minuteweather GROUP BY wsid) T
	ON sd.wsid = T.wsid
	WHERE sd.tablename = 'minuteweather'  AND sd.clone = 0
	-- above gets Campbell Stations
UNION
	SELECT s.idsite, sd.wsid, s.name, s.timezone, T.lastwx, sd.latency, s.code,  'IP-100' AS wxsrc, CONCAT(s.code,'|',sd.wsid) AS pk
	FROM site s	INNER JOIN vw_currentws_deployment sd
		ON s.code = sd.code
	INNER JOIN
		(SELECT mac, MAX(TIMESTAMP) AS lastwx FROM minuteweatherrw GROUP BY mac) T
	ON sd.mac = T.mac
	WHERE sd.tablename = 'minuteweatherrw'
	-- above gets Rainwise
UNION
	SELECT s.idsite, sd.wsid, s.name, s.timezone, T.lastwx, sd.latency, s.code, 'CS' as wxsrc, CONCAT(s.code,'|',sd.wsid) AS pk
	FROM site s	INNER JOIN vw_currentws_deployment sd
		ON s.code = sd.code
	INNER JOIN
		(SELECT wsid, MAX(TIMESTAMP) AS lastwx FROM minuteweatherfg GROUP BY wsid) T
	ON sd.wsid = T.wsid
	WHERE sd.tablename = 'minuteweatherfg'
	-- FG and KEE: two rf gauges
UNION
	SELECT s.idsite, sd.wsid, s.name, s.timezone, T.lastwx, sd.latency, s.code, 'CS' as wxsrc, CONCAT(s.code,'|',sd.wsid) AS pk
	FROM site s	INNER JOIN vw_currentws_deployment sd
		ON s.code = sd.code
	INNER JOIN
		(SELECT wsid, MAX(TIMESTAMP) AS lastwx FROM wx_singlesensor1m GROUP BY wsid) T
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

// get latest timestamp in aggregate table (weather, weather2 or weather2f) from all active stations
// We need a UNION query with where clauses to prevent dups for AQU (weather and wx_singlesensor1m)
$sites = Array();
$sites['aggregate'] = Array();
$sites['minute']    = Array();
$sql = "SELECT 'a1' AS branch, sd.idsite, sd.code, sd.name, sd.timezone, T.lastwx, T.src, st.latency, st.wsid, st.type, sd.dest_table
	FROM vw_currentws_deployment sd INNER JOIN
		(SELECT w.idsite, src, lastwx FROM weather w INNER JOIN
			(SELECT idsite, MAX( TIMESTAMP) AS lastwx FROM weather GROUP BY idsite) T2
				ON w.timestamp = T2.lastwx AND w.idsite = T2.idsite
		) T
	ON sd.idsite = T.idsite
    INNER JOIN station st ON sd.wsid = st.wsid
	WHERE sd.dest_table = 'weather'
UNION
	-- @@FG @@KEE SPECIAL CASE for FG/KEE stations, which aggregate to weather2rf
	SELECT 'a2' AS branch, sd.idsite, sd.code, sd.name, sd.timezone, T.lastwx, T.src, st.latency, st.wsid, st.type, sd.dest_table
	FROM vw_currentws_deployment sd INNER JOIN
		(SELECT w.idsite, src, lastwx FROM weather2rf w INNER JOIN
			(SELECT idsite, MAX( TIMESTAMP) AS lastwx FROM weather2rf GROUP BY idsite) T2
				ON w.timestamp = T2.lastwx AND w.idsite = T2.idsite
		) T
	ON sd.idsite = T.idsite
    INNER JOIN station st ON sd.wsid = st.wsid
	WHERE sd.dest_table = 'weather2rf'
UNION
	-- @@AQU SPECIAL CASE 2nd station, because it does NOT aggregate to weather
	SELECT 'a3' AS branch, sd.idsite, sd.code, sd.name, sd.timezone, T.lastwx, 'CS' as src, st.latency, st.wsid, st.type, sd.dest_table
	FROM vw_currentws_deployment sd INNER JOIN
		(SELECT w.idsite, 'CS' as src, lastwx FROM wx_singlesensor15m w INNER JOIN
			(SELECT idsite, MAX( TIMESTAMP) AS lastwx FROM  wx_singlesensor15m GROUP BY idsite) T2
				ON w.timestamp = T2.lastwx AND w.idsite = T2.idsite
		) T
	ON sd.idsite = T.idsite
    INNER JOIN station st ON sd.wsid = st.wsid
	WHERE sd.dest_table = 'wx_singlesensor15m'
UNION
	-- @@ SPECIAL CASE for 2nd CD station, because it aggregates to weather2
	SELECT  'a4' AS branch, sd.idsite, sd.code, sd.name, sd.timezone, T.lastwx, T.src, T.src AS wxsrc, st.latency, st.wsid, st.type, sd.dest_table
	FROM site s INNER JOIN (
    	SELECT wsid, idsite, 'CS' as src, max(timestamp) as lastwx FROM weather2 group by wsid, idsite
	) T
	ON s.idsite = T.idsite
	INNER JOIN vw_currentws_deployment sd ON sd.idsite = T.idsite AND sd.wsid = T.wsid
	INNER JOIN station st ON sd.wsid = st.wsid";
echo $sql; die;
$changedtoOFF = $changedToOK = Array();
$result = mysqli_query($mysqli, $sql);
$debug_ts = Array();
while ($row = mysqli_fetch_array($result,MYSQLI_ASSOC)){
    // adjust device times to Eastern for comparison
	$debug_ts[] = $row;
    $eastern  = AdjustToEastern(new DateTime($row['lastwx']),$row['timezone']);
    $lastxmit = strtotime($eastern->format('Y-m-d H:i'));
	$type = $row['type'];
	$code = $row['code'];
	$wsid = $row['wsid'];
	$wssrc = $row['wxsrc'];
	$idsite = $row['idsite'];
	$tzone  = $row['timezone'];
	$aggtbl = $row['dest_table'];
	$minwxpk = $code."|".$wsid;
	$sites[$wsid] = Array('code'=>$code, 'idsite'=>$idsite);
	// latency is the time needed to get new data, and we'll double it. Since latency is in minutes,
	// 75 * latency = 1.25 * 60 * latency = seconds
	$okdiff   = $row['latency'] *  75;  // allow for 1.25 * latency
	$warndiff = $row['latency'] * 240;  // 4 * latency

	if ($row['src'] != $row['wxsrc']) {
		$sql = "SELECT timestamp AS lastts, src FROM {$aggtbl} where idsite = {$row['idsite']} AND src='{$row['wxsrc']}'  ORDER BY timestamp DESC LIMIT 1";
		echo "\nlast src sql {$sql} ";
		$tmp1 = $db->getScalar($sql);
		echo " returned {$tmp1}\n";
		$tmp2 = AdjustToEastern(new DateTime($tmp1),$row['timezone']);
		$lastfromsrc = strtotime($tmp2->format('Y-m-d H:i'));
	} else {
		$lastfromsrc = $lastxmit;
	}
	$secsdiff = $now - $lastfromsrc;
	if ($debug) {
		echo "\n{$code} TZ:{$row['timezone']} NOW EAST ",Date('Y-m-d H:i',$now),"  LastWX EAST ",($eastern->format('Y-m-d H:i')),
			" Last From src ",Date('Y-m-d H:i',$lastfromsrc),"\n";
		print_r($row);
	}

	$status = "OK";
	if ($secsdiff > $warndiff) {
		$status = "OFFLINE";
	} elseif ($secsdiff > $okdiff ) {
		$status = "WARNING";
	}
	$laststat = isset($laststatus[$wsid]) ? $laststatus[$wsid]['status'] : "";
	$laststatchange = isset($laststatus[$wsid]) ? $laststatus[$wsid]['status_changed'] : "";
	if ($debug) {
		echo "STATUS: {$status} LAST ST: {$laststat} DIFF secs: {$secsdiff} OK_IF <= {$okdiff} WARN_IF <= {$warndiff}\n";
	}
	$srclast  = Date('Y-m-d H:i', $lastfromsrc);
	$expected = Date('Y-m-d H:i', $now - $okdiff);
	$lastminwx = null;
	if (isset($lastminuteweather[$minwxpk])) {
		$lastminwx = $lastminuteweather[$minwxpk]['lastwx'];
		//echo "LMWX {$lastminwx}";
		$lastminwx = AdjustToEastern(new DateTime($lastminwx), $tzone);
		$lastminwx = $lastminwx->format('Y-m-d H:i');
	}
	if ($status != $laststat || $laststatchange == "") {
		$sql = "INSERT INTO station_check ( `wsid`,`type`, `status_changed`, `checked`, `last_wx`, `last_minutewx`,
			`last_expected`, `status`, `timezone`, `code`, `idsite`) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
		$params = array("isssssssssi",$wsid, $type, $now_str, $now_str, $srclast, $lastminwx, $expected, $status, $tzone, $code, $idsite);
		$debug_sql = "INSERT INTO station_check ( `wsid`,`type`, `status_changed`, `checked`, `last_wx`, `last_minutewx`,
`last_expected`, `status`, `timezone`, `code`, `idsite`) VALUES ({$wsid},'{$type}','{$now_str}','{$now_str}','{$srclast}','{$lastminwx}','{$expected}'
,'{$status}','{$tzone}','{$code}','{$idsite}')";
echo "\nDEBUG SQL {$debug_sql}";
		//$ok = $db->exec_prepared($sql, $params);
		if ($db->error) {
			echo $sql,"\n",print_r($params);
			echo "\n!!ERROR ",$db->error,"\n";
		}
		if ($laststat == "OFFLINE" && $status == "OK") {
			$changedToOK[] = "{$type} Station {$wsid} at {$code} is now online again. Last report was received {$srclast}.";
		}
		if ($laststat != "OFFLINE" && $status == "OFFLINE"){
			$changedtoOFF[] = "{$type} Station {$wsid} at {$code} has gone offline. Last report was received {$srclast} (expected before {$expected})";
		}
	} else {
        $sql = "UPDATE station_check SET checked=?, last_wx=?,  `last_minutewx`=?, last_expected=? WHERE wsid=? and status_changed=?";
		$params = array("ssssis", $now_str,  $srclast, $lastminwx, $expected, $wsid, $laststatchange);
        $debug_sql = "UPDATE station_check SET checked='{$now_str}', last_wx='{$srclast}', `last_minutewx`='{$lastminwx}', last_expected='{$expected}' WHERE wsid='{$wsid}' and status_changed='{$laststatchange}'";
		if (!$debug) {
			//$ok = $db->exec_prepared($sql, $params);
			if ($db->error) {
				echo $sql,"\n",print_r($params);
				echo "\n!!ERROR ",$db->error,"\n";
			}
		}
	}
}
mysqli_free_result($result);
if ($debug) {
	echo "\nWX TIMESTAMPS\n",print_r($debug_ts, TRUE);
	exit;
}

//insert the check record
$etime = microtime(true);
$runtime = round(($etime - $stime),2);
$hostname = php_uname("n");
$comment = "check_devices finished; runtime {$runtime} seconds";
$sql = "INSERT INTO alertcheck(script,comment, machine) VALUES ('check_devices',?,?)";
$params = array("ss",$comment,$hostname);
if (!$debug) {
	$ok = $db->exec_prepared($sql,$params);
}
$db = null;

$now = new DateTime();
$comment = count($sites['aggregate'])." sites offline: ";

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
	   // mail($mailTo, $subject, $msgbody, $headers);
	}
}

?>