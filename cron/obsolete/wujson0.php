<?php

/*	2013-01-28 modified to save summary and METARs only, not hourly observations
    Runs for all WU/RW tracks, but not CHRB
	2014-02-04 Runs for All
*/

function HeatIndex($t,$h){
  //compute heatindex as a function of temperature (F) and relative humidity
  //we expect to get t in as t * 10 (rainwise RAW data) and return hi * 10 (RAW format)
    if ($t == "" || $h == null){
        return null;
    }
    if ($t >= 80) {
        $hi = -42.379 + 2.04901523 * $t + 10.14333127 * $h - 0.22475541 * $h * $t - 6.83783 * .001 * $t * $t
              - 5.481717 * .01 * $h * $h + 1.22874 * .001 * $t * $t *$h + 8.5282 * .0001 * $t * $h * $h - 1.99 * .000001 * $t * $t * $h * $h;
        $hi = round($hi,1);
    } else {
        $hi = $t;
    }
    return $hi;
}
function windChill($t, $v){
    //compute windchill as function of temp and windspeed (simplified)
    if ($t == "" || $v == null){
        return null;
    }
    $v *= .1;  // wspd in this db is x 10
    if ($t <= 50 && $v >= 5){
      return  round((35.74 + (0.6215 * $t) - (35.75 * pow($v,0.16)) + (0.4275 * $t * pow($v,0.16))),1);
    } else {
      return $t;
    }
}

function dewpoint($t,$h){
    if ($t == "" || $h == null){
        return null;
    }
	$t= ($t-32)*5/9;	// convert to C
	$H= (log10($h)-2)/0.4343 + (17.62*$t)/(243.12+$t);
	return round(((243.12*$H)/(17.62-$H))*9/5 +32,1);	// back to F
}

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

$BA_MYSQL_HOST   = 'bioappeng.com';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';
date_default_timezone_set("America/New_York");
echo "Weather Underground JSON History Fetch ".date(DATE_RFC822)."\n";
$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
if (mysqli_connect_errno()) {
    echo "Connect failed: ".mysqli_connect_error();
    die;
}

$sql = "SELECT idsite, type, name, lat, lon,  icao1, icao2 FROM site WHERE (lat IS NOT NULL AND lon IS NOT NULL) OR icao1 IS NOT NULL"; //WHERE (wxsrc='WU' OR wxsrc='RW')";
$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);

//http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'
//new key from wunderground 211e66dff0f0fe93

$yesterday = date("Ymd",strtotime("yesterday"));
$urlstem   = "http://api.wunderground.com/api/828bcd358ed824c8/history_".$yesterday."/q/";

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
    foreach($data->history->observations as $obs){
    	$metar = $obs->metar;
		$stat  = substr($metar,6,4);
        $ts = $obs->date->year."-".$obs->date->mon."-".$obs->date->mday." ".$obs->date->hour.":".$obs->date->min.":00";
		if (substr($metar,0,5) == "METAR"){
	        $temp = sctest($obs->tempi);
	        $humi = sctest($obs->hum);
	        $wspd = sctest($obs->wspdi,10);
	        $baro = sctest($obs->pressurei,100);

	        $hi = (sctest($obs->heatindexi) != "NULL") ? $obs->heatindexi : HeatIndex($temp,$humi);
	        $wc = (sctest($obs->windchilli) != "NULL") ? $obs->windchilli : windChill($temp, $wspd);
	        $sql = "REPLACE INTO weather(idsite, wsid, timestamp, tout, hum,
	                baro, wspd, wdir, gust,
	                rf, srad, uv, t1, t2, wsbatt, rssi, txid, mv1, mv2, ver, cumrf,
	                dewpoint, windchill, heatindex) VALUES ('";
	        $rf = sctest($obs->precipi,100);
	        $cumrf += $rf == null ? 0 : $rf;
	        $sql .= $row['idsite']."',NULL,'".$ts."','".$temp."','".$humi."','"
	               .$baro."','".$wspd."','".sctest($obs->wdird)."','".sctest($obs->wgusti)."','"
	               .$rf."',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'WU','".$cumrf."','"
	               .sctest($obs->dewpti)."','".$wc."','".$hi."')";
	        //echo "\nSQL: {$sql}\n";
	        //$ok = mysqli_query($mysqli,$sql);
	        //echo "\nINSERT? ".($ok ? "Y " : "N ").$row['idsite']." ".$ts;
	        //if (!$ok) echo "ERROR: ".mysqli_error($mysqli);
		}
		$metarsql = "REPLACE INTO metar(ts,station,metar) VALUES('{$ts}','{$stat}','{$metar}')";
        $ok = mysqli_query($mysqli,$metarsql);
    }
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
		$sumsql = "REPLACE INTO weathersummary(idsite, date, src, precip, meantemp, mintemp, maxtemp, minhumidity, maxhumidity,
			meanpressure, minpressure, maxpressure, meandewpt, mindewpt, maxdewpt, meanwspeed, minwspeed, maxwspeed,
			growdegreedays, cooldegreedays, heatdegreedays, rain, snow, fog, thunder, tornado, hail)
			VALUES ({$row['idsite']},'".$data->history->date->year."-".$data->history->date->mon."-".$data->history->date->mday;
		$sumsql .= "','WU',{$precipi},{$meantempi},{$mintempi},{$maxtempi},{$minhumidity},{$maxhumidity},
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
