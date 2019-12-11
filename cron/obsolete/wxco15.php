<?php

/*
2019-02-12T17:15:00-0500
012345678901234567890123
*/

require "DB.class.php";
$db = new DB();

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}
function zns($val){
	return empty($val) ? 'NULL' : "'{$val}'";
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

date_default_timezone_set("America/New_York");
echo "Weather Company JSON Data Fetch ".date(DATE_RFC822)."\n";
/*
https://api.weather.com/v3/wx/observations/current?geocode=35.177211,33.327799&units=e&language=en-US&format=json&apiKey=81dc0de24b4946aa9c0de24b4906aade
*/
$debug = false;
$sql = "SELECT idsite, code, type, name, lat, lon, timezone FROM site WHERE lat IS NOT NULL AND lon IS NOT NULL";
$tracks = $db->query($sql, false, 'code');
$urlstem = "https://api.weather.com/v3/wx/observations/current?geocode=";
$urlsuffix = "&units=e&language=en-US&format=json&apiKey=81dc0de24b4946aa9c0de24b4906aade";

$opts = Array("https"=>Array("method" => "GET","header" => "Accept-language: en\r\n"."accept-encoding: gzip, deflate, br\r\n"."Connection: close\r\n"));

foreach($tracks as $code => $row){
	if ($row['lat']=='' || $row['lat']=='' ){
		continue;
	}
    $url = "{$urlstem}{$row['lat']},{$row['lon']}{$urlsuffix}";
	echo "\n{$code}";	//echo "\n{$code}\n{$url}"; continue;
	$context = stream_context_create($opts);
	$json = file_get_contents($url,false,$context);
    $data = json_decode($json);
	if (empty($data)) {
		continue;
	}
	$cloudCeiling = $data->cloudCeiling;
	$cloudCoverPhrase = $data->cloudCoverPhrase;
	$dayOfWeek = $data->dayOfWeek;
	$dayOrNight = $data->dayOrNight;
	$expirationTimeUtc = $data->expirationTimeUtc;
	$iconCode = $data->iconCode;
	$iconCodeExtend = $data->iconCodeExtend;
	$obsQualifierCode = $data->obsQualifierCode;
	$obsQualifierSeverity = $data->obsQualifierSeverity;
	$precip1Hour = $data->precip1Hour;
	$precip6Hour = $data->precip6Hour;
	$precip24Hour = $data->precip24Hour;
	$pressureAltimeter = $data->pressureAltimeter;
	$pressureChange = $data->pressureChange;
	$pressureMeanSeaLevel = $data->pressureMeanSeaLevel;
	$pressureTendencyCode = $data->pressureTendencyCode;
	$pressureTendencyTrend = $data->pressureTendencyTrend;
	$relativeHumidity = $data->relativeHumidity;
	$snow1Hour = $data->snow1Hour;
	$snow6Hour = $data->snow6Hour;
	$snow24Hour = $data->snow24Hour;
	$sunriseTimeLocal = substr($data->sunriseTimeLocal,0,19);
	$sunriseTimeUtc = $data->sunriseTimeUtc;
	$sunsetTimeLocal = substr($data->sunsetTimeLocal,0,19);
	$sunsetTimeUtc = $data->sunsetTimeUtc;
	$temperature = $data->temperature;
	$temperatureChange24Hour = $data->temperatureChange24Hour;
	$temperatureDewPoint = $data->temperatureDewPoint;
	$temperatureFeelsLike = $data->temperatureFeelsLike;
	$temperatureHeatIndex = $data->temperatureHeatIndex;
	$temperatureMax24Hour = $data->temperatureMax24Hour;
	$temperatureMaxSince7Am = $data->temperatureMaxSince7Am;
	$temperatureMin24Hour = $data->temperatureMin24Hour;
	$temperatureWindChill = $data->temperatureWindChill;
	$uvDescription = $data->uvDescription;
	$uvIndex = $data->uvIndex;
	$validTimeLocal = substr($data->validTimeLocal,0,19);
	$validTimeUtc = $data->validTimeUtc;
	$visibility = $data->visibility;
	$windDirection = $data->windDirection;
	$windDirectionCardinal = $data->windDirectionCardinal;
	$windGust = $data->windGust;
	$windSpeed = $data->windSpeed;
	$wxPhraseLong = $data->wxPhraseLong;
	$wxPhraseMedium = $data->wxPhraseMedium;
	$wxPhraseShort = $data->wxPhraseShort;
	$ts = prev15($validTimeLocal);

	$sql = "REPLACE INTO wxco15min(code, timestamp, cloudCeiling, cloudCoverPhrase, dayOfWeek, dayOrNight,\n"
		." expirationTimeUtc, iconCode, iconCodeExtend, obsQualifierCode, obsQualifierSeverity, precip1Hour, \n"
		."precip6Hour, precip24Hour, pressureAltimeter, pressureChange, pressureMeanSeaLevel, pressureTendencyCode, \n"
		."pressureTendencyTrend, relativeHumidity, snow1Hour, snow6Hour, snow24Hour, sunriseTimeLocal, sunriseTimeUtc, sunsetTimeLocal,  \n"
		."sunsetTimeUtc, temperature, temperatureChange24Hour, temperatureDewPoint, temperatureFeelsLike, temperatureHeatIndex, \n"
		."temperatureMax24Hour, temperatureMaxSince7Am, temperatureMin24Hour, temperatureWindChill, uvDescription, uvIndex,  \n"
		."validTimeLocal, validTimeUtc, visibility, windDirection, windDirectionCardinal, windGust, windSpeed, \n"
		."wxPhraseLong, wxPhraseMedium, wxPhraseShort)\nVALUES ('{$code}','{$ts}',";
	$sql .= zn($cloudCeiling).
		','.zns($cloudCoverPhrase).
		','.zns($dayOfWeek).
		','.zns($dayOrNight).
		','.zn($expirationTimeUtc).
		','.zn($iconCode).
		','.zn($iconCodeExtend).
		','.zn($obsQualifierCode).
		','.zn($obsQualifierSeverity).
		','.zn($precip1Hour).
		','.zn($precip6Hour).
		','.zn($precip24Hour).
		','.zn($pressureAltimeter).
		','.zn($pressureChange).
		','.zn($pressureMeanSeaLevel).
		','.zn($pressureTendencyCode).
		','.zns($pressureTendencyTrend).
		','.zn($relativeHumidity).
		','.zn($snow1Hour).
		','.zn($snow6Hour).
		','.zn($snow24Hour).
		','.zns($sunriseTimeLocal).
		','.zn($sunriseTimeUtc).
		','.zns($sunsetTimeLocal).
		','.zn($sunsetTimeUtc).
		','.zn($temperature).
		','.zn($temperatureChange24Hour).
		','.zn($temperatureDewPoint).
		','.zn($temperatureFeelsLike).
		','.zn($temperatureHeatIndex).
		','.zn($temperatureMax24Hour).
		','.zn($temperatureMaxSince7Am).
		','.zn($temperatureMin24Hour).
		','.zn($temperatureWindChill).
		','.zns($uvDescription).
		','.zn($uvIndex).
		','.zns($validTimeLocal).
		','.zn($validTimeUtc).
		','.zn($visibility).
		','.zn($windDirection).
		','.zns($windDirectionCardinal).
		','.zn($windGust).
		','.zn($windSpeed).
		','.zns($wxPhraseLong).
		','.zns($wxPhraseMedium).
		','.zns($wxPhraseShort).");";
	$ok =  $db->exec($sql);
	if ($db->error > '') {
		echo "\n{$sql}";
		echo "\nError:".$db->error;
		die;
	}
}

$hostname = php_uname("n");
//$sql = "INSERT INTO alertcheck (script, timestamp, comment,machine) VALUES ('ds_yesterday','".date('Y-m-d H:i:s')."','WU YESTERDAY Complete',{$hostname})";
//$ok = mysqli_query($mysqli,$sql);
echo "\nWx Co 30 scrape completed at ".date(DATE_RFC822)."\n";
?>
