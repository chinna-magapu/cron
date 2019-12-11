<?php
require_once('DB.class.php');
require("simplestats.php");
$db = new DB;
$xldist = getDistances();
$xlcode = getBAECodes();
$baecodes = array_keys($xlcode);
$surfacemap = getSurfaceMap();
$debug = false;

function isOurs($fn) {
	//determine if the filename belongs to a bae track
	global $baecodes;
	if (is_numeric($fn[2])) {
		$prefix = substr($fn,0,2);
	} else {
		$prefix = substr($fn,0,3);
	}
	return array_key_exists($prefix, $baecodes);
}

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

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}

function toSecs($s){
	$t = explode(":",$s);
	if (count($t) == 2 ){
		return $t[0]*60.0 + $t[1];
	} else {
		return $t[0] * 1.0;
	}
}

function loadHTML($url="",$fname="") {
	// works with either url or file on disk
	global $debug;
	if ($url != '') {
		$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
    	$html = file_get_contents($url);
	} else {
	    $html = file_get_contents($fname);
	}
	$dom = new DomDocument();
	if ($dom->loadHTML($html)){
        if ($debug) echo "\nLoadHTML: Success";
		//$dom->saveHTMLFile("EQtemp.html");
		return $dom;
	}  else{
        if ($debug) echo "\nLoadHTML: FAILED";
        return false;
	}
}

function processWorkout($dom, $url) {
	global $debug, $db;
	$namedata = parseFileName($url);
	$code  = $namedata['code'];
	$date  = $namedata['date'];
	$idsite= $namedata['idsite'];
	$fn    = $namedata['fn'];

	$xpath = new DOMXPath($dom);
	$query ="/html/body//form[contains(@action,'virtualstable')]//table";
	$datquery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']";
	$datquery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']";

	$sumquery ="/html/body//form[contains(@action,'virtualstable')]//table//td[@colspan='7']";
	$sumresult = $xpath->evaluate($sumquery);
	if ($debug) var_dump($sumresult);

	$races = $raceTimes = $raceTimeN = Array();

	foreach($sumresult as $ndx=>$node){
		if ($debug) echo "\n# {$ndx} SUMMARY";
		$text = $node->nodeValue;
		$text = str_wstoDelim(trim($text));
		if ($debug) echo "Text Delim\n{$text}\n";
		$raceAtts= parseHeader($text);
		if ($debug) {
			echo "\nRace Atts:\n";
			print_r($raceAtts);
		}
		$races[] = $raceAtts;
		$raceTimes[] = Array();
		$raceTimeN[] = Array();
	}

	$timequery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']//td[4]";
	$timeresult = $xpath->evaluate($timequery,$node);
	if ($debug) var_dump($timeresult);
	$currentRace = -1;
	foreach ($timeresult as $k=>$td){
		if ($debug) echo "Row # ($k) Col 4 |{$td->nodeValue}|\n";
		if ($td->nodeValue == 'Time') {
			$currentRace++;
			if ($debug) echo "Current Race|{$currentRace}|\n";
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
			$q1 = $q2 = $q3 = $q4 = NULL;
			if ($horses >= 4) {
				$q1 = quartile($data,1);
				$q2 = quartile($data,2);
				$q3 = quartile($data,3);
				$q4 = quartile($data,4);
			}
			$q1 = zn($q1);
			$q2 = zn($q2);
			$q3 = zn($q3);
			$q4 = zn($q4);
			$stdev = zn($stdev);
			$distance_text = $racedata['distance'];
			$distance_yds  = isset($xldist[$distance_text]) ? $xldist[$distance_text] : null;
	        $surface = $racedata['surface'];
			$testpk  = $code.'|'.$surface;
			$track   = isset($surfacemap[$testpk]) ? $surfacemap[$testpk] : null;
	        $tracknote = $racedata['speed'];
			$sql = "REPLACE INTO equibase_workouts (`url`, `workout`, `code`, `idsite`, `surface`,
			 `track`, `distance_text`, `distance_yds`, `tracknote`, `mean`, `stdev`, `horses`,
			  `q1`, `q2`, `q3`, `q4`) VALUES ('{$fn}',{$ndx},'{$code}',{$idsite},'{$surface}',
			  '{$track}','{$distance_text}',{$distance_yds},'{$tracknote}',{$mean},{$stdev},{$horses},
			  {$q1},{$q2},{$q3},{$q4})";
			$db->exec($sql);
			if ($db->error > '') {
				echo "\nError: {$db->error}\n";
				echo "\nSQL: {$sql}\n";
				if ($debug) die;
			}
			$sql = "REPLACE INTO equibase_workout_data(url, workout, code, idsite, times_json) VALUES
				('{$fn}',{$ndx},'{$code}',{$idsite},'".json_encode($data)."')";
			$db->exec($sql);
		}
	}
}

// suppress warnings from dom loader
error_reporting(E_ALL ^ E_WARNING);
echo "EQUIBASE training scrape ".date(DATE_RFC822)."\n";
$dom = getHTML("http://www.equibase.com/static/workout/index.html");
if ($dom === FALSE) {
	echo "\nError loading DOM Document";
	die;
}
$xpath = new DOMXPath($dom);
$nodequery ="/html/body//table[@class='table-hover']";
$xresult =  $xpath->evaluate($nodequery);
$context_node = null;
if ($xresult->length == 1) {
	$contextnode = $xresult->item(0);
} else {
	echo "\nContext node query has ".$xresult->length." elements\n ";
}
$urlquery = './/a[starts-with(@href, "/static/workout/") and not(contains(@href,"calendar.html"))]';
$urlnodes =  $xpath->evaluate($urlquery,$contextnode);
//echo "\nCount of urls : {$urlnodes->length}";
$ct = 0;
foreach ($urlnodes as $node){
	$text = $node->textContent;
	$atts = $node->attributes;
	$url  = $atts->getNamedItem("href")->nodeValue;
	echo "\n{$ct} {$url}";
	$fname = substr($url,16);
	$isOurs = isOurs($fname);
	if ($isOurs) {
	$exists = $db->getScalar("SELECT COUNT(*) FROM equibase_workouts WHERE url='{$fname}'");
		if ($exists == 0) {
			$workouturl = "http://www.equibase.com{$url}";
			$trackdom = loadHTML($workouturl);
			processWorkout($trackdom, $url);
		}
	}
	/* if ($trackdom !== false){
		$trackdom->saveHTMLFile($fname);
	} */
	$ct++;
}
echo "\nDone ".date(DATE_RFC822)."\n";

?>