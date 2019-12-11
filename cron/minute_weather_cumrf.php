<?php
require "DB.class.php";
$db = new DB();
date_default_timezone_set('UTC');

function calcCumRF($idsite, $wsid, $recalc_all=false){
	// get max timestamp with cumrf set
	global $db;
    $sql = "SELECT MAX(timestamp) FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid} AND cumrf IS NOT NULL";
    $startts = $db->getScalar($sql);
	if (empty($startts) || $recalc_all) {
		// update first row and go from there
		$startts = $db->getScalar("SELECT MIN(timestamp) FROM minuteweather idsite={$idsite} AND wsid={$wsid}");
        $sql = "UPDATE minuteweather SET cumrf=rf WHERE timestamp='{$startts}'";
		$db->exec($sql);
	}
	$sql = "SELECT local_ts, cumrf FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp='{$startts}'";

	$row = $db->query($sql, true);
	$last_local_ts = $row['local_ts'];
	$cumrf =  $row['cumrf'];
	if (empty($last_cumrf)) {
		$cumrf = 0.0;     // should not be needed
	}
	$sql = "SELECT local_ts, timestamp, rf FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid}
			AND timestamp > '{$startts}' ORDER BY timestamp;";
	$result = $db->query($sql,false,'timestamp',MYSQLI_BOTH);
	if (count($result) == 0 ){
		echo "\nNo data found to process\n";
		exit;
	}
	$nonzero_rf = array();
	// we'll issue an update for each row has non-zero rf individually, then handle the remainders with simple WHERE later
	foreach ($result as $ts=>$row){
        // start by checking to see if midnight has passed
		$local_ts = $row[0];
		$diff = strtotime($local_ts) - strtotime($last_local_ts);
		$time = substr($local_ts,11,5);  //ex. 2014-08-25 13:42:00 ignore secs

        if ($time=="00:00" || substr($local_ts,0,10) > substr($last_local_ts,0,10)) {
        	$cumrf = 0.0;
			//$nonzero_rf[] ="RESET {$time} {$local_ts}  {$last_local_ts}";
			//echo "\nRESET {$time} {$local_ts}  {$last_local_ts}\n";
        }
		$cumrf += $row[2];
		if ($cumrf > 0.0) {
			$nonzero_rf[] = "UPDATE minuteweather SET cumrf={$cumrf} WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp='{$row[1]}' ";
			array($row[1],$cumrf);
		}
		$last_local_ts = $local_ts;
	}
	// we have to be careful not to update a row that came in AFTER this computation was started
	$lastts = $row[1];
	//print_r($nonzero_rf);
	//die;
	echo "\nUpdating ",count($nonzero_rf)," rows with non-zero cum. rf\n";
	$errorflag = false;
	for ($i=0; $i < count($nonzero_rf) / 1000; $i++ ){
		$q = array_slice($nonzero_rf, $i*1000, 1000);
		$sql = implode(" ;\n",$q);
		$startndx = $i*1000;
		echo "\n1000 Rows starting at {$startndx}";
		echo "\nFirst: {$q[0]}\nLast: ".$q[count($q)-1];
		//'2015-01-14 22:23:00'
		$ts0 = substr($q[0],-22);   // get quotes
		$ts9 = substr($q[count($q)-1],-22);
		$nullsql = "UPDATE minuteweather SET cumrf=0.0 WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp >={$ts0} AND timestamp <={$ts9} AND cumrf IS NULL";
		$db->exec_multi($sql);
		if ($db->error != "") {
			echo "\nError ",$db->error,"\n";
			$errorflag = true;
			die;
		} else {
			echo "\nUPDATE OK\n";
			echo "\nNull SQL: {$nullsql}\n";
			$db->exec($nullsql);
			if ($db->error != "") {
				echo "\nNULL SQL Error ",$db->error,"\n";
			} else {

			}
		}
	}
	echo "Last TS was '{$lastts}'\n";
    if (!$errorflag){
		$sql =  "UPDATE minuteweather SET cumrf=0.0 WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp <='{$lastts}' AND cumrf IS NULL";
		$db->exec($sql);

    }
}
if (isset($argc)){
	if ($argc < 2) {
		echo "Usage: php minute_weather_cumrf idsite wsid [recalc]";
		exit;
	}
	$idsite = $argv[1];
	$wsid   = $argv[2];
	$recalc = isset($argv[3]) ? $argv[3] : 0;
} else {
	$idsite = isset($_GET['idsite']) ? $_GET['idsite'] : '';
	$wsid   = isset($_GET['wsid'])   ? $_GET['wsid'] : '';
	$recalc = isset($_GET['recalc']) ? $_GET['recalc'] : '';
	if (empty($idsite) || empty($wsid)){
		echo "<pre>";
		echo "Usage: php minute_weather_cumrf?idsite=NNNN&wsid=wwww[&recalc=1]";
		exit;
	}
}
calcCumRF($idsite, $wsid, $recalc);
// catch any stray minutes that came in
calcCumRF($idsite, $wsid,false);
?>
