<?php

require_once("weatherfunctions.php");
require "database_inc.php";
OpenMySQLi();

$sql = "SELECT idsite, timestamp, tout, hum, wspd, idweather FROM weather WHERE dewpoint IS NULL ORDER BY idweather ";
$result = mysqli_query($mysqli,$sql);

//http://api.wunderground.com/api/828bcd358ed824c8/history_20111111/q/38.202638,-85.770538.json'

while ($row = mysqli_fetch_array($result, MYSQLI_NUM)){
    $temp = $row[2];
    $hum  = $row[3];
    $wspd = $row[4];
    if ($temp < 120 && $temp > -40 && $hum <= 100 && $hum >0 && $wspd >= 0 && $wspd <1000 ) {
        $hi =  HeatIndex($temp,$hum);
        $dp =  dewpoint ($temp,$hum);
        $wc =  windChill($temp,$wspd);
        $sql = "UPDATE weather SET dewpoint='{$dp}', heatindex='{$hi}',  windchill='{$wc}' WHERE idweather={$row[4]};";
        $ok = mysqli_query($mysqli, $sql);
        if ($ok){
            echo "\n",$row[0]," ",$row[1]," ",$row[5]," UPDATED.";
        } else {
            echo "\n",$row[0]," ",$row[1]," ",$row[5]," ERROR: ",mysqli_error($mysqli);
        }
    }
}
echo "DONE!\n";
?>
