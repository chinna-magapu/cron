<?php
require("DB.class.php");
require_once("Logger.class.php");
$Logger = new Logger("load_wx.log");
$debug = false;

/****************** NOTE 2018-07-05 ******************
*	Added ability to "clone" weather to another site
*	Currently limited only to minuteweather. Easily
*	extensible to other tables
******************************************************/
/****************** NOTE 2018-12-14 ******************
*	Both In- and Out-of-order logic are contained in
*	and this script and in load_weather_oo.php. The
*	scripts should be maintained in parallel in the
*	hope of reaching the goal of a single script
*	running on a decent server!!
******************************************************/

/****************** NOTE 2019-04-04 ******************
 * ALTER TABLE `station_deployment` ADD `dest_table` VARCHAR(20) NOT NULL DEFAULT 'weather' AFTER `tablename`;
 * so we can remove special casing for KEE and CD
******************************************************/

function isDST($ts, $tzone) {
	$dt = new DateTime($ts, new DateTimeZone($tzone));
	return $dt->format('I');
}
/*
 *	simplify by removing multiple date computations from cumulative rf functions, and then start from
 * 	00:01:00 (out-of-order with precip) or last stored timestatmp (in order).
 *  The file received one minute after midnight (e.g., AQUEDUCT1_20181212_000100_WS.dat) will have these timestamps:
 *	2018-12-11 23:56:00","2018-12-11 23:57:00","2018-12-11 23:58:00","2018-12-11 23:59:00","2018-12-12 00:00:00"
 *	So we always want to compute the date from the first TS in the file
 *
 *	If a file spans several days, we'll encounter a 00:00:00 timestamp that is NOT the last in the file: so reset
 *	cumrf to 0 and in_order to TRUE
*/
function isInOrder($idsite, $wsid, $first_ts, $tablename) {
	global $db;
	/*	wx_singlesensor1m  code+timestamp or wsid+'rf'+timestamp
	 *	minuteweather and minuteweatherfg wsid + timestamp OR idsite+wsid+ts
	 * 	the mySql query optimizer works much more effective with idsite (a secondard index)
	 * 	return last timestamp if it IS in order, false otherwise
	*/
	if ($tablename == 'wx_singlesensor1m') {
		$wc = "WHERE wsid={$wsid} AND sensorcol='rf' AND timestamp < '{$first_ts}' ";
	} else {
		$wc = "WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp < '{$first_ts}' ";
	}
	$sql = "SELECT MAX(timestamp) FROM {$tablename} {$wc} ";
	$ts = $db->getScalar($sql);
	if (strtotime($ts) === FALSE) {
		$retval = FALSE;
		return $retval;
	} else {
		$diff = strtotime($first_ts) - strtotime($ts);
	}
	// allow a for missing 5 minutes - still in order
	$retval = $diff >= 60 && $diff <= 300 ? $ts : FALSE;
	return $retval;
}

