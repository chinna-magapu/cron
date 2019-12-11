<?php
/* --------------------------------------
 *
 *	THIS IS CURRENT  AS OF 12-19-2017
 *
 *	TO USE EDIT select on lines 56-58
 *
*/
require_once("weatherfunctions.php");
require "database_inc.php";
OpenMySQLi();
date_default_timezone_set("America/New_York");

function sctest($value, $scale=1.0){
	if ($value == 'T'){
		return 0.001;   //trace precip
	}
    if ($value== -999 || $value== -9999 ) {
        return "NULL";
    } else {
        return $value * $scale;
    }
}

function _mysqli_fetch_all($result) {
    $all = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $all[] = $row;
    }
    return $all;
    mysqli_free_result($result);
}

if ($argc < 2) {
	echo "Usage:  wu-backfill-hist start-date days ";
	exit;
}
$sdate  = $argv[1];
$gdays  = $argv[2];
$usepws = isset($argv[3]) ? $argv[3] : 0;
$gdays  = is_numeric($gdays) ? ($gdays < 400 ? $gdays : 400) : 1;
echo "\nArgs: from ".$sdate." for {$gdays} days.";
echo "\nWeather Underground JSON History Fetch ".date(DATE_RFC822)."\n";

// general fill:
$sql = "SELECT idsite, code, type, name, lat, lon FROM site WHERE lat IS NOT NULL;";
// one or more sites
$sql = "SELECT idsite, code, type, name, lat, lon FROM site WHERE idsite IN (414, 413, 412) AND lat IS NOT NULL;";

$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);
if (!count($tracks)) {
	echo "\nNo tracks with id ".$idsite;
	die;
}
//print_r($tracks); die;
//http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'
//aqueduct http://api.wunderground.com/api/828bcd358ed824c8/history_20160214/q/40.677661,-73.828981.json

$days = 0;
$getdate  = date("Ymd",strtotime($sdate." + {$days} DAY"));
$lastdate = date("Ymd",strtotime($sdate." + {$gdays} DAY" ));

echo "\nFrom ".$getdate." to ".$lastdate;
for($days = 0; $days < $gdays ; $days++){
	foreach ($tracks as $row) {
		if ($days > 400){
			echo "\n",$days," is over 400. Should not happen\n";
			die;
		}
		$getdate  = date("Ymd",strtotime($sdate." + {$days} DAY"));
		if ($usepws == 1) {
			$urlstem   = "http://api.wunderground.com/api/828bcd358ed824c8/history_".$getdate."/q/";  //mick's
		} else {
			$urlstem   = "http://api.wunderground.com/api/0b771401b22d9d12/history_".$getdate."/q/";  //powerwise systems
			$urlstem   = "http://api.wunderground.com/api/211e66dff0f0fe93/history_".$getdate."/q/";  //cm dev key
		}
	    $url = $urlstem.$row['lat'].",".$row['lon'].".json";
		//http://api.wunderground.com/api/0b771401b22d9d12/history_20150719/q/49.285789,-123.044049.json
	    echo "\n",$url,"\n";
	    $json = file_get_contents($url);
	    $data = json_decode($json);
		//print_r($data); die;
		$summary = $data->history->dailysummary[0];
		//echo "\nSUMMARY\n",print_r($summary,true),"\n";
	    echo "\n",$url;
	    echo " Date: ",$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday,"\n";
		//daily summary
		$sumsql = "REPLACE INTO weathersummary(idsite, date, src, precip, meantemp, mintemp, maxtemp, minhumidity, maxhumidity,
			meanpressure, minpressure, maxpressure, meandewpt, mindewpt, maxdewpt, meanwspeed, minwspeed, maxwspeed,
			growdegreedays, cooldegreedays, heatdegreedays, rain, snow, fog, thunder, tornado, hail)
			VALUES ({$row['idsite']},'".$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday;
		$sumsql .= "','WU',{$summary->precipi},{$summary->meantempi},{$summary->mintempi},{$summary->maxtempi},{$summary->minhumidity},{$summary->maxhumidity},
			{$summary->meanpressurei},{$summary->minpressurei},{$summary->maxpressurei},{$summary->meandewpti},{$summary->mindewpti},{$summary->maxdewpti},
			{$summary->meanwindspdi},{$summary->minwspdi},{$summary->maxwspdi},
			{$summary->gdegreedays},{$summary->coolingdegreedays},{$summary->heatingdegreedays},
			{$summary->rain},{$summary->snow},{$summary->fog},{$summary->thunder},{$summary->tornado},{$summary->hail})";
		echo "\n{$sumsql}\n";
		$ok =  mysqli_query($mysqli,$sumsql);
	    sleep(8);
	}
}

echo "\nWeather Underground scrape completed at ".date(DATE_RFC822)."\n";
?>
