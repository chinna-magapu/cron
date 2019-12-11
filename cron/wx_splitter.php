<?php
require_once("Logger.class.php");

	/*	------------------------- WX_SPLITTER and LOAD_WEATHER_OO.PHP -----------------------
	 *	1. load_weather_opt treats any file > 4,000 bytes as possibly out of order
	 *     and renames the file to ______oo.DAT. l-wx_opt ignores any oo.dat files present
	 *	2. wx_splitter.php always runs before load_weather_opt so it will pick
	 *	   up an ___________oo.dat files before being processed by load_weather_oo.php
	 *	3. Example: fn = SAR_1234_WS.dat, l-wx-opt runs at 14:01
	 *		a. l-wx-opt renames SAR_1234_WS.dat => SAR_1234_WSoo.dat
	 *		b. Next run 14:06
	 *			i. wx_splitter runs before ANY other process, finds 2 days:
	 *			   SAR_1234_001_WSoo.dat, SAR_1234_002_WSoo.dat remain in ..../data/ws
	 *			ii. SAR_1234_WSoo.dat is moved into ..../datas/plits as a backup
	 *		c. l-wx-opt ignores any *oo.dat files
	 *		d. load_weather_oo.php is the last to run and handles 00n_WSoo.dat
	*/

function writePart($first_ndx, $last_ndx, $filename, $suffix) {
	global $headers, $lines, $debug;
	// we assume that any large file is out of order!

	$fn = str_replace('_WSoo.dat',$suffix.'_WSoo.dat',$filename);
	if ($debug) echo "WRITE PART filename {$filename} suffix {$suffix} fn {$fn}\n"; //die;
	$fhandle = fopen($fn,'w');
	if (! $fhandle) {
		print_r(error_get_last());
		die;
	}
	for ($i = 0; $i < count($headers); $i++) {
		fwrite($fhandle, $headers[$i]);
	}
	for ($i = $first_ndx; $i <= $last_ndx; $i++) {
		fwrite($fhandle, $lines[$i]);
	}
	fclose($fhandle);
}

$Logger = new Logger("wxsplit.log");
$debug = false;

if (PHP_SAPI == "cli"){
	$debug = $argc > 1 && $argv[1] == "debug";
} else {
	$debug = isset($_GET['debug']) && $_GET['debug'] == "debug";
	echo "<pre>";
}

$error = "";
$hostname = php_uname("n");

$today = Date('Y-m-d');
$datadir  = "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS";
$datapath = "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS\\";
if ($hostname == 'NASREDDIN') {
	$datadir  = "..\\data\\WS";
	$datapath = "..\\data\\WS\\";
}

foreach (glob("{$datapath}*_WSoo.dat") as $filename) {
	$part = 0;
	$suffix = '';
	$fname = basename($filename);
	if (preg_match('/.*_00\d_WS.dat/', $fname) || preg_match('/.*_00\d_WSoo.dat/', $fname)) {
		echo "\n{$fname}is already split";
		continue;
	}
	if ($debug) echo "\nFN:{$fname} Filename: {$filename}";

	if (filesize($filename) == 0) {

		$newfname = $datapath.'zerolen\\'.$fname;
		rename($filename, $newfname);
		continue;
	}
	$lines = file($filename);
	$msg = count($lines)." lines were present in  {$filename}";
	$Logger->write($msg);
	if ($debug) echo "($msg)\n";
	if (count($lines) < 5) {
		$Logger->write("Only ".count($lines)." lines were present in  {$filename}");
		continue;
	}
	$headers = array_slice($lines,0,4);
	$test1 = substr($headers[0],1,4);  // TOA5
	$test2 = substr($headers[1],1,4);  // TIME
	if ($test1 != 'TOA5' || $test2 != "TIME") {
		$msg = "Bad Headers in  {$filename}";
		$Logger->write($msg);
		if ($debug) echo "{$msg}\n";
		continue;
	}
	/*
	 *	Example line
	 * "2018-11-13 14:31:00",167087,0,0,22.64,9.66,0,0,0.307,0,13.97,0,16.8,NAN,0,13.12,0,0,0,0
	 *
	*/
	if ($debug) echo "\nSplitting ...";
	$first_time = substr($lines[4],12,8);
	$data_start = 4;
	if ($first_time == '00:00:00') {
		$msg = "First timestamp in {$filename} is ".substr($lines[4],1,19);
		$Logger->write($msg);
		if ($debug) echo "($msg)\n";
		continue;
	}
	for ($i = 4; $i < count($lines); $i++) {
		$time = substr($lines[$i],12,8);
		if ($time == '00:00:00' && $i < count($lines)-1) {
			// we hit midgnight
			$part++;
			$suffix = substr('_00'.$part,-4);
			writePart($data_start, $i, $filename, $suffix);
			$msg = "Wrote file part {$suffix} of {$fname}";
			$Logger->write($msg);
			if ($debug) echo "{$msg}\n";
			$data_start = $i + 1;
		}
	}
	if ($data_start > 4 && $data_start < $i) {
		$part++;
		$suffix = substr('_00'.$part,-4);
		writePart($data_start, $i-1, $filename, $suffix);
		$msg = "Wrote file part {$suffix} of {$fname}";
		$Logger->write($msg);
		if ($debug) echo "{$msg}\n";
		$newfname = $datapath.'splits\\'.$fname;
		rename($filename, $newfname);
		$msg = "Moved to{$newfname}";
		$Logger->write($msg);
		if ($debug) echo "{$msg}\n";
	}
}
echo "SPLITTER JOB COMPLETE ".date(DATE_RFC822)."\n";
?>