function calcCumMinuteRF_ss($wsid, $first_ts){
	global $db, $debug, $Logger;
	$msg = "Starting Cumulative Rainfall\n";
	$dt = substr($first_ts,0,10);
	$last_csv_ts = isInOrder(0, $wsid, $first_ts,'wx_singlesensor1m');
	$inOrder = ! ($last_csv_ts === FALSE);

	if ( !$inOrder) {
		// same code that we've been using
		if ($debug) echo "\nProcessing {$wsid} From {$dt} 00:01";
		$midnight = Date('Y-m-d H:i:s',strtotime(substr($first_ts,0,10).' 00:00:00 + 1 day'));  //midnight next day
		$sql = "SELECT MIN(timestamp) AS firstrf FROM  wx_singlesensor1m WHERE wsid = {$wsid} AND sensorcol'rf' "
			 ." AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND dataval > 0;";
		if ($debug) echo "\n$sql\n";
		$first_rf_ts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($first_rf_ts)) {
			$updatesql = "UPDATE wx_singlesensor1m SET agg_val = 0 WHERE wsid = {$wsid} AND sensorcol='rf' AND timestamp between '{$dt} 00:01:00' AND '{$midnight}'";
			$msg .=  "No rf: updatesql {$updatesql}\n";
		} else {
			if ($debug) echo "first rf: {$first_rf_ts}\n ";
			if (substr($first_rf_ts,10) == "00:01:00") {
				$updatesql = "";
				$msg .= "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($first_rf_ts." - 1 MINUTE"));
				$updatesql = "UPDATE wx_singlesensor1m SET agg_val = 0 WHERE wsid = {$wsid} AND sensorcol='rf' "
					."AND timestamp between '{$dt} 00:01:00' AND '{$endts}' ";
				$msg .= "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, dataval, agg_val FROM wx_singlesensor1m WHERE wsid = {$wsid} AND sensorcol='rf' "
				  ."AND timestamp BETWEEN '{$first_rf_ts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$agg_val = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cur_agg = $wrow['agg_val'];
				$agg_val += $wrow['dataval'];
				$sqls[] = "UPDATE wx_singlesensor1m SET agg_val={$agg_val} WHERE wsid = {$wsid} AND sensorcol = 'rf' AND timestamp='{$wrow['timestamp']}';";
			}
			$updates = implode("\r\n",$sqls);
			if ($debug) echo "agg_val\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			$db->exec($updatesql);
		}
		if (count($sqls)>0){
			$updates = implode("\r\n",$sqls);
			$db->exec_multi($updates);
		}
	} else {
		// in-order processing
		if ($debug) echo "\nProcessing {$wsid} From {$first_ts}";
		$last_csv_rf = $db->getScalar("SELECT agg_val FROM  wx_singlesensor1m WHERE wsid={$wsid} AND sensorcol='rf' AND timestamp='{$last_csv_ts}'");
		$sql = "SELECT MIN(timestamp) AS firstrf FROM  wx_singlesensor1m WHERE wsid = {$wsid} AND sensorcol'rf' "
			 ." AND timestamp > '{$last_csv_ts}' AND dataval > 0;";
		if ($debug) echo "\n$sql\n";
		$first_rf_ts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($first_rf_ts)) {
			$updatesql = "UPDATE wx_singlesensor1m SET agg_val = {$last_csv_rf} WHERE wsid = {$wsid} AND sensorcol='rf' AND "
				."timestamp > '{$last_csv_ts}';";
			$msg .=  "No rf: updatesql {$updatesql}\n";
		} else {
			if ($debug) echo "first rf since {$last_csv_ts}: {$first_rf_ts}\n ";
			if ($first_rf_ts == $first_ts) {
				$updatesql = "";
				$msg .= "Rainfall since last csv, no update \n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($first_rf_ts." - 1 MINUTE"));
				$updatesql = "UPDATE wx_singlesensor1m SET agg_val = {$last_csv_rf} WHERE wsid = {$wsid} AND sensorcol='rf' "
					."AND timestamp between '{$first_ts}' AND '{$endts}';";
				$msg .= "updatesql {$updatesql}\n";
			}
			$midnight = substr($first_rf_ts,10)."00:00";
			$wsql = "SELECT timestamp, dataval, agg_val FROM wx_singlesensor1m WHERE wsid = {$wsid} AND sensorcol='rf' "
				  ."AND timestamp between '{$first_rf_ts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$agg_val = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cur_agg = $wrow['agg_val'];
				$agg_val += $wrow['dataval'];
				$sqls[] = "UPDATE wx_singlesensor1m SET agg_val={$agg_val} WHERE wsid = {$wsid} AND sensorcol='rf' AND timestamp='{$wrow['timestamp']}';";
			}
			$updates = implode("\r\n",$sqls);
			if ($debug) echo "agg_val\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			$db->exec($updatesql);
		}
		if (count($sqls)>0){
			$updates = implode("\r\n",$sqls);
			$db->exec_multi($updates);
		}
	}
	return $msg;
}

