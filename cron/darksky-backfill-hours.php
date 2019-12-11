<?php

/*
2019-02-12T17:15:00-0500
012345678901234567890123
*/

require_once("weatherfunctions.php");
require "DB.class.php";
$db = new DB();

function site_now($tzone){
    $tzone = $tzone == "" ? "GMT" : $tzone;
    $tz = new DateTimeZone($tzone);
    $tUnixTime = time();
    $sGMTString = gmdate("Y-m-d H:i:00", $tUnixTime);
    date_default_timezone_set('GMT');
    $dt = new DateTime($sGMTString);
    $dt->setTimezone($tz);
    return $dt->format(DATE_ISO8601);
}
function tolocalTime($timeval, $timezone) {
    $tz = new DateTimeZone($tzone);
    $sGMTString = gmdate("Y-m-d H:i:00", $timeval);
    date_default_timezone_set('GMT');
    $dt = new DateTime($sGMTString);
    $dt->setTimezone($tz);
    return $dt->format('Y-m-d H:i:s');

}

function prev15($ts) {
	// we always want previous because we want observations not a forecast
	$base = substr($ts,0,14);
	$mins = (int)substr($ts,14,2);
	if ($mins < 15) {
		return $base.'00:00';
	} elseif ($mins < 30) {
		return $base.'15:00';
	} elseif ($mins < 45) {
		return $base.'30:00';
	} else {
		return $base.'45:00';
	}
}

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}
function zns($val){
	return empty($val) ? 'NULL' : "'{$val}'";
}

date_default_timezone_set("America/New_York");
echo "Dark Sky JSON History Fetch ".date(DATE_RFC822)."\n";

