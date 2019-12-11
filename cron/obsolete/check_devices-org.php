<?php
/* modified 4/06/2010 to work with timestamps in device local time rather than UTC */
/* modified 1/26/2011 to work with new weather/site relationship */
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
$db = new DB;
$mysqli = $db->mysqli;
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

if (!$debug){
	$hostname = php_uname("n");
	$sql = "INSERT INTO alertcheck(script,comment, machine) VALUES ('check_devices','Check Weather Devices Ran',{$hostname})";
	$ok = mysqli_query($mysqli,$sql);
}
$sql = "SELECT s.idsite, s.wsid, name, timezone, T.lastwx, s.code, s.wxsrc
	FROM site s	INNER JOIN
	(SELECT wsid, MAX(TIMESTAMP) AS lastwx FROM minuteweather GROUP BY wsid) T
	ON s.wsid = T.wsid
	WHERE s.wsid IS NOT NULL
UNION
	SELECT s.idsite, s.wsid, s.name, timezone, T.lastwx, s.code, s.wxsrc
	FROM site s INNER JOIN station_deployment sd ON s.idsite = sd.idsite INNER JOIN
	(SELECT mac, MAX(TIMESTAMP) AS lastwx FROM minuteweatherrw GROUP BY mac) T
	ON sd.mac = T.mac
	WHERE s.wsid IS NOT NULL AND sd.edate IS NULL";
$lastminuteweather = $db->query($sql, false, 'code');

$sql = "SELECT DISTINCT sc.wsid, lastcheck, status, status_changed
        FROM station_check sc INNER JOIN
                ( SELECT wsid, MAX(checked) AS lastcheck
                        FROM station_check GROUP BY wsid
                ) T
        ON sc.wsid = T.wsid AND sc.checked = T.lastcheck;";
$laststatus = $db->query($sql,false,'wsid');
//print_r($laststatus); die;
// get latest timestamp from all active stations
// note that we join on wsid only because we only want the current site for any given station
$sites = Array();
$sites['aggregate'] = Array();
$sites['minute']    = Array();
$sql = "SELECT s.idsite, s.code, name, timezone, T.lastwx, T.src, s.wxsrc, st.latency, s.wsid, st.type
	FROM site s	INNER JOIN
	(
	SELECT w.idsite, src, lastwx FROM weather w INNER JOIN
		(SELECT idsite, MAX( TIMESTAMP) AS lastwx FROM weather GROUP BY idsite) T2
		ON w.timestamp = T2.lastwx AND w.idsite = T2.idsite
	) T
	ON s.idsite = T.idsite
	INNER JOIN station st ON s.wsid = st.wsid
	WHERE s.wsid IS NOT NULL
    UNION
	SELECT s.idsite, s.code, name, timezone, T.lastwx, T.src, s.wxsrc, st.latency, s.wsid, st.type
	FROM site s	INNER JOIN
	(
	SELECT w.idsite, 'CS' as src, lastwx FROM wx_singlesensor1m w INNER JOIN
		(SELECT idsite, MAX( TIMESTAMP) AS lastwx FROM wx_singlesensor1m GROUP BY idsite) T2
		ON w.timestamp = T2.lastwx AND w.idsite = T2.idsite
	) T
	ON s.idsite = T.idsite
	INNER JOIN station st ON s.wsid = st.wsid
	WHERE s.wsid IS NOT NULL";

