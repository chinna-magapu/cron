<?php
require("DB.class.php");

function minDownToQuarterHour($timestring) {
    $minutes = date('i', strtotime($timestring));
    return $minutes % 15;
}
function minUpToQuarterHour($timestring) {
    $minutes = date('i', strtotime($timestring));
    return 15 - ($minutes % 15);
}

function nearestQtr($ts, $below) {

	if ($below) {
		$mins = minDownToQuarterHour($ts);
		return Date("Y-m-d H:i:00",strtotime($ts.' - '.$mins.' minutes'));
	} else {
		$mins = minUpToQuarterHour($ts);
		return Date("Y-m-d H:i:00",strtotime($ts.' + '.$mins.' minutes'));
	}
	$mins = substr($ts,14,2);
}

function AggregateToWeather($mac, $idsite, $wsid, $first_ts, $last_ts) {
	global $db;
	// we will add a margin to the timestamp range by using then earest quarter  hour before and after
	$botts  = nearestQtr($first_ts,true);
	$topts  = nearestQtr($last_ts,false);
	$sql =
"
REPLACE INTO weather
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, srad, t1, mv1, mv2, ver, dewpoint, heatindex, windchill, cumrf,src, wsbatt)

SELECT {$idsite}, {$wsid},
TIMESTAMP(CAST(DATE(timestamp) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(timestamp) BETWEEN 46 AND 59 THEN 1+HOUR(timestamp) ELSE HOUR(timestamp) END,':',
CASE
WHEN MINUTE(timestamp) BETWEEN 46 AND 59 OR MINUTE(timestamp) = 0 THEN '00'
WHEN MINUTE(timestamp) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(timestamp) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(timestamp) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(tia*0.1) AS tout, AVG(ria) AS hum, AVG(bia*0.1) AS baro,
	AVG(wia) AS wspd, AVG(dia) AS wdir, MAX(wih) AS gust,
	SUM(ris) AS rf, AVG(sia) AS srad,
	NULL, NULL, NULL, 'IP-100' AS ver,
	AVG(dewpoint*0.1) AS dewpoint,AVG(heatindex*0.1) AS heatindex, AVG(windchill*0.1) AS windchill,
	MAX(rds) AS cumrf, 'IP-100' as Src, AVG(batt) AS wsbatt
FROM minuteweatherrw
WHERE mac='{$mac}' AND timestamp >= '{$first_ts}' AND timestamp <= '{$last_ts}'
GROUP BY mac,
TIMESTAMP(CAST(DATE(timestamp) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(timestamp) BETWEEN 46 AND 59 THEN 1+HOUR(timestamp) ELSE HOUR(timestamp) END,':',
CASE
WHEN MINUTE(timestamp) BETWEEN 46 AND 59 OR MINUTE(timestamp) = 0 THEN '00'
WHEN MINUTE(timestamp) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(timestamp) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(timestamp) BETWEEN 31 AND 45 THEN '45'
END,':00'));
";
//echo "<pre>SQL\n";
//echo $sql;

	$test = $db->exec($sql);
	if ($test !== false) {
		return "\nAggegation to weather table\nfrom {$first_ts} to {$last_ts} succeeded.\n{$test} rows were written";
	} else {
		return '<pre>Error: '.$db->error.'</pre>';
	}
}

function zn($val){
	if (empty($val) && $val !== 0 && $val !== '0') {
		return "NULL";
	} else {
		return "'{$val}'";
	}
}

function fetchStation($mac) {
	global $macs, $debug, $db;
	date_default_timezone_set($macs[$mac]['timezone']);
	$today = date('Y-m-d 00:00:00');
	$now   = date('Y-m-d H:i:00');
	date_default_timezone_set('America/New_York');

	$urlbase = "http://rainwise.net/inview/api/stationdata.php?star=1&username=bioappeng&pid=521b64b3a9788e651686c088cd8cbd5f&sid=527677375428a183d338a81f87362f03";
	// &sdate=2014-10-10%2001:00:00&edate=2014-10-10%2001:15:00&mac=0090C2EF12A0
	$startts = $macs[$mac]['lastwx'] > '' ? $macs[$mac]['lastwx'] : $today;
	//rainwise.net allows up to ca.44000 record (about 30 days); keep it safe at 29
	if ( strtotime($today) - strtotime($macs[$mac]['lastwx']) > (86400 * 29)) {
		$startts = strtotime($today.' -29 day');
	}
	$endts   = $now;

    $url = $urlbase."&sdate=".urlencode($startts)."&edate=".urlencode($endts)."&mac=".$mac;
	if ($debug) echo "\n{$url}\n";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$csvdata = file_get_contents($url,false,$context);
	$savepath = "ip100data.csv";
   	$handle = fopen($savepath,'w');
	fwrite($handle, $csvdata);
	fclose($handle);

	//if ($debug) echo "\n{$csvdata}\n";
	$sql = "REPLACE INTO minuteweatherrw (`mac`, `timestamp`, `cd`, `tia`, `til`, `tih`, `tdl`, `tdh`, `ria`, `ril`, `rih`, `rdl`, `rdh`,
		 `bia`, `bil`, `bih`, `bdl`, `bdh`, `wia`, `dia`, `wih`, `dih`, `wdh`, `ddh`, `ris`, `rds`, `lis`, `lds`, `sia`, `sis`, `sds`, `unt`,
		 `ver`, `heatindex`, `windchill`, `dewpoint`, `uv`, `batt`, `evpt`, `serial`, `t`, `flg`, `ip`, `utc`,
		 `tinia`, `tinil`, `tinih`, `tindl`, `tindh`) VALUES ";

	$sql = "REPLACE INTO minuteweatherrw (`mac`, `timestamp`, `tia`, `til`, `tih`, `tdl`, `tdh`, `ria`, `ril`, `rih`, `rdl`, `rdh`,
		 `bia`, `bil`, `bih`, `bdl`, `bdh`, `wia`, `dia`, `wih`, `dih`, `wdh`, `ddh`, `ris`, `rds`, `lis`, `lds`, `sia`, `sis`, `sds`,
		 `heatindex`, `windchill`, `dewpoint`, `uv`, `batt`, `evpt`, `flg`, `utc`, `tinia`, `tinil`, `tinih`, `tindl`, `tindh`) VALUES ";
	if (strlen($csvdata) >0){
		$lines = explode("\n", $csvdata);
		$array = array();
		// line 0 is header
		for($i=1; $i<count($lines); $i++) {
			$line = $lines[$i];
			if (strlen($line) > 0) $array[] = str_getcsv($line);
		}
		$rlines = array();
		foreach ($array as $row){
			$vals = array_map("zn",$row);
			$rlines[] = "(".implode(",",$vals).")";
		}
		$sql .= implode(",\n",$rlines)."\n";

		/*$savepath = "data.sql";
	   	$handle = fopen($savepath,'w');
		fwrite($handle, $sql);
		fclose($handle);
		die; */
		if (count($array)> 0){
			$test = $db->exec($sql);
			if ($test === FALSE) {
				echo "SQL Error: ".$db->error;
				echo $sql,"\n";
				return false;
			} else {
				echo "Success {$test} rows inserted.";
				return Array($startts, $endts);
			}
		}
	}

}

