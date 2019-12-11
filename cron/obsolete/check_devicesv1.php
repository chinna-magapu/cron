<?php
/* modified 4/06/2010 to work with timestamps in device local time rather than UTC */

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

$mailTo   = 'mlpeterson23@gmail.com';
$mailCc   = 'cmeadow@trefoil.com';
$mailFrom = 'admin@bioappeng.com';
// copied credentials from database_inc, not sure whether this will execute in same directory
$BA_MYSQL_HOST   = '66.186.177.211';
$BA_MYSQL_USER   = 'bioappdb';
$BA_MYSQL_PASS   = 'nutm8g';
$BA_MYSQL_DB_NAME= 'bioappeng';

$oldtz = date_default_timezone_get();
$mysqli = new mysqli($BA_MYSQL_HOST, $BA_MYSQL_USER, $BA_MYSQL_PASS, $BA_MYSQL_DB_NAME);

// get comparison time in US/Eastern
date_default_timezone_set("US/Eastern");
$now = new DateTime();
$now->modify('-30 minute');
$comptime = strtotime($now->format('Y-m-d H:i'));

// get latest timestamp from all sites
$sites = Array();
$sql = "SELECT site_idsite, name, mysql_timezone, MAX(timestamp)
        FROM weather INNER JOIN site ON weather.site_idsite = site.idsite
        WHERE  1
        GROUP BY site_idsite, name, mysql_timezone";
//        HAVING max(timestamp) < DATE_SUB('".$gmt->format("Y-m-d H:i")."',INTERVAL 30 MINUTE)";

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

// mail setup
$headers = 'From: '.$mailFrom."\r\n".
    'Cc: '.$mailCc."\r\n" .
    'X-Mailer: PHP/' . phpversion(). "\r\n" ;
$subject = 'Weather Station Down';

date_default_timezone_set($oldtz);
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
$sql = "INSERT INTO alertcheck(timestamp,comment) VALUES (?,?)";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ss',$now->format("Y-m-d H:i:s"), $comment);
$ok = $stmt->execute();
$stmt->close();
$mysqli->close();
?>