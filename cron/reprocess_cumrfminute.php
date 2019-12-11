<?php
require("DB.class.php");

function calcCumMinuteRF($idsite, $wsid, $startdate){
	// get max timestamp with cumrf set
	$outfn = "updates{$idsite}.out.sql";
	$handle = fopen($outfn, 'w') or die('Cannot open file:  '.$outfn);
	global $db;
	$msg = "Starting Cumulative Rainfall\n";
	$startdate = substr($startdate,0,10);
    $sql = "SELECT distinct DATE(timestamp) AS dt FROM minuteweather WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp > '{$startdate}' ORDER BY dt";
	//echo $sql;
	$dates = $db->query($sql);
	if (count($dates)==0) {
		echo "\nNo Dates\n";
		die;
	}
    foreach ($dates as $dtrow) {
		echo "\nProcessing {$dtrow['dt']}";
		$dt = $dtrow['dt'];
		$midnight = Date('Y-m-d H:i:s',strtotime($dt.' + 1 day'));  //midnight next day
		$sql = "SELECT MIN(timestamp) AS firstrf FROM minuteweather WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND rf > 0;";
		echo "\n$sql\n";
		$firstts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($firstts)) {
			// no rainfall
			$updatesql = "UPDATE minuteweather SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$midnight}';";
			fwrite($handle, $updatesql."\n");
			echo "No rf: updatesql {$updatesql}\n";
		} else {
			echo "first rf: {$firstts}\n ";
			if (substr($firstts,10) == "00:00:00") {
				$updatesql = "";
				echo "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($firstts." - 1 MINUTE"));
                $updatesql = "UPDATE minuteweather SET cumrf = 0 WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp between '{$dt} 00:01:00' AND '{$endts}';";
				fwrite($handle, $updatesql."\n");
				echo "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, rf FROM minuteweather WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp BETWEEN '{$firstts}' AND '{$midnight}' ORDER BY timestamp";
			$wrows = $db->query($wsql);
			$cumrf = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$cumrf += $wrow['rf'];
				// $sqls[] = "UPDATE minuteweather SET cumrf={$cumrf} WHERE idsite = {$idsite} AND wsid={$wsid} AND timestamp='{$wrow['timestamp']}';";
				$sqls[] = "REPLACE INTO `wx_cumrf`(`wsid`, `timestamp`, `idsite`, `cumrf`) VALUES ('{$wsid}', '{$wrow['timestamp']}',{$idsite},{$cumrf});";
			}
			$updates = implode("\r\n",$sqls);
			echo "CUMRF\n",print_r($updates,true),"\n\n";
		}
		if ($updatesql > "") {
			echo "would exec here"; //$db->exec($updatesql);
		}
		if (count($sqls)>0){
			fwrite($handle,"TRUNCATE TABLE wx_cumrf;\n");
			$updates = implode("\n",$sqls);
			fwrite($handle, $updates."\n");
			$sql = "UPDATE minuteweather m INNER JOIN wx_cumrf r ON m.wsid = r.wsid AND m.timestamp = r.timestamp SET m.cumrf = r.cumrf;";
			fwrite($handle, $sql."\n");
			echo "would exec here";  //$db->exec_multi($updates);
		}
    }
	$msg .= "Done.". PHP_EOL;
	fwrite($handle,"quit;\n");
	fclose($handle);
	return $msg;
}
$db = new DB;

//echo calcCumMinuteRF(802,'70127' ,'2019-01-08 00:01:00');
echo calcCumMinuteRF(107,'30972' ,'2019-01-08 00:01:00');
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