function calcCumMinuteRF($idsite, $wsid, $first_ts, $tablename){
	// get max timestamp with cumrf set
	global $db, $debug;
	$msg = "Starting Cumulative Rainfall\n";
	$dt = substr($first_ts,0,10);
	$last_csv_ts = isInOrder($idsite, $wsid, $first_ts, $tablename);
	$inOrder = ! ($last_csv_ts === FALSE);
	if ( !$inOrder ) {
		// same logic we've been using
		if ($debug) echo "\nProcessing {$wsid} From {$dt} 00:01";
		$midnight = Date('Y-m-d H:i:s',strtotime(substr($first_ts,0,10).' 00:00:00 + 1 day'));  //midnight next day
		$sql = "SELECT MIN(timestamp) AS firstrf FROM {$tablename} WHERE idsite = {$idsite} AND wsid={$wsid} "
			."AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND rf > 0;";
		if ($debug) echo "\n$sql\n";
		$first_rf_ts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($first_rf_ts)) {
			// no rainfall
			$updatesql = "UPDATE {$tablename} SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} "
				."AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' ";
			$msg .=  "No rf: updatesql {$updatesql}\n";
		} else {
			if ($debug) echo "first rf: {$first_rf_ts}\n ";
			if (substr($first_rf_ts,10) == "00:01:00") {
				$updatesql = "";
				$msg .= "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($first_rf_ts." - 1 MINUTE"));
				$updatesql = "UPDATE {$tablename} SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$endts}' ";
				$msg .= "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, rf, cumrf FROM {$tablename} WHERE idsite = {$idsite} AND wsid={$wsid} "
				."AND timestamp BETWEEN '{$first_rf_ts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$cumrf = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cumrf += $wrow['rf'];
				$sqls[] = "UPDATE {$tablename} SET cumrf={$cumrf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
			}
			$updates = implode("\r\n",$sqls);
			if ($debug) echo "CUMRF\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			if ($debug) echo "CUMRF will execute $updatesql\n";
			$db->exec($updatesql);
		}
		if (count($sqls)>0){
			if ($debug) echo "CUMRF will execute updates!!\n";
			$updates = implode("\r\n",$sqls);
			$db->exec_multi($updates);
		}
	} else {
		// IN-ORDER processing
		if ($debug) echo "\nProcessing {$wsid} From {$first_ts} ";
		$last_csv_rf = $db->getScalar("SELECT cumrf FROM  {$tablename} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$last_csv_ts}'");
		$sql = "SELECT MIN(timestamp) AS firstrf FROM  {$tablename} WHERE idsite = {$idsite} AND  wsid = {$wsid} "
			 ." AND timestamp > '{$last_csv_ts}' AND rf > 0;";
		$first_rf_ts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($first_rf_ts)) {
			// no rainfall
			$updatesql = "UPDATE {$tablename} SET cumrf = {$last_csv_rf} WHERE idsite = {$idsite} AND wsid = {$wsid} "
				."AND timestamp > '{$last_csv_ts}';";
			$msg .=  "No rf: updatesql {$updatesql}\n";
		} else {
			if ($debug) echo "first rf since {$last_csv_ts}: {$first_rf_ts}\n ";
			if ($first_rf_ts == $first_ts) {
				$updatesql = "";
				$msg .= "Rainfall since last csv, no update \n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($first_rf_ts." - 1 MINUTE"));
				$updatesql = "UPDATE {$tablename} SET cumrf = {$last_csv_rf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp BETWEEN '{$first_ts}' AND '{$endts}';";
				$msg .= "updatesql {$updatesql}\n";
			}
			$midnight = substr($first_rf_ts,10)."00:00";
			$wsql = "SELECT timestamp, rf, cumrf FROM {$tablename} WHERE idsite = {$idsite} AND wsid={$wsid} "
				."AND timestamp between '{$first_rf_ts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$cumrf = $last_csv_rf;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cumrf += $wrow['rf'];
				$sqls[] = "UPDATE {$tablename} SET cumrf={$cumrf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
			}
			$updates = implode("\r\n",$sqls);
			if ($debug) echo "CUMRF\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			if ($debug) echo "CUMRF will execute $updatesql\n";
			$db->exec($updatesql);
		}
		if (count($sqls)>0){
			if ($debug) echo "CUMRF will execute updates!!\n";
			$updates = implode("\r\n",$sqls);
			$db->exec_multi($updates);
		}
	}

	if ($tablename == 'minuteweatherfg'){
   		// process rf2
		if ( !$inOrder ) {
			$midnight = Date('Y-m-d H:i:s',strtotime(substr($first_ts,0,10).' 00:00:00 + 1 day'));  //midnight next day
			$sql = "SELECT MIN(timestamp) AS firstrf FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} "
				."AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND rf2 > 0;";
			if ($debug) echo "\n$sql\n";
			$first_rf2_ts = $db->getScalar($sql);
			$updatesql = "";
			$sqls = array();
			if (empty($first_rf2_ts)) {
				// no rainfall
				$updatesql = "UPDATE minuteweatherfg SET cumrf2 = 0 WHERE idsite = {$idsite} AND wsid={$wsid} ".
					"AND timestamp between '{$dt} 00:01:00' AND '{$midnight}'";
				$msg .=  "No rf: updatesql {$updatesql}\n";
			} else {
				if ($debug) echo "first rf2: {$first_rf2_ts}\n ";
				if (substr($first_rf2_ts,10) == "00:01:00") {
					$updatesql = "";
					$msg .= "Rainfall since midnight, no update 0\n";
				} else {
					$endts = Date("Y-m-d H:i:s", strtotime($first_rf2_ts." - 1 MINUTE"));
	                $updatesql = "UPDATE minuteweatherfg SET cumrf2 = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$endts}' ";
					$msg .= "updatesql {$updatesql}\n";
				}
				$wsql = "SELECT timestamp, rf2, cumrf2 FROM {$tablename} WHERE idsite = {$idsite} AND wsid={$wsid} "
					."AND timestamp BETWEEN '{$first_rf2_ts}' AND '{$midnight}' ORDER BY timestamp";
				$wrows = $db->query($wsql);
				$cumrf2 = 0.0;
				$sqls = array();
				foreach ($wrows as $wrow) {
					$cumrf2 += $wrow['rf2'];
					$sqls[] = "UPDATE minuteweatherfg SET cumrf2={$cumrf2} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
				}
				$updates = implode("\r\n",$sqls);
				if ($debug) echo "CUMRF2\n",print_r($updates,true),"\n\n";
			}
			if ($updatesql > "") {
				if ($debug) echo "CUMRF2 will execute $updatesql\n";
				$db->exec($updatesql);
			}
			if (count($sqls)>0){
				if ($debug) echo "CUMRF2 will execute updates!!\n";
				$updates = implode("\r\n",$sqls);
				$db->exec_multi($updates);
			}
		} else {
			// in-order processing
			if ($debug) echo "\nProcessing {$wsid} From {$first_ts} ";
			$last_csv_rf2 = $db->getScalar("SELECT cumrf2 FROM  minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$last_csv_ts}'");
			$sql = "SELECT MIN(timestamp) AS firstrf FROM  minuteweatherfg WHERE idsite = {$idsite} AND  wsid = {$wsid} "
				 ." AND timestamp > '{$last_csv_ts}' AND rf2 > 0;";
			$first_rf2_ts = $db->getScalar($sql);
			$updatesql = "";
			$sqls = array();
			if (empty($first_rf2_ts)) {
				// no rainfall
				$updatesql = "UPDATE minuteweatherfg SET cumrf2 = {$last_csv_rf2} WHERE idsite = {$idsite} AND wsid = {$wsid} "
					."AND timestamp > '{$last_csv_ts}';";
				$msg .=  "No rf2: updatesql {$updatesql}\n";
			} else {
				if ($debug) echo "first rf2 since {$last_csv_ts}: {$first_rf2_ts}\n ";
				if ($first_rf2_ts == $first_ts) {
					$updatesql = "";
					$msg .= "Rainfall since last csv, no update \n";
				} else {
					$endts = Date("Y-m-d H:i:s", strtotime($first_rf2_ts." - 1 MINUTE"));
					$updatesql = "UPDATE minuteweatherfg SET cumrf2 = {$last_csv_rf2} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp BETWEEN '{$first_ts}' AND '{$endts}';";
					$msg .= "updatesql {$updatesql}\n";
				}
				$midnight = substr($first_rf2_ts,10)."00:00";
				$wsql = "SELECT timestamp, rf2, cumrf2 FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} "
					."AND timestamp between '{$first_rf2_ts}' AND '{$midnight}' ORDER BY timestamp";
				$wrows = $db->query($wsql);
				$cumrf2 = $last_csv_rf2;
				$sqls = array();
				foreach ($wrows as $wrow) {
					$cumrf += $wrow['rf2'];
					$sqls[] = "UPDATE minuteweatherfg SET cumrf2={$cumrf2} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
				}
				$updates = implode("\r\n",$sqls);
				if ($debug) echo "CUMRF2\n",print_r($updates,true),"\n\n";
			}
			if ($updatesql > "") {
				if ($debug) echo "CUMRF2 will execute $updatesql\n";
				$db->exec($updatesql);
			}
			if (count($sqls)>0){
				if ($debug) echo "CUMRF2 will execute updates!!\n";
				$updates = implode("\r\n",$sqls);
				$db->exec_multi($updates);
			}
		}
	}
	return $msg;
}

