<?php
// 15 minute data for all Weather Underground tracks, by lat/long
// current license allows 100 calls/min 5000/day
require_once("weatherfunctions.php");
function xHeatIndex($t,$h){
  //compute heatindex as a function of temperature (F) and relative humidity
  //we expect to get t in as t * 10 (rainwise RAW data) and return hi * 10 (RAW format)
    if ($t >= 80) {
        $hi = -42.379 + 2.04901523 * $t + 10.14333127 * $h - 0.22475541 * $h * $t - 6.83783 * .001 * $t * $t
              - 5.481717 * .01 * $h * $h + 1.22874 * .001 * $t * $t *$h + 8.5282 * .0001 * $t * $h * $h - 1.99 * .000001 * $t * $t * $h * $h;
        $hi = round($hi,1);
    } else {
        $hi = $t;
    }
    return $hi;
}
function xwindChill($t, $v){
    //compute windchill as function of temp and windspeed (simplified)
    //we expect to get t,v in as t, v*10 (rainwise RAW data) and return wc * 10 (RAW format)
	echo "WU C WindChill t={$t} v={$v}\n";
    $v *= .1;
    if ($t <= 50 && $v >= 5){
      return  round((35.74 + (0.6215 * $t) - (35.75 * pow($v,0.16)) + (0.4275 * $t * pow($v,0.16))),1);
    } else {
      return $t;
    }
}

function xdewpoint($t,$h){
	$t= ($t-32)*5/9;	// convert to C
	$H= (log10($h)-2)/0.4343 + (17.62*$t)/(243.12+$t);
	return round(((243.12*$H)/(17.62-$H))*9/5 +32,1);	// back to F
}

function site_now($tzone){
    $tzone = $tzone == "" ? "GMT" : $tzone;
    $tz = new DateTimeZone($tzone);
    $tUnixTime = time();
    $sGMTString = gmdate("Y-m-d H:i:00", $tUnixTime);
    date_default_timezone_set('GMT');
    $dt = new DateTime($sGMTString);
    $dt->setTimezone($tz);
    return $dt->format('Y-m-d H:i:s');
}

function _mysqli_fetch_all($result) {
    $all = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $all[] = $row;
    }
    return $all;
    mysqli_free_result($result);
}

function sctest($value, $scale=1.0, $param = ''){
    if ($value == -999 || $value == -9999 ) {
        return $param != 'rf' ? "NULL" : 0;
    } else {
        return $value * $scale;
    }
}

function check_weather($rundt){
	global $tracks, $mysqli;

	foreach($tracks as $track){
		$url   = "http://api.wunderground.com/api/828bcd358ed824c8/conditions/q/".$track['lat'].','.$track['lon'].'.json';
		$sitenow = site_now($track['timezone']);
		date_default_timezone_set($track['timezone']);
		$tz = new DateTimeZone($track['timezone']);
		$rundt->setTimezone($tz);
		$runts = $rundt->format('Y-m-d H:i:s');
		$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
		$json = file_get_contents($url,false,$context);
	    $data = json_decode($json);
		if ($data){
			echo "\nFetched from {$url}";
		    $ts = Date('Y-m-d H:i:s',strtotime($data->current_observation->observation_time_rfc822));
	        $temp = sctest($data->current_observation->temp_f);
			$humi = $data->current_observation->relative_humidity;
			$humi = str_replace('%','',$humi);
	        $humi = sctest($humi);
	        $wspd = sctest($data->current_observation->wind_mph,10);
	        $baro = sctest($data->current_observation->pressure_in,100);
	        $hi = (sctest($data->current_observation->heat_index_f) != "NULL") ? $data->current_observation->heat_index_f : HeatIndex($temp,$humi);
	        $wc = (sctest($data->current_observation->windchill_f) != "NULL") ? $data->current_observation->windchill_f : windChill($temp, $wspd);
	        $wcx= (sctest($data->current_observation->windchill_f) != "NULL") ? $data->current_observation->windchill_f : xwindChill($temp, $wspd);
			echo "\nwc={$wc} wcx={$wcx}\n\n";die;
	        $sql = "REPLACE INTO weather(src,idsite, wsid, observation_ts, timestamp, tout, hum,
	                baro, wspd, wdir, gust,
	                rf, srad, uv, t1, t2, wsbatt, rssi, txid, mv1, mv2, ver, cumrf,
	                dewpoint, windchill, heatindex) VALUES ('WU','";
	        $rf = sctest($data->current_observation->precip_1hr_in,100,'rf');
	        $cumrf = sctest($data->current_observation->precip_today_in,100,'rf');
			$ok = false;
	        $sql .= $track['idsite']."',NULL,'".$ts."','".$runts."','".$temp."','".$humi."','"
	               .$baro."','".$wspd."','".sctest($data->current_observation->wind_degrees)."','".sctest($data->current_observation->wind_gust_mph,10)."','"
	               .$rf."',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'WU','".$cumrf."','"
	               .sctest($data->current_observation->dewpoint_f)."','".$wc."','".$hi."')";
			echo "\nSQL: {$sql}\n";
	        //$ok = mysqli_query($mysqli,$sql);
	        $ok = true;
	        echo "\nINSERT? ".($ok ? "Y " : "N ").$track['idsite']." ".$ts;
	        if (!$ok) echo "ERROR: ".mysqli_error($mysqli);
		}
		sleep(1);  // 1 sec sleep max 60 / minute
	} // foreach
	$hostname = php_uname("n");
	//$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('wu_current','15-Minute WU Job','{$hostname}')";
	//$ok = mysqli_query($mysqli,$sql);
}


$BA_MYSQL_HOST   = 'bioappeng.com';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';
date_default_timezone_set("America/New_York");
$runts = Date('Y-m-d H:i:00');
$rundt = new DateTime($runts);

echo "BAE 15 Minute Scrape ".date(DATE_RFC822)."\n";
$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
if (mysqli_connect_errno()) {
    echo "Connect failed: ".mysqli_connect_error();
    die;
}
$sql = "SELECT idsite, type, name, timezone, lat, lon FROM site WHERE wxsrc='WU';";
$sql = "SELECT idsite, type, name, timezone, lat, lon FROM site WHERE idsite=100";
$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);
check_weather($rundt);

?>
