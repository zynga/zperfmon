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

CREATE TABLE IF NOT EXISTS `splunk_stats` (
  `timestamp` timestamp NOT NULL default '0000-00-00 00:00:00',
  `query_name` VARCHAR(255),
  `value` bigint(20) default '0',
  PRIMARY KEY  (`timestamp`, `query_name`),
  KEY `timestamp` USING BTREE (`timestamp`)
);
