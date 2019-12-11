<?php
require("database_inc.php");
date_default_timezone_set("America/New_York");
/*
debugging GS
SELECT * FROM going_files where filename in ('DELMARTURF_20160818_080116_GS.dat','DELMARTURF_20160818_175802_GS.dat')

DELETE FROM WHERE filename IN ('DELMARTURF_20160818_080116_GS.dat','DELMARTURF_20160818_175802_GS.dat') LIMIT 2;
DELETE FROM going_import WHERE filename IN ('DELMARTURF_20160818_080116_GS.dat','DELMARTURF_20160818_175802_GS.dat');

xcopy  F:\work\bae\cronfiles\DELMARTURF_20160818*.dat \inetpub\wwwroot\data
php load_moisture.php

*/
function next_set_number($idsite, $track, $date, $going=false){
	global $mysqli;
	$qtrack = $mysqli->escape_string($track);
	if ($going){
		$sql = "SELECT MAX(`set`) FROM going_import WHERE idsite={$idsite} AND track='{$qtrack}' AND date='{$date}';";
	} else {
		$sql = "SELECT MAX(`set`) FROM moisture_import WHERE idsite={$idsite} AND track='{$qtrack}' AND date='{$date}';";
	}
	$result = $mysqli->query($sql);
	$row = mysqli_fetch_row($result);
	$set = $row[0];
	$result->free();
	return empty($set) ? 1 : $set+1;
}

function loaddata($lines, $idsite, $track, $date, $fname){
	global $mysqli, $debug;
	$ret = $comments = "";
	$n = $okcnt = 0;
	$set = next_set_number($idsite, $track, $date)-1;
	$test = $lines[0][0];
	$lastn = 999999;
	for ($i=0; $i<5; $i++) {
		if (substr($lines[$i],0,2) == 'Pt') {
			$comments = $lines[$i-1];
			$n = $i + 1;
			break;
		}
	}
	while ($n < count($lines)){
		$data = explode("\t",$lines[$n++]);
		if (count($data) == 3){
			$N = (int)$data[0];
			if ($N < $lastn){
				//start a new set
				$set++;
				$ret .= "\nSET #{$set}:";
                // 2017 special case Belmont; set 1 is Inner Turf Course and set 2 Widener Turf Course
				if ($idsite == 802 && $set == 2 &&  strtoupper(substr($fname,0,11)) == 'BELMONTTURF'){
					$track = 'Widener Turf Course';
				}
				// special case Keeneland, which will have 51 Main Track then 46 Turf Track in one file
				if ($idsite == 104 && $set > 1 && $lastn == 51){
					// good to go if first one had 126
					$track = 'Turf Track';
				}
				// special case Fair Grounds. If 42 or fewer then turf track; if > 42 then Main Track
				// but if 90 then the first 42 are still turf
				if ($idsite == 103 && $lastn > 42 && $lastn < 999999) {
					// the second check ensures that this is set two or higher
					$sql = "UPDATE moisture_import SET Track='Main Track'
					        WHERE filename = '{$fname}' AND `set`=".($set-1)." AND idsite=103 AND Track='Turf Track' AND date='{$date}'";
					$ok = $mysqli->query($sql);
				}
				// special case Canterbury Park. If 42= turf track; otherwise Main Track (should be 48)
				// but if 90 then the first 42 are still turf
				if ($idsite == 401 && $lastn == 42) {
					$sql = "UPDATE moisture_import SET Track='Turf Track'
					        WHERE filename = '{$fname}' AND `set`=".($set-1)." AND idsite=401 AND Track='Main Track' AND date='{$date}'";
					$ok = $mysqli->query($sql);
				}
			}
			if ($debug) echo "N{$N} LINE {$lines[$n-1]}\n";
			$lastn = $N;
			$moist = $data[1];
			$rod = $data[2];
			$sql = "REPLACE INTO moisture_import (filename,`set`, date, idsite, track, N, moisture, rod_length)
				VALUES ('{$fname}',{$set},'{$date}',{$idsite},'{$track}',{$N},{$moist},{$rod});";
			$ok = $mysqli->query($sql);
			if ($debug) echo "\n$sql\nResult({$ok})\nError: ".$mysqli->error,"\n";

			$ok = true;
			if (!$ok){
				$ret .= "\n  L# {$N} ".$lines[$n-1]."::".$mysqli->error;
			} else {
				$okcnt++;
			}
		}
	}
	/* @@FG SPECIAL CASE - see comments */
	if ($idsite == 103 && $lastn == 90) {
		//Fair Grounds forgot to reset the counter, so 1-42 are Turf, 43 to 90 are Main
		$sql = "UPDATE moisture_import SET Track='Main Track', `set` = `set`+1, N = N - 42
			WHERE filename = '{$fname}' AND `set`=".($set)." AND N > 42 AND idsite=103 AND Track='Turf Track' AND date='{$date}' ";
		$ok = $mysqli->query($sql);
		$comments .= " \nData set #{$set} had 90 points and was split by the loader process into Turf Track and Main Track at point #43.";
	}
	if ($idsite == 103 && $lastn > 48 && $lastn != 90) {
		$comments .= " \nWARNING. Data set #{$set} had {$lastn} points. Actual track and/or split point between Turf Track and Main Track cannot be determined.";
	}
	if ($idsite == 103 && $lastn > 44 && $lastn < 52) {
		$sql = "UPDATE moisture_import SET Track='Main Track'
			WHERE filename = '{$fname}' AND `set`=".($set)." AND idsite=103 AND Track='Turf Track' AND date='{$date}'";
			$comments .= " \nSet {$set} data set had {$lastn} points. The loader process assumed it is the Main Track.";
		$ok = $mysqli->query($sql);
	}
	if ($debug) echo "\nDONE\n";
	return array('ret'=>$ret,'comments'=>$comments);
}