function AggregateToWeather_ss($wsid, $first_min, $last_min) {
	global $db, $wssingle, $Logger;
	// add a margin to the timestamp range by going to the nearest 15 minutes before
	$last_ts  = $last_min;
	$first_ts  = Date('Y-m-d H:i:00',strtotime($first_min." - 15 MINUTE"));
	$mins = (int)substr($first_ts,14,2);
	if ($mins > 0 && $mins <= 15) {
		$minstr = '01';
	} else if ($mins > 15 && $mins <= 30) {
		$minstr = '16';
	} else if ($mins > 30 && $mins <= 45) {
		$minstr = '31';
	} else {
		$minstr = '46';
	}
	$first_ts = substr($first_ts,0,14).$minstr.":00";
	//$Logger->write("Aggregate to weather from {$first_min} to {$last_min}");
	//$Logger->write("Aggregate to weather mgns {$first_ts} to {$last_ts}");
	$sensorcol = $wssingle[$wsid]['sensorcol'];
	$sql =
"
REPLACE INTO wx_singlesensor15m
(`wsid`, `sensorcol`, `idsite`, `code`,  `timestamp`,  `dataval`, `agg_val`)
SELECT wsid, sensorcol, idsite, code,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts, ";
	switch ($sensorcol) {
		case 'rf':
	    	$expr = "SUM(3.93701 * dataval) AS dataval";
	    	$aggr = "MAX(3.93701 * agg_val) AS agg_val";
			break;
	}
$sql .= "{$expr}, {$aggr}
FROM wx_singlesensor1m
WHERE wsid='{$wsid}' AND sensorcol='rf' AND timestamp BETWEEN '{$first_ts}' AND  '{$last_ts}'
GROUP BY wsid, qtrts
";
	$test = $db->exec($sql);
	//echo "<pre>SQL\n{$sql}\n"; var_dump($test); echo "</pre>";
	if ($test !== false) {
		return "\nAggegation to weather table\nfrom {$first_ts} to {$last_ts} succeeded.\n{$test} rows were written";
	} else {
		return '<pre>Error: '.$db->error.'</pre>';
	}
}

function AggregateToWeather($idsite, $wsid, $first_min, $last_min, $tablename, $dest_table) {
	global $db, $Logger, $debug;
	// add a margin to the timestamp range by going to the nearest 15 minutes before
	// in here dest_table is either weather or weather2
	$last_ts  = $last_min;
	$first_ts  = Date('Y-m-d H:i:00',strtotime($first_min." - 15 MINUTE"));
	$mins = (int)substr($first_ts,14,2);
	if ($mins > 0 && $mins <= 15) {
		$minstr = '01';
	} else if ($mins > 15 && $mins <= 30) {
		$minstr = '16';
	} else if ($mins > 30 && $mins <= 45) {
		$minstr = '31';
	} else {
		$minstr = '46';
	}
	$first_ts = substr($first_ts,0,14).$minstr.":00";
	//$Logger->write("Aggregate to weather from {$first_min} to {$last_min}");
	//$Logger->write("Aggregate to weather mgns {$first_ts} to {$last_ts}");
	//$Logger->write("Aggregate to weather from {$first_min} to {$last_min}");
	//$Logger->write("Aggregate to weather margins  {$first_ts} to {$last_ts}");
	$sql =
"
REPLACE INTO {$dest_table}
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, srad, t1, t2, mv1, mv2, ver, dewpoint, heatindex, windchill, cumrf, wsbatt, src)
SELECT idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, AVG(solarkw)*1000 AS srad,
	AVG(1.8 * soiltempbot  + 32.0) AS t1, AVG(1.8 * soiltemptop  + 32.0) AS t2, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'BAE WS 2014-08' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf, 100 * AVG(batt) as wsbatt, 'CS' as src
