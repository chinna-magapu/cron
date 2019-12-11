<?php
function HeatIndex($t,$h){
  //compute heatindex as a function of temperature (F) and relative humidity
  //we expect to get t in as t * 10 (rainwise RAW data) and return hi * 10 (RAW format)
    if ($t >= 80) {
        $hi = -42.379 + 2.04901523 * $t + 10.14333127 * $h - 0.22475541 * $h * $t - 6.83783 * .001 * $t * $t
              - 5.481717 * .01 * $h * $h + 1.22874 * .001 * $t * $t *$h + 8.5282 * .0001 * $t * $h * $h - 1.99 * .000001 * $t * $t * $h * $h;
        $hi = round($hi,1);
    } else {
        $hi = $t;
    }
    return $hi;
}
function windChill($t, $v){
    //compute windchill as function of temp and windspeed (simplified)
    //we expect to get t,v in as t, v*10 (rainwise RAW data) and return wc * 10 (RAW format)
    $v *= .1;
    if ($t <= 50 && $v >= 5){
      return  round((35.74 + (0.6215 * $t) - (35.75 * pow($v,0.16)) + (0.4275 * $t * pow($v,0.16))),1);
    } else {
      return $t;
    }
}
function dewpoint($t,$h){
	$t= ($t-32)*5/9;	// convert to C
	$H= (log10($h)-2)/0.4343 + (17.62*$t)/(243.12+$t);
	return round(((243.12*$H)/(17.62-$H))*9/5 +32,1);	// back to F
}

function str_squeeze($test) {
    return trim(preg_replace( '/ +/', ' ', $test));
}

function winddir($w){
    if ($w == "N"){
        $wd = 0;
    } else if($w == "NNE") {
        $wd = 23;
    } else if($w == "NE") {
        $wd = 45;
    } else if($w == "ENE") {
        $wd = 68;
    } else if($w == "E") {
        $wd = 90;
    } else if($w == "ESE") {
        $wd = 113;
    } else if($w == "SE") {
        $wd = 135;
    } else if($w == "SSE") {
        $wd = 158;
    } else if($w == "S") {
        $wd = 180;
    } else if($w == "SSW") {
        $wd = 203;
    } else if($w == "SW") {
        $wd = 225;
    } else if($w == "WSW") {
        $wd = 248;
    } else if($w == "W") {
        $wd = 270;
    } else if($w == "WNW") {
        $wd = 293;
    } else if($w == "NW") {
        $wd = 315;
    } else if($w == "NNW") {
        $wd = 338;
    } else {
        $wd = 1; //error flag
        echo "\nBad winddir: ",$w,"\n";
    }
    return $wd;
}

function dashNull($s){
	if (trim($s) == '--')
		return 'NULL';
	else
		return $s;
}

function getLastWeek($idsite){
    global $mysqli;
    // we have 96 per day (24x4) so if we get  700 readings we should easily cover a week
    $sql = "SELECT timestamp FROM weather WHERE idsite={$idsite} AND src='CHRB' ORDER BY timestamp DESC LIMIT 800";
    $tslist = Array();
    $result = mysqli_query($mysqli,$sql);
    while ($row = mysqli_fetch_array($result)){
        $tslist[$row[0]] = 1;
    }
    mysqli_free_result($result);
    return $tslist;
}