$debug = false;
if (PHP_SAPI == "cli"){
	$debug = $argc > 1 && $argv[1] == "debug";
} else {
	$debug = isset($_GET['debug']) > 1 && $_GET['debug'] == "debug";
	echo "<pre>";
}
date_default_timezone_set("America/New_York");
echo "IP100 Loader ",date('Y-m-d H:i:s'),"\n";

$error = "";
$db = New DB;
$db->debug = false;

$hostname = php_uname("n");

$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('ip100_weather','bioappeng.us IP100 Load Weather Job ran','{$hostname}')";
if ($debug) echo "<pre>\nquery\n{$sql}";
$db->exec($sql);

$macs = array();
$sql = "select wsid, mac, idsite, code, timezone from vw_currentws_deployment where type='ip-100' ORDER BY mac";
$result = $db->query($sql);
foreach ($result as $row) {
	$macs[$row['mac']] = Array('idsite'=>$row['idsite'],'wsid'=>$row['wsid'],'timezone'=>$row['timezone'],'lastwx'=>'');
}
$sql = "SELECT mac, max(timestamp) AS lastwx FROM minuteweatherrw GROUP BY mac";
$result = $db->query($sql);
foreach ($result as $row) {
	if (isset($macs[$row['mac']])){
	$macs[$row['mac']]['lastwx'] = $row['lastwx'];
}
}
if ($debug) print_r($macs);
foreach ($macs as $mac => $info) {
	echo "\nStation mac: {$mac}\n";
	$dts = fetchStation($mac);
	if (is_array($dts)) {
		//print_r($dts);
		echo AggregateToWeather($mac,$info['idsite'],$info['wsid'],$dts[0],$dts[1]);
	}
}
?>
