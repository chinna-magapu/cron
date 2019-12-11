<?php
echo "<pre>";
if ($handle = opendir('../moisture')) {
    while (($entry = readdir($handle)) !== false) {
    	if (substr($entry,-4) == ".dat" || substr($entry,-4) == ".DAT" ){
	        echo "$entry\n";
    	}
    }
    closedir($handle);
	echo "\nJOB COMPLETE\n";
} else {
	echo "Can't open ../moisture";
}
?>
