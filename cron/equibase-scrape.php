<?php
/*****************************************************************************************
 *
 *	THIS IS THE CURRENT SCRIPT AS OF 12-19-2017
 *
 *	TO ADD A NEW TRACK:
 *		1. Edit the site record in equibase (add bae code and name)
 *		2. Add rows to equibase_trackmap for each track
 *
*/


require_once('DB.class.php');
require("simplestats.php");
$debug = isset($argv[1]) && $argv[1]=="debug";
$db = new DB;
$xldist = getDistances();
if ($debug) print_r($xldist);
$xlcode = getBAECodes();
print_r($xlcode);

$surfacemap = getSurfaceMap();

/* SELECT url, code, CONCAT(  '20', MID( url, 7, 2 ) ,  '-', MID( url, 3, 2 ) ,  '-', MID( url, 5, 2 ) ) AS dt
FROM equibase_workouts WHERE LENGTH( code ) =2

 SELECT url, code, CONCAT(  '20', MID( url, 8, 2 ) ,  '-', MID( url, 4, 2 ) ,  '-', MID( url, 6, 2 ) ) AS dt
FROM equibase_workouts WHERE LENGTH( code ) =3

UPDATE equibase_workouts
SET date = CONCAT(  '20', MID( url, 8, 2 ) ,  '-', MID( url, 4, 2 ) ,  '-', MID( url, 6, 2 ) )
WHERE LENGTH( code ) =3

UPDATE equibase_workouts
SET date = CONCAT(  '20', MID( url, 7, 2 ) ,  '-', MID( url, 3, 2 ) ,  '-', MID( url, 5, 2 ) )
WHERE LENGTH( code ) =2
*/

function isOurs($fn) {
	/* determine if the filename belongs to a bae track
	 * DRF codes are 2-3 characters in length, so find
	 * the numeric start and extract the code
	*/
	global $xlcode;
	if (is_numeric($fn[2])) {
		$prefix = substr($fn,0,2);
	} else {
		$prefix = substr($fn,0,3);
	}
	return array_key_exists($prefix, $xlcode);
}

