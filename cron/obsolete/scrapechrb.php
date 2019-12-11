<?php
function str_squeeze($test) {
    return trim(ereg_replace( ' +', ' ', $test));
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
        $msg .= "\nBad winddir: ",$w,"\n";
    }
    return $wd;
}

function getLastWeek($idsite){
    global $mysqli;
    // we have 96 per day (24x4) so if we get  700 readings we should easily cover a week
    $sql = "SELECT timestamp FROM weather WHERE idsite={$idsite} ORDER BY timestamp DESC LIMIT 800";
    $tslist = Array();
    $result = mysqli_query($mysqli,$sql);
    while ($row = mysqli_fetch_array($result)){
        $tslist[$row[0]] = 1;
    }
    mysqli_free_result($result);
    return $tslist;
}

function scrape($id, $pg, $tcnt, $overwrite = false){
    global $baseurl;
    global $mysqli;
    global $msg
    //return;
    $url = $baseurl.$pg;
    $msg .=  "\n\nScrape: {$id} {$url}\n";
    $txt = file_get_contents($url);
    if ($txt != ""){
        $lines = explode("\n",$txt);
        //print_r($lines);
        $i = 0;
        while($i <count($lines) && substr(trim($lines[$i++]),-1,1) != "%"){
            null;
        }
        $i++;  //skip -----------
        $line = str_squeeze($lines[$i]);
        $ok = true;
        $skipflag = false;
        $tslist = getLastWeek($id);

        for ($j = $i; $j < count($lines); $j++){
            $line = str_squeeze($lines[$j]);
            $data = explode(" ",$line);
            if (count($data) >= 16) {
                $date = "20".substr($data[0],6,2)."-".substr($data[0],0,2)."-".substr($data[0],3,2)." "
                            .substr($data[1],0,2).":".substr($data[1],2,2).":00";
                $wdir = winddir($data[5]);
                $sql = "SELECT COUNT(*) FROM weather WHERE timestamp='{$date}' AND idsite={$id}";
                //$result = mysqli_query($mysqli,$sql);
                //$row = mysqli_fetch_array($result);
                //$exists = ($row[0] > 0);

                //$msg .=  "TEST DATE",$date," ",$tslist[$date],"\n";
                $exists = array_key_exists($date,$tslist);
                if (!$exists || $overwrite){
                    $msg .=  " Processing {$id} {$date} ";
                    if ($tcnt == 2){
                        $sql = "INSERT INTO weather (idsite, wsid, timestamp, tout, hum,
                            baro, wspd, wdir, gust, rf, srad, uv, t1, t2,
                            wsbatt, rssi, txid, mv1, mv2, ver, cumrf) VALUES
                            ('{$id}',NULL,'{$date}',{$data[2]},{$data[4]},
                            NULL,".($data[6]*10).",{$wdir},".($data[7]*10).",".($data[11]*100).",NULL,NULL,{$data[15]},{$data[16]},
                            NULL,NULL,NULL,{$data[17]},{$data[18]},'{$pg}',".($data[12]*100).")";;

                    } else {
                        $sql = "INSERT INTO weather (idsite, wsid, timestamp, tout, hum,
                            baro, wspd, wdir, gust, rf, srad, uv, t1, t2,
                            wsbatt, rssi, txid, mv1, mv2, ver, cumrf) VALUES
                            ('{$id}',NULL,'{$date}',{$data[2]},{$data[4]},
                            NULL,".($data[6]*10).",{$wdir},".($data[7]*10).",".($data[11]*100).",NULL,NULL,{$data[15]},NULL,
                            NULL,NULL,NULL,{$data[16]},NULL,'{$pg}',".($data[12]*100).")";;
                    }
                    $ok  = mysqli_query($mysqli,$sql);
                    $msg .=  ($ok ? " INS OK " : " INS ERROR ")."\n";
                } else {
                    if (!$skipflag) {
                        $msg .= " Skipping {$id} {$date}";
                        $skipflag = true;
                    } else {
                        $msg .= ".";
                    }
                }
            }
        }
    } else {
        $msg .= "Failed to retrieve data.";
    }
}
//$mailTo   = 'mlpeterson23@gmail.com';
$mailTo   = 'cmeadow@dbtelligence.com';
$mailCc   = 'mlpeterson23@gmail.com';
$mailFrom = 'admin@bioappeng.com';
// mail setup
$headers = 'From: '.$mailFrom."\r\n".
    'Cc: '.$mailCc."\r\n" .
    'X-Mailer: PHP/' . phpversion(). "\r\n" ;

$BA_MYSQL_HOST   = '66.186.177.211';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';

$msg .= "CHRB Weather Scrape ".date(DATE_RFC822)."\n";
$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
if (mysqli_connect_errno()) {
    $msg .= "Connect failed: ".mysqli_connect_error();
    die;
}

$ids = Array(107,304,306,101);
$urls = Array("DLM7DAY.txt","ANI7DAY.txt","ALA7DAY.txt","HLY7DAY.txt");
$trks = Array(2,2,1,2);
$baseurl="http://www.westernwx.com/history/SOCAL/";
for ($ndx = 0; $ndx < count($ids); $ndx++){
    scrape($ids[$ndx],$urls[$ndx],$trks[$ndx]);
}

/*                                                                                                    6" Soil Temp  Soil Moisture
                   Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass   Dirt  Grass   Dirt
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %
------------------------------------------------------------------------------------------------------------------------------
  04/01/11 0845   74.3  52.2    46   ESE   0.4   1.1   ENE   0.1   1.8    0.00  0.00   54.4  76.6    64.5   61.2   15.7   29.4
       0    1       2     3      4     5    6     7     8     9     10      11    12     13    14      15     16     17     18
*/
$mysqli->close();
$msg .= "\nJOB COMPLETE\n"
?>