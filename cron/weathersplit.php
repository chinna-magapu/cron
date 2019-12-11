<?php
$hdrlines = Array('"TOA5","70127","CR1000","70127","CR1000.Std.28","CPU:BelmontFTP.CR1","23368","Table1"',
'"TIMESTAMP","RECORD","BP_mmHg_Avg","Rain_mm_Tot","AirTC_Avg","RH","SlrkW_Avg","SlrMJ_Tot","WS_ms_Avg","WindDir","VWC_Avg","EC_Avg","T_Avg","Gflux_Avg","T_Top_Avg","T_Bot_Avg","Lightning_Tot"',
'"TS","RN","mmHg","mm","Deg C","%","kW/m^2","MJ/m^2","meters/second","degrees","m^3/m^3","dS/m","Deg C","W/m^2","Deg C","Deg C","Danger_level"',
'"","","Avg","Tot","Avg","Smp","Avg","Tot","Avg","Smp","Avg","Avg","Avg","Avg","Avg","Avg","Tot"');
/*
"2016-11-17 11:57:00"
*/

$fn   = "F:\\work\\bae\\weather\\belmont-odd\\BELMONT1_20170312_144640_WS.dat";
$path = "F:\\work\\bae\\weather\\belmont-odd\\";
$file = fopen($fn, 'r');
$lastdt = "";
$outfn = "";
$outfile = "";
$lc = 0;
while (($line = fgets($file)) !== false) {
	if ($lc++ < 5) continue;
	$dt = substr($line,1,10);
	if ($dt != $lastdt) {
		if ($outfn != "") {
			fclose($outfile);
		}
		$lastdt = $dt;
		echo "OUTFILE: {$outfn}\r\n";
		$outfn = $path.$dt.'_WS.dat';
		$outfile = fopen($outfn,'w');
		foreach ($hdrlines as $hline) {
			fwrite($outfile,$hline."\r\n");
		}
	}
	fwrite($outfile,$line);
}
fclose($file);
fclose($outfile);
?>