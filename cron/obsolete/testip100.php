<?php
require("DB.class.php");

echo("Hello, World!");
$debug = true;
fetchStation();
exit;
function zn($val){
	if (empty($val) && $val !== 0 && $val !== '0') {
		return "NULL";
	} else {
		return "'{$val}'";
	}
}

function fetchStation() {
	global $wsnames, $debug, $db;
	//date_default_timezone_set($wsnames[$mac]['timezone']);
	$today = date('Y-m-d 00:00:00');
	$now   = date('Y-m-d H:i:00');
	date_default_timezone_set('America/New_York');

	$urlbase = "http://rainwise.net/inview/api/stationdata.php?star=1&username=bioappeng&pid=521b64b3a9788e651686c088cd8cbd5f&sid=527677375428a183d338a81f87362f03";
	// &sdate=2014-10-10%2001:00:00&edate=2014-10-10%2001:15:00&mac=0090C2EF12A0
	//$startts = $wsnames[$mac]['lastwx'] > '' ? $wsnames[$mac]['lastwx'] : $today;
	//$endts   = $now;

    //$url = $urlbase."&sdate=".urlencode($startts)."&edate=".urlencode($endts)."&mac=".$mac;
	$url = "http://rainwise.net/inview/api/stationdata.php?star=1&username=bioappeng&pid=521b64b3a9788e651686c088cd8cbd5f&sid=527677375428a183d338a81f87362f03&sdate=2015-06-07+00%3A46%3A00&edate=2015-06-07+08%3A02%3A00&mac=0090C2EE7AE4";

	if ($debug) echo "\n{$url}\n";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$csvdata = file_get_contents($url,false,$context);
	$savepath = "data.csv";
   	$handle = fopen($savepath,'w');
	fwrite($handle, $csvdata);
	fclose($handle);

	//if ($debug) echo "\n{$csvdata}\n";
	$sql = "REPLACE INTO minuteweatherrw (`mac`, `timestamp`, `cd`, `tia`, `til`, `tih`, `tdl`, `tdh`, `ria`, `ril`, `rih`, `rdl`, `rdh`,
		 `bia`, `bil`, `bih`, `bdl`, `bdh`, `wia`, `dia`, `wih`, `dih`, `wdh`, `ddh`, `ris`, `rds`, `lis`, `lds`, `sia`, `sis`, `sds`, `unt`,
		 `ver`, `heatindex`, `windchill`, `dewpoint`, `uv`, `batt`, `evpt`, `serial`, `t`, `flg`, `ip`, `utc`,
		 `tinia`, `tinil`, `tinih`, `tindl`, `tindh`) VALUES ";

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
		$sql .= implode(",\n",$rlines);

		$savepath = "data.sql";
	   	$handle = fopen($savepath,'w');
		fwrite($handle, $sql);
		fclose($handle);
		die;
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

?>
