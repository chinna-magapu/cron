<?php
$hostname = php_uname("n");
$datadir  = $hostname == "VPS" ? "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS" : '../../data/WS';
$datapath = $hostname == "VPS" ? "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS\\" : '../../data/WS/';

$datadir  = "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS";
$datapath = "D:\\HostingSpaces\\admin\\bioappeng.us\\data\\WS\\";

foreach (glob("{$datapath}*_WS.dat") as $filename) {
	echo "$filename\nBasename: ". basename($filename)." || size " . filesize($filename) . "\n";
}
?>