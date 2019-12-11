<?php
function HeatIndex($t,$h){
  //compute heatindex as a function of temperature (F) and relative humidity
  //we expect to get t in as t * 10 (rainwise RAW data) and return hi * 10 (RAW format)
    if (!is_numeric($t) || !is_numeric($h) ){
        return null;
    }
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
    if (!is_numeric($t) || !is_numeric($v) ){
        return null;
    }
    $v *= .1;  // wspd in this db is x 10
    if ($t <= 50 && $v >= 5){
      return  round((35.74 + (0.6215 * $t) - (35.75 * pow($v,0.16)) + (0.4275 * $t * pow($v,0.16))),1);
    } else {
      return $t;
    }
}

function dewpoint($t,$h){
    if (!is_numeric($t) || !is_numeric($h) ){
        return null;
    }
	$t= ($t-32)*5/9;	// convert to C
	$H= (log10($h)-2)/0.4343 + (17.62*$t)/(243.12+$t);
	return round(((243.12*$H)/(17.62-$H))*9/5 +32,1);	// back to F
}

function mbToHg($mb) {
	if (!is_numeric($mb)) {
		return null;
	}
	return $mb /  33.863886666667;
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

?>
