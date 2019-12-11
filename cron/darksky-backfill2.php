<?php


require_once("weatherfunctions.php");
require "DB.class.php";
$db = new DB;

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}
function zns($val){
	return empty($val) ? 'NULL' : "'{$val}'";
}

$sql = "SELECT idsite, code, type, name, lat, lon,  icao1, icao2 FROM site WHERE (lat IS NOT NULL AND lon IS NOT NULL) OR icao1 IS NOT NULL";
$result = mysqli_query($mysqli,$sql);
$tracks = _mysqli_fetch_all($result);
/*
http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'
seameadow dev key from wunderground 211e66dff0f0fe93

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

 so calls made between 3:01AM and 4:59 PM standard time using Eastern 'yesterday' will work
 so calls made between 4:01AM and 5:59 PM DST using Eastern 'yesterday' will work      */

$debug = false;
$yesterday = date("Y-m-d",strtotime("yesterday"))."T00:00:00";
$urlstem   = "https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/";
$urlsuffix = ','.$yesterday."?exclude=currently,flags";
//FG 29.983199,-90.083760
foreach($tracks as $row){
	if ($row['lat']=='' || $row['lat']=='' ){
		continue;
	}
    $url = $urlstem.$row['lat'].",".$row['lon'].$urlsuffix;
	//echo "\n{$url}\n"; die;
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
    @$data = json_decode($json);
	if (empty($data)) {
		continue;
	}
	// collect mins and maxes from the hourly data (which may not be hourly)
		$cur_time = $lst_time = false;
	$hours = 0;
		$total_precip = -1;
	$min_hum = 101.1; $max_hum = -1.1; $min_dew = 1000.1; $max_dew = -1000.1;
	$min_bar = 11101.1; $max_bar = -1.1; $min_wsp = 1000.1; $max_wsp = -1000.1;
	$avg_tmp = $avg_app = "";
	foreach ($data->hourly->data as $hour) {
		$hours++;
		$lst_time = $cur_time;
		$cur_time = $hour->time;
			if (isset($hour->precipIntensity)) {
				if ($lst_time !== false) {
					$total_precip += $hour->precipIntensity * 3600 / ($cur_time - $lst_time);
		} else {
			// assume one hour
			$total_precip = $hour->precipIntensity;
		}
		}
		// because an observation may be missing, skip errors - just doing max-min
		$avg_tmp += $hour->temperature;
		$avg_app += $hour->apparentTemperature;

		$h_humidity  = isset($hour->humidity)  ? $hour->humidity  : NULL;
		$h_pressure  = isset($hour->pressure)  ? $hour->pressure  : NULL;
		$h_dewPoint  = isset($hour->dewPoint)  ? $hour->dewPoint  : NULL;
		$h_windSpeed = isset($hour->windSpeed) ? $hour->windSpeed : NULL;
		if (isset($hour->windSpeed)) {
			if (!empty($h_humidity) && $h_humidity < $min_hum) $min_hum = $h_humidity;
			if (!empty($h_humidity) && $h_humidity > $max_hum) $max_hum = $h_humidity;
			if (!empty($h_pressure) && $h_pressure < $min_bar) $min_bar = $h_pressure;
			if (!empty($h_pressure) && $h_pressure > $max_bar) $max_bar = $h_pressure;
			if (!empty($h_dewPoint) && $h_dewPoint < $min_dew) $min_dew = $h_dewPoint;
			if (!empty($h_dewPoint) && $h_dewPoint > $max_dew) $max_dew = $h_dewPoint;
			if (!empty($h_windSpeed) && $h_windSpeed < $min_wsp) $min_wsp = $h_windSpeed;
			if (!empty($h_windSpeed) && $h_windSpeed > $max_wsp) $max_wsp = $h_windSpeed;
			//echo "Hourly at ",Date("Y-m-d H:i",$hour->time), "precipIntensity: ",$hour->precipIntensity,"\n";
		}
	}
	$min_hum = $min_hum >=   101 ? NULL : $min_hum * 100;
	$max_hum = $max_hum <=    -1 ? NULL : $max_hum * 100;
	$min_dew = $min_dew >=  1000 ? NULL : $min_dew;
	$max_dew = $max_dew <= -1000 ? NULL : $max_dew;
	$min_bar = $min_bar >= 11101 ? NULL : mbToHg($min_bar);
	$max_bar = $max_bar <=    -1 ? NULL : mbToHg($max_bar);
	$min_wsp = $min_wsp >=  1000 ? NULL : $min_wsp;
	$max_wsp = $max_wsp <= -1000 ? NULL : $max_wsp;
	$avg_tmp = $hours > 0 ? $avg_tmp / $hours : NULL;
	$avg_app = $hours > 0 ? $avg_tmp / $hours : NULL;
	/*
	echo "\nTOTAL PRECIP {$total_precip}\n";
	echo "MINMAX hum {$min_hum} {$max_hum} dew  {$min_dew} {$max_dew} \n";
	echo "MINMAX wsp {$min_wsp} {$max_wsp} bat  {$min_bar} {$max_bar} \n";
	echo "DATE ",Date("Y-m-d",$day->time), " hours={$hours} summary ",$day->summary, "\n";
	echo " precInt ",$day->precipIntensity, "TOTAL", $day->precipIntensity * $hours, "\n\n";
		*/
    echo "\n",$url;
	//die;
	$day = $data->daily->data[0];
	if ($debug) print_r($day);
	//daily summary
	$windGust = null;
	$windGustTime = null;
	$ozone = $precipIntensityMaxTime = null;
	if (!empty($day)) {
		$windbearing  = isset($day->windBearing) ? $day->windBearing : NULL;
		$precip       = isset($day->precipIntensity) ? $day->precipIntensity * $hours : 0;
		$summary      = zn($day->summary);
		$mintemp      = zn($day->temperatureMin);
		$maxtemp      = zn($day->temperatureMax);
		$meanpressure = zn(mbToHg($day->pressure));
		$meandewpt    = zn($day->dewPoint);
		$meanwindspd  = zn($day->windSpeed);
		$humidity     = zn($day->humidity * 100);
		$uvIndex      = isset($day->uvIndex) ? $day->uvIndex : NULL;
		$moonPhase    = isset($day->moonPhase) ? $day->moonPhase : NULL;
		$cloudCover   = isset($day->cloudCover) ? $day->cloudCover : NULL;
		$precipType             = isset($day->precipType) ? $day->precipType : NULL;
		$windGust               = isset($day->windGust) ? $day->windGust : NULL;
		$ozone                  = isset($day->ozone) ? $day->ozone : NULL;
		$visibility             = isset($day->visibility) ? $day->visibility : NULL;
		$uvIndexTime            = isset($day->uvIndexTime) ? Date('Y-m-d H:i:00', $day->uvIndexTime) : NULL;
		$windGustTime           = isset($day->windGustTime) ? Date('Y-m-d H:i:00', $day->windGustTime) : NULL;
		$precipIntensity    	= isset($day->precipIntensity) ? $day->precipIntensity : NULL;
		$precipIntensityMax 	= isset($day->precipIntensityMax) ? $day->precipIntensityMax : NULL;
		$precipProbability  	= isset($day->precipProbability) ? $day->precipProbability : NULL;
		$precipIntensityMaxTime = isset($day->precipIntensityMaxTime) ? Date('Y-m-d H:i:00', $day->precipIntensityMaxTime) : NULL;
		$temperatureMinTime     = Date('Y-m-d H:i:00', $day->temperatureMinTime);
		$temperatureMaxTime     = Date('Y-m-d H:i:00', $day->temperatureMaxTime);
		$apparentTemperatureMinTime = Date('Y-m-d H:i:00', $day->apparentTemperatureMinTime);
		$apparentTemperatureMaxTime = Date('Y-m-d H:i:00', $day->apparentTemperatureMaxTime);
		$sumsql = "REPLACE INTO weathersummary_ds (idsite, code, date, precip, "
			."meantemp, mintemp, maxtemp, meanhumidity, minhumidity, maxhumidity, meanpressure, minpressure, maxpressure,\n"
			."meandewpt, mindewpt, maxdewpt, meanwspeed, minwspeed, maxwspeed, apparenttemp, apparenttempmin, apparenttempmax,\n"
			."moonphase, precipIntensity, precipIntensityMax, precipIntensityMaxTime, precipProbablility,\n"
			."precipType, temperatureMaxTime, temperatureMinTime, apparenttempMaxTime, apparenttempMinTime,\n"
			."uvIndexTime, windGust, windGustTime, windbearing, cloudcover, visibility, ozone, uvIndex, icon, summary)\n"
			."VALUES ({$row['idsite']},'{$row['code']}','".Date('Y-m-d',$day->time)."',{$precip},\n"
			."{$avg_tmp},{$mintemp},{$maxtemp},{$humidity},{$min_hum},{$max_hum},".zn($meanpressure).",".zn($min_bar).",".zn($max_bar).",\n"
			."{$meandewpt},{$min_dew},{$max_dew},{$meanwindspd},{$min_wsp},{$max_wsp},{$avg_app},{$day->apparentTemperatureMin},{$day->apparentTemperatureMax},\n"
			.zn($moonPhase).','.zn($precipIntensity).",".zn($precipIntensityMax).",".zns($precipIntensityMaxTime).",".zn($precipProbability).",\n"
			.zns($precipType).",'{$temperatureMaxTime}','{$temperatureMinTime}','{$apparentTemperatureMaxTime}','{$apparentTemperatureMinTime}',\n"
			.zns($uvIndexTime).','.zns($windGust).",".zns($windGustTime).",".zn($windbearing).",".zn($cloudCover).",".zn($visibility).",\n"
			.zn($ozone).','.zn($uvIndex).",'{$day->icon}','{$summary}');\n";

		$ok =  mysqli_query($mysqli,$sumsql);

	}
	echo "\nOK{$ok}:\n{$sumsql}\n";	//die;
	if ($mysqli->error > '') {
		echo "\nError:".$mysqli->error;
		//die;
	}
}


$hostname = php_uname("n");
$sql = "INSERT INTO alertcheck (script, timestamp, comment,machine) VALUES ('ds_yesterday','".date('Y-m-d H:i:s')."','WU YESTERDAY Complete',{$hostname})";
$ok = mysqli_query($mysqli,$sql);
echo "\nDark Sky scrape completed at ".date(DATE_RFC822)."\n";
?>