FROM {$tablename}
WHERE wsid={$wsid} AND idsite={$idsite} AND timestamp >= '{$first_ts}' AND timestamp <= '{$last_ts}'
GROUP BY idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00'));
";
//echo "<pre>SQL\n";
if ($debug) echo $sql;
	$test = $db->exec($sql);
//var_dump($test);
//echo "</pre>";
	if ($test !== false) {
		return "\nAggegation to weather table for {$idsite} wsid {$wsid}\nfrom {$first_ts} to {$last_ts} succeeded.\n{$test} rows were written";
	} else {
		return '<pre>Error: '.$db->error.'</pre>';
	}
}

function AggregateToWeather2rf($idsite, $wsid, $first_min, $last_min, $tablename, $dest_table="weather2rf") {
	global $db, $Logger, $debug;
	// add a margin to the timestamp range by going to the nearest 15 minutes before
	$last_ts  = $last_min;
	$first_ts  = Date('Y-m-d H:i:00',strtotime($first_min." - 15 MINUTE"));
	$mins = (int)substr($first_ts,14,2);
	if ($mins > 0 && $mins <= 15) {
		$minstr = '01';
	} else if ($mins > 15 && $mins <= 30) {
		$minstr = '16';
	} else if ($mins > 30 && $mins <= 45) {
		$minstr = '31';
	} else {
		$minstr = '46';
	}
	$first_ts = substr($first_ts,0,14).$minstr.":00";
	//$Logger->write("Aggregate to weather from {$first_min} to {$last_min}");
	//$Logger->write("Aggregate to weather mgns {$first_ts} to {$last_ts}");
	//$Logger->write("Aggregate to weather from {$first_min} to {$last_min}");
	//$Logger->write("Aggregate to weather margins  {$first_ts} to {$last_ts}");
	$sql =
"
REPLACE INTO {$dest_table}
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, rf2, srad, t1, t2, mv1, mv2, ver, dewpoint, heatindex,
	windchill, cumrf, cumrf2, wsbatt, src)
SELECT idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, SUM(3.93701 * rf2) AS rf2, AVG(solarkw)*1000 AS srad,
	AVG(1.8 * soiltempbot  + 32.0) AS t1, AVG(1.8 * soiltemptop  + 32.0) AS t2, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'CS201609' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf, 3.93701 * MAX(cumrf2) AS cumrf2, 100 * AVG(batt) as wsbatt, 'CS' as src
FROM {$tablename}
WHERE wsid={$wsid} AND idsite={$idsite} AND timestamp >= '{$first_ts}' AND timestamp <= '{$last_ts}'
GROUP BY idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00'));
";
//echo "<pre>SQL\n";
if ($debug) echo $sql;
	$test = $db->exec($sql);
//var_dump($test);
//echo "</pre>";

	if ($test !== false) {
		return "\nAggegation to weather table\nfrom {$first_ts} to {$last_ts} succeeded.\n{$test} rows were written";
	} else {
		return '<pre>Error: '.$db->error.'</pre>';
	}
}

