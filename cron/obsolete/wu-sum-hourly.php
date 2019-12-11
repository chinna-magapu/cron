<?php

/*	2013-01-28 modified to save summary and METARs only, not hourly observations
    Runs for all WU/RW tracks, but not CHRB
	2014-02-04 Runs for All
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

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}

function _mysqli_fetch_all($result) {
    $all = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $all[] = $row;
    }
    return $all;
    mysqli_free_result($result);
}

$api_keys = array('828bcd358ed824c8','e1d1ac2498b91318','a582e90f4e3c7a51','1b8427e0a1a1ae61','211e66dff0f0fe93');
//http://api.wunderground.com/api/828bcd358ed824c8/history_20160304/q/29.173938,-82.217348.json Date: 2016-03-04
//http://api.wunderground.com/api/e1d1ac2498b91318/history_20160304/q/29.173938,-82.217348.json Date: 2016-03-04
//http://api.wunderground.com/api/a582e90f4e3c7a51/history_20160304/q/29.173938,-82.217348.json Date: 2016-03-04
//http://api.wunderground.com/api/1b8427e0a1a1ae61/history_20160304/q/29.173938,-82.217348.json Date: 2016-03-04
//http://api.wunderground.com/api/211e66dff0f0fe93/history_20160304/q/29.173938,-82.217348.json Date: 2016-03-04
$runhr = Date("H");
echo "Weather Underground JSON History TEST ".date(DATE_RFC822)."\n";

$sql = "SELECT idsite, type, name, lat, lon,  icao1, icao2 FROM site WHERE (lat IS NOT NULL AND lon IS NOT NULL) OR icao1 IS NOT NULL"; //WHERE (wxsrc='WU' OR wxsrc='RW')";
$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);

//http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'
//new key from wunderground 211e66dff0f0fe93

$yesterday = date("Ymd",strtotime("yesterday"));
$keyndx = $runhr % 5;
$apikey = $api_keys[$keyndx];
$urlstem   = "http://api.wunderground.com/api/{$apikey}/history_".$yesterday."/q/";

foreach($tracks as $row){
	if ($row['lat']=='' || $row['lat']=='' ){
		continue;
	}
    $url = $urlstem.$row['lat'].",".$row['lon'].".json";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
    $data = json_decode($json);
    echo "\n",$url;
    echo " Date: ",$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday,"\n";
    $cumrf = 0;
	//daily summary
	if (count($data->history->dailysummary) > 0) {
		$summary = $data->history->dailysummary[0];
		$precipi      = zn($summary->precipi);
		if ($precipi == 'T') $precipi = 0.001;
		$meantempi    = zn($summary->meantempi);
		$mintempi     = zn($summary->mintempi);
		$maxtempi     = zn($summary->maxtempi);
		$minhumidity  = zn($summary->minhumidity);
		$maxhumidity  = zn($summary->maxhumidity);
		$meanpressurei= zn($summary->meanpressurei);
		$minpressurei = zn($summary->minpressurei);
		$maxpressurei = zn($summary->maxpressurei);
		$meandewpti   = zn($summary->meandewpti);
		$mindewpti    = zn($summary->mindewpti);
		$maxdewpti    = zn($summary->maxdewpti);
		$meanwindspdi = zn($summary->meanwindspdi);
		$minwspdi     = zn($summary->minwspdi);
		$maxwspdi     = zn($summary->maxwspdi);
		$gdegreedays  = zn($summary->gdegreedays);
		$heatingdegreedays = zn($summary->heatingdegreedays);
		$coolingdegreedays = zn($summary->coolingdegreedays);
		$rain   = zn($summary->rain);
		$snow   = zn($summary->snow);
		$hail   = zn($summary->hail);
		$fog    = zn($summary->fog);
		$thunder= zn($summary->thunder);
		$tornado= zn($summary->tornado);
		$sumsql = "REPLACE INTO wxsummarytest(idsite, date, hr, src, precip, meantemp, mintemp, maxtemp, minhumidity, maxhumidity,
			meanpressure, minpressure, maxpressure, meandewpt, mindewpt, maxdewpt, meanwspeed, minwspeed, maxwspeed,
			growdegreedays, cooldegreedays, heatdegreedays, rain, snow, fog, thunder, tornado, hail)
			VALUES ({$row['idsite']},'".$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday;
		$sumsql .= "',{$runhr},'WU',{$precipi},{$meantempi},{$mintempi},{$maxtempi},{$minhumidity},{$maxhumidity},
			{$meanpressurei},{$minpressurei},{$maxpressurei},{$meandewpti},{$mindewpti},{$maxdewpti},
			{$meanwindspdi},{$minwspdi},{$maxwspdi},
			{$gdegreedays},{$coolingdegreedays},{$heatingdegreedays},
			{$rain},{$snow},{$fog},{$thunder},{$tornado},{$hail})";
		$ok =  mysqli_query($mysqli,$sumsql);

	}
	echo "\n{$ok}: {$sumsql}\n";
	echo "\nError:".$mysqli->error;
    sleep(9);
}

//now get the icao data
foreach($tracks as $row){
	if ($row['icao1']==''){
		continue;
	}
    $url = $urlstem.$row['icao1'].".json";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
    $data = json_decode($json);
    echo "\nICAO q",$url;
    //echo " Date: ",$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday,"\n";
	// skip the metars; we just want summary
	//daily summary
	if (count($data->history->dailysummary) > 0) {
		$summary = $data->history->dailysummary[0];
		$precipi      = zn($summary->precipi);
		$meantempi    = zn($summary->meantempi);
		$mintempi     = zn($summary->mintempi);
		$maxtempi     = zn($summary->maxtempi);
		$minhumidity  = zn($summary->minhumidity);
		$maxhumidity  = zn($summary->maxhumidity);
		$meanpressurei= zn($summary->meanpressurei);
		$minpressurei = zn($summary->minpressurei);
		$maxpressurei = zn($summary->maxpressurei);
		$meandewpti   = zn($summary->meandewpti);
		$mindewpti    = zn($summary->mindewpti);
		$maxdewpti    = zn($summary->maxdewpti);
		$meanwindspdi = zn($summary->meanwindspdi);
		$minwspdi     = zn($summary->minwspdi);
		$maxwspdi     = zn($summary->maxwspdi);
		$gdegreedays  = zn($summary->gdegreedays);
		$heatingdegreedays = zn($summary->heatingdegreedays);
		$coolingdegreedays = zn($summary->coolingdegreedays);
		$rain   = zn($summary->rain);
		$snow   = zn($summary->snow);
		$hail   = zn($summary->hail);
		$fog    = zn($summary->fog);
		$thunder= zn($summary->thunder);
		$tornado= zn($summary->tornado);
		$sumsql = "REPLACE INTO weathersummaryicao(idsite, date, icao, src, precip, meantemp, mintemp, maxtemp, minhumidity, maxhumidity,
			meanpressure, minpressure, maxpressure, meandewpt, mindewpt, maxdewpt, meanwspeed, minwspeed, maxwspeed,
			growdegreedays, cooldegreedays, heatdegreedays, rain, snow, fog, thunder, tornado, hail)
			VALUES ({$row['idsite']},'".$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday;
		$sumsql .= "','{$row['icao1']}','WU',{$precipi},{$meantempi},{$mintempi},{$maxtempi},{$minhumidity},{$maxhumidity},
			{$meanpressurei},{$minpressurei},{$maxpressurei},{$meandewpti},{$mindewpti},{$maxdewpti},
			{$meanwindspdi},{$minwspdi},{$maxwspdi},
			{$gdegreedays},{$coolingdegreedays},{$heatingdegreedays},
			{$rain},{$snow},{$fog},{$thunder},{$tornado},{$hail})";
		$ok =  mysqli_query($mysqli,$sumsql);
	}
	//echo "\n{$ok}: {$sumsql}\n";
	//echo "\nError:".$mysqli->error;
    sleep(15);
}

//now get the icao2 data
foreach($tracks as $row){
	if ($row['icao2']==''){
		continue;
	}
    $url = $urlstem.$row['icao2'].".json";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
    $data = json_decode($json);
    echo "\nICAO q",$url;
    //echo " Date: ",$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday,"\n";
	// skip the metars; we just want summary
	//daily summary
	if (count($data->history->dailysummary) > 0) {
		$summary = $data->history->dailysummary[0];
		$precipi      = zn($summary->precipi);
		$meantempi    = zn($summary->meantempi);
		$mintempi     = zn($summary->mintempi);
		$maxtempi     = zn($summary->maxtempi);
		$minhumidity  = zn($summary->minhumidity);
		$maxhumidity  = zn($summary->maxhumidity);
		$meanpressurei= zn($summary->meanpressurei);
		$minpressurei = zn($summary->minpressurei);
		$maxpressurei = zn($summary->maxpressurei);
		$meandewpti   = zn($summary->meandewpti);
		$mindewpti    = zn($summary->mindewpti);
		$maxdewpti    = zn($summary->maxdewpti);
		$meanwindspdi = zn($summary->meanwindspdi);
		$minwspdi     = zn($summary->minwspdi);
		$maxwspdi     = zn($summary->maxwspdi);
		$gdegreedays  = zn($summary->gdegreedays);
		$heatingdegreedays = zn($summary->heatingdegreedays);
		$coolingdegreedays = zn($summary->coolingdegreedays);
		$rain   = zn($summary->rain);
		$snow   = zn($summary->snow);
		$hail   = zn($summary->hail);
		$fog    = zn($summary->fog);
		$thunder= zn($summary->thunder);
		$tornado= zn($summary->tornado);
		$sumsql = "REPLACE INTO weathersummaryicao(idsite, date, icao, src, precip, meantemp, mintemp, maxtemp, minhumidity, maxhumidity,
			meanpressure, minpressure, maxpressure, meandewpt, mindewpt, maxdewpt, meanwspeed, minwspeed, maxwspeed,
			growdegreedays, cooldegreedays, heatdegreedays, rain, snow, fog, thunder, tornado, hail)
			VALUES ({$row['idsite']},'".$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday;
		$sumsql .= "','{$row['icao2']}','WU',{$precipi},{$meantempi},{$mintempi},{$maxtempi},{$minhumidity},{$maxhumidity},
			{$meanpressurei},{$minpressurei},{$maxpressurei},{$meandewpti},{$mindewpti},{$maxdewpti},
			{$meanwindspdi},{$minwspdi},{$maxwspdi},
			{$gdegreedays},{$coolingdegreedays},{$heatingdegreedays},
			{$rain},{$snow},{$fog},{$thunder},{$tornado},{$hail})";
		$ok =  mysqli_query($mysqli,$sumsql);
	}
	//echo "\n{$ok}: {$sumsql}\n";
    sleep(15);
}

$sql = "INSERT INTO alertcheck (script, timestamp, comment) VALUES ('wu_json','".date('Y-m-d H:i:s')."','WU JSON Complete')";
$ok = mysqli_query($mysqli,$sql);
echo "\nWeather Underground scrape completed at ".date(DATE_RFC822)."\n";
?>