function scrape($id, $pg, $tcnt, $wsid, $overwrite = false){
    global $baseurl;
    global $mysqli;
    //return;
    $url = $baseurl.$pg;
    echo "\n\nScrape: {$id} {$url}\n";
    $txt = file_get_contents($url);
    if ($txt != ""){
        $lines = explode("\n",$txt);
        //print_r($lines);
        $i = 0;
        while($i <count($lines) && substr(trim($lines[$i++]),-3,3) != "==="){
            null; //skip past weekly summary ending with ===
        }

        while($i <count($lines) && substr(trim($lines[$i++]),-3,3) != "---"){
            null; //then skip past -----------
        }

        $line = str_squeeze($lines[$i]);
        $ok = true;
        $skipflag = false;
        $tslist = getLastWeek($id);

        for ($j = $i; $j < count($lines); $j++){
        	$line = str_replace("-7999.0"," NULL",$lines[$j]);
        	$line = str_replace("-6999.0"," NULL",$line);
        	$line = str_replace("7999.0"," NULL",$line);
        	$line = str_replace("6999.0"," NULL",$line);
        	//$line = str_replace(" -- "," NULL ",$line);
            $line = str_squeeze($line);
            $data = explode(" ",$line);
			//print_r($data); die;
            if (count($data) >= 18) {
                $date = "20".substr($data[0],6,2)."-".substr($data[0],0,2)."-".substr($data[0],3,2)." "
                            .substr($data[1],0,2).":".substr($data[1],2,2).":00";
                $wdir = winddir($data[5]);
				if ($data[1]=='--' || $data[2] == '--' || $data[5]=='--') {
					continue;
				}
                $sql = "SELECT COUNT(*) FROM weather WHERE timestamp='{$date}' AND idsite={$id}";
				$chrbtemp = $data[2];
				$chrbrh   = $data[4];
				$heatndx  = HeatIndex($chrbtemp, $chrbrh);
				$windch   = windChill($chrbtemp, $data[6]);
                $exists = array_key_exists($date,$tslist);
                if (!$exists || $overwrite){
                    echo " Processing {$id} {$date} ";
                    if (true || $tcnt == 2){
                        $sql = "REPLACE INTO weather (src, idsite, wsid, timestamp, tout, dewpoint, hum,
                            baro, wspd, wdir, gust, rf, srad, uv, t1, t2,
                            wsbatt, rssi, txid, mv1, mv2, ver, cumrf, heatindex, windchill) VALUES
                            ('CHRB','{$id}','{$wsid}','{$date}',{$data[2]},{$data[3]},{$data[4]},
                            NULL,".($data[6]*10).",{$wdir},".($data[10]*10).",".($data[11]*100).",NULL,NULL,".dashNull($data[15]).",".dashNull($data[16])
                            .",NULL,NULL,NULL,".dashNull($data[17]).",".dashNull($data[18]).",'{$pg}',".($data[12]*100).",{$heatndx},{$windch})";
                    }
                    $ok  = mysqli_query($mysqli,$sql);
                    echo ($ok ? " INS OK\n " : " INS ERROR ".$mysqli->error." SQL\n{$sql}\n");
                } else {
                    if (!$skipflag) {
                        echo " Skipping {$id} {$date}";
                        $skipflag = true;
                    } else {
                        echo ".";
                    }
                }
            }
        }
    } else {
        echo "Failed to retrieve data.";
    }
}

$BA_MYSQL_HOST   = 'bioappeng.com';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';

echo "CHRB Weather Scrape ".date(DATE_RFC822)."\n";
$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
if (mysqli_connect_errno()) {
    echo "Connect failed: ".mysqli_connect_error();
    die;
}
$sql = "SELECT idsite, url, tracks, wsid FROM chrbscrape";
$result = mysqli_query($mysqli,$sql);
$ids = $urls = $trks = $wsids = Array();
while ($row = mysqli_fetch_array($result)){
    $ids[]  = $row[0];
    $urls[] = $row[1];
    $trks[] = $row[2];
    $wsids[]= $row[3];
}
mysqli_free_result($result);

//$ids = Array(107,304,306,101,307);
//$urls = Array("SOCAL/DLM7DAY.txt","SOCAL/ANI7DAY.txt","SOCAL/ALA7DAY.txt","SOCAL/HLY7DAY.txt","bayarea/GGF7DAY.txt");
//$trks = Array(2,2,1,2,2);
//http://www.westernwx.com/history/SOCAL/HLY7DAY.htm
$baseurl="http://www.westernwx.com/history/";
for ($ndx = 0; $ndx < count($ids); $ndx++){
	if ($ids[$ndx] != 306) {
		// los Alamitos is reporting garbage Mar 2016
	    scrape($ids[$ndx],$urls[$ndx],$trks[$ndx], $wsids[$ndx], true);
	}

}

/*                                                                                                    6" Soil Temp  Soil Moisture
                   Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass   Dirt  Grass   Dirt
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %
------------------------------------------------------------------------------------------------------------------------------
  04/01/11 0845   74.3  52.2    46   ESE   0.4   1.1   ENE   0.1   1.8    0.00  0.00   54.4  76.6    64.5   61.2   15.7   29.4
       0    1       2     3      4     5    6     7     8     9     10      11    12     13    14      15     16     17     18
*/
$hostname = php_uname("n");
$svrprefix = $hostname == 'VPS' ? 'bioappeng.us ' :'bioappeng.com ';
$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('chrb.php','".$svrprefix." CHRB Scrape ran','{$hostname}')";

$mysqli->close();
echo "\nJOB COMPLETE\n";