/*
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538,2019-02-12T17:15:00?exclude=minutely,flags
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/29.983199,-90.08376,2017-09-19T00:00:00?exclude=currently,flags
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538,2017-09-08T00:00:00?exclude=currently,flags
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538,2017-09-08T00:00:00?exclude=currently,flags
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538,2017-09-10T20:00:00
FG
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/29.983199,-90.08376,2017-09-19T00:00:00?exclude=currently,flags
// new Dark Sky key: 129ee67bc4a65644e1fd9b6567669258
*/
/* Time zone offset extremes as of Sep 2017  offset from US Eastern are -3 to +7 (Nicosia)
timezone				GMT	Easterm Offset (standard time)
America/Los_Angeles		-8	-3
America/Vancouver		-8	-3
America/Denver			-7	-2
America/Chicago			-6	-1
America/New_York		-5	0
America/Indianapolis	-5	0
America/Moncton			-4	1
Europe/Nicosia			 2	7
*/
$debug = false;
$sql = "SELECT idsite, code, type, name, lat, lon, timezone FROM site WHERE lat IS NOT NULL AND lon IS NOT NULL";
$tracks = $db->query($sql, false, 'code');
$urlstem = "https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/";
$urlsuffix = "?exclude=minutely&units=us";
//FG 29.983199,-90.083760
$show_precip = false;
foreach($tracks as $code => $row){
	if ($row['lat']=='' || $row['lat']=='' ){
		continue;
	}
	$site_now = site_now($tracks[$code]['timezone']);
	$prev_15  = prev15($site_now);
	$show_precip = substr($prev_15,-2,2) == '00'; // show precip on the hour only
    $url = "{$urlstem}{$row['lat']},{$row['lon']},{$prev_15}{$urlsuffix}";

	//echo "\n{$url}"; continue; die;
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
    @$data = json_decode($json);
	if (empty($data)) {
		continue;
	}
	$flags = $data->flags;
	$test = isset($flags->{'darksky-unavailable'});

	if ($test) {
		$sql = "INSERT INTO darksky_unavailable(code, timestamp) VALUES ('{$code}','{$prev_15}');";
		continue;
	}
	$nearest = isset($flags->{'nearest-station'}) ? $flags->{'nearest-station'} : NULL;
	$cur_time = $lst_time = false;
	$hours = 0;
	$total_precip = $hour_precip = 0;
	$cur_15 = $data->currently;
	if ($show_precip) {
		foreach ($data->hourly->data as $hour) {
			// all we want is precip, and ignore forecast hours
			$lst_time = $cur_time;
			$cur_time = $hour->time;
			if ($cur_time > $cur_15->time) {
				break;
			}
			$hours++;
			if (isset($hour->precipIntensity)) {
				if ($lst_time !== false) {
					$total_precip += $hour->precipIntensity * 3600 / ($cur_time - $lst_time);
					if ($cur_15->time == $cur_time) {
						$hour_precip += $hour->precipIntensity;
					}
				} else {
					// assume one hour
					$total_precip = $hour->precipIntensity;
				}
			}
		}
	}
    //echo "\n",$url;
	$daily  = $data->daily->data[0];
	$cur_15 = $data->currently;
	if ($debug) print_r($cur_15);
	if (!empty($cur_15)) {
		$summary		= isset($cur_15->summary) ? $cur_15->summary : NULL;
		$icon			= isset($cur_15->icon) ? $cur_15->icon : NULL;
		$temperature	= isset($cur_15->temperature) ? $cur_15->temperature : NULL;
		$apparenttemp	= isset($cur_15->apparentTemperature) ? $cur_15->apparentTemperature : NULL;
		$dewpoint		= isset($cur_15->dewPoint) ?$cur_15->dewPoint : NULL;
		$humidity		= isset($cur_15->humidity) ? $cur_15->humidity * 100 : NULL;
		$pressure		= isset($cur_15->pressure) ? mbToHg($cur_15->pressure) : NULL;
		$windspeed		= isset($cur_15->windSpeed) ? $cur_15->windSpeed : NULL;
		$windgust		= isset($cur_15->windGust) ? $cur_15->windGust : NULL;
		$windbearing	= isset($cur_15->windBearing) ? $cur_15->windBearing : NULL;
		$cloudcover		= isset($cur_15->cloudCover) ? $cur_15->cloudCover : NULL;
		$uvIndex		= isset($cur_15->uvIndex) ? $cur_15->uvIndex : NULL;
		$visibility		= isset($cur_15->visibility) ? $cur_15->visibility : NULL;
		$ozone			= isset($cur_15->ozone) ? $cur_15->ozone : NULL;
		$precipType		= isset($cur_15->precipType) ? $cur_15->precipType : NULL;
		$precipIntensity= isset($cur_15->precipIntensity) ? $cur_15->precipIntensity : NULL;
		$sql = "REPLACE INTO weather_ds (idsite, code, timestamp, src, precip, 	cum_precip, precipType, "
			."temp,  apparenttemp, humidity, pressure, dewpoint, windspeed, windgust, windbearing, \n"
			."cloudcover, visibility, ozone, uvIndex, icon, summary, nearest_station) \n"
			."VALUES ({$row['idsite']},'{$row['code']}','{$prev_15}','DS',".zn($hour_precip).",".zn($total_precip).",".zns($precipType).",\n"
			.zn($temperature).",".zn($apparenttemp).",".zn($humidity).",".zn($pressure).",".zn($dewpoint).",".zn($windspeed).",".zn($windgust).",".zn($windbearing).",\n"
			.zn($cloudcover).",".zn($visibility).",".zn($ozone).",".zn($uvIndex).",".zns($icon).",".zns($summary).",".zn($nearest).");\n";
		$ok =  $db->exec($sql);
		//echo "\nOK{$ok}:\n{$sql}\n";
		if ($db->error > '') {
			echo "\nError:".$db->error;
		}
		$wxsql = "\nINSERT IGNORE INTO weather(idsite, timestamp, tout, hum, baro, wspd, wdir, gust, rf,\n"
			."uv, cumrf, dewpoint, heatindex, windchill, observation_ts, src)\n"
			."SELECT idsite, timestamp, ROUND(temp,0), humidity, ROUND(pressure * 100,0), windspeed * 10, windbearing, windgust * 10, \n"
			."precip * 100, uvIndex, cum_precip * 100, dewpoint, fn_heatindex(temp, humidity,1), fn_windchill(temp,windspeed,1), timestamp, 'DS'\n"
			."FROM weather_ds WHERE idsite={$row['idsite']} AND timestamp='{$prev_15}';";
		$ok =  $db->exec($wxsql);
		//echo "\nOK{$ok}:\n{$wxsql}\n";
		if ($db->error > '') {
			echo "\nError:".$db->error;
		}
	}
}

$hostname = php_uname("n");
//$sql = "INSERT INTO alertcheck (script, timestamp, comment,machine) VALUES ('ds_yesterday','".date('Y-m-d H:i:s')."','WU YESTERDAY Complete',{$hostname})";
//$ok = mysqli_query($mysqli,$sql);
echo "\nDark Sky scrape completed at ".date(DATE_RFC822)."\n";
?>
