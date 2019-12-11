<?php
/*  Runs for all tracks */

require "DB.class.php";
$db = new DB();

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}
function zns($val){
	return empty($val) ? 'NULL' : "'{$val}'";
}

function mbToHg($mb) {
	if (!is_numeric($mb)) {
		return null;
	}
	return $mb /  33.863886666667;
}
function processHour($hour, $idsite, $code, $nearest, $last_cum_precip) {
	global $db, $debug;
	if ($debug) print_r($hour);
	if (!empty($hour)) {
		$timestamp		= Date('Y-m-d H:i:00', $hour->time);
		$summary		= isset($hour->summary) ? $hour->summary : NULL;
		$icon			= isset($hour->icon) ? $hour->icon : NULL;
		$temperature	= isset($hour->temperature) ? $hour->temperature : NULL;
		$apparenttemp	= isset($hour->apparentTemperature) ? $hour->apparentTemperature : NULL;
		$dewpoint		= isset($hour->dewPoint) ?$hour->dewPoint : NULL;
		$humidity		= isset($hour->humidity) ? $hour->humidity * 100 : NULL;
		$pressure		= isset($hour->pressure) ? mbToHg($hour->pressure) : NULL;
		$windspeed		= isset($hour->windSpeed) ? $hour->windSpeed : NULL;
		$windgust		= isset($hour->windGust) ? $hour->windGust : NULL;
		$windbearing	= isset($hour->windBearing) ? $hour->windBearing : NULL;
		$cloudcover		= isset($hour->cloudCover) ? $hour->cloudCover : NULL;
		$uvIndex		= isset($hour->uvIndex) ? $hour->uvIndex : NULL;
		$visibility		= isset($hour->visibility) ? $hour->visibility : NULL;
		$ozone			= isset($hour->ozone) ? $hour->ozone : NULL;
		$precipType		= isset($hour->precipType) ? $hour->precipType : NULL;
		$hour_precip    = isset($hour->precipIntensity) ? $hour->precipIntensity : NULL;
		$cum_precip     = $last_cum_precip + (empty($hour_precip) ? 0 : $hour_precip);
		$sql = "REPLACE INTO weather_ds (idsite, code, timestamp, src, precip, 	cum_precip, precipType, "
			."temp,  apparenttemp, humidity, pressure, dewpoint, windspeed, windgust, windbearing, \n"
			."cloudcover, visibility, ozone, uvIndex, icon, summary, nearest_station) \n"
			."VALUES ({$idsite},'{$code}','{$timestamp}','DS',"
			.zn($hour_precip).",".zn($cum_precip).",".zns($precipType).",\n"
			.zn($temperature).",".zn($apparenttemp).",".zn($humidity).","
			.zn($pressure).",".zn($dewpoint).",".zn($windspeed).","
			.zn($windgust).",".zn($windbearing).",\n"
			.zn($cloudcover).",".zn($visibility).",".zn($ozone).","
			.zn($uvIndex).",".zns($icon).",".zns($summary).",".zn($nearest).");\n";
		$ok =  $db->exec($sql);
		if ($debug || $db->error > '') {
			echo "OK{$ok}:\n{$sql}\n";
			echo "\nError:".$db->error;
		}
		$wxsql = "\nINSERT IGNORE INTO weather(idsite, timestamp, tout, hum, baro, wspd, wdir, gust, rf,\n"
			."uv, cumrf, dewpoint, heatindex, windchill, observation_ts, src)\n"
			."SELECT idsite, timestamp, ROUND(temp,0), humidity, ROUND(pressure * 100,0), windspeed * 10, windbearing, windgust * 10, \n"
			."precip * 100, uvIndex, cum_precip * 100, dewpoint, fn_heatindex(temp, humidity,1), fn_windchill(temp,windspeed,1), timestamp, 'DS'\n"
			."FROM weather_ds WHERE idsite={$idsite} AND timestamp='{$timestamp}';";
		$ok =  $db->exec($wxsql);

	}
}
echo "\nDark Sky scrape started at ".date(DATE_RFC822)."\n";
$startscrape = strtotime("now");
$debug = $debug_verbose = $do_summary = false;
$sdate = $filldays = "";