/* CAN END WITH
03/01/16 0730   53.8  53.7   100   ESE   0.3   2.4     E   0.4   2.6    0.00  0.00   53.6  57.8    61.2     --   25.2   20.5
             --     --    --    --    --    --    --    --    --    --      --    --     --    --      --     --     --     --

 http://www.westernwx.com/history/bayarea/GGF7DAY.htm
                                                                                                    6" Soil Temp  Soil Moisture
                   Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass  Synth  Grass  Synth
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %
------------------------------------------------------------------------------------------------------------------------------
  01/27/15 1400   64.5  48.9    57   NNW   0.4   3.1   NNW   0.6   3.9    0.00  0.00   51.9  66.2    54.8   58.9   -1.3    7.2
  01/27/15 1300   65.2  46.6    51    NW   0.3   3.1   NNW   0.4   3.3    0.00  0.00   51.9  66.2    54.6   58.5   -1.3    7.2
  01/27/15 1200   65.8  45.1    47   NNW   0.2   2.9    NW   0.3   2.9    0.00  0.00   51.9  66.2    54.5   58.2   -1.4    7.2

  http://www.westernwx.com/history/SOCAL/ALA7DAY.htm
                                                                                                      6" Soil Temp  Soil Moisture
                   Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass  Synth  Grass  Synth
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %
------------------------------------------------------------------------------------------------------------------------------
  01/27/15 1400   67.6  50.3    54    SW   5.6   9.9   SSW   5.3  10.5    0.00  0.00   48.4  70.7 -6999.0  -13.6-6999.0   39.3
  01/27/15 1300   67.9  42.3    39   WNW   0.9   5.5     W   2.4   6.8    0.00  0.00   48.4  69.7 -6999.0  -13.5-6999.0   39.0
  01/27/15 1200   67.4  45.1    45     W   2.8   6.1    NW   1.6   6.1    0.00  0.00   48.4  68.9 -6999.0  -13.5-6999.0   39.0

	http://www.westernwx.com/history/SOCAL/ANI7DAY.htm
                  Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass  Synth  Grass  Synth    Min
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track    Bat
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %  Volts
-------------------------------------------------------------------------------------------------------------------------------------
  01/27/15 1500   72.4  44.4    37     S   3.3   5.9     S   2.3   7.9    0.00  0.00   54.1  74.7    60.9   86.6   16.5   15.7   12.2
  01/27/15 1400   72.2  43.8    36    SE   1.1   5.9   SSE   2.5   7.7    0.00  0.00   54.1  73.3    59.8   85.3   16.5   15.9   12.2
  01/27/15 1300   72.3  46.5    40     E   0.5   8.3   SSE   2.9   8.3    0.00  0.00   54.1  72.4    58.7   83.0   16.5   16.1   12.2
  01/27/15 1200   70.7  45.0    40   SSE   3.5   7.5   SSE   2.7   7.7    0.00  0.00   54.1  70.8    57.7   80.7   16.5   16.3   12.2


http://www.westernwx.com/history/SOCAL/DLM7DAY.htm
                                                                                                   6" Soil Temp  Soil Moisture
                   Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass   Dirt  Grass   Dirt
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %
------------------------------------------------------------------------------------------------------------------------------
  08/13/15 1345   75.5  67.3    76     W   6.7   9.6     W   6.1   9.6    0.00  0.00   64.5  77.9    76.0     --   21.6   34.1
  08/13/15 1330   75.7  66.4    73     W   5.3   9.4     W   5.2   9.4    0.00  0.00   64.5  77.9    75.9     --   21.6   34.1
  08/13/15 1315   75.6  67.4    76     W   5.3   8.3     W   4.5   9.9    0.00  0.00   64.5  77.9    75.8     --   21.6   34.0
  08/13/15 1300   75.9  66.5    73     W   3.4   8.6   WNW   3.6   8.6    0.00  0.00   64.5  76.9    75.7     --   21.6   34.0

Array
(
    [0] => 01/27/15
    [1] => 1500
    [2] => 72.4
    [3] => 44.4
    [4] => 37
    [5] => S
    [6] => 3.3
    [7] => 5.9
    [8] => S
    [9] => 2.3
    [10] => 7.9
    [11] => 0.00
    [12] => 0.00
    [13] => 54.1
    [14] => 74.7
    [15] => 60.9
    [16] => 86.6
    [17] => 16.5
    [18] => 15.7
    [19] => 12.2
)
*/
?>