<?php
/*****************************************************************************************
 *
 *	New Equibase script Oct 2018
 *
 *	TO ADD A NEW TRACK:
 *		1. Edit the site record in equibase (add bae code and name)
 *		2. Add rows to equibase_trackmap for each track
 *	New API tables are
 *		equibase_endpoints_log
 *  	equibase_wrk_data
 *		equibase_wrksummary
 *
*/

function isOurs($drfcode) {
	/* determine DRF code is ours - temporarily only KEE  */
	global $xlcode;
	//return array_key_exists($drfcode, $xlcode);
	return ($drfcode == 'KEE');
}


function getEndpoints() {
	global $db, $api_get_endpoints, $endpoints, $debug;
	/* returns a list of ALL available endpoints - not all are ours */
	$debug = false;
	$dnow = Date('Y-m-d H:i:s');
	$unow = strtotime(Date('Y-m-d H:i:s'));
	$hrs48 = (60 * 60 * 48) - 300; //5-minute margin
	if ($debug) echo ("\nGet Endpoints: URL: {$api_get_endpoints}\n");
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($api_get_endpoints,false,$context);
	if ($json){
		$data = json_decode($json);
		if ($debug) {
			echo "\njson len:", strlen($json);
			echo "\nData\n";
			print_r($data);
		}
		$values = Array();
		foreach($data->trackDateUriList as $ndx=>$obj) {
			$drfcode = trim($obj->trackId);
			if (isOurs($drfcode)) {
				$sql = "SELECT downloaded, hits FROM equibase_endpoints_log WHERE endpoint='{$obj->endpoint}'";
				$test = $db->query("SELECT downloaded, hits FROM equibase_endpoints_log WHERE endpoint='{$obj->endpoint}'",true);
				$fetch = false;
				if (empty($test)) {
					$fetch = true;
				} else {
					$lapsed = $unow - strtotime($test['downloaded']);
					if ($test['hits'] < 2 && $lapsed > $hrs48) {
						$fetch = true;
					}
				}
				if ($fetch) {
					$line = "\n('".$obj->endpoint."','".$drfcode."','".$obj->country."','".$obj->raceDate."','".$dnow."', 1),";
					$values[] = $line;
					$endpoints[] = $obj->endpoint;
				}
			}
		}
		/* do not uses REPLACE INTO as there might be dependent children */
		if (count($endpoints) > 0) {
			$sql = "INSERT INTO equibase_endpoints_log (`endpoint`, `code`, `country`, `date`,`downloaded`,`hits`) VALUES\n";
			$sql .= implode("\n",$values);
			$sql = rtrim($sql,',')."\nON DUPLICATE KEY UPDATE hits = hits+1;";
			if ($debug) echo "SQL\n{$sql}";
			$ok = $db->exec($sql);
			if ($db->error) {
				echo "DB Error in Get Endpoints\n{$db->error}\n";
				die;
			}
		} else {
			echo "\nNo Endpoints were found for processing.";
		}

	} else {
		echo "Failed to fetch data from {$api_get_endpoints}\n";
		die;
	}
}

function getTrackEndpoints($drfcode, $country = "USA") {
	global $db, $api_get_endpoints, $endpoints, $debug;
	/* returns a list of endpoints for track/country Equibase uses USA and CAN so far */
	$dnow = Date('Y-m-d H:i:s');
	$unow = strtotime(Date('Y-m-d H:i:s'));
	$hrs48 = 60 * 60 * 48;
	if ($country == 'US') $country = 'USA';
	if ($country == 'CA') $country = 'CAN';
	$url = $api_get_endpoints."{$drfcode}/{$country}";
	if ($debug) echo ("\nGet Track Endpoints: URL: {$url}\n");
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
	if ($json){
		if ($debug) {
			echo "\njson len:", strlen($json);
			echo "\nData\n";
			print_r($data);
		}
		$values = Array();
		foreach($data->trackDateUriList as $ndx=>$obj) {
			$drfcode = trim($obj->trackId);
			if (isOurs($drfcode)) {
				$test = $db->query("SELECT downloaded, hits FROM equibase_workouts WHERE endpoint='{$obj->endpoint}'",true);
				$fetch = false;
				if (empty($test)) {
					$fetch = true;
				} else {
					$lapsed = $unow - strtotime($test['downloaded']);
					if ($test['hits'] < 2 || $lapsed > $hrs48) {
						$fetch = true;
					}
				}
				if ($fetch) {
					$line = "\n('".$obj->endpoint."','".$drfcode."','".$obj->country."','".$obj->raceDate."','".$dnow."', 1),";
					$values[] = $line;
					$endpoints[] = $obj->endpoint;
				}
			}
		}
		/* do not uses REPLACE INTO as there might be dependent children */
		$sql = "INSERT INTO equibase_endpoints_log (`endpoint`, `code`, `country`, `date`,`downloaded`,`hits`) VALUES\n";
		$sql .= implode("\n",$values);
		$sql = rtrim($sql,',')."\nON DUPLICATE KEY UPDATE hits = hits+1;";
		if ($debug) echo "SQL\n{$sql}";
		$ok = $db->exec($sql);
		if ($db->error) {
			echo "DB Error in getTrackEndpoints\n{$db->error}\n";
			die;
		}
	} else {
		echo "Failed to fetch data from {$url}\n";
		die;
	}
}

