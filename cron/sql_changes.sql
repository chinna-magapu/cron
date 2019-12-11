-- 2018-07-05
-- Table structure for table `wx_clone`
--

CREATE TABLE IF NOT EXISTS `wx_clone` (
  `wsid_src` int(10) unsigned NOT NULL,
  `wsid_dst` int(10) unsigned NOT NULL,
  `code_src` varchar(3) NOT NULL,
  `code_dst` varchar(3) NOT NULL,
  `id_src` int(10) unsigned NOT NULL,
  `id_dst` int(10) unsigned NOT NULL,
  `sdate` date NOT NULL,
  `edate` date default NULL,
  PRIMARY KEY  (`wsid_src`,`wsid_dst`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `wx_clone`
--

INSERT INTO `wx_clone` (`wsid_src`, `wsid_dst`, `code_src`, `code_dst`, `id_src`, `id_dst`, `sdate`, `edate`) VALUES
(71834, 718341, 'SAR', 'GTC', 809, 813, '2108-07-05', NULL);

INSERT INTO station_deployment (`wsid`, `sdate`, `edate`, `type`, `idsite`, `code`, `name`, `tablename`, `mac`, `timezone`)
SELECT 718341, `sdate`, `edate`, `type`, 813, 'GTC', 'Greentree', `tablename`, `mac`, `timezone` FROM `station_deployment` WHERE wsid =  71834 AND code='SAR' AND sdate='2018-07-05'

-- 2018-07-05
DROP VIEW `vw_currentws_deployment` ;
CREATE ALGORITHM=UNDEFINED DEFINER=`bioappdb`@`localhost` SQL SECURITY DEFINER VIEW `vw_currentws_deployment` AS 
select `station_deployment`.`wsid` AS `wsid`,`station_deployment`.`sdate` AS `sdate`,`station_deployment`.`edate` AS `edate`,
`station_deployment`.`type` AS `type`,`station_deployment`.`idsite` AS `idsite`,`station_deployment`.`code` AS `code`,
`station_deployment`.`name` AS `name`,`station_deployment`.`tablename` AS `tablename`,`station_deployment`.`mac` AS `mac`,
`station_deployment`.`timezone` AS `timezone` , `station`.`clone`
FROM `station_deployment` 
INNER JOIN `station` ON `station_deployment`.`wsid` = `station`.`wsid`
where isnull(`station_deployment`.`edate`);