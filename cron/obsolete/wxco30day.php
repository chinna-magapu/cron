<?php
require "DB.class.php";
$db = new DB();

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}
function zns($val){
	return empty($val) ? 'NULL' : "'{$val}'";
}

date_default_timezone_set("America/New_York");
echo "Weather Company 30 Day JSON Data Fetch ".date(DATE_RFC822)."\n";
/*
$url = 'https://api.weather.com/v3/wx/conditions/historical/dailysummary/30day?geocode=33.950571,-118.343904&units=e&language=en-US&format=json&apiKey=81dc0de24b4946aa9c0de24b4906aade';
*/
$debug = false;
$sql = "SELECT idsite, code, type, name, lat, lon, timezone FROM site WHERE lat IS NOT NULL AND lon IS NOT NULL";
$tracks = $db->query($sql, false, 'code');
$urlstem = "https://api.weather.com/v3/wx/conditions/historical/dailysummary/30day?geocode=";
$urlsuffix = "&units=e&language=en-US&format=json&apiKey=81dc0de24b4946aa9c0de24b4906aade";
$opts = Array("https"=>Array("method" => "GET","header" => "Accept-language: en\r\n"."accept-encoding: gzip, deflate, br\r\n"."Connection: close\r\n"));

foreach($tracks as $code => $row){
	if ($row['lat']=='' || $row['lat']=='' ){
		continue;
	}
    $url = "{$urlstem}{$row['lat']},{$row['lon']}{$urlsuffix}";
	echo "{$code}\n";
	$context = stream_context_create($opts);
	$json = file_get_contents($url,false,$context);
    @$data = json_decode($json);
	//print_r($data); die;

	if (empty($data)) {
		continue;
	}
//validTimeLocal: [
//2019-02-20T07:00:00-0500",
//12345678901234567890
	$precipArr = $data->precip24Hour;
	$rainArr   = $data->rain24Hour;
	$snowArr   = $data->snow24Hour;
	$tminArr   = $data->temperatureMin;
	$tmaxArr   = $data->temperatureMax;
	$icondArr  = $data->iconCodeDay;
	$iconnArr  = $data->iconCodeNight;
	$phrasedArr= $data->wxPhraseLongDay;
	$phrasenArr= $data->wxPhraseLongNight;
	foreach ($data->validTimeLocal as $ndx=>$ts) {
		$ts = substr($ts,0,19); // strip off timezone
		$precip  = $precipArr[$ndx];
		$rain    = $rainArr[$ndx];
		$snow    = $snowArr[$ndx];
		$tmin    = $tminArr[$ndx];
		$tmax    = $tmaxArr[$ndx];
		$icond   = $icondArr[$ndx];
		$iconn   = $iconnArr[$ndx];
		$phrased = $phrasedArr[$ndx];
		$phrasen = $phrasenArr[$ndx];
		$sql = "REPLACE INTO `wxco30day`(`code`, `timestamp`, `precip`, `rf`, `snow`,\n"
			."`minTemp`, `maxTemp`, `icon_day`, `icon_night`, `wx_phrase_day`, `wx_phrase_night`)\n"
			."VALUES ('{$code}','{$ts}',".zn($precip).",".zn($rain).",".zn($snow).",\n"
			.zn($tmin).",".zn($tmax).",".zn($icond).",".zn($iconn).",".zns($phrased).",".zns($phrasen).");\n";
		$ok =  $db->exec($sql);
		if ($db->error > '') {
			echo "\n{$sql}";
			echo "\nError:".$db->error;
			die;
		}
	}
}

$hostname = php_uname("n");
//$sql = "INSERT INTO alertcheck (script, timestamp, comment,machine) VALUES ('ds_yesterday','".date('Y-m-d H:i:s')."','WU YESTERDAY Complete',{$hostname})";
//$ok = mysqli_query($mysqli,$sql);
echo "\nWx Co 30 scrape completed at ".date(DATE_RFC822)."\n";
?>
