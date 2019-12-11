/* most stations, but special case Keeneland 71834 and CD 77935 BUT KEENELAND IS NOT ACTIVE AT THIS TIME
$table_suffix = ($idsite == 104 && $wsid == 71834) || ($idsite == 102 && $wsid == 77935) ? "2" : "";
*/

-- CS | BEL 802 70127 | SAR 71834 809 (ended 2018-09-28 17:50 | DMR 30972 107 | SA 28416 304 |
REPLACE INTO weather
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, srad, t1, t2, mv1, mv2, ver, dewpoint, heatindex, windchill, cumrf, wsbatt, src)
SELECT idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, AVG(solarkw)*1000 AS srad,
	AVG(1.8 * soiltempbot  + 32.0) AS t1, AVG(1.8 * soiltemptop  + 32.0) AS t2, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'BAE WS 2014-08' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf, 100 * AVG(batt) as wsbatt, 'CS' as src
FROM minuteweather
WHERE wsid=70127 AND idsite=802 AND timestamp >= '2019-01-02 00:01' AND timestamp <= '2019-01-17 00:00'
GROUP BY idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00'));

-- CS 77935 102
REPLACE INTO weather2
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, srad, t1, t2, mv1, mv2, ver, dewpoint, heatindex, windchill, cumrf, wsbatt, src)
SELECT idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, AVG(solarkw)*1000 AS srad,
	AVG(1.8 * soiltempbot  + 32.0) AS t1, AVG(1.8 * soiltemptop  + 32.0) AS t2, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'BAE WS 2014-08' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf, 100 * AVG(batt) as wsbatt, 'CS' as src
FROM minuteweather
WHERE wsid=77935 AND idsite=102 AND timestamp >= '2019-01-02 00:01' AND timestamp <= '2019-01-17 00:00'
GROUP BY idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00'));

-- FG 62304 / KEE 82367
REPLACE INTO weather2rf
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, rf2, srad, t1, t2, mv1, mv2, ver, dewpoint, heatindex,
	windchill, cumrf, cumrf2, wsbatt, src)
SELECT idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, SUM(3.93701 * rf2) AS rf2, AVG(solarkw)*1000 AS srad,
	AVG(1.8 * soiltempbot  + 32.0) AS t1, AVG(1.8 * soiltemptop  + 32.0) AS t2, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'CS201609' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf, 3.93701 * MAX(cumrf2) AS cumrf2, 100 * AVG(batt) as wsbatt, 'CS' as src
FROM minuteweatherfg
WHERE wsid=62304 AND idsite=103 AND timestamp >= '2019-01-15 00:01' AND timestamp <= '2019-01-17 00:00'
GROUP BY idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00'))

-- AQU function AggregateToWeather_ss($wsid, $first_min, $last_min)
REPLACE INTO wx_singlesensor15m
(`wsid`, `sensorcol`, `idsite`, `code`,  `timestamp`,  `dataval`, `agg_val`)
SELECT wsid, sensorcol, idsite, code,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,dataval, agg_val 
FROM wx_singlesensor1m
WHERE wsid='{$wsid}' AND sensorcol='rf' AND timestamp BETWEEN '{$first_ts}' AND  '{$last_ts}'
GROUP BY wsid, qtrts
/*
";
	switch ($sensorcol) {
		case 'rf':
	    	$expr = "SUM(3.93701 * dataval) AS dataval";
	    	$aggr = "MAX(3.93701 * agg_val) AS agg_val";
			break;
	}
$sql .= "{$expr}, {$aggr}
*/

-- FG
REPLACE INTO weather2rf
(idsite, wsid, timestamp, tout, hum, baro, wspd, wdir, gust, rf, rf2, srad, t1, t2, mv1, mv2, ver, dewpoint, heatindex,
	windchill, cumrf, cumrf2, wsbatt, src)
SELECT idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00')) AS qtrts,
	AVG(1.8 * temp +32) AS tout, AVG(RH) AS hum, AVG(0.0393700791974 * baro)*100 AS baro,
	AVG(2.23694 * windspeed)*10 AS wspd, AVG(winddir) AS wdir, MAX(2.23694 * windspeed)*10 AS gust,
	SUM(3.93701 * rf) AS rf, SUM(3.93701 * rf2) AS rf2, AVG(solarkw)*1000 AS srad,
	AVG(1.8 * soiltempbot  + 32.0) AS t1, AVG(1.8 * soiltemptop  + 32.0) AS t2, AVG(vmc*1000) AS mv1, AVG(ec*1000) AS mv2, 'CS201609' AS ver,
	AVG(fn_dewpoint(1.8 * temp +32.0, RH, 1)) AS dewpoint,
	AVG(fn_heatindex(1.8 * temp +32.0, RH, 1)) AS heatindex, AVG(fn_windchill(1.8 * temp +32.0, RH, 1)) AS windchill,
	3.93701 * MAX(cumrf) AS cumrf, 3.93701 * MAX(cumrf2) AS cumrf2, 100 * AVG(batt) as wsbatt, 'CS' as src
FROM minuteweatherfg
WHERE wsid=62304 AND idsite=103 AND timestamp >= '2018-12-19 00:01' AND timestamp <= '2019-01-17'
GROUP BY idsite, wsid,
TIMESTAMP(CAST(DATE(local_ts) AS CHAR(10)),
CONCAT(
CASE WHEN MINUTE(local_ts) BETWEEN 46 AND 59 THEN 1+HOUR(local_ts) ELSE HOUR(local_ts) END,':',
CASE
WHEN MINUTE(local_ts) BETWEEN 46 AND 59 OR MINUTE(local_ts) = 0 THEN '00'
WHEN MINUTE(local_ts) BETWEEN 1 AND 15 THEN '15'
WHEN MINUTE(local_ts) BETWEEN 16 AND 30 THEN '30'
WHEN MINUTE(local_ts) BETWEEN 31 AND 45 THEN '45'
END,':00'));
