<?php
require("DB.class.php");
// only works for Aqueduct - only station in this table so far March 2016
function calcCumRF($idsite, $wsid, $startdate){
	// get max timestamp with cumrf set
	global $db;
	$msg = "<pre>Starting Cumulative Rainfall\n";
	$startdate = substr($startdate,0,10);
    $sql = "SELECT distinct DATE(timestamp) AS dt FROM wx_singlesensor15m WHERE idsite = {$idsite} AND wsname='{$wsid}' AND timestamp > '{$startdate}' ORDER BY dt";
	echo $sql;
	$dates = $db->query($sql);
	if (count($dates)==0) {
		echo "\nNo Dates\n";
		die;
	}
    foreach ($dates as $dtrow) {
		echo "\nProcessing {$dtrow['dt']}";
		$dt = $dtrow['dt'];
		$midnight = Date('Y=-m-d H:i:s',strtotime($dt.' + 1 day'));  //midnight next day
		$sql = "SELECT MIN(timestamp) AS firstrf FROM wx_singlesensor15m WHERE idsite = {$idsite} AND  wsname='{$wsid}' AND timestamp between '{$dt} 00:01:00' AND '{$midnight}' AND dataval > 0;";
		echo "\n$sql\n";
		$firstts = $db->getScalar($sql);
		$updatesql = "";
		$sqls = array();
		if (empty($firstts)) {
			// no rainfall
			$updatesql = "UPDATE wx_singlesensor15m SET agg_val = 0 WHERE idsite = {$idsite} AND  wsname='{$wsid}'  AND timestamp between '{$dt} 00:01:00' AND '{$midnight}'";
			echo "No rf: updatesql {$updatesql}\n";
		} else {
			echo "first rf: {$firstts}\n ";
			if (substr($firstts,10) == "00:00:00") {
				$updatesql = "";
				echo "Rainfall since midnight, no update 0\n";
			} else {
				$endts = Date("Y-m-d H:i:s", strtotime($firstts." - 1 MINUTE"));
                $updatesql = "UPDATE wx_singlesensor15m SET agg_val = 0 WHERE idsite = {$idsite} AND  wsname='{$wsid}'  AND timestamp between '{$dt} 00:01:00' AND '{$endts}'";
				echo "updatesql {$updatesql}\n";
			}
			$wsql = "SELECT timestamp, dataval FROM wx_singlesensor15m WHERE idsite = {$idsite} AND  wsname='{$wsid}' AND timestamp BETWEEN '{$firstts}' AND '{$midnight}' ORDER BY timestamp";
			//echo $wsql; die;
			$wrows = $db->query($wsql);
			$agg_val = 0.0;
			$sqls = array();
			foreach ($wrows as $wrow) {
				$agg_val += $wrow['dataval'];
				$sqls[] = "UPDATE wx_singlesensor15m SET agg_val={$agg_val} WHERE idsite = {$idsite} AND  wsname='{$wsid}'  AND timestamp='{$wrow['timestamp']}';";
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
echo calcCumRF(801,'72759','2016-01-23 00:00:00');

//echo calcCumRF('104','9001',true);
//echo AggregateToWeather('104','9001');
//echo calcCumRF('809','9001',true);
//echo AggregateToWeather('809','9001');
?>
