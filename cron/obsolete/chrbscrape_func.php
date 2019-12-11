<?php
function chrbscrape($script){
	// defines a common function for wu_patchrw and chrb15
    global $mysqli;
	$trackndx = array('Golden Gate Fields'=>307,'Santa Anita'=>304,'Los Alamitos'=>306,'Del Mar'=>107);
	$wsidndx  = array('304'=>381821,'306'=>381822,'307'=>381823,'107'=>381824);

    $url = 'http://www.westernwx.com/history/SoCal/chrblast.htm';
    $txt = file_get_contents($url);
    if ($txt != ""){
        $lines = explode("\n",$txt);
		//print_r($lines);
        $i = 0;
        while($i <count($lines) && substr(trim($lines[$i++]),-1,1) != "%"){
            null;
        }
        $i++;  //skip -----------
        $ok = true;
        $skipflag = false;

        for ($j = $i; $j < count($lines); $j++){
        	$trackname = trim(substr($lines[$j],0,22));
			@$idsite = $trackndx[$trackname];
			@$wsid   = $wsidndx[$idsite];
			$line = substr($lines[$j],22);
			// the sentinel values (bad data?) cause problems because there is no delimiter
			// Santa Anita           03/30/15 1300   78.1  50.8    38   SSW   3.2   9.4   0.00  0.00   50.4  79.0    66.8   98.0   19.2-7999.0
            // so we'll replace them with -- which is then converted to NULL

        	$line = str_replace("-7999.0"," -- ",$line);
        	$line = str_replace("7999.0"," -- ",$line);
        	$line = str_replace("-6999.0"," -- ",$line);
        	$line = str_replace("6999.0"," -- ",$line);
            $line = str_squeeze($line);
            $data = explode(" ",$line);
			//print_r($data);//die;
            if (count($data) >= 16) {
            	// php may drop a leading 0
				$chrbtemp = $data[2];
				$chrbrh   = $data[4];
				$heatndx  = HeatIndex($chrbtemp, $chrbrh);
				$windch   = windChill($chrbtemp, $data[6]);
				if (strlen($data[0])==7) $data[0] = '0'.$data[0];
				if (strlen($data[1])< 4) $data[1] = substr('0000'.$data[0],-4,4);
                $date = "20".substr($data[0],6,2)."-".substr($data[0],0,2)."-".substr($data[0],3,2)." "
                            .substr($data[1],0,2).":".substr($data[1],2,2).":00";
                $wdir = winddir($data[5]);
				echo "\nWeather for {$trackname} ... ";
				$sql = "REPLACE INTO weather (src, idsite, wsid, timestamp, tout, dewpoint, hum,
						baro, wspd, wdir, gust, rf, srad, uv, t1, t2,
						wsbatt, rssi, txid, mv1, mv2, ver, cumrf, heatindex, windchill) VALUES
						('CHRB','{$idsite}',{$wsid},'{$date}',{$data[2]},{$data[3]},{$data[4]},
						NULL,".($data[6]*10).",{$wdir},".($data[7]*10).",".($data[8]*100).",NULL,NULL,".dashNull($data[12]).",".dashNull($data[13])
						.",NULL,NULL,NULL,".dashNull($data[14]).",".dashNull($data[15]).",'chrblast.htm',".($data[9]*100).",{$heatndx},{$windch})";
				$ok  = mysqli_query($mysqli,$sql);
				echo ($ok ? " INS OK " : " INS ERROR ");
				//echo "\n$sql\n";
				echo $mysqli->error;
            }
        }
    } else {
        echo "Failed to retrieve data.";
    }
	$mach = php_uname("n");
	$sql = "INSERT INTO alertcheck (script, comment, machine) VALUES ('{$script}','CHRB Scrape Complete','{$mach}')";
	$ok = mysqli_query($mysqli,$sql);
}
?>
