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

function _mysqli_fetch_all($result) {
    $all = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $all[] = $row;
    }
    return $all;
    mysqli_free_result($result);
}

if ($argc < 3) {
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

$sql = "SELECT idsite, type, name, lat, lon FROM site WHERE idsite={$idsite};";
//echo $sql;
$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);
if (!count($tracks)) {
	echo "\nNo tracks with id ".$idsite;
	die;
}
$row    = $tracks[0];
//http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'
//aqueduct http://api.wunderground.com/api/828bcd358ed824c8/history_20160214/q/40.677661,-73.828981.json

$days = 0;
$getdate  = date("Ymd",strtotime($sdate." + {$days} DAY"));
$lastdate = date("Ymd",strtotime($sdate." + {$gdays} DAY" ));

echo "\nFrom ".$getdate." to ".$lastdate;
for($days = 0; $days < $gdays ; $days++){
//for($days = 0; $getdate < $lastdate; $days++){
	if ($days > 400){
		echo "\n",$days," is over 400. Should not happen\n";
		die;
	}
	$getdate  = date("Ymd",strtotime($sdate." + {$days} DAY"));
	if ($usepws != 1)
		$urlstem   = "http://api.wunderground.com/api/828bcd358ed824c8/history_".$getdate."/q/";  //mick's
    else
		$urlstem   = "http://api.wunderground.com/api/0b771401b22d9d12/history_".$getdate."/q/";  //powerwise systems
	$urlstem   = "http://api.wunderground.com/api/0b771401b22d9d12/history_".$getdate."/q/";  //powerwise systems
	/*if ($row['q'] != ""){
	    $url = $urlstem.$row['q'].".json";
	}  else {
	} */
    $url = $urlstem.$row['lat'].",".$row['lon'].".json";
	//http://api.wunderground.com/api/0b771401b22d9d12/history_20150719/q/49.285789,-123.044049.json
    echo "\n",$url,"\n";
    $json = file_get_contents($url);
    $data = json_decode($json);
	//print_r($data);
	$summary = $data->history->dailysummary[0];
	//echo "\nSUMMARY\n",print_r($summary,true),"\n";
    echo "\n",$url;
    echo " Date: ",$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday,"\n";
    $cumrf = 0;
    foreach($data->history->observations as $obs){
    	$metar = $obs->metar;
		$stat  = substr($metar,6,4);
	    $ts = $obs->date->year."-".$obs->date->mon."-".$obs->date->mday." ".$obs->date->hour.":".$obs->date->min.":00";
		if (substr($metar,0,5) == "METAR"){
			// intermediate observations start with SPECI"
	        $temp = sctest($obs->tempi);
	        $humi = sctest($obs->hum);
	        $wspd = sctest($obs->wspdi,10);
	        $baro = sctest($obs->pressurei,100);

	        $hi = (sctest($obs->heatindexi) != "NULL") ? $obs->heatindexi : HeatIndex($temp,$humi);
	        $wc = (sctest($obs->windchilli) != "NULL") ? $obs->windchilli : windChill($temp, $wspd);
	        $sql = "INSERT INTO weather(src, idsite, wsid, timestamp, tout, hum,
	                baro, wspd, wdir, gust,
	                rf, srad, uv, t1, t2, wsbatt, rssi, txid, mv1, mv2, ver, cumrf,
	                dewpoint, windchill, heatindex) VALUES ('WU','";
	        $rf = sctest($obs->precipi,100);
	        $cumrf += $rf == null ? 0 : $rf;
			$ok = false;
	        $sql .= $row['idsite']."',NULL,'".$ts."','".$temp."','".$humi."','"
	               .$baro."','".$wspd."','".sctest($obs->wdird)."','".sctest($obs->wgusti)."','"
	               .$rf."',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'WU','".$cumrf."','"
	               .sctest($obs->dewpti)."','".$wc."','".$hi."')";
	        //echo "\nSQL: {$sql}\n";
	        $ok = mysqli_query($mysqli,$sql);
	        echo "\nINSERT? ".($ok ? "Y " : "N ").$row['idsite']." ".$ts;
	        if (!$ok) echo "ERROR: ".mysqli_error($mysqli);
		}
		$metarsql = "REPLACE INTO metar(ts,station,metar) VALUES('{$ts}','{$stat}','{$metar}')";
        $ok = mysqli_query($mysqli,$metarsql);
    }
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
    sleep(15);
}

echo "\nWeather Underground scrape completed at ".date(DATE_RFC822)."\n";
?>