function process_file_ss($dirpath, $fname){
	global $wssingle, $debug, $db, $wsids;
	$notes = "Weather File Loader ".date(DATE_RFC822)."\n{$fname}\n";
	echo "Weather  File Loader ".date(DATE_RFC822)."\n{$fname}\n";
	$fullfname = $dirpath.$fname;

	$tzone = 'UTC';
	$tz = new DateTimeZone($tzone);
	$rsql =  "REPLACE INTO `wx_singlesensor1m` (`wsid`, `sensorcol`, `timestamp`, `idsite`, `code`, `dataval`,`agg_val`,`local_ts`) VALUES ";
	$row = 1;
	if (($handle = fopen($fullfname, "r")) !== FALSE) {
		$adj_for_dst = 0; 	/*	dst adjustment can be -1, 0, or +1 */
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
			if ($row==1){
				$wsid = $data[1];
				if (!array_key_exists($wsid, $wssingle)){
					echo "\nSingle Sensor Abort - missing station name {$wsid} in wssingle table.";
				    fclose($handle);
					return;
				}
                $choplen= is_numeric($wssingle[$wsid]['choplen']) ? $wssingle[$wsid]['choplen'] : 0;
				$tzone  = $wssingle[$wsid]['timezone'];
				$idsite = $wssingle[$wsid]['idsite'];
				$code  = $wssingle[$wsid]['code'];
				$sensorcol = $wssingle[$wsid]['sensorcol'];
				$csvoffset = $wssingle[$wsid]['csvoffset'];
				if (empty($tzone)) $tzone = 'UTC';
				$tz = new DateTimeZone($tzone);
				$row++;
				continue;
			}
			if ($row < 5) {
				$row++;
				continue;
			}
			if ($row == 5){
				// first data row
				$is_dst = isDST($data[0], $tzone);
				$adj_for_dst = $is_dst == 1 ? $wssingle[$wsid]['dst_start'] : $wssingle[$wsid]['dst_end'];
				$first_ts = $data[0];
				if ($adj_for_dst != 0) {
					$first_ts = Date('Y-m-d H:i:s',strtotime($data[0].$adj_for_dst.' HOUR'));
				}
			}
			if ($row > 4){
				if ($adj_for_dst != 0) {
					$data[0] = Date('Y-m-d H:i:s',strtotime($data[0].$adj_for_dst.' HOUR'));
				}
				if ($choplen > 0) {
					array_splice($data,count($data)-$choplen,$choplen); // remove last $choplen
				}
				$local_ts = $ts = $last_ts = $data[0];		// local_ts no longer really needed
				if ($debug) echo "Data: ".implode(" | ",$data)."\n";
				$dataval = $data[$csvoffset];
				$rsql .= "('{$wsid}','{$sensorcol}','{$ts}',801,'{$code}',{$dataval},NULL,'{$local_ts}'),";
			}
	        $row++;
	    }
		$rsql = rtrim($rsql, ",");
		$rsql = str_replace("'NAN'",'NULL',$rsql);

	    fclose($handle);
		if ($debug) echo "\n{$rsql}\n";
		$db->exec($rsql);
		$error = $db->error;
		if (empty($error)) {
			$notes = "Upload succeeded; ".($row - 4)." rows of data were processed";
			if ($debug) echo "call calcCumMinuteRF__ss with {$idsite} {$wsid} {$first_ts} \n";
			$notes .= calcCumMinuteRF_ss($wsid, $first_ts);
			if ($debug) echo "call AggregateToWeather_ss with 1. {$wsid} 2. {$wsid} 3. {$first_ts} 4. {$last_ts} \n";
			$notes .= AggregateToWeather_ss($wsid, $first_ts, $last_ts);
			annotate($fname,$notes,1);
			$newfname = $dirpath.'processed\\'.$fname;
			rename($fullfname, $newfname);
		} else {
			$notes = $error;
			annotate($fname,$notes,0);
		}
	}
}

