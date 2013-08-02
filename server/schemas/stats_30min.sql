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

CREATE TABLE IF NOT EXISTS stats_30min
(
	timestamp    timestamp    NOT NULL DEFAULT '0000-00-00 00:00:00',
	gameid       smallint(6)  NOT NULL DEFAULT -1,
	DAU          int(11)      NOT NULL DEFAULT -1,
	web_count    int(11)      NOT NULL DEFAULT -1,
	db_count     int(11)      NOT NULL DEFAULT -1,
	mc_count     int(11)      NOT NULL DEFAULT -1,
	mb_count     int(11)      NOT NULL DEFAULT -1,
	admin_count  int(11)	  NOT NULL DEFAULT -1,
	proxy_count  int(11)	  NOT NULL DEFAULT -1,
	queue_count  int(11)	  NOT NULL DEFAULT -1,
	PRIMARY KEY (timestamp),
	INDEX USING BTREE(timestamp)
);


-- daily aggregated table

CREATE TABLE IF NOT EXISTS stats_daily LIKE stats_30min;

