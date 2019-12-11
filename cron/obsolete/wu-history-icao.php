<?php

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

if ($argc < 4) {
	echo "Usage:  wu-gethistory idsite start-date days ";
	exit;
}
$idsite = $argv[1];
$sdate  = $argv[2];
$gdays  = $argv[3];
$usepws = isset($argv[4]) ? $argv[4] : 0;
$gdays  = is_numeric($gdays) ? ($gdays < 400 ? $gdays : 400) : 1;
echo "\nArgs: Site ".$idsite." from ".$sdate." for {$gdays} days.";
echo "\nWeather Underground JSON History Fetch ".date(DATE_RFC822)."\n";

$sql = "SELECT idsite, type, name, icao1 FROM site WHERE idsite={$idsite};";
$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);
if (!count($tracks)) {
	echo "\nNo tracks with id ".$idsite;
	die;
}
$row    = $tracks[0];
if ($row['icao1'] ==""){

}


/* AccountName		API Key
Powerwise General	0b771401b22d9d12
RSTL				828bcd358ed824c8
Intellergy SHW		4e30586d503f4d40
Hancock Place Dash	a582e90f4e3c7a51
Rainwise			e6d1dd5c9898131f
Rainwise Dev		b1bee45b3d7e38f1
Intellergy WSU		1b8427e0a1a1ae61
Personal-CM-For BAE	211e66dff0f0fe93
*/



$getdate = "";
$keyidx = 1;
switch ($keyidx){
	case 0:
		$urlstem   = "http://api.wunderground.com/api/211e66dff0f0fe93/history_".$getdate."/q/";  //cm for bae
		break;
	case 1:
		$urlstem   = "http://api.wunderground.com/api/0b771401b22d9d12/history_".$getdate."/q/";  //powerwise systems */
		break;
	case 2:
		$urlstem   = "http://api.wunderground.com/api/828bcd358ed824c8/history_".$getdate."/q/";  //bae
		break;
	case 3:
		$urlstem   = "http://api.wunderground.com/api/1b8427e0a1a1ae61/history_".$getdate."/q/";  //wsu
		break;
	case 4:
		$urlstem   = "http://api.wunderground.com/api/4e30586d503f4d40/history_".$getdate."/q/";  //intellergy shw
		$urlstem   = "http://api.wunderground.com/api/e1d1ac2498b91318/history_".$getdate."/q/";  //intellergy shw
		break;
	case 5:
		$urlstem   = "http://api.wunderground.com/api/a582e90f4e3c7a51/history_".$getdate."/q/";  //hancock
		break;
}

//http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'
$days = 0;
$getdate  = date("Ymd",strtotime($sdate." + {$days} DAY"));
$lastdate = date("Ymd",strtotime($sdate." + {$gdays} DAY" ));

echo "\nFrom ".$getdate." to ".$lastdate;
echo "\nFor {$idsite} Using {$keyidx}: {$urlstem}";
for($days = 0; $days < $gdays ; $days++){
//for($days = 0; $getdate < $lastdate; $days++){
	if ($days > 400){
		echo "\n",$days," is over 400. Should not happen\n";
		die;
	}
   	if ($row['icao1']==''){
		echo "\nicao1 is null";
		die;
	}
	$getdate  = date("Ymd",strtotime($sdate." + {$days} DAY"));
	if ($getdate >= date("Ymd")){
		echo "\n{$getdate}: Can't get today or future weather";
		die;
	}
	switch ($keyidx){
		case 0:
			$urlstem   = "http://api.wunderground.com/api/211e66dff0f0fe93/history_".$getdate."/q/";  //cm for bae
			break;
		case 1:
			$urlstem   = "http://api.wunderground.com/api/0b771401b22d9d12/history_".$getdate."/q/";  //powerwise systems */
			break;
		case 2:
			$urlstem   = "http://api.wunderground.com/api/828bcd358ed824c8/history_".$getdate."/q/";  //bae
			break;
		case 3:
			$urlstem   = "http://api.wunderground.com/api/1b8427e0a1a1ae61/history_".$getdate."/q/";  //wsu
			break;
		case 4:
			$urlstem   = "http://api.wunderground.com/api/e1d1ac2498b91318/history_".$getdate."/q/";  //intellergy shw
			break;
		case 5:
			$urlstem   = "http://api.wunderground.com/api/a582e90f4e3c7a51/history_".$getdate."/q/";  //hancock
			break;
	}
    $url = $urlstem.$row['icao1'].".json";
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
    $data = json_decode($json);
	//echo $url,"\n";
	//print_r($data);
	//die;
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
		$e = $mysqli->error;
		if ($e > ""){
			echo "\n{$getdate} =>OK {$ok}: E:",$mysqli->error;
			echo "\nSQL {$sumsql}";

		} else {
			echo "\n{$getdate} =>OK {$ok}: E:",$mysqli->error;
		}
	}
	//echo "\n{$ok}: {$sumsql}\n";
	//echo "\nError:".$mysqli->error;
    sleep(8);
}

echo "\nWeather Underground scrape completed at ".date(DATE_RFC822)."\n";
echo "\nFor {$idsite} Using {$keyidx}: {$urlstem}";
?>
