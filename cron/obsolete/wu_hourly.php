<?php
// 15 minute data for all Weather Underground tracks, by lat/long

// current license allows 100 calls/min 5000/day
require_once("weatherfunctions.php");
require "database_inc.php";
OpenMySQLi();
date_default_timezone_set("America/New_York");

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

function check_weather($runts){
	global $tracks, $mysqli;

	foreach($tracks as $track){
		$url   = "http://api.wunderground.com/api/828bcd358ed824c8/conditions/q/".$track['lat'].','.$track['lon'].'.json';
		$sitenow = site_now($track['timezone']);
		date_default_timezone_set($track['timezone']);
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
	        $sql = "REPLACE INTO weather(src,idsite, wsid, timestamp, runtime, tout, hum,
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
		        //echo "\nSQL: {$sql}\n";
	        $ok = mysqli_query($mysqli,$sql);
	        echo "\nINSERT? ".($ok ? "Y " : "N ").$track['idsite']." ".$ts;
	        if (!$ok) echo "ERROR: ".mysqli_error($mysqli);
		}
		sleep(1);  // 1 sec sleep max 60 / minute
	} // foreach
}


$runts = Date('Y-m-d H:i:00');
echo "BAE Test RW Hourly ".date(DATE_RFC822)."\n";
$hostname = php_uname("n");
$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('wu_hourly','Rainstorm WU Hourly Disabled',{$hostname})";
$ok = mysqli_query($mysqli,$sql);
// May 2013 disabled because we're doing 15-minute runs
//$sql = "SELECT idsite, type, name, timezone, lat, lon FROM site WHERE wxsrc='WU';";
//$result = mysqli_query($mysqli,$sql);
//$tracks = _mysqli_fetch_all($result);
//check_weather($runts);

?>