function parseFileName($url, $separator = '/') {
	/* Parse the date from a filename, mmddyy
	 * Example:
	 * "http://www.equibase.com/static/workout/CD110914USA-EQB.html";
	 *                                        01234567890
	*/
	global $xlcode;
	$pos = strripos($url, $separator);
	$fn  = substr($url, $pos+1);
	if (is_numeric($fn[2])) {
		$prefix = substr($fn,0,2);
		$dt = '20'.substr($fn,6,2).'-'.substr($fn,2,2).'-'.substr($fn,4,2);
	} else {
		$prefix = substr($fn,0,3);
		$dt = '20'.substr($fn,7,2).'-'.substr($fn,3,2).'-'.substr($fn,5,2);
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
	$sql = "SELECT e.code, s.idsite FROM equibase e INNER JOIN site s ON e.code = s.code";
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

function parseRaceLen($textdelim) {
	#Three|Furlongs
	if ($textdelim==''){
		return false;
	}
	$textarr = explode("|",$textdelim);

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
function getUrlContent($url) {
	fopen("cookies.txt", "w");
	$parts = parse_url($url);
	$host = $parts['host'];
	$ch = curl_init();
	$header = array('GET /1575051 HTTP/1.1',
		"Host: {$host}",
		'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language:en-US,en;q=0.8',
		'Cache-Control:max-age=0',
		'Connection:keep-alive',
		'Host:adfoc.us',
		'User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36',
	);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
	curl_setopt($ch, CURLOPT_COOKIESESSION, true);

	curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
	curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}
function loadHTML($url="",$fname="",$savefile=false) {
	// works with either url or file on disk
	global $debug;
	echo $url,"\r\n\r\n";
	if ($url != '') {
		/*
		 *	Oct 2018. Equibase rejects script requests, so we simulate a browser using getUrlContent
		 *	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
    	 *	$html = file_get_contents($url);
		 */
		$html = getUrlContent($url);
	} else {
	    $html = file_get_contents($fname);
	}
	//echo $html; die;
	$dom = new DomDocument();
	if ($dom->loadHTML($html)){
        if (true || $debug) echo "\nLoadHTML: Success";
		if ($savefile) $dom->saveHTMLFile("EQtempNEW.html");
		return $dom;
	}  else {
        if ($debug) echo "\nLoadHTML: FAILED";
        return false;
	}
}

function processWorkout($dom, $url) {
	global $debug, $db, $xldist, $xlcode, $surfacemap;
	$namedata = parseFileName($url);
	$code  = $namedata['code'];
	$date  = $namedata['date'];
	$idsite= $namedata['idsite'];
	$fn    = $namedata['fn'];

	$xpath = new DOMXPath($dom);

	// old $sumquery ="/html/body//form[contains(@action,'virtualstable')]//table//td[@colspan='7']";
	//$sumquery ="/html/body//form[contains(@action,'virtualstable')]//div[contains(@class,'col-md-12')]/p";
	$sumquery ="/html/body//form[contains(@action,'virtualstable')]//div[contains(@class,'session-info')]";
	$sumresult = $xpath->evaluate($sumquery);
	if ($debug) var_dump($sumresult);
	$races = $raceTimes = $raceTimeN = Array();

	foreach($sumresult as $ndx=>$node){
		if ($debug) echo "\n# {$ndx} SUMMARY\n";
		$distnode = $xpath->evaluate("h3",$node);
		$text = $distnode->item(0)->nodeValue;						// 2017-03-05 this is already in a form suitable for xlDist,
		$text = str_replace('|',' ',str_wstoDelim(trim($text)));  //but we'll strip out extra spaces just in case
		$raceAtts['distance'] = $text;
		echo "Distance:{$text}\n";
		$att_td = $xpath->evaluate("table//td",$node);
		/*
		 * Tables are in the form
		 *	Breed Type:	Thoroughbred
		 *	Surface:	Dirt
		 *	Track:	Fast
		 *
		 *	So we want nodes 3 and 5
		 */
		//print_r($att_tabled); die;
		$raceAtts['surface'] = $att_td->item(3)->nodeValue;
		$raceAtts['speed']   = $att_td->item(5)->nodeValue;
		if ($debug) echo "Atts\n",print_r($raceAtts,1);

		if ($debug) {
			echo "\nRace Atts:\n";
			print_r($raceAtts);
		}
		$races[] = $raceAtts;
		$raceTimes[] = Array();
		$raceTimeN[] = Array();
	}

	//$timequery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='table-hover']//td[4]";
	//$timequery ="/html/body//form[contains(@action,'virtualstable')]//div[contains(@class,'col-md-12')]/table[@class='table-hover']//td[5]";
	//$tablequery ="/html/body//form[contains(@action,'virtualstable')]//div[contains(@class,'col-md-12')]/table[@class='table-hover']";
	$tablequery ="/html/body//form[contains(@action,'virtualstable')]//table[@class='fullwidth phone-collapse']";
	$tableresult = $xpath->evaluate($tablequery);
	if ($debug) var_dump($tableresult);
	$currentRace = -1;
	foreach ($tableresult as $k=>$tbl){
		$currentRace++;
		$timequery =".//td[5]";	// one based
		$timeresult = $xpath->evaluate($timequery,$tbl);
		foreach ($timeresult as $k=>$td){
			$tt = trim($td->nodeValue);
			if ($debug) echo "Row # ($k) Col 5 |{$tt}|\n";
			$raceTimes[$currentRace][] = $tt;
			$raceTimeN[$currentRace][] = toSecs(trim($td->nodeValue));
		}
	}
	$logsql = "REPLACE INTO equibase_workouts_log (`url`, `scraped`, `workouts`) VALUES ('{$fn}','".date("Y-m-d H:i:s")."',".count($races).")";
	$db->exec($logsql);
	//var_dump($races);
	//var_dump($raceTimes);
	//var_dump($raceTimeN);
	//die;
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
				$q1 = quartile($sdata,1);
				$q2 = quartile($sdata,2);
				$q3 = quartile($sdata,3);
			}
			$q1 = zn($q1);
			$q2 = zn($q2);
			$q3 = zn($q3);
			$stdev = zn($stdev);
			$distance_text = $racedata['distance'];
			$distance_yds  = isset($xldist[$distance_text]) ? $xldist[$distance_text] : 'NULL';
	        $surface = $racedata['surface'];
			$testpk  = $code.'|'.$surface;
			$track   = isset($surfacemap[$testpk]) ? $surfacemap[$testpk] : 'NULL';
	        $tracknote = $racedata['speed'];
			$datepart = substr($fn,-18,6);
			$xdate = '20'.substr($datepart,4,2).'-'.substr($datepart,0,2).'-'.substr($datepart,2,2);
			//echo "\nDDD: {$date} {$xdate} {$datepart}";
			$sql = "REPLACE INTO equibase_workouts (`url`,`date`,`workout`, `code`, `idsite`, `surface`,
			 `track`, `distance_text`, `distance_yds`, `tracknote`, `mean`, `stdev`, `horses`,
			  `q1`, `q2`, `q3`,`wmin`,`wmax`) VALUES ('{$fn}','{$date}',{$ndx},'{$code}',{$idsite},'{$surface}',
			  '{$track}','{$distance_text}',{$distance_yds},'{$tracknote}',{$mean},{$stdev},{$horses},
			  {$q1},{$q2},{$q3},{$wmin},${wmax})";
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
//$dom = loadHTML("http://www.equibase.com/static/workout/index.html","",true);
$dom = loadHTML("","EQtempNEW.html");
if ($dom === FALSE) {
	echo "\nError loading DOM Document";
	die;
}
$xpath = new DOMXPath($dom);
$nodequery ="/html/body//table[@class='fullwidth']";
$xresult =  $xpath->evaluate($nodequery);
$context_node = null;
if ($xresult->length == 1) {
	$contextnode = $xresult->item(0);
} else {
	echo "\nContext node query has ".$xresult->length." elements\n ";
}

$urlquery = './/a[starts-with(@href, "/static/workout/") and not(contains(@href,"calendar.html"))]';
$urlnodes =  $xpath->evaluate($urlquery,$contextnode);
echo "\nCount of urls : {$urlnodes->length}";
$ct = 0;
foreach ($urlnodes as $node){
	$text = $node->textContent;
	$atts = $node->attributes;
	$url  = $atts->getNamedItem("href")->nodeValue;
	if ($debug) echo "\n{$ct} {$url}";
	$fname = substr($url,16);
	$isOurs = isOurs($fname);
	if ($debug || true) echo "\n{$fname} ->{$isOurs}";
	if ($isOurs) {
	$exists = $db->getScalar("SELECT COUNT(*) FROM equibase_workouts WHERE url='{$fname}'");
		if (true || $exists == 0) {
			$workouturl = "http://www.equibase.com{$url}";
			$trackdom = loadHTML($workouturl,"",false);
			echo "\nProcessing {$url}\n";
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