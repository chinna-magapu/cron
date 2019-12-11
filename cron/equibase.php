<?php
/* ----------------------------------------------
 *
 *	THIS IS NOT CURRENT DO NOT USE THIS SCRIPT!!
 *
 *	USE equibase-scrape.php INSTEAD
 *
*/
require_once('DB.class.php');
require("simplestats.php");
$db = new DB;
$xldist = getDistances();
print_r($xldist);
$xlcode = getBAECodes();
print_r($xlcode);
echo "TEST {$xlcode['AP']}\n";
$surfacemap = getSurfaceMap();
print_r($surfacemap);

function parseFileName($url, $separator = '/') {
	//"http://www.equibase.com/static/workout/CD110914USA-EQB.html";
	//                                        01234567890
	global $xlcode;
	$pos = strripos($url, $separator);
	$fn  = substr($url, $pos+1);
	if (is_numeric($fn[2])) {
		$prefix = substr($fn,0,2);
		$dt = '20'.substr($fn,6,2).'-'.substr($fn,2,2).'-'.substr($fn,4,2);
	} else {
		$prefix = substr($fn,0,3);
		$dt = '20'.substr($fn,6,2).'-'.substr($fn,2,2).'-'.substr($fn,4,2);
	}
	$idsite = isset($xlcode[$prefix]) ? $xlcode[$prefix] : '000';
	return Array("idsite"=>$idsite, "code"=>$prefix, "date"=>$dt, 'fn'=>$fn);
}

function getDistances(){
	global $db;
	$sql = "SELECT distance, yards FROM equibase_xldist";
	$xldist = $db->querySimpleArray($sql,'distance','yards');
	return $xldist;
}

function getBAECodes(){
	global $db;
	$sql = "SELECT code, idsite FROM equibase WHERE idsite IS NOT NULL";
	$baecodes = $db->querySimpleArray($sql,'code','idsite');
	return $baecodes;
}

function getSurfaceMap(){
	global $db;
	$sql = "SELECT CONCAT(code,'|', surface) AS pk, track from equibase_trackmap";
	$trackmap = $db->querySimpleArray($sql,'pk','track');
	return $trackmap;
}

function str_squeeze($test) {
    return trim(preg_replace( '/ +/', ' ', $test));
}
function str_wstoDelim($test) {
	$test = trim(preg_replace( '/\s+/', ' ', $test));
    return trim(preg_replace( '/\s/', '|', $test));
}

function parseHeader($textdelim){
	#Distance:|Three|Furlongs|Breed|Type:|Thoroughbred|Surface:|Dirt|Track:|Fast
	#Distance:|Three|Furlongs|Breed|Type:|Thoroughbred|Surface:|Dirt|training|Track:|Fast
	#Distance:|Five|Furlongs|Breed|Type:|Thoroughbred|Surface:|Dirt|Track:|Fast
	if ($textdelim==''){
		return false;
	}
	//$textdelim = strtolower($textdelim);
	$textarr = explode("|",$textdelim);
	if ($textarr[0] != 'Distance:') {
		return false;
	}
	$dist = $textarr[1];
	$i = 2;
	$c = count($textarr);
	while ($i < $c && $textarr[$i] != "Breed") {
		echo "1a {$textarr[$i]}\n";
		$dist .= ' '.$textarr[$i++];
	}
	echo "\n2 Dist {$dist}";
	if ($i >= $c){
		return false;
	}
	while ($i< $c && $textarr[$i++] != "Surface:") {
	}
	if ($i >= $c){
		return false;
	}
	$surface = $textarr[$i++];
	while ($i< $c && $textarr[$i] != "Track:") {
		$surface .= ' '.$textarr[$i++];
	}
	$trackspeed = $textarr[$c-1];
	return Array("distance"=>$dist,"surface"=>$surface,"speed"=>$trackspeed);
}

function toSecs($s){
	$t = explode(":",$s);
	if (count($t) == 2 ){
		return $t[0]*60.0 + $t[1];
	} else {
		return $t[0] * 1.0;
	}
}

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}

function scrape($url="",$fname="",$overwrite=FALSE) {
	// works with either url or file on disk
	$scrapee = $url == "" ? $fname : $url;
    echo "\n\nScrape: {$url}\n";
	if ($url != '') {
		$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
    	$html = file_get_contents($fname);
	} else {
	    $html = file_get_contents($fname);
	}


	$dom = new DomDocument();
	if ($dom->loadHTML($html)){
        echo "Success!";
		//$dom->saveHTMLFile("EQtemp.html");
		return $dom;
	}  else{
        echo "FAILED!";
        return false;
	}
}

error_reporting(E_ALL ^ E_WARNING);
date_default_timezone_set('America/New_York');
echo "EQUIBASE training scrape ".date(DATE_RFC822)."\n";

