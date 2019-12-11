<?php
function str_squeeze($test) {
    return trim(preg_replace( '/ +/', ' ', $test));
}

require_once("weatherfunctions.php");

function dashNull($s){
	if (trim($s) == '--')
		return 'NULL';
	else
		return $s;
}

include_once("chrbscrape_func.php");

$BA_MYSQL_HOST   = 'bioappeng.com';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';
date_default_timezone_set('America/New_York');
echo "CHRB Weather Scrape ".date(DATE_RFC822)."\n";
$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);
if (mysqli_connect_errno()) {
    echo "Connect failed: ".mysqli_connect_error();
    die;
}


chrbscrape('chrb15');

/*                                                                                                    6" Soil Temp  Soil Moisture
                   Air DewPt        2min  2min 10min H o u r l y   Max         Total    D a i l y   Grass   Dirt  Grass   Dirt
                  Temp  Temp    RH  Wind  Wind  Gust  Wind  Wind  Wind    Prec  Prec   MinT  MaxT   Track  Track  Track  Track
   Date    Time      F     F     %   Dir   mph   mph   Dir   mph  Gust      In Today      F     F       F      F      %      %
------------------------------------------------------------------------------------------------------------------------------
  04/01/11 0845   74.3  52.2    46   ESE   0.4   1.1   ENE   0.1   1.8    0.00  0.00   54.4  76.6    64.5   61.2   15.7   29.4
       0    1       2     3      4     5    6     7     8     9     10      11    12     13    14      15     16     17     18
*/

/*Array
(
    [0] => <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2//EN">
    [1] => <HTML>
    [2] => <HEAD>
    [3] => <TITLE>WESTERN WEATHER GROUP</TITLE>
    [4] => <META HTTP-EQUIV="refresh" CONTENT="60">
    [5] => <META HTTP-EQUIV="pragma" CONTENT="no-cache">
    [6] => <META HTTP-EQUIV="expires" CONTENT="Fri, 1 Jan 1999 00:00:01 MST">
    [7] => <PRE>
    [8] => <b><font size="4">CALIFORNIA HORSE RACING BOARD WEATHER STATION NETWORK</font><font size="3">
    [9] =>                                                                                                             Synth         Synth
    [10] => Latest 15 Minute Observations          Air DewPt         Avg  Wind   Max        Total    D a i l y   Grass  /Dirt  Grass  /Dirt
    [11] =>                                       Temp  Temp    RH  Wind Speed  Wind   Prec  Prec   MinT  MaxT   Track  Track  Track  Track
    [12] => Race Track             Date    Time      F     F     %   Dir   mph  Gust     in Today      F     F       F      F      %      %
    [13] => -------------------------------------------------------------------------------------------------------------------------------
    [14] => Golden Gate Fields    05/09/13 1445   61.0  53.3    76    SW  10.3  16.5   0.00  0.00   51.9  61.6    61.6   66.7   22.8    9.9
    [15] => Santa Anita           05/09/13 1445   72.8  54.1    52    SW   5.2  11.8   0.00  0.03   50.0  75.4    71.3   66.4   20.9    9.9
    [16] => Hollywood Park        05/09/13 1445   66.5  54.8    66   WSW   4.5  12.1   0.00  0.00   54.8  69.1    72.9   69.7   18.0   17.1
    [17] => Los Alamitos          05/09/13 1445   67.6  56.1    67   SSW   7.5  13.6   0.00  0.31   54.8  69.1    65.6     --   43.9     --
    [18] => Del Mar               01/14/13 1930   40.9  28.4    61     E   0.6   3.7   0.00  0.00   29.7  55.7    49.7   54.4   29.0   20.7
    [19] =>
)
*/
$mysqli->close();
echo "\nJOB COMPLETE\n"
?>