function load_gs_data($lines, $idsite, $track, $date, $fname){
	global $mysqli;
	$ret = Array('ret'=>"", 'comments'=>"");
	$comments = "";
	if (count($lines)<4){
		$ret['ret'] = "\nOnly ".count($lines)." lines in file";
    	return $ret;
	}
	if (trim($lines[2]) == "PENETRATION SHEAR" || trim($lines[2]) == "PENETRATION\tSHEAR"){
		$comments = $lines[1];
		if (trim($comments) == "NO COMMENT" || trim($comments) == "NO COMMENTS"){
			$comments = "";
		}
	}
	$n = 2;   //lowest possible line number
	$set = next_set_number($idsite, $track, $date, true);
echo "\n*** set idsite track date fname {$idsite}|{$track}|{$date}|{$fname}\n\n";
	$test = $lines[0][0];
	while ($n < count($lines) && !is_numeric($test)){
		$n++;
		$test = $lines[$n][0];
	}
	$N = $okcnt = 0;
	while ($n < count($lines)){
		$data = explode("\t",$lines[$n++]);
		if (count($data) == 2){
			$N++;
			$lastn = $N;
			$penet = $data[0];
			$shear = $data[1];
			$sql = "INSERT INTO going_import (filename,`set`, date, idsite, track, N, penetration, shear)
				VALUES ('{$fname}',{$set},'{$date}',{$idsite},'{$track}',{$N},{$penet},{$shear})
				ON DUPLICATE KEY UPDATE penetration={$penet}, shear={$shear};";
			$ok = $mysqli->query($sql);
			//echo "\n$sql\n";
			if (!$ok){
				$ret .= "\n  L# {$N} ".$lines[$n-1]."::".$mysqli->error;
			} else {
				$okcnt++;
			}
		}
	}
	$ret = "{$okcnt} lines loaded successfully.\n".$ret;
	return array('ret'=>$ret,'comments'=>$comments);
}


function annotate_gs($fname,$comments, $success,$load_comments){
	global $mysqli;
	$sql = "REPLACE INTO going_files(filename, results,success,load_comments) VALUES (?,?,?,?) ";
    $stmt = $mysqli->stmt_init();
    $stmt->prepare($sql);
    $stmt->bind_param("ssis",$fname,$comments,$success,$load_comments);
	$ok = $stmt->execute();
	if (!$ok){
		echo "ERROR on annotate: ".$mysqli->error;
	}
}

