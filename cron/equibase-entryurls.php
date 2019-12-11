<?php
require_once('DB.class.php');
/* --------------------------------------
 *
 *	THIS IS PRIMARILY A SNOOPY DEMO
 *
 *	Fails a javascript test at Equibase but might be useful elsewhere.
 *
*/

$savepath = "c:\\windows\\temp\\";
//$savepath = "D:\\HostingSpaces\\admin\\bioappeng.us\\wwwroot\\racecard\\";
$url = "http://www.equibase.com/premium/eqbHorsemenAreaDownloadAction.cfm?sn=ONSC-SA-20141228D";
$p = strpos($url,"=");
$fn = substr($url,$p+1);
echo "1P {$p} FN {$fn}";

$db = new DB;
$xlcode = getBAECodes();
$debug = false;
if ($debug) print_r($xlcode);

function parseFileName($fn) {
	//ONSC-AQU-20141227D.pdf
	//ONDC-TP-20141228D.pdf
	//        01234567890
	global $xlcode;
	$tmp = explode('-',$fn);
	$code = $tmp[1];
	$idsite = isset($xlcode[$code]) ? $xlcode[$code] : 0;
	$dt = substr($tmp[2],0,4).'-'.substr($tmp[2],4,2).'-'.substr($tmp[2],6,2);
	return Array("idsite"=>$idsite, "code"=>$code, "date"=>$dt);
}

function getBAECodes(){
	global $db;
	$sql = "SELECT e.code, s.idsite FROM equibase e INNER JOIN site s ON e.code = s.code";
	$baecodes = $db->querySimpleArray($sql,'code','idsite');
	return $baecodes;
}

function str_squeeze($test) {
    return trim(preg_replace( '/ +/', ' ', $test));
}
function str_wstoDelim($test) {
	$test = trim(preg_replace( '/\s+/', ' ', $test));
    return trim(preg_replace( '/\s/', '|', $test));
}

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}

function savePg($document){
	global $savepath;
	$handle = fopen($savepath."htmldata.html",'w');
	fwrite($handle, $document);
	fclose($handle);
}

function getPDF($url){
	global $db, $savepath;
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
   	$file = file_get_contents($url);
	$p = strpos($url,"=");
	$fn = substr($url,$p+1);
	$fn .= ".pdf";
	echo "2P {$p} FN {$fn}";
	//$p = strpos('sn',$url);
	//$fn = substr($url, $p+4).".pdf";
	$handle = fopen($savepath.$fn,'w');
	fwrite($handle, $file);
	fclose($handle);
	$fdata = parseFileName($fn);
	$logsql = "REPLACE INTO equibase_racecard_log (`url`, `date`, `code`,`idsite`) VALUES ('{$fn}','".$fdata["date"]."','".$fdata["code"]."',".$fdata["idsite"].")";
	$db->exec($logsql);
	if ($db->error <> '') {
		echo "ERROR: ",$db->error,"\n";
	}
	echo "\nSaved {$fn}\n";
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
//		$html = getUrlContent($url);
		$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
    	$html = file_get_contents($url);
	} else {
	    $html = file_get_contents($fname);
	}
	//echo $html; die;
	$dom = new DomDocument();
	if ($dom->loadHTML($html)){
		print_r($dom);//	die;
		//echo '<pre>', htmlspecialchars($dom->saveHTML()), '</pre>';
		echo '<pre>',$dom->saveHTML(), '</pre>';
        if (true || $debug) echo "\nLoadHTML: Success";
		if ($savefile) $dom->saveHTMLFile("EQtempNEW.html");
		return $dom;
	}  else {
        if ($debug) echo "\nLoadHTML: FAILED";
        return false;
	}
}

// suppress warnings from dom loader
// error_reporting(E_ALL ^ E_WARNING);
echo "EQUIBASE entry urls ".date(DATE_RFC822)."\n";
//$dom = loadHTML("",'entryscrape\\entries-2018-11-12_1640#2.html');
$dom = loadHTML("",'entryscrape\\entries-2018-11-12_1756#2.html');
//$dom = loadHTML("https://docs.microsoft.com/en-us/windows-server/administration/windows-commands/wmic",'');
if ($dom === FALSE) {
	echo "\nError loading DOM Document";
	continue;
}
	foreach($xlcode as $code => $idsite ){
		$xpath = new DOMXPath($dom);
		$urlquery = './/a[starts-with(@href, "/premium/eqbHorsemenAreaDownloadAction.cfm") and .//a[contains(., "Overnight")]]';
		$urlquery = './/a[starts-with(@href, "/static/entry/{$code}")] and [contains(., "Overnight")]';
		$urlquery = './/a[starts-with(@href, "/static/entry/'.$code.'")]';
echo "\nURL Query\n{$urlquery}";
		$urlnodes =  $xpath->evaluate($urlquery);
		/* Count of urls : 5
		0 /static/horsemen/horsemenareaON.html?SAP=TN
		0 /premium/eqbHorsemenAreaDownloadAction.cfm?sn=ONDC-FG-20141227D
		0 /premium/eqbHorsemenAreaDownloadAction.cfm?sn=ONDC-FG-20141228D
		0 /premium/eqbHorsemenAreaDownloadAction.cfm?sn=ONDC-FG-20141229D
		0 /premium/eqbHorsemenAreaDownloadAction.cfm?sn=ONDC-FG-20141231D
		Done Sat, 27 Dec 14 12:42:36 -0500
		*/
		echo "\nCount of urls : {$urlnodes->length}";
print_r($urlnodes);
		$ct = 0;
		foreach ($urlnodes as $node){
			$text = $node->textContent;
			$atts = $node->attributes;
			$url  = $atts->getNamedItem("href")->nodeValue;
			echo "\n{$text} {$url}";
			$ct++;
			/*if (substr($url,0,10)=="/premium/e"){
				$p = strpos($url,"=");
				$fn = substr($url,$p+1);
				$fn .= ".pdf";
				$exists = $db->getScalar("SELECT COUNT(*) FROM equibase_racecard_log WHERE url='{$fn}'");
				if ($exists == 0) {
	                $pdfurl = "http://www.equibase.com".$url;
					getPDF($pdfurl);
				}
			}*/
		}
	}
echo "\nDone ".date(DATE_RFC822)."\n";

?>