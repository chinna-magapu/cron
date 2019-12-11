<?php
require("DB.class.php");

function calcCumMinuteRF($idsite, $wsid, $startdate){
	// get max timestamp with cumrf set
	$outfn = "updates{$idsite}.out.sql";
	$handle = fopen($outfn, 'w') or die('Cannot open file:  '.$outfn);
	global $db, $debug;

	echo "\n--- Starting Cumulative Rainfall RF ----\n";
	$startdate = substr($startdate,0,10);
    $sql = "SELECT distinct DATE(timestamp) AS dt FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp > '{$startdate}' ORDER BY dt";
	//echo $sql;
	$dates = $db->query($sql);
	if (count($dates)==0) {
		echo "\nNo Dates\n";
		return;
	}
    foreach ($dates as $dtrow) {
		echo "\nProcessing {$dtrow['dt']}";
		$dt = $dtrow['dt'];
		$midnight = Date('Y-m-d H:i:s',strtotime($dt.' + 1 day'));  //midnight next day
		$sql = "SELECT MIN(timestamp) AS firstrf FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND rf > 0;";
	echo "\n$sql\n";
		$firstts = $db->getScalar($sql);
	echo "FIRSTTS {$firstts}";
		$updatesql = "";
		$sqls = array();
		if (empty($firstts)) {
			// no rainfall
			$updatesql = "UPDATE minuteweatherfg SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}';";
			fwrite($handle, $updatesql."\n");
			echo "No rf: updatesql {$updatesql}\n";
		} else {
			echo "first rf: {$firstts}\n ";
			if (substr($firstts,10) == "00:00:00") {
				$updatesql = "";
				echo "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($firstts." - 1 MINUTE"));
                $updatesql = "UPDATE minuteweatherfg SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$endts}';";
				fwrite($handle, $updatesql."\n");
				echo "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, rf, cumrf FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp BETWEEN '{$firstts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$cumrf = 0.0;
			foreach ($wrows as $wrow) {
				$cumrf += $wrow['rf'];
				// $sqls[] = "UPDATE minuteweatherfg SET cumrf={$cumrf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
				$sqls[] = "REPLACE INTO `wx_cumrf`(`wsid`, `timestamp`, `idsite`, `cumrf`) VALUES ('{$wsid}', '{$wrow['timestamp']}',{$idsite},{$cumrf});";
			}
			$updates = implode("\r\n",$sqls);
			echo "CUMRF\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			echo "would exec here"; //$db->exec($updatesql);
		}
		if (count($sqls)>0) {
			fwrite($handle,"TRUNCATE TABLE wx_cumrf;\n");
			$updates = implode("\n",$sqls);
			fwrite($handle, $updates."\n");
			// $sql = "UPDATE minuteweatherfg m INNER JOIN wx_cumrf r ON m.wsid = r.wsid AND m.timestamp = r.timestamp SET m.cumrf = r.cumrf;";
			// fwrite($handle, $sql."\n");
			echo "Updates in RF";  //$db->exec_multi($updates);
		}
		// Process RF2 for this date
		$sql = "SELECT MIN(timestamp) AS firstrf FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid}
			AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND rf2 > 0;";
		echo "\n$sql\n";
		$firstts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($firstts)) {
			// no rainfall
			$updatesql = "UPDATE minuteweatherfg SET cumrf2 = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}';";
			fwrite($handle, $updatesql."\n");
			echo "No rf2: updatesql {$updatesql}\n";
		} else {
			echo "first rf2: {$firstts}\n ";
			if (substr($firstts,10) == "00:00:00") {
				$updatesql = "";
				echo "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($firstts." - 1 MINUTE"));
                $updatesql = "UPDATE minuteweatherfg SET cumrf2 = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$endts}';";
				fwrite($handle, $updatesql."\n");
				echo "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, rf2, cumrf2 FROM minuteweatherfg WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp BETWEEN '{$firstts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$cumrf = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cumrf += $wrow['rf2'];
				// $sqls[] = "UPDATE minuteweatherfg SET cumrf={$cumrf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
				$sqls[] = "INSERT INTO `wx_cumrf`(`wsid`, `timestamp`, `idsite`, `cumrf2`) VALUES ('{$wsid}', '{$wrow['timestamp']}',{$idsite},{$cumrf}) "
					."\nON DUPLICATE KEY UPDATE cumrf2 = {$cumrf};";
			}
			$updates = implode("\r\n",$sqls);
			echo "CUMRF\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			echo "would exec here"; //$db->exec($updatesql);
		}
		if (count($sqls)>0){
			$updates = implode("\n",$sqls);
			fwrite($handle, $updates."\n");
			$sql = "UPDATE minuteweatherfg m INNER JOIN wx_cumrf r ON m.wsid = r.wsid AND m.timestamp = r.timestamp
				SET m.cumrf = r.cumrf, m.cumrf2 = r.cumrf2;";
			fwrite($handle, $sql."\n");
			echo "would exec here";  //$db->exec_multi($updates);
		}
		$updatesql = "UPDATE wx_cumrf SET cumrf = 0 WHERE cumrf IS NULL;\nUPDATE wx_cumrf SET cumrf2 = 0 WHERE cumrf2 IS NULL;";
		fwrite($handle, $updatesql."\n");
	} //FOREACH
	// SELECT MIN(timestamp) AS firstrf FROM minuteweatherfg WHERE idsite = 103 AND wsid=62304 ANDtimestamp between '2018-12-19 00:01:00' AND '2018-12-20 00:00:00' AND rf > 0;

	fclose($handle);
	return "\n\nDONE\n";
}
$db = new DB;
$debug = true;
$doexec = false;

echo calcCumMinuteRF(103,'62304' ,'2018-12-20 00:01:00');
/*
echo calcCumMinuteRF(103,'9001' ,'2014-12-23 00:00:00');
echo calcCumMinuteRF(104,'9001' ,'2014-09-29 00:00:00');
echo calcCumMinuteRF(104,'9003' ,'2015-09-24 00:00:00');
echo calcCumMinuteRF(801,'72759','2016-12-13 00:00:00');
echo calcCumMinuteRF(802,'9002' ,'2015-04-25 00:00:00');
echo calcCumMinuteRF(809,'9001' ,'2014-08-12 00:00:00');
echo calcCumMinuteRF(809,'9003' ,'2015-07-23 00:00:00');
echo calcCumMinuteRF(999,'72759','2016-01-23 00:00:00');
/*
SELECT idsite, wsid, min(timestamp) as mints, max(timestamp) as maxts  FROM `minuteweather` group by idsite, wsid
103	9001	2014-12-23 10:10:00	2016-03-06 10:35:00
104	9001	2014-09-29 11:56:00	2014-10-26 10:49:00
104	9003	2015-09-24 15:12:00	2015-11-01 09:45:00
801	72759	2016-02-13 11:07:00	2016-02-13 12:05:00
802	9002	2015-04-25 15:01:00	2016-03-06 12:35:00
809	9001	2014-08-14 12:13:00	2014-08-21 13:59:00
809	9003	2015-07-23 19:53:00	2015-09-10 21:22:00
999	72759	2016-01-23 12:36:00	2016-02-15 12:00:00
999	9001	2014-08-19 10:56:00	2014-08-27 18:09:00
*/
?>