function annotate($fname,$comments, $success=1, $load_comments){
	global $mysqli;
	$sql = "REPLACE INTO moisture_files(filename, results,success, load_comments) VALUES (?,?,?,?) ";
    $stmt = $mysqli->stmt_init();
    $stmt->prepare($sql);
    $stmt->bind_param("ssis",$fname,$comments,$success,$load_comments);
	$ok = $stmt->execute();
	if (!$ok){
		echo "ERROR on annotate: ".$mysqli->error;
	}
}
function process_file($fname){
	global $mysqli, $tracknames, $datadir;

	$notes = "Moisture File Loader ".date(DATE_RFC822)."\n{$fname}\n";
	//echo "Moisture File Loader ".date(DATE_RFC822)."\n{$fname}\n";

	//AQUEDUCTMAIN_20130329_160129_TDR.dat
	$tmp = explode('_',$fname);
	if (count($tmp)<4){
		$notes .= " Abort - unexpected filename format.\n";
		annotate($fname,$notes,0);
		return;
	}
	if (!array_key_exists($tmp[0],$tracknames)){
		echo "Abort - missing filename or moisture flag in Track table.\n";
		//do NOT record file
		return;
	}
	$idsite = $tracknames[$tmp[0]][0];
	$track  = $tracknames[$tmp[0]][1];
	if (strlen($tmp[1]) < 8){
		$notes .= " Abort - invalid date ({$tmp[1]})\n";
		annotate($fname,$notes,0);
		return;
	}
	$date = substr($tmp[1],0,4).'-'.substr($tmp[1],4,2).'-'.substr($tmp[1],6,2);
	$test = strtotime($date);
	if ( empty ($test )) {
		$notes .= " Abort - invalid date ({$tmp[1]})\n";
		annotate($fname,$notes,0);
		return;
	}
	try {
		$text = file_get_contents($datadir.'/'.$fname);
		$text = str_replace("\r\n","\n",$text);
		$lines = explode("\n",$text);
		$notes .= "\nLine count: ".count($lines);
		$retval = loaddata($lines,$idsite,$track,$date, $fname);
		$notes .= $retval['ret']."\nJOB COMPLETE\n";
		$comments = $retval['comments'];
		$success = 1;
	} catch (Exception $e) {
		$success = 0;
		$notes = 'Exception: '.$e->getMessage(). "\n";
	}
	annotate($fname,$notes, $success, $comments);
}

function process_gs_file($fname){
	global $mysqli, $tracknames, $datadir ;

	$notes = "Going Stick file Loader ".date(DATE_RFC822)."\n{$fname}\n";
	echo "Going Stick File Loader ".date(DATE_RFC822)."\n{$fname}\n";

	//ARLINGTON_20130509_142359_GS.dat
	$tmp = explode('_',$fname);
	if (count($tmp)<4){
		$notes .= " Abort - unexpected filename format.\n";
		annotate_gs($fname,$notes,0);
		return;
	}
	if (!array_key_exists($tmp[0],$tracknames)){
		echo " Abort - missing filename or going stick flag in Track table.\n";
		echo "\nName: ",$tmp[0],"=>",bin2hex($tmp[0]);
		return;
	}
	$idsite = $tracknames[$tmp[0]][0];
	$track  = $tracknames[$tmp[0]][1];
	if (strlen($tmp[1]) < 8){
		$notes .= "  Abort - invalid date ({$tmp[1]})\n";
		annotate_gs($fname,$notes,0);
		return;
	}
	$date = substr($tmp[1],0,4).'-'.substr($tmp[1],4,2).'-'.substr($tmp[1],6,2);
	$test = strtotime($date);
	if ( empty ($test )) {
		$notes .= " Abort - invalid date ({$tmp[1]})\n";
		annotate_gs($fname,$notes,0);
		return;
	}
	try {
		$text = file_get_contents($datadir.'/'.$fname);
		$text = str_replace("\r\n","\n",$text);
		$lines = explode("\n",$text);
		$notes .= "\nLine count: ".count($lines);
		/* NOT RELIABLE
		if (strlen($lines[1])>= 15 && substr($lines[1],8,1)=='_' && is_numeric(substr($lines[1],0,8)) && is_numeric(substr($lines[1],9,6))) {
			//example 20150418_115822
			$mins = substr($lines[1],11,2);
			$hrs  = substr($lines[1],9,2);
			$hrs += $mins >= 30 ? 1 : 0;
			$testdt = strtotime(substr($lines[1],0,8).' '.$hrs.'0000');
            if (!empty($testdt)) {
				$date = date('Y-m-d H:i:s',$testdt);
            }
		} */
		$retval = load_gs_data($lines,$idsite,$track,$date, $fname);
		$notes .= $retval['ret']."\nJOB COMPLETE\n";
		$comments = $retval['comments'];
		$success = 1;
	} catch (Exception $e) {
		$success = 0;
		$notes = 'Exception: '.$e->getMessage(). "\n";
	}
	annotate_gs($fname,$notes, $success, $comments);
}

$debug = false;
if (PHP_SAPI == "cli"){
	$debug = $argc > 1 && $argv[1] == "debug";
} else {
	$debug = isset($_GET['debug']) && $_GET['debug'] == "debug";
	echo "<pre>";
}


$mysqli = null;
OpenMySQLi();