function process_file($dirpath, $fname){
	global $wsids, $wssingle, $debug, $db, $Logger, $clones;
	$notes = "Weather File Loader ".date(DATE_RFC822)."\n{$fname}\n";
	echo "Weather  File Loader ".date(DATE_RFC822)."\n{$fname}\n";
	$Logger->write("Weather  File Loader ".date(DATE_RFC822)."   {$fname}");

	$fullfname = $dirpath.$fname;

	$sql = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp,
			gflux, soiltemptop, soiltempbot, local_ts)\n 	VALUES ";
	$sql17 = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp,
			gflux, soiltemptop, soiltempbot, lightning, local_ts)\n 	VALUES ";
	$sql18 = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp, "
			." gflux, soiltemptop, soiltempbot, lightning, batt, local_ts)\n VALUES ";
	$sql19 = "REPLACE INTO minuteweatherfg(wsid, idsite, timestamp, recno, baro, rf, rf2, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp, "
			." gflux, soiltemptop, soiltempbot, lightning, batt, local_ts)\n VALUES ";
	$sql20 = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, "
			."gflux, soiltemptop, soiltempbot, lightning, batt, local_ts)\n VALUES ";
	$sql26 = "REPLACE INTO minuteweatherfg(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp, "
			."gflux, soiltemptop, soiltempbot, lightning, batt, rf2, filler2, filler3, filler4, pump, valve_open, valve_close, relay_status,local_ts)\n 	VALUES ";

	$row = 1;
	if (($handle = fopen($fullfname, "r")) !== FALSE) {
		$adj_for_dst = 0; 	/*	dst adjustment can be -1, 0, or +1 */
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	        if ($debug) echo "\nRow {$row} with ",count($data)," fields\n";
			if ($row==1){
				$wsid = $data[1];
				if (!array_key_exists($wsid,$wsids)){
					if (array_key_exists($wsid, $wssingle)) {
					    fclose($handle);
						return process_file_ss($dirpath, $fname);
					} else {
						$newfname = $dirpath.'unknown\\'.$fname;
					    fclose($handle);
						rename($fullfname, $newfname);
						if ($debug) echo "\nAbort - missing station name {$wsid} in wsids table.";
						return;
					}
				}
                $choplen= is_numeric($wsids[$wsid]['choplen']) ? $wsids[$wsid]['choplen'] : 0;   // data fields to removed from end filler1 - filler4
				$tzone  = $wsids[$wsid]['timezone'];
				$idsite = $wsids[$wsid]['idsite'];
				if (empty($tzone)) $tzone = 'UTC';
				$tz = new DateTimeZone($tzone);
				$row++;
				continue;
			}
			if ($row == 2) {
				$rsql = "";
				$fcount = count($data);
				switch($fcount) {
					case 17:  /* currently unused */
						$rsql = $sql17;
						break;
					case 20: /* santa anita only - other chrb maybe later? */
						$rsql = $sql20;
						break;
					case 18:
					case 22:
						// format with filler1-filler4, discarded
						$rsql = $sql18;
						break;
					case 23: /* new KEE (now SAR) with 2 rf */
						$rsql = $sql19;
						break;
					case 26:
						$rsql = $sql26;
						break;
					default:
						echo "\n!!!! UNDEFINED rsql PROCESSING {$dirpath}{$fname}\n";
						break 2;  // exits the WHILE loop
				}
			}
			if ($row == 5) {
				// first data row
				$is_dst = isDST($data[0], $tzone);
				$adj_for_dst = $is_dst == 1 ? $wsids[$wsid]['dst_start'] : $wsids[$wsid]['dst_end'];
				$first_ts = $data[0];
				if ($adj_for_dst != 0) {
					$first_ts = Date('Y-m-d H:i:s',strtotime($data[0].$adj_for_dst.' HOUR'));
				}
			}
			if ($row > 4){
				// this code should now work for all data
				if ($adj_for_dst != 0) {
					$data[0] = Date('Y-m-d H:i:s',strtotime($data[0].$adj_for_dst.' HOUR'));
				}
				if ($choplen > 0) {
					array_splice($data,count($data)-$choplen,$choplen); // remove last $choplen
				}
				$local_ts = $data[0];		// no longer really needed
				$last_ts = $data[0];
				if ($debug) echo "Data: ".implode(" | ",$data)."\n";
				$rsql .= "\n({$wsid},{$idsite},'".implode("','",$data)."','{$local_ts}'),";
			}
	        $row++;
	    }
		//echo "\n",$rsql; "\n\n"; die;
		if ($rsql == "") {
			echo "\n!!!! BAD FIELD COUNT {$fcount} PROCESSING {$dirpath}{$fname}\n";
			$notes = "!!!! BAD FIELD COUNT {$fcount} PROCESSING {$dirpath}{$fname}\n";
			annotate($fname,$notes,0);
			return;
		} else {
			$rsql = rtrim($rsql, ",");  //udv
			// fixups for invalid data
			$rsql = str_replace("'NAN'",'NULL',$rsql);
		    fclose($handle);
			if ($debug) echo "\n{$rsql}\n";
			$db->exec($rsql);
			/*
			if ($wsid == '71834' && $idsite == 411) {
				$fixsql = "UPDATE minuteweather SET timestamp = DATE_SUB(timestamp, INTERVAL 2 HOUR), local_ts= DATE_SUB(local_ts, INTERVAL 2 HOUR)\n "
						."WHERE $idsite=411 AND wsid='71834' AND timestamp BETWEEN '{$first_ts}' AND '{$last_ts}' ORDER BY timestamp; ";
				$db->exec($fixsql);
			}
			*/
		}
		$tablename = $wsids[$wsid]['tablename'];
		if ($debug) echo "\n!!!!Tablename||{$tablename}||\n";
		$error = $db->error;
		if (empty($error)) {
			$notes = "Upload succeeded; ".($row - 5)." rows of data were processed";
			if ($debug) echo "call calcCumMinuteRF with {$idsite} {$wsid} {$first_ts} \n";
			$notes .= calcCumMinuteRF($idsite, $wsid, $first_ts, $tablename);
			if (isset($clones[$wsid])) {
				if ($tablename == 'minuteweather') {
					$csql = "REPLACE INTO minuteweather (wsid, idsite, timestamp, created, recno, baro, rf, temp, RH, solarkw, solarmj, "
						."windspeed, winddir, vmc, ec, soiltemp, gflux, soiltemptop, soiltempbot, lightning, batt, agg, cumrf, local_ts)\n"
						."SELECT {$clones[$wsid]['wsid_dst']}, {$clones[$wsid]['id_dst']}, timestamp, created, recno, baro, rf, temp, RH, solarkw, solarmj, "
						."windspeed, winddir, vmc, ec, soiltemp, gflux, soiltemptop, soiltempbot, lightning, batt, agg, cumrf, local_ts\n"
						."FROM minuteweather WHERE timestamp BETWEEN '{$first_ts}' AND '{$last_ts}' AND wsid={$wsid} AND idsite={$idsite}";
				} else {
					$csql = "REPLACE INTO minuteweatherfg(wsid, idsite, timestamp, created, recno, baro, rf, rf2, temp, RH, solarkw, solarmj, "
							."windspeed, winddir, vmc, ec, soiltemp, gflux, soiltemptop, soiltempbot, lightning, batt, agg, cumrf, local_ts)\n"
							."SELECT {$clones[$wsid]['wsid_dst']}, {$clones[$wsid]['id_dst']}, timestamp, created, recno, baro, rf, rf2, temp, RH, solarkw, solarmj, "
							."windspeed, winddir, vmc, ec, soiltemp, gflux, soiltemptop, soiltempbot, lightning, batt, agg, cumrf, local_ts\n"
							."FROM minuteweatherfg WHERE timestamp BETWEEN '{$first_ts}' AND '{$last_ts}' AND wsid={$wsid} AND idsite={$idsite}";
				}
				if ($debug) echo "\nCSQL\n{$csql}";
				$db->exec($csql);
			}
			$dest_table = $wsids[$wsid]['dest_table'];
			if ($tablename == "minuteweatherfg") {
				if ($debug) echo "call AggregateToWeather with 1. {$idsite} 2. {$wsid} 3. {$first_ts} 4. {$last_ts} 5. {$tablename} 6. {$dest_table} \n";
				$notes .= AggregateToWeather2rf($idsite, $wsid, $first_ts, $last_ts, $tablename, $dest_table);
			} else {
				if ($debug) echo "call AggregateToWeather with 1. {$idsite} 2. {$wsid} 3. {$first_ts} 4. {$last_ts} 5. {$tablename} 6. {$dest_table} \n";
				$notes .= AggregateToWeather($idsite, $wsid, $first_ts, $last_ts, $tablename, $dest_table);
				if (isset($clones[$wsid])) {
					$notes .= AggregateToWeather($clones[$wsid]['id_dst'], $clones[$wsid]['wsid_dst'], $first_ts, $last_ts, $tablename, $dest_table);
				}
			}
			if ($debug) echo "\n\nNOTES",$notes;// die;
			//if ($debug) echo "CalcCumRF with 1. {$idsite} 2. {$wsid} 3. {$first_ts}  \n";
			//$notes .= calcCumRF($idsite,$wsid, $first_ts);
			annotate($fname,$notes,1);
			$newfname = $dirpath.'processed\\'.$fname;
			rename($fullfname, $newfname);
		} else {
			if ($debug) echo "<pre>ERROR\n {$error}\n";
			$notes = $error;
			annotate($fname,$notes,0);
		}
	}
}

