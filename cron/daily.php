<?php

date_default_timezone_set("America/New_York");
require "database_inc.php";
OpenMySQLi();

function _mysqli_fetch_all($result, $keyby = false) {
    $all = array();
    while ($row = mysqli_fetch_assoc($result)) {
    	if (!$keyby){
	        $all[] = $row;
    	} else {
	        $all[$row[$keyby]] = $row;
    	}
    }
    return $all;
    mysqli_free_result($result);
}

function generate_ts($date){
	$times = array();
    for ($i = 0; $i< 96; $i++){
    	$mins = $i * 15;
		$ts   = date('Y-m-d H:i:s',strtotime($date." + {$mins} minutes "));
    	$times[$ts] = $i;
    }
	return $times;
}

function clean_excess_wu($idsite, $date){
	global $mysqli, $times;
	$sdate = $date." 00:00:00";
	$edate = date('Y-m-d H:i:s',strtotime($date." + 1 DAY + 1 MINUTE "));
	$sql = "SELECT * FROM weather WHERE idsite={$idsite} AND timestamp BETWEEN '{$sdate}' AND '{$edate}'";
	echo "\nSQL {$sql}\n";
	$result = $mysqli->query($sql);
	$data = _mysqli_fetch_all($result);
	$sqls = array();
	foreach($data as $i=>$row){
		$ts = $row['timestamp'];
		if (!array_key_exists($ts,$times)) {
			if($i==0){
				$diffa = strtotime($data[$i+1]['timestamp']) - strtotime($row['timestamp']);
				if ($diffa < 900 && $row['src'] == 'WU'){
					$sql = "INSERT INTO weather_deleted SELECT * FROM weather WHERE idweather={$row['idweather']};\n";
					$sqls[] = $sql;
					$sql = "DELETE FROM weather WHERE idweather={$row['idweather']} LIMIT 1;\n";
					$sqls[] = $sql;
				}
			} elseif ($i==count($data)-1){
				$diffb = strtotime($row['timestamp']) - strtotime($data[$i-1]['timestamp']);
				if ($diffb < 900 && $row['src'] == 'WU'){
					$sql = "INSERT INTO weather_deleted SELECT * FROM weather WHERE idweather={$row['idweather']};\n";
					$sqls[] = $sql;
					$sql = "DELETE FROM weather WHERE idweather={$row['idweather']} LIMIT 1;\n";
					$sqls[] = $sql;
				}
			} else {
				$diffa = strtotime($data[$i+1]['timestamp']) - strtotime($row['timestamp']);
				$diffb = strtotime($row['timestamp']) - strtotime($data[$i-1]['timestamp']);
				if ($diffa < 900 && $diffb < 900 && $row['src'] == 'WU'){
					$sql = "INSERT INTO weather_deleted SELECT * FROM weather WHERE idweather={$row['idweather']};\n";
					$sqls[] = $sql;
					$sql = "DELETE FROM weather WHERE idweather={$row['idweather']} LIMIT 1;\n";
					$sqls[] = $sql;
				}
			}
		}
	}
	if (count($sqls)){
		foreach ($sqls as $sql){
			$ok = $mysqli->query($sql);
			$msg = $ok ? ' OK' : $mysqli->error;
			echo "\nQ: {$sql} Result {$msg} ";
		}
	}

}
date_default_timezone_set("America/New_York");
/*
if ($argc < 4) {
	echo "Usage:  clean_weather idsite start-date days ";
	exit;
}
$idsite = $argv[1];
$date  = $argv[2];
$days = isset($argv[3]) ? $argv[3] : 1; */

$sql = "SELECT idsite, DATE_FORMAT( TIMESTAMP,  '%Y-%m-%d' ) AS date, COUNT( * ) AS readings, count(distinct src) as sources
		FROM weather  WHERE timestamp > DATE_SUB(NOW(),INTERVAL 30 DAY)
		GROUP BY idsite, DATE_FORMAT( TIMESTAMP,  '%Y-%m-%d' )
		HAVING COUNT( * ) >96 AND count(distinct src) > 1 ";

echo $sql,"\n";
$result = $mysqli->query($sql);
$cleanlist = _mysqli_fetch_all($result);
echo "\n",count($cleanlist)," Site Days to clean\n";
foreach ($cleanlist as $row){
	$idsite = $row['idsite'];
	$date   = $row['date'];
	if (!empty($date)){
		$times = generate_ts($date);
		echo "Cleaning {$idsite} for {$date} with {$row['readings']} rows\n";
		clean_excess_wu($idsite,$date);
	} else {
		echo "BAD DATE: {$idsite} for {$date} with {$row['readings']} rows\n";
	}
}
$mach = php_uname("n");
$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('daily.php','WU Clean Daily','{$mach}')";
$ok = mysqli_query($mysqli,$sql);
echo "LOG SQL: {$sql}\n";
?>