if ($handle = opendir('F:\work\\bae\\equibase-html\\baefiles')) {
    while (($entry = readdir($handle)) !== false) {
		$test = strtolower(substr($entry,-4));
    	if ($test == "html"){
	    	echo "Filename: {$entry}\n";

###############################################################################
//$fname = "F:\work\\bae\\equibase-html\\baefiles\\AQU112214USA-EQB.html";
$fname = "F:\work\\bae\\equibase-html\\baefiles\\".$entry;
$namedata = parseFileName($fname,'\\');
$code  = $namedata['code'];
$date  = $namedata['date'];
$idsite= $namedata['idsite'];
$fn    = $namedata['fn'];

$exists = $db->getScalar("SELECT COUNT(*) FROM equibase_workouts WHERE url='{$fn}'");

$dom = scrape('',$fname);
if ($dom === FALSE) {
	echo "Error loading DOM Document";
	continue;
}

$xpath = new DOMXPath($dom);
// /html/body/div[1]/div[4]/div[1]/form
$query ="/html/body//form[contains(@action,'virtualstable')]//table";
$sumquery ="/html/body//form[contains(@action,'virtualstable')]//table//td[@colspan='7']";
$datquery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']";
$datquery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']";
$sumresult = $xpath->evaluate($sumquery);
#$datresult = $xpath->evaluate($datquery);
var_dump($sumresult);
#var_dump($datresult);

$races = $raceTimes = $raceTimeN = Array();

foreach($sumresult as $ndx=>$node){
	echo "\n# {$ndx} SUMMARY";
	#var_dump($node);
	$text = $node->nodeValue;
	//echo "Text\n{$text}\n";
	$text = str_wstoDelim(trim($text));
	echo "Text Delim\n{$text}\n";
	$raceAtts= parseHeader($text);
	echo "\nRace Atts:\n";
	print_r($raceAtts);
	$races[] = $raceAtts;
	$raceTimes[] = Array();
	$raceTimeN[] = Array();
}

$timequery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']//td[4]";
$timeresult = $xpath->evaluate($timequery,$node);
var_dump($timeresult);
$currentRace = -1;
foreach ($timeresult as $k=>$td){
	echo "Row # ($k) Col 4 |{$td->nodeValue}|\n";
	if ($td->nodeValue == 'Time') {
		$currentRace++;
		echo "Current Race|{$currentRace}|\n";
	} else {
		$raceTimes[$currentRace][] = trim($td->nodeValue);
		$raceTimeN[$currentRace][] = toSecs(trim($td->nodeValue));
	}
}

$logsql = "REPLACE INTO equibase_workouts_log (`url`, `scraped`, `workouts`) VALUES ('{$fn}','".date("Y-m-d H:i:s")."',".count($races).")";
$db->exec($logsql);

foreach ($races as $ndx=>$racedata) {
	$data = $raceTimeN[$ndx];
	$horses = count($data);
	if ($horses > 0) {
		$mean = avg($data);
		$stdev = stdev($data, false);
		$q1 = $q2 = $q3 = NULL;
		$wmin = min($data);
		$wmax = max($data);
		if ($horses >= 4) {
			$sdata = $data;
			sort($sdata);
			echo "SDATA";
			print_r($sdata);
			echo "DATA";
			print_r($data);
			$q1 = quartile($sdata,1);
			$q2 = quartile($sdata,2);
			$q3 = quartile($sdata,3);
		}
		$q1 = zn($q1);
		$q2 = zn($q2);
		$q3 = zn($q3);
		$stdev = zn($stdev);
		$distance_text = $racedata['distance'];
		$distance_yds  = isset($xldist[$distance_text]) ? $xldist[$distance_text] : null;
        $surface = $racedata['surface'];
		$testpk  = $code.'|'.$surface;
		$track   = isset($surfacemap[$testpk]) ? $surfacemap[$testpk] : null;
        $tracknote = $racedata['speed'];
		$sql = "REPLACE INTO equibase_workouts (`url`, `workout`, `code`, `idsite`, `surface`,
		 `track`, `distance_text`, `distance_yds`, `tracknote`, `mean`, `stdev`, `horses`,
		  `q1`, `q2`, `q3`, `wmin`,`wmax`) VALUES ('{$fn}',{$ndx},'{$code}',{$idsite},'{$surface}',
		  '{$track}','{$distance_text}',{$distance_yds},'{$tracknote}',{$mean},{$stdev},{$horses},
		  {$q1},{$q2},{$q3},{$wmin},${wmax})";
		$db->exec($sql);
		if ($db->error > '') {
			echo "\nError: {$db->error}\n";
			echo "\nSQL: {$sql}\n";
			die;
		}
		$sql = "REPLACE INTO equibase_workout_data(url, workout, code, idsite, times_json) VALUES
			('{$fn}',{$ndx},'{$code}',{$idsite},'".json_encode($data)."')";
		$db->exec($sql);
	}
}

//print_r($races);
//print_r($raceTimes);
//print_r($raceTimeN);
############################################################################
	    }
	}
    closedir($handle);
}

die("\nDone");
?>