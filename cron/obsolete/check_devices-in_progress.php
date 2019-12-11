<?php
/* modified 4/06/2010 to work with timestamps in device local time rather than UTC */
/* modified 1/26/2011 to work with new weather/site relationship */

function AdjustToEastern($datestamp, $tz){
    // compute difference between US/Eastern and $tz in hours
    date_default_timezone_set('US/Eastern');
    $dt1 = New DateTime();
    date_default_timezone_set($tz);
    $dt2 = New DateTime();
    $diff = strtotime($dt1->format('Y-m-d H:m')) - strtotime($dt2->format('Y-m-d H:m'));
    $diff = intval($diff /3600) % 24 ;
    $datestamp->modify($diff." hour");
    return New DateTime($datestamp->format('Y-m-d H:i'));
}


//$mailTo   = 'mlpeterson23@gmail.com';
$mailTo   = 'maint.tracking@gmail.com';
$mailCc   = 'cmeadow@dbtelligence.com';
$mailFrom = 'admin@bioappeng.com';
// copied credentials from database_inc, not sure whether this will execute in same directory

$BA_MYSQL_HOST   = 'bioappeng.com';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';

$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);

// get comparison time in US/Eastern
date_default_timezone_set("US/Eastern");
$now = new DateTime();
$now->modify('-30 minute');
$comptime = strtotime($now->format('Y-m-d H:i'));

// get latest timestamp from all active stations
// note that we join on wsid only because we only want the current site for any given station
$sites = Array();
$sql = "SELECT site.idsite, name, mysql_timezone, MAX(timestamp)
        FROM weather INNER JOIN station ON weather.wsid = station.wsid
		INNER JOIN site s ON station.wsid = site.wsid
        WHERE  station.active = 1 AND station.type = 'rainwise'
        GROUP BY site.idsite, name, mysql_timezone";
//        HAVING max(timestamp) < DATE_SUB('".$gmt->format("Y-m-d H:i")."',INTERVAL 30 MINUTE)";
echo $sql;
die;
$result = mysqli_query($mysqli, $sql);
while ($row = mysqli_fetch_array($result)){
    // adjust device times to Eastern for comparison
    $eastern  = AdjustToEastern(new DateTime($row[3]),$row[2]);
    $lastxmit = strtotime($eastern->format('Y-m-d H:i'));
    if ($lastxmit < $comptime){
    $sites[$row[0]] = Array(
        'name'=>$row[1],
        'Eastern Time'=>$eastern->format('Y-m-d H:i'),
        'Local Time'  =>$row[3]);
    }
}
mysqli_free_result($result);
print_r($sites);
die;
// mail setup
$headers = 'From: '.$mailFrom."\r\n".
    'Cc: '.$mailCc."\r\n" .
    'X-Mailer: PHP/' . phpversion(). "\r\n" ;
$subject = 'Weather Station Down';

$now = new DateTime();
$comment = count($sites)." sites offline: ";

//send mail for each site
if (count($sites) > 0) {
    foreach($sites as $id=>$info){
        $sql = "SELECT count(*) FROM alerts_sent
                WHERE site={$id} AND timestamp > DATE_SUB('".$now->format("Y-m-d H:i:s")."',INTERVAL 1 DAY)";
        //echo "SQL:{$sql}\n";
        $result = mysqli_query($mysqli, $sql);
        $row    = mysqli_fetch_array($result);
        $rc     = $row[0];
        mysqli_free_result($result);
        if ($rc ==0){
            $comment .= $info['name']."(Sent Mail), ";
            $msg = "Weather station {$id} at {$info['name']} has not transmitted since
            {$info['Eastern Time']} Eastern Time ({$info['Local Time']} Local Time). ";
            //echo "msg:{$msg}\n";
            // Send mail
            mail($mailTo, $subject, $msg, $headers);
            $sql = "INSERT INTO alerts_sent(timestamp, site, sent_to, lastts) VALUES (?,?,?,?)";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('siss',$now->format("Y-m-d H:i:s"), $id, $mailTo, $info['Local Time']);
            $ok = $stmt->execute();
            $stmt->close();
        } else {
            $comment .= $info['name']."(Not Mailed), ";

        }
    }
    $comment = substr($comment,0,-2);
}

//insert the check record
$sql = "INSERT INTO alertcheck(script,timestamp,comment) VALUES ('check_devices',?,?)";
$stmt = $mysqli->prepare($sql);
$checktime = $now->format("Y-m-d H:i:s");
$stmt->bind_param('ss',$checktime, $comment);
$ok = $stmt->execute();
$stmt->close();
$mysqli->close();
?>