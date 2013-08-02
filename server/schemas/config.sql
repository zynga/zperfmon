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

CREATE DATABASE IF NOT EXISTS config;

CREATE TABLE IF NOT EXISTS config.gameidmap
(
  `id`  int(6) default NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
);


LOCK TABLES config.gameidmap WRITE;
INSERT IGNORE INTO config.gameidmap VALUES('88','adventure_prod');
INSERT IGNORE INTO config.gameidmap VALUES('61','cafe');
INSERT IGNORE INTO config.gameidmap VALUES('223','castle_prod');
INSERT IGNORE INTO config.gameidmap VALUES('75','city');
INSERT IGNORE INTO config.gameidmap VALUES('81','empire');
INSERT IGNORE INTO config.gameidmap VALUES('90','familyville');
INSERT IGNORE INTO config.gameidmap VALUES('63','farm');
INSERT IGNORE INTO config.gameidmap VALUES('66','fish');
INSERT IGNORE INTO config.gameidmap VALUES('119','forestville');
INSERT IGNORE INTO config.gameidmap VALUES('67','frontier');
INSERT IGNORE INTO config.gameidmap VALUES('115','hidden_chronicles');
INSERT IGNORE INTO config.gameidmap VALUES('237','towerville');
INSERT IGNORE INTO config.gameidmap VALUES('69','treasure');
INSERT IGNORE INTO config.gameidmap VALUES('46','vampires');
INSERT IGNORE INTO config.gameidmap VALUES('45','yoville');
INSERT IGNORE INTO config.gameidmap VALUES('232','zoomobile');
UNLOCK TABLES;