function annotate($fname,$comments, $success=1){
	global $db;
	$params = Array("ssi",$fname, $comments, $success);
	$sql = "REPLACE INTO ws_files(filename, results,success) VALUES (?,?,?) ";
	$db->exec_prepared($sql, $params);
	if ($db->error > "" ){
		echo "ERROR on annotate: ".$db->error;
	}
}

if (PHP_SAPI == "cli"){
	$debug = $argc > 1 && $argv[1] == "debug";
	$globprefix = $argc > 1 && $argv[1] != "debug" ? $argv[1] : "";
} else {
	$debug = isset($_GET['debug']) && $_GET['debug'] == "debug";
	echo "<pre>";
}

$error = "";
$db = New DB;
$db->debug = false;
$hostname = php_uname("n");

$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('load_weather','WS Load Weather Job ran','{$hostname}')";
if ($debug) echo "<pre>\nquery\n{$sql}";
//$db->exec($sql);

$sql = "SELECT sd.wsid, si.idsite, sd.code, si.timezone, st.dst_start, st.dst_end, st.datacnt, st.choplen, sd.tablename, sd.dest_table
FROM station_deployment sd
INNER JOIN site si ON sd.idsite = si.idsite
INNER JOIN station st ON sd.wsid = st.wsid
WHERE sd.edate IS NULL AND sd.mac IS NULL AND tablename <>  'wx_singlesensor1m'
ORDER BY sd.wsid";

$wsids = $db->query($sql,false,'wsid');
$wssingle = array();
$sql = "SELECT ss.`wsid`, `sensorcol`, ss.`idsite`, ss.`code`, `colname`, `agg_col`, `csvoffset`, `units1min`, `units15min`,
	si.timezone,  st.dst_start, st.dst_end, st.datacnt, st.choplen
	FROM `wx_singlesensor` ss
	INNER JOIN station st ON ss.wsid = st.wsid
	INNER JOIN site si ON ss.code = si.code";
$wssingle = $db->query($sql,false,'wsid');

$today = Date('Y-m-d');
$sql = "SELECT wsid_src, wsid_dst, code_src, code_dst, id_src, id_dst
	FROM wx_clone WHERE sdate <= '{$today}' AND edate IS NULL";
$clones = $db->query($sql,false,'wsid_src');

$datadir  = "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS";
$datapath = "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS\\";
if ($hostname == 'NASREDDIN') {
	$datadir  = "..\\data\\WS";
	$datapath = "..\\data\\WS\\";
}
foreach (glob("{$datapath}{$globprefix}*_WS.dat") as $filename) {
	$fname = basename($filename);
	if (preg_match('/.*_00\d_WSoo.dat/', $fname)) {
		$Logger.write("Leaving {$fname} for out-of-order processor");
		continue;
	}
	if ($debug || true) echo "Filename: {$fname}\n";
	if (filesize($filename) == 0) {
		$newfname = $datapath.'zerolen\\'.$fname;
		rename($filename, $newfname);
		continue;
	}
	if (filesize($filename) > 4000) {
		$newfname = str_replace('_WS.dat','_WSoo.dat',$filename);
		rename($filename, $newfname);
		continue;
	}
   	$sql = "SELECT COUNT(*) FROM ws_files WHERE success=1 AND filename=?";
	$params = array('s',$fname);
    $is_present = $db->getScalar_prepared($sql,$params);
	if ($is_present==0) {
		echo "PRESENT {$is_present}: {$fname} Start:",Date('Y-m-d H:i:s'),"\n";
		process_file($datapath, $fname);
		echo "PRESENT {$is_present}: {$fname}  Done:",Date('Y-m-d H:i:s'),"\n";
	} else {
		echo "PRESENT {$is_present}: {$fname}\n";
		$newfname = $datapath.'already_processed\\'.$fname;
		rename($filename, $newfname);
	}
}
echo "\nJOB COMPLETE\n";
?>
