<?php
function median($data) {
	return percentile($data, 50);
}

function quartile($data, $quartile) {
	// $quartile is in the range 0-4
	return percentile($data, $quartile * 25);
}

function percentile($Array, $percentile) {
	//accept either numbers 1-100 or 0.01 - 0.99
	if ($percentile > 1 && $percentile <= 100) {
		$percentile *= 0.01;
	}
	if ($percentile == 1){
		return max($Array);
	}
	if ($percentile == 0){
		return min($Array);
	}
	if ($percentile > 0 && $percentile < 1) {
		$pos = (count($Array) - 1) * $percentile;
		$base = floor($pos);
		$rest = $pos - $base;

		if( isset($Array[$base+1]) ) {
			return $Array[$base] + $rest * ($Array[$base+1] - $Array[$base]);
		} else {
			return $Array[$base];
		}
	} else {
		return false;
	}
}

function avg($data) {
	if (count($data)) {
		return array_sum($data) / count($data);
	}
	return false;
}

function stdev($data, $sample = true) {
	if( count($data) < 2 ) {
    	return false;
	}
	$avg = avg($data);
	$sum = 0;
	foreach($data as $value) {
		$sum += pow($value - $avg, 2);
	}
	if ($sample){
		return sqrt((1 / (count($data) - 1)) * $sum);
	} else {
		return sqrt((1 / count($data)) * $sum);
	}
}
?>
