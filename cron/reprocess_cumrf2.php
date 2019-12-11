<?php
require("DB.class.php");

//explain SELECT distinct substr(timestamp,0,10) FROM weather WHERE idsite = 103 and timestamp > '2016-01-01'
//explain SELECT distinct DATE(timestamp) FROM weather WHERE idsite = 103 and timestamp > '2016-01-01'
//explain SELECT DATE_FORMAT(timestamp, '%Y-%m-%d') as dt FROM weather WHERE idsite = 103 and timestamp > '2016-01-01' GROUP BY DATE_FORMAT(timestamp, '%Y-%m-%d')
// last uses filesort
function calcCumRF($idsite, $wsid, $startdate){
	// get max timestamp with cumrf set
	global $db;
	$msg = "<pre>Starting Cumulative Rainfall\n";
	$startdate = substr($startdate,0,10);
    $sql = "SELECT distinct DATE(timestamp) AS dt FROM weather WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp > '{$startdate}' ORDER BY dt";
	//echo $sql;
	$dates = $db->query($sql);
	if (count($dates)==0) {
		echo "\nNo Dates\n";
		die;
	}
    foreach ($dates as $dtrow) {
		echo "\nProcessing {$dtrow['dt']}";
		$dt = $dtrow['dt'];
		$midnight = Date('Y=-m-d H:i:s',strtotime($dt.' + 1 day'));  //midnight next day
		$sql = "SELECT MIN(timestamp) AS firstrf FROM weather WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND rf > 0;";
		echo "\n$sql\n";
		$firstts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($firstts)) {
			// no rainfall
			$updatesql = "UPDATE weather SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}'";
			echo "No rf: updatesql {$updatesql}\n";
		} else {
			echo "first rf: {$firstts}\n ";
			if (substr($firstts,10) == "00:00:00") {
				$updatesql = "";
				echo "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($firstts." - 1 MINUTE"));
                $updatesql = "UPDATE weather SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$endts}'";
				echo "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, rf FROM weather WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp BETWEEN '{$firstts}' AND '{$midnight}' ORDER BY timestamp";
			//echo $wsql; die;
			$wrows = $db->query($wsql);
			$cumrf = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cumrf += $wrow['rf'];
				$sqls[] = "UPDATE weather SET cumrf={$cumrf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
			}
			$updates = implode("\r\n",$sqls);
			echo "CUMRF\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			$db->exec($updatesql);
		}
		if (count($sqls)>0){
			$updates = implode("\r\n",$sqls);
			$db->exec_multi($updates);
		}
    }
	$msg .= "</pre>";
	return $msg;
}

function AggregateToWeather($idsite, $wsid) {
	global $db;
	// we will add a margin to the timestamp range by using the nearest hour before and after
	//$last_ts  = substr($last_ts,0,14)."59:59";
	//$first_ts = substr($first_ts,0,14)."00:00";
	//echo "\n{$first_ts} {$last_ts}\n";
	$sql =
"
REPLACE INTO weather
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
FROM minuteweather
WHERE wsid={$wsid} AND idsite={$idsite}
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
//echo $sql;

	$test = $db->exec($sql);
//var_dump($test);
//echo "</pre>";

	if ($test !== false) {
		return "\nAggegation to weather table\nfrom {$first_ts} to {$last_ts} succeeded.\n{$test} rows were written";
	} else {
		return '<pre>Error: '.$db->error.'</pre>';
	}
}

/*
wsid	idsite	lst	fst	rows
9001	103	2016-01-29 15:00:00	2014-12-23 10:10:00	485272
9001	104	2014-10-26 10:49:00	2014-09-29 11:56:00	33552
9001	809	2014-08-21 13:59:00	2014-08-14 12:13:00	10179
9001	999	2014-08-27 18:09:00	2014-08-19 10:56:00	8898
9002	802	2016-01-29 17:00:00	2015-04-25 15:01:00	401875
9003	104	2015-11-01 09:45:00	2015-09-24 15:12:00	54384
9003	809	2015-09-10 21:22:00	2015-07-23 19:53:00	60637
*/

$db = new DB;
echo calcCumRF(103,'9001' ,'2014-12-23 00:00:00');
echo calcCumRF(104,'9001' ,'2014-09-29 00:00:00');
echo calcCumRF(104,'9003' ,'2015-09-24 00:00:00');
echo calcCumRF(801,'72759','2016-12-13 00:00:00');
echo calcCumRF(802,'9002' ,'2015-04-25 00:00:00');
echo calcCumRF(809,'9001' ,'2014-08-12 00:00:00');
echo calcCumRF(809,'9003' ,'2015-07-23 00:00:00');
echo calcCumRF(999,'72759','2016-01-23 00:00:00');

//echo calcCumRF('104','9001',true);
//echo AggregateToWeather('104','9001');
//echo calcCumRF('809','9001',true);
//echo AggregateToWeather('809','9001');
?>