$changedtoOFF = $changedToOK = Array();
$result = mysqli_query($mysqli, $sql);
while ($row = mysqli_fetch_array($result)){
    // adjust device times to Eastern for comparison
    $eastern  = AdjustToEastern(new DateTime($row['lastwx']),$row['timezone']);
    $lastxmit = strtotime($eastern->format('Y-m-d H:i'));
	$type = $row['type'];
	$code = $row['code'];
	$wsid = $row['wsid'];
	$idsite = $row['idsite'];
	$tzone  = $row['timezone'];
	$sites[$wsid] = Array('code'=>$code, 'idsite'=>$idsite);
	// latency is the time needed to get new data, and we'll double it
	$okdiff   = $row['latency'] *  75;  // allow for 1.25 * latency
	$warndiff = $row['latency'] * 240;  // 4 * latency

	//if ($debug) print_r($row); echo "$\n",($eastern->format('Y-m-d H:i'));
	if ($row['src'] != $row['wxsrc']) {
		$sql = "SELECT timestamp AS lastts, src FROM weather where idsite = {$row['idsite']} AND src='{$row['wxsrc']}'  ORDER BY timestamp DESC LIMIT 1";
		//echo "\nlast src sql {$sql} ";
		$tmp1 = $db->getScalar($sql);
		//echo " returned {$tmp1}\n";
		$tmp2 = AdjustToEastern(new DateTime($tmp1),$row['timezone']);
		$lastfromsrc = strtotime($tmp2->format('Y-m-d H:i'));
	} else {
		$lastfromsrc = $lastxmit;
	}
	$secsdiff = $now - $lastfromsrc;
	if ($debug) echo "\n{$code}: TZ: ",date_default_timezone_get();
	if ($debug) echo "\nNow {$now} ",Date('Y-m-d H:i',$now), " lastfromsrc ",Date('Y-m-d H:i',$lastfromsrc);

	$status = "OK";
	if ($secsdiff > $warndiff) {
		$status = "OFFLINE";
	} elseif ($secsdiff > $okdiff ) {
		$status = "WARNING";
	}
	$laststat = isset($laststatus[$wsid]) ? $laststatus[$wsid]['status'] : "";
	$laststatchange = isset($laststatus[$wsid]) ? $laststatus[$wsid]['status_changed'] : "";
	if ($debug) echo "\nST: {$status} LST: {$laststat} {$code}: secsdiff okdiff warning diff secs: {$secsdiff} {$okdiff} {$warndiff}\n";
	$srclast  = Date('Y-m-d H:i', $lastfromsrc);
	$expected = Date('Y-m-d H:i', $now - $okdiff);
	$lastminwx = null;
	if (isset($lastminuteweather[$code])) {
		$lastminwx = $lastminuteweather[$code]['lastwx'];
		//echo "LMWX {$lastminwx}";
		$lastminwx = AdjustToEastern(new DateTime($lastminwx), $tzone);
		$lastminwx = $lastminwx->format('Y-m-d H:i');
	}
	if ($status != $laststat || $laststatchange == "") {
		$sql = "INSERT INTO station_check ( `wsid`,`type`, `status_changed`, `checked`, `last_wx`, `last_minutewx`,
			`last_expected`, `status`, `timezone`, `code`, `idsite`) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
		$params = array("isssssssssi",$wsid, $type, $now_str, $now_str, $srclast, $lastminwx, $expected, $status, $tzone, $code, $idsite);
		$ok = $db->exec_prepared($sql, $params);
		if ($db->error) {
			echo "\n!!ERROR ",$db->error,"\n";
		}
		if ($laststat == "OFFLINE" && $status == "OK") {
			$changedtoOK[] = "{$type} Station {$wsid} at {$code} is now online again. Last report was received {$srclast}.";
		}
		if ($laststat != "OFFLINE" && $status == "OFFLINE"){
			$changedtoOFF[] = "{$type} Station {$wsid} at {$code} has gone offline. Last report was received {$srclast} (expected before {$expected})";
		}
	} else {
        $sql = "UPDATE station_check SET checked=?, last_wx=?,  `last_minutewx`=?, last_expected=? WHERE wsid=? and status_changed=?";
		$params = array("ssssis", $now_str,  $srclast, $lastminwx, $expected, $wsid, $laststatchange);
		$ok = $db->exec_prepared($sql, $params);
		if ($db->error) {
			echo "\n!!ERROR ",$db->error,"\n";
		}
	}
}
mysqli_free_result($result);

//insert the check record
$etime = microtime(true);
$runtime = round(($etime - $stime),2);
$hostname = php_uname("n");
$comment = "check_devices finished; runtime {$runtime} seconds";
$sql = "INSERT INTO alertcheck(script,comment, machine) VALUES ('check_devices',?,?)";
$params = array("ss",$comment,$hostname);
$ok = $db->exec_prepared($sql,$params);
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
    foreach($changedtoOFF as $msgline){
        $msgbody .= $msgline."\r\n\r\n";
	}
    foreach($changedtoOK as $msgline){
        $msgbody .= $msgline."\r\n\r\n";
	}
	$msgbody .= "\n\n";
	mail($mailTo, $subject, $msgbody, $headers);
}

?>