for ($argndx=1; $argndx < $argc; $argndx++) {
	$debug = $debug || ($argv[$argndx] == "-debug");
	$debug_verbose = $debug_verbose || ($argv[$argndx] == "-verbose");
	$do_summary    = $do_summary    || ($argv[$argndx] == "-summary");
	if (substr($argv[$argndx],0,3) == '-sd') {
		$sdate = substr($argv[$argndx],3);
	}
	if (substr($argv[$argndx],0,2) == '-n') {
		$filldays = substr($argv[$argndx],2);
	}
}
$debug = $debug || $debug_verbose;
if ($sdate == "" || $filldays == "") {
	echo "\nUsage:   php darksky-backfill.php -sdSTART -nDAYS [-debug] [-verbose] [-summary] ";
	echo "\nExample: php darksky-backfill.php -sd2018-01-01 -n30\n";
	exit;
}
echo "\nArgs: Start {$sdate} for {$filldays} days.";

$sql = "SELECT idsite, code, type, name, lat, lon, timezone FROM site WHERE lat IS NOT NULL AND lon IS NOT NULL";
$tracks = $db->query($sql, false, 'code');
/*
https://api.darksky.net/forecast/d5d908d7d2edfb404a450e6e53cc7de7/38.202638,-85.770538,2017-09-08T00:00:00?exclude=currently
https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/38.202638,-85.770538,2017-09-08T00:00:00?exclude=currently,flags
https://api.darksky.net/forecast/d5d908d7d2edfb404a450e6e53cc7de7/38.202638,-85.770538
https://api.darksky.net/forecast/d5d908d7d2edfb404a450e6e53cc7de7/38.202638,-85.770538
https://api.darksky.net/forecast/d5d908d7d2edfb404a450e6e53cc7de7/38.202638,-85.770538,2017-09-10T20:00:00
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
	//FG 29.983199,-90.083760

for ($dinc = 0; $dinc < $filldays; $dinc++) {
	$qday = date("Y-m-d",strtotime($sdate." + {$dinc} DAY"))."T00:00:00";
	$urlstem   = "https://api.darksky.net/forecast/129ee67bc4a65644e1fd9b6567669258/";
	$urlsuffix = ','.$qday."?exclude=currently";
	foreach($tracks as $row){
		if ($row['lat']=='' || $row['lat']=='' ){
			continue;
		}
		$idsite = $row['idsite'];
		$code   = $row['code'];
		echo "\nProcessing {$code} {$qday}";
	    $url = $urlstem.$row['lat'].",".$row['lon'].$urlsuffix;
		//echo "\n{$url}\n"; die;
		$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
		$json = file_get_contents($url,false,$context);
	    @$data = json_decode($json);
		if (empty($data)) {
			continue;
		}
		$flags = $data->flags;
		$test = isset($flags->{'darksky-unavailable'});
        if ($test) {
			$sql = "REPLACE INTO darksky_unavailable(code, timestamp) VALUES ('{$code}','{$prev_15}');";
			continue;
		}
		$nearest = isset($flags->{'nearest-station'}) ? $flags->{'nearest-station'} : NULL;
		// collect mins and maxes from the hourly data (which may not be hourly)
		$cur_time = $lst_time = false;
		$hours = 0;
		$total_precip = 0;
		$min_hum = 101.1; $max_hum = -1.1; $min_dew = 1000.1; $max_dew = -1000.1;
		$min_bar = 11101.1; $max_bar = -1.1; $min_wsp = 1000.1; $max_wsp = -1000.1;
		$avg_tmp = $avg_app = "";
		foreach ($data->hourly->data as $hour) {
			processHour($hour, $idsite, $code, $nearest, $total_precip);
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
		$day = $data->daily->data[0];
		$min_hum = $min_hum >=   101 ? NULL : $min_hum * 100;
		$max_hum = $max_hum <=    -1 ? NULL : $max_hum * 100;
		$min_dew = $min_dew >=  1000 ? NULL : $min_dew;
		$max_dew = $max_dew <= -1000 ? NULL : $max_dew;
		$min_bar = $min_bar >= 11101 ? NULL : mbToHg($min_bar);
		$max_bar = $max_bar <=    -1 ? NULL : mbToHg($max_bar);
		$min_wsp = $min_wsp >=  1000 ? NULL : $min_wsp;
		$max_wsp = $max_wsp <= -1000 ? NULL : $max_wsp;
		$avg_tmp = $hours > 0 ? $avg_tmp / $hours : NULL;
		$avg_app = $hours > 0 ? $avg_tmp / $hours : NNLL;
		$windGust = null;
		$windGustTime = null;
		$ozone = $precipIntensityMaxTime = null;
		if (!empty($day)) {
			$windbearing  = isset($day->windBearing) ? $day->windBearing : NULL;
			$precip       = isset($day->precipIntensity) ? $day->precipIntensity * $hours : 0;
			$summary      = isset($day->summary) ? $day->summary: NULL;
			$mintemp      = isset($day->temperatureMin) ? $day->temperatureMin : NULL;
			$maxtemp      = isset($day->temperatureMax) ? $day->temperatureMax : NULL;
			$meanpressure = isset($day->pressure) ? mbToHg($day->pressure) : NULL;
			$meandewpt    = isset($day->dewPoint) ? $day->dewPoint : NULL;
			$meanwindspd  = isset($day->windSpeed) ? $day->windSpeed : NULL;
			$humidity     = isset($day->humidity) ? ($day->humidity * 100) : NULL;
			$uvIndex      = isset($day->uvIndex) ? $day->uvIndex : NULL;
			$moonPhase    = isset($day->moonPhase) ? $day->moonPhase : NULL;
			$cloudCover   = isset($day->cloudCover) ? $day->cloudCover : NULL;
			$precipType   = isset($day->precipType) ? $day->precipType : NULL;
			$windGust     = isset($day->windGust) ? $day->windGust : NULL;
			$ozone        = isset($day->ozone) ? $day->ozone : NULL;
			$visibility   = isset($day->visibility) ? $day->visibility : NULL;
			$uvIndexTime  = isset($day->uvIndexTime) ? Date('Y-m-d H:i:00', $day->uvIndexTime) : NULL;
			$windGustTime = isset($day->windGustTime) ? Date('Y-m-d H:i:00', $day->windGustTime) : NULL;
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
				."VALUES ({$row['idsite']},'{$row['code']}','".Date('Y-m-d',$day->time)."',".zn($precip).",\n"
				.zn($meandewpt).",".zn($avg_tmp).",".zn($mintemp).",".zn($maxtemp).",".zn($humidity).","
				.zn($min_hum).",".zn($max_hum).",".zn($meanpressure).",".zn($min_bar).",".zn($max_bar).",\n"
				.zn($min_dew).",".zn($max_dew).",".zn($meanwindspd).",".zn($min_wsp).",".zn($max_wsp)
				.",".zn($avg_app).",".zn($day->apparentTemperatureMin).",".zn($day->apparentTemperatureMax).",\n"
				.zn($moonPhase).','.zn($precipIntensity).",".zn($precipIntensityMax).",".zns($precipIntensityMaxTime).",".zn($precipProbability).",\n"
				.zns($precipType).",'{$temperatureMaxTime}','{$temperatureMinTime}','{$apparentTemperatureMaxTime}','{$apparentTemperatureMinTime}',\n"
				.zns($uvIndexTime).','.zns($windGust).",".zns($windGustTime).",".zn($windbearing).",".zn($cloudCover).",".zn($visibility).",\n"
				.zn($ozone).','.zn($uvIndex).",'{$day->icon}','{$summary}');\n";
			if ($do_summary) {
				$ok = $db->exec($sumsql);
				if ($debug || $db->error > '') {
					echo "\nError:".$db->error;
					echo "OK{$ok}:\n{$sumsql}\n";
					break 2;
				}
			}
		}
	}
}

$hostname = php_uname("n");
echo "\nDark Sky scrape completed at ".date(DATE_RFC822);
$endscrape = strtotime("now");
$minutes = round(($endscrape - $startscrape) / 60,2);
echo "\n{$minutes} minutes of processing\n";

?>
