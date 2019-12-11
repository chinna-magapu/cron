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

function fetchStation() {
	global $station_data, $debug, $verbose, $db, $sdate, $filldays;
	$mac = $station_data['mac'];
	$timezone = $station_data['timezone'];
	date_default_timezone_set($timezone);
	$today = date('Y-m-d 00:00:00');
	$now   = date('Y-m-d H:i:00');
	$days_add = $filldays - 1;
	date_default_timezone_set('America/New_York');

	$urlbase = "http://rainwise.net/inview/api/stationdata.php?star=1&username=bioappeng&pid=521b64b3a9788e651686c088cd8cbd5f&sid=527677375428a183d338a81f87362f03";
	// &sdate=2014-10-10%2001:00:00&edate=2014-10-10%2001:15:00&mac=0090C2EF12A0
	$startts = $sdate;
	$endts   = Date('Y-m-d 23:59:59', strtotime($startts." + {$days_add} DAY"));

    $url = $urlbase."&sdate=".urlencode($startts)."&edate=".urlencode($endts)."&mac=".$mac;
	if ($debug) echo "\n{$url}\n";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$csvdata = file_get_contents($url,false,$context);
	$savepath = "ip100_bfdata.csv";
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

$debug = $verbose = false;
$filldays = 1;
$sdate = $code = "";
for ($argndx=1; $argndx < $argc; $argndx++) {
	$debug = $debug || ($argv[$argndx] == "-debug");
	$verbose = $verbose || ($argv[$argndx] == "-verbose");
	if (substr($argv[$argndx],0,5) == '-code') {
		$code = substr($argv[$argndx],5);
	}
	if (substr($argv[$argndx],0,3) == '-sd') {
		$sdate = substr($argv[$argndx],3);
	}
	if (substr($argv[$argndx],0,2) == '-n') {
		$filldays = substr($argv[$argndx],2);
	}
}
$debug = $debug || $verbose;
if ($debug) {
	echo "Code:{$code} Days:{$filldays} Start:{$sdate} UNIX:".strtotime($sdate)."\n";
}

if (strtotime($sdate) == 0 || $code == "" || $filldays > 29) {
	echo "\nUsage:   php load_ip100_backfill.php -codeXXX -sdSTART  [-nDAYS] [-debug] [-verbose] [-summary]";
	echo "\n         Maximum of 29 days in one call.";
	echo "\nExamples:\nphp load_ip100_backfill.php -codeAP -sd2019-07-08";
	echo "\nphp load_ip100_backfill.php -codeAQU -sd2019-08-07 -n3";
	echo "\nphp load_ip100_backfill.php -codeCD  -sd2019-08-07 -n3";
	echo "\nphp load_ip100_backfill.php -codeEMD -sd2019-07-09 -n2";
	echo "\nphp load_ip100_backfill.php -codeEMD -sd2019-07-22";
	echo "\nphp load_ip100_backfill.php -codeEMD -sd2019-07-24";
	echo "\nphp load_ip100_backfill.php -codeEMD -sd2019-08-04";
	echo "\nphp load_ip100_backfill.php -codeEMD -sd2019-08-07";
	echo "\nphp load_ip100_backfill.php -codeKEE -sd2019-08-07 -n3";
	exit;
}
date_default_timezone_set("America/New_York");
echo "\nIP100 Backfill Loader ",date('Y-m-d H:i:s'),"\n";
echo "\nArgs: Fill {$code} Start {$sdate} for {$filldays} days.";

$error = "";
$db = New DB;
$db->debug = false;

$hostname = php_uname("n");
$codes = array();
$macs  = array();
$sql   = "SELECT wsid, mac, idsite, code, timezone from vw_currentws_deployment
		WHERE type='ip-100' AND code=? AND sdate <=?";
$params = array('ss',$code,$sdate);
$station_data = $db->fetchrow_prepared($sql, $params);

if ($debug) print_r($station_data);

$dts = fetchStation();
if (is_array($dts)) {
	if ($debug) {
		print_r($dts);
	}
	echo AggregateToWeather($station_data['mac'],$station_data['idsite'],$station_data['wsid'],$dts[0],$dts[1]);
}
?>