/*
 *	Surface / Track Note parsing
 *	All Weather Training (Fast) [surface] => Dirt (Fast)
 *	012345678901234567890123456              01234567890
 *                     ^21                      ^    ^10
*/

function processEndpoint($endpoint) {
	/*
	 *	Examples:
	 *	/workouts/basic/SA /USA/2018-10-10
	 *	/workouts/basic/KEE/USA/2018-10-14
	*/
	global $db, $api_get_endpoint, $xldist, $xlcode, $surfacemap, $debug;
	$url = $api_get_endpoint.$endpoint;
	$context = stream_context_create(array('http' => array('header'=>'Connection: close')));
	$json = file_get_contents($url,false,$context);
	if ($json){
		$data = json_decode($json);
		if ($debug) {
			echo "\nProcess Endpoint {$url}, Len(json)=", strlen($json);
			echo "\nData\n";
			print_r($data);
		}
		$workdate = $data->raceDate;
		$drfcode  = $data->trackId;
		$country  = $data->country;
		$idsite   = $xlcode[$drfcode];
		$workouts = Array();
		$distances = Array();
		$workout_set = 0;
		foreach($data->workouts as $ndx=>$obj) {
			$disttext = $obj->distanceText;
			$surfnote = $obj->surface;  //[surface] => All Weather Training (Fast) [surface] => Dirt (Fast)
			$ppos = strpos($surfnote,'(');
			if ($ppos === FALSE) {
				$surface = "unknown";
				$note    = '';
				$track   = 'unknown';
			} else {
				$surface = substr($surfnote, 0, $ppos-1);
				$ppos2   = strpos($surfnote,')',$ppos-1);
				if ($ppos2 === FALSE) $ppos2 = strlen($surfnote) - 1;
				$note = substr($surfnote, $ppos+1, $ppos2 - $ppos -1);
				$trackkey = $drfcode.'|'.$surface;
				if (array_key_exists($trackkey, $surfacemap)) {
					$track = $surfacemap[$trackkey];
				} else {
					$track = "Unknown";
				}
			}
			if (! array_key_exists($disttext, $workouts)) {
				if (array_key_exists($disttext, $xldist)) {
					$yards = $xldist[$disttext]['yards'];
					$hb    = $xldist[$disttext]['hb'];
				} else {
					$yards = -999;
					$hb    = 'U';
				}
				$workouts[$disttext] = Array('date' => $workdate, 'workout_set'=>$workout_set++, 'hb'=>$hb, 'code'=>$drfcode,
					'idsite'=>$idsite, 'track'=> $track, 'surface' => $surface, 'distance_text' => $disttext, 'distance_yds'=>$yards,
					'tracknote'=> $note, 'mean'=>null, 'stdev'=>null, 'horses'=>0, 'wmin'=>null, 'q1'=>null, 'q2'=>null,
					'q3'=>null, 'wmax'=>null,'times'=>Array());
			}
			$w = &$workouts[$disttext];
			foreach ($obj->horseList as $k=>$horse) {
				$w['horses']++;
				$w['times'][] = toSecs($horse->workTiming);
			}
			unset($w);
		}
		$now = date('Y-m-d H:i:s');
		$sql = "UPDATE  equibase_endpoints_log SET workout_sets= ".count($workouts)
			 ." WHERE endpoint='{$endpoint}'";
		if ($debug) {
			echo "\nProcess Endpoint {$url}\nSQL: \n{$sql}";
		}
		$ok = $db->exec($sql);
		if ($db->error) {
			echo "\n2.Process Endpoint {$url}\nSQL: \n{$sql}";
			echo "\n  ***ERROR {$db->error}\n";
		}
		foreach($workouts as $k=>$w) {
			$sql = "REPLACE INTO equibase_wrk_data(endpoint, workout_set, hb, code, idsite, times_json)\n"
				."VALUES ('{$endpoint}',{$w['workout_set']},'{$w['hb']}','{$drfcode}',{$idsite},\n'".json_encode($w['times'])."');";
			$db->exec($sql);
			if ($db->error) {
				echo "\n3.Process Endpoint {$url}\nSQL: \n{$sql}";
				echo "\n  ***ERROR {$db->error}\n";
			}
			if ($w['horses'] > 0) {
				$wdata = $w['times'];
				$mean = avg($wdata);
				$stdev = stdev($wdata, false);
				$q1 = $q2 = $q3 = NULL;
				$wmin = min($wdata);
				$wmax = max($wdata);
				if ($w['horses'] >= 4) {
					$sdata = $wdata;
					sort($sdata);
					$q1 = quartile($sdata,1);
					$q2 = quartile($sdata,2);
					$q3 = quartile($sdata,3);
				}
				$q1 = zn($q1);
				$q2 = zn($q2);
				$q3 = zn($q3);
				$stdev = zn($stdev);
				$sql = "REPLACE INTO equibase_wrksummary (`endpoint`,`date`,`workout_set`,`hb`, `code`, `idsite`, `surface`,
				 `track`, `distance_text`, `distance_yds`, `tracknote`, `mean`, `stdev`, `horses`,
				  `q1`, `q2`, `q3`,`wmin`,`wmax`) VALUES ('{$endpoint}','{$workdate}',{$w['workout_set']},'{$w['hb']}','{$drfcode}',{$idsite},'{$w['surface']}',
				  '{$w['track']}','{$w['distance_text']}',{$w['distance_yds']},'{$w['tracknote']}',{$mean},{$stdev},{$w['horses']},
				  {$q1},{$q2},{$q3},{$wmin},${wmax});";
				$db->exec($sql);
				if ($db->error) {
					echo "\n4.Process Endpoint {$url}\nSQL: \n{$sql}";
					echo "\n  ***ERROR {$db->error}\n";
				}
			}

		}
	} else {
		echo "\nProcess Endpoint Failed to fetch data from {url}\n";
	}
}


