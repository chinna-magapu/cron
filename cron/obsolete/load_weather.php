<?php
require("DB.class.php");

function calcCumRF($idsite, $wsid, $recalc_all=false){
	// get max timestamp with cumrf set
	global $db;
	$msg = "<pre>Starting Cumulative Rainfall\n";
    $sql = "SELECT MAX(timestamp) FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid} AND cumrf IS NOT NULL";
    $startts = $db->getScalar($sql);
	if (empty($startts) || $recalc_all) {
		// update first row and go from there
		$startts = $db->getScalar("SELECT MIN(timestamp) FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid}");
        $sql = "UPDATE minuteweather SET cumrf=rf WHERE timestamp='{$startts}' AND idsite={$idsite} AND wsid='{$wsid}'";
		$db->exec($sql);
	}
	$sql = "SELECT local_ts, cumrf FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp='{$startts}'";

	$row = $db->query($sql, true);
	$last_local_ts = $row['local_ts'];
	$cumrf =  $row['cumrf'];
	if (empty($cumrf)) {
		$cumrf = 0.0;     // should not be needed
	}
	$last_cumrf = $cumrf;
	$sql = "SELECT local_ts, timestamp, rf FROM minuteweather WHERE idsite={$idsite} AND wsid={$wsid}
			AND timestamp > '{$startts}' ORDER BY timestamp;";
	$result = $db->query($sql,false,'timestamp',MYSQLI_BOTH);
	if (count($result) == 0 ){
		$msg .= "No data found to process\n";
		return $msg;
	}
	$updates = array();
	$firstts = $startts;
	foreach ($result as $ts=>$row){
        // start by checking to see if midnight has passed
		$local_ts = $row[0];
		$time = substr($local_ts,11,5);  //ex. 2014-08-25 13:42:00 ignore secs

        if ($time=="00:00" || substr($local_ts,0,10) > substr($last_local_ts,0,10)) {
        	// we always have one update to finish
			$updates[] = Array('cumrf'=>$last_cumrf,'firstts'=> $firstts, 'lastts' => $row[1]);
			$sqls = Array();
			foreach($updates as $updata) {
				$sqls[] = "UPDATE minuteweather SET cumrf={$updata['cumrf']} WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp >='{$updata['firstts']}' AND timestamp < '{$updata['lastts']}'";
			}
			$sql = implode(" ;\n",$sqls);
			$db->exec_multi($sql);
			if ($db->error != "") {
				$msg .= "\nError ".$db->error."\n";
				$errorflag = true;
			}
        	$cumrf = $row[2];
			$last_cumrf = $cumrf;
			$firstts = $row[1];
        } else {
			$cumrf += $row[2];
        }
		if ($cumrf != $last_cumrf) {
			// lastts will have a < condition applied
			$updates[] = Array('cumrf'=>$last_cumrf,'firstts'=> $firstts, 'lastts' => $row[1]);
			$last_cumrf = $cumrf;
			$firstts = $row[1];
		}
		$last_local_ts = $local_ts;
	}
	$sqls = Array();
	$updates[] = Array('cumrf'=>$last_cumrf,'firstts'=> $firstts, 'lastts' => $row[1]);
	foreach($updates as $updata) {
		$sqls[] = "UPDATE minuteweather SET cumrf={$updata['cumrf']} WHERE idsite={$idsite} AND wsid={$wsid} AND timestamp >='{$updata['firstts']}' AND timestamp < '{$updata['lastts']}'";
	}
	$sql = implode(" ;\n",$sqls);
	echo "\nSQL:", $sql;
		$db->exec_multi($sql);
		if ($db->error != "") {
			$msg .= "\nError ".$db->error."\n";
			$errorflag = true;
		}

	$msg .= "</pre>";
	return $msg;
}

function AggregateToWeather($idsite, $wsid, $first_ts, $last_ts) {
	global $db;
	// we will add a margin to the timestamp range by using the nearest hour before and after
	$last_ts  = substr($last_ts,0,14)."59:59";
	$first_ts = substr($first_ts,0,14)."00:00";
	//echo "\n{$first_ts} {$last_ts}\n";
	$sql =
"
REPLACE INTO weather
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, srad, t1, mv1, mv2, ver, dewpoint, heatindex, windchill, cumrf)
SELECT idsite, wsid,
CONCAT(CAST(DATE(local_ts) AS CHAR(10)),' ',
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END
,':00') AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, SUM(solarmj)*1000 AS srad,
	AVG(1.8 * soiltemp  + 32.0) AS t1, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'BAE WS 2014-08' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf
FROM minuteweather
WHERE wsid={$wsid} AND idsite={$idsite} AND timestamp >= '{$first_ts}' AND timestamp <= '{$last_ts}'
GROUP BY idsite, wsid,
CONCAT(CAST(DATE(local_ts) AS CHAR(10)),' ',
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END
,':00');
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

function process_file($dirpath, $fname){
	global $wsnames, $debug, $db;
	//print_r($wsnames); die;
	//if ($debug) print_r($wsnames);
	$notes = "Weather File Loader ".date(DATE_RFC822)."\n{$fname}\n";
	echo "Weather  File Loader ".date(DATE_RFC822)."\n{$fname}\n";

	$tzone = 'UTC';
	$tz = new DateTimeZone($tzone);

	//$sql = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp, local_ts)\n
	//		VALUES ";
	$sql = "INSERT IGNORE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp,
			gflux, soiltemptop, soiltempbot, local_ts)\n 	VALUES ";
	$sql = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp,
			gflux, soiltemptop, soiltempbot, local_ts)\n 	VALUES ";
	$sql17 = "REPLACE INTO minuteweather(wsid, idsite, timestamp, recno, baro, rf, temp, RH, solarkw, solarmj, windspeed, winddir, vmc, ec, soiltemp,
			gflux, soiltemptop, soiltempbot, lightning, local_ts)\n 	VALUES ";
	$row = 1;
	$fullfname = $dirpath.$fname;
	if (($handle = fopen($fullfname, "r")) !== FALSE) {
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	        //echo "<p> $num fields in line $row: <br /></p>\n";
			if ($row==1){
				$wsname = $data[1];
				if (!array_key_exists($wsname,$wsnames)){
					echo "\nAbort - missing station name {$wsname} in wsnames table.";
					//do NOT record file
					return;
				}
                $wsid = $wsnames[$wsname]['wsid'];
				$tzone = $wsnames[$wsname]['timezone'];
				$idsite = $wsnames[$wsname]['idsite'];
				if (empty($tzone)) $tzone = 'UTC';
				date_default_timezone_set($tzone);
				$tz = new DateTimeZone($tzone);
			}
			if ($row == 2) {
                $rsql = count($data)==17 ? $sql17 : $sql;
			}
			if ($row == 5 && count($data)>=13){
				$first_ts = $data[0];
			}
			if ($row > 4 && count($data)==13){
				$ts = $data[0];
				$last_ts = $data[0];
				$dt = new DateTime($ts);
				$dt->setTimezone($tz);
				$local_ts = $dt->format('Y-m-d H:i:s');
				if ($debug) echo "Data: ".implode(" | ",$data)."\n";
				$rsql .= "\n({$wsid},{$idsite},'".implode("','",$data)."',NULL,NULL,NULL,'{$local_ts}'),";
			} elseif ($row > 4 && count($data)>=16){
				// data fixups for belmont
	            if (substr($fname,0,7) == 'BELMONT'){
					$data[12] = 'NAN';
					$data[13] = 'NAN';
	            }
				$ts = $data[0];
				$last_ts = $data[0];
				$dt = new DateTime($ts);
				$dt->setTimezone($tz);
				$local_ts = $dt->format('Y-m-d H:i:s');
				if ($debug) echo "Data: ".implode(" | ",$data)."\n";
				$rsql .= "\n({$wsid},{$idsite},'".implode("','",$data)."','{$local_ts}'),";
			} else {
				if ($debug) echo "Cnt ".count($data)." Data: ".implode(" | ",$data)."\n";
			}
	        $row++;
	    }
		$rsql = rtrim($rsql, ",");
		// fixups for invalid data
		$rsql = str_replace("'-7999'",'NULL',$rsql);
		$rsql = str_replace("'7999'",'NULL',$rsql);
		$rsql = str_replace("'NAN'",'NULL',$rsql);

	    fclose($handle);
		if ($debug) echo "\n$rsql";
		if ($debug) echo "</pre>";
		$db->exec($rsql);
		$error = $db->error;
		if (empty($error)) {
			$notes = "Upload succeeded; ".($row - 4)." rows of data were processed";
			$notes .= calcCumRF($idsite,$wsid);
			$notes .= AggregateToWeather($idsite, $wsid, $first_ts, $last_ts);
			annotate($fname,$notes,1);
		} else {
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

$debug = false;
if (PHP_SAPI == "cli"){
	$debug = $argc > 1 && $argv[1] == "debug";
} else {
	$debug = isset($_GET['debug']) && $_GET['debug'] == "debug";
	echo "<pre>";
}

$error = "";
$db = New DB;
$db->debug = false;
$hostname = php_uname("n");
$svrprefix = $hostname == 'VPS' ? 'bioappeng.us ' :'bioappeng.com ';
$sql = "INSERT INTO alertcheck (script, timestamp, comment) VALUES ('load_weather','".date('Y-m-d H:i:s')."',".$hostname.'WS Load Weather Job ran)';
if ($debug) echo "<pre>\nquery\n{$sql}";
$db->exec($sql);

$wsnames = array();
$sql = "SELECT s.wsid, s.wsname, s.idsite, si.timezone FROM wsnames s INNER JOIN site si
		  ON s.idsite = si.idsite ORDER BY wsname;";
$result = $db->query($sql);
foreach ($result as $row) {
	$wsnames[$row['wsname']] = Array('idsite'=>$row['idsite'],'wsid'=>$row['wsid'],'timezone'=>$row['timezone']);
}
$datadir  = $hostname == "VPS" ? "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS" : '../../data/WS';
$datapath = $hostname == "VPS" ? "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS\\" : '../../data/WS/';
if ($handle = opendir($datadir)) {
    while (($entry = readdir($handle)) !== false) {
    	if ($debug) echo "Filename: {$entry}\n";
		$test = strtolower(substr($entry,-7));
    	if ($test == "_ws.dat"){
	    	$sql = "SELECT COUNT(*) FROM ws_files WHERE success=1 AND filename=?";
			$params = array('s',$entry);
		    	$is_present = $db->getScalar_prepared($sql,$params);
			if ($is_present==0){
				echo "PRESENT {$is_present}: {$entry}\n";
				process_file($datapath, $entry);
			} else {
				if ($debug) echo "PRESENT {$is_present}: {$entry}\n";
			}
    	}
    }
    closedir($handle);
	echo "\nJOB COMPLETE\n";
}

?>