$tracknames = array();
$sql = "SELECT idsite, track, moisture_filename FROM track WHERE moisture=1 AND moisture_filename IS NOT NULL ORDER BY idsite, track";
$result = $mysqli->query($sql);
// there are two track for churchill downs in one file. Because the array is indexed by filename stem,
// it will always be the last (Turf Track) associated with the filename
$dbug = true;
$hostname = php_uname("n");
echo "\nTDR/GS load: ".date(DATE_RFC822)."\n";
if ($dbug) echo "\nHost: {$hostname}";

while($row = mysqli_fetch_assoc($result)){
	$tracknames[$row['moisture_filename']] = Array($row['idsite'],$row['track']);
	/* @@AQU SPECIAL CASE - if name is AQUEDUCT treat as AQUEDUCTMAIN */
	if ($row['moisture_filename']=='AQUEDUCTMAIN') {
		$tracknames['AQUEDUCT'] = Array($row['idsite'],$row['track']);
		$tracknames['AQUEDUCTMAIN'] = Array($row['idsite'],$row['track']);
	}
	if (false && $dbug) {
		echo "\nTDR ".$row['moisture_filename']."|".$row['idsite']."|".$row['track'];
	}
}
$hostlocs = array("DBM"=>Array("BIOAPPENG2008","VPS","CMEADOW"),"DEV"=>Array("NASREDDIN","OUSPENSKY"));
$hostname = php_uname("n");
$loc = "bae.com";
if (in_array($hostname,$hostlocs["DBM"])) {
	$loc = "dbm";
} elseif (in_array($hostname,$hostlocs["DEV"])) {
	$loc = "dev";
}
$datadir  = $loc == "dbm" ? "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\tdrgs" :
	($loc == 'dev' ?  '..\\data\\tdrgs' : '/home/rbeaumont/data');
if ($dbug) echo "\nDatadir: {$datadir}";
$result-> free();
if ($handle = opendir($datadir)) {
    while (($entry = readdir($handle)) !== false) {
	
	if ($dbug) echo "\nTDR Filename: {$entry}";
	$test = strtolower(substr($entry,-7));
	if ($test == "tdr.dat"){
	    	$sql = "SELECT COUNT(*) FROM moisture_files WHERE success=1 AND filename='".$mysqli->escape_string($entry)."'";
	    	$is_present = MySQLiScalar($sql);
			if ($is_present==0){
				echo "\nNOT PRESENT {$is_present}: {$entry}";
				process_file($entry);
			} else {
				if ($debug) echo "PRESENT {$is_present}: {$entry}";
			}
    	}
    }
	closedir($handle);
	echo "\nTDR JOB COMPLETE\n";
}  else { 
        echo "\nopendir failed on {$datadir}\n";
}
$sql = "INSERT INTO alertcheck (machine, script, timestamp, comment) VALUES ('{$hostname}','load_moisture','".date('Y-m-d H:i:s')."','Load Moisture Job ran')";
if ($debug) echo "<pre>\nquery\n{$sql}";
$ok = $mysqli->query($sql);
if (!$ok) {
	echo "\nError: ".$mysqli->error;
}

$tracknames = array();
$sql = "SELECT idsite, track, going_points, going_filename FROM track WHERE going_points > 0 AND going_filename IS NOT NULL";
$result = $mysqli->query($sql);
while($row = mysqli_fetch_assoc($result)){
	$tracknames[$row['going_filename']] = Array($row['idsite'],$row['track'],$row['going_points']);
	if (false && $dbug) {
		echo "\n GS ".$row['going_filename']."|".$row['idsite']."|".$row['track'];
	}
}
$result-> free();
if ($handle = opendir($datadir)) {
    while (($entry = readdir($handle)) !== false) {
		if ($dbug) echo "GS Filename: {$entry}\n";
		$test = strtolower(substr($entry,-6));
    	if ($test == "gs.dat"){
 			echo "Filename: {$entry}\n";
 	    	$sql = "SELECT COUNT(*) FROM going_files WHERE success=1 AND filename='".$mysqli->escape_string($entry)."'";
	    	$is_present = MySQLiScalar($sql);
			if ($is_present==0){
				echo "PRESENT {$is_present}: {$entry}\n";
				process_gs_file($entry);
  			} else {
				if ($debug) echo "PRESENT {$is_present}: {$entry}\n";
			}
    	}
    }
    closedir($handle);
} else {
	echo "\nopendir failed on {$datadir}\n";
}
$sql = "INSERT INTO alertcheck (machine, script, timestamp, comment) VALUES ('{$hostname}','load_moisture','".date('Y-m-d H:i:s')."','Going Stick Job ran')";
if ($debug) echo "<pre>\nquery\n{$sql}";
$ok = $mysqli->query($sql);
echo "\nGOING STICK JOB COMPLETE\n";

?>
