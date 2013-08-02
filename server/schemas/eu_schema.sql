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

CREATE DATABASE IF NOT EXISTS eu;

CREATE TABLE IF NOT EXISTS eu.common_eu (
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `game` varchar(30) NOT NULL,
  `class` varchar(20) NOT NULL,
  `hostgroup` varchar(80) NOT NULL,
  `plugin` varchar(60) NOT NULL,
  `metric` varchar(50) NOT NULL,
  `value` double default NULL,
  `stddev` float NOT NULL,
   PRIMARY KEY (timestamp, game, class, hostgroup, plugin, metric)
);

