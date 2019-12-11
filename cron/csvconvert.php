<?php
echo "MySQL CSV Converter (tabs to semicolons) ... ";
if ($argc < 3) {
    echo "Usage php csvconvert infile outfile ";
    exit;
}
$text = file_get_contents($argv[1]);
$textlines = explode("\r\n",$text);
$out = "";
foreach($textlines as $line){
    if (strlen($line)){
        $cols = explode("\t",$line);
        $newcols = '"'.implode('";"',$cols).'"'."\r\n";
        $out .= $newcols;
    }
}
file_put_contents($argv[2],$out);
echo "Done.\n";
echo "\nIn:    ",$argv[1],"\nOut:   ",$argv[2],"\n";
?>