function getDistances(){
	global $db;
	$sql = "SELECT distance, yards, hb FROM equibase_xldist";
	$xldist = $db->query($sql,false,'distance');
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

function zn($val){
	return empty($val) && !is_numeric($val) ? 'NULL' : $val;
}

function toSecs($s){
	/* $s is in the form msshh */
	$secs = 0;
	if (strlen($s) == 5) {
		$secs += (double)substr($s,0,1) * 60;
		$s = substr($s,1,4);
	}
	$secs += ( (double)substr($s,0,2) +  ((double)substr($s,2,2) * 0.01) );
	return $secs;
}

/***************************************
 *
 *	MAIN()
 *
***************************************/
require_once('DB.class.php');
require("simplestats.php");

echo "Equibase workouts API run: ".date(DATE_RFC822)."\n";

$api_user 	= 'mqs';
$api_pw 	= '2018mqs3Qbwr';
$api_key 	= 'QdieUTc0Bo4OmFe1oCu9vjjfqsUg6RDhzoWvHChg';
$api_base = 'https://api.equibase.com/data-api/';
$api_sufx = '/workouts/basic/';
$api_get_endpoints = $api_base.$api_key.$api_sufx;
$api_get_endpoint  = $api_base.$api_key;

//https://api.equibase.com/data-api/QdieUTc0Bo4OmFe1oCu9vjjfqsUg6RDhzoWvHChg/entries/full/KEE/USA/2018-10-24/7/D/TB

//$debug = true;
$debug = isset($argv[1]) && $argv[1]=="debug";
$db = new DB;
$xldist = getDistances();
if ($debug) print_r($xldist);
$xlcode = getBAECodes();
if ($debug) print_r($xlcode);
$surfacemap = getSurfaceMap();
if ($debug) print_r($surfacemap);

$endpoints = Array();
getEndpoints();
if ($debug) print_r($endpoints);

foreach($endpoints as $ndx=>$endpoint) {
	//processEndpoint('/workouts/basic/KEE/USA/2018-10-11');
	processEndpoint($endpoint);
}
echo "\nDone ".date(DATE_RFC822)."\n";

?>