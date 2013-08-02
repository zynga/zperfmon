-- 
--  Copyright 2013 Zynga Inc.
--  
--  Licensed under the Apache License, Version 2.0 (the "License");
--     you may not use this file except in compliance with the License.
--     You may obtain a copy of the License at
--  
--     http://www.apache.org/licenses/LICENSE-2.0
--  
--     Unless required by applicable law or agreed to in writing, software
--       distributed under the License is distributed on an "AS IS" BASIS,
--       WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
--     See the License for the specific language governing permissions and
--     limitations under the License.
--  

CREATE TABLE IF NOT EXISTS `vertica_stats_30min` (
  `web_cpu_idle` float default '100',
  `web_cpu_iowait` float default '0',
  `web_mem_used` bigint(20) default '0',
  `web_mem_free` bigint(20) default '0',
  `web_nw_rx_pkts` int(11) default '0',
  `web_nw_rx_bytes` bigint(20) default '0',
  `web_nw_tx_pkts` int(11) default '0',
  `web_nw_tx_bytes` bigint(20) default '0',
  `web_rps` float default '0',
  `db_cpu_idle` float default '100',
  `db_cpu_iowait` float default '0',
  `db_mem_free` bigint(20) default '0',
  `db_nw_rx_pkts` int(11) default '0',
  `db_nw_rx_bytes` bigint(20) default '0',
  `db_nw_tx_pkts` int(11) default '0',
  `db_nw_tx_bytes` bigint(20) default '0',
  `mc_cpu_idle` float default '100',
  `mc_cpu_iowait` float default '0',
  `mc_mem_free` bigint(20) default '0',
  `mc_nw_rx_pkts` int(11) default '0',
  `mc_nw_rx_bytes` bigint(20) default '0',
  `mc_nw_tx_pkts` int(11) default '0',
  `mc_nw_tx_bytes` bigint(20) default '0',
  `timestamp` timestamp NOT NULL default '0000-00-00 00:00:00',
  `web_cpu_system` float default '0',
  `web_cpu_user` float default '0',
  PRIMARY KEY  (`timestamp`),
  KEY `timestamp` USING BTREE (`timestamp`)
);


-- daily aggregated table

CREATE TABLE IF NOT EXISTS `vertica_stats_daily` LIKE vertica_stats_30min;

