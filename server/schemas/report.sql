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

CREATE DATABASE IF NOT EXISTS report;

CREATE TABLE IF NOT EXISTS report.instance_utilization
(
  `date` DATE NOT NULL ,
  `game` varchar(255) NOT NULL,
  `total_instance` int(6) default NULL,
  `DAU` int(11) default NULL,
  `DAU_per_instance` int(11) default NULL,
  `optimal_instance_count` int(11) default NULL,
  `slack_per` decimal(4,2) default NULL,
  `cloud_id` int(11) default NULL,
  PRIMARY KEY  (`game`,`date`)
);

CREATE TABLE IF NOT EXISTS report.instance_class_summary
(
  `date` DATE NOT NULL ,
  `game` varchar(255) NOT NULL,
  `class_id` TINYINT(2) NOT NULL,
  `total_instance` int(11) default NULL,
  `DAU_per_instance` int(11) default NULL,
  `optimal_instance_count` int(11) default NULL,
  `slack_per` decimal(4,2) default NULL,
  PRIMARY KEY  (`game`,`date`,`class_id`)
);

CREATE TABLE IF NOT EXISTS report.instance_pool_summary
(
  `date` DATE NOT NULL ,
  `game` varchar(255) NOT NULL,
  `class_id` TINYINT(2) NOT NULL,
  `pool_name` varchar(255) NOT NULL,
  `type_id` TINYINT(2) default NULL,
  `total_instance` int(11) default NULL,
  `DAU_per_instance` int(11) default NULL,
  `utilization_per` decimal(4,2) default NULL,
  `optimal_instance_count` int(11) default NULL,
  `slack_per` decimal(4,2) default NULL,
  `bottleneck` varchar(255) default NULL,
  `underutilized` varchar(255) default NULL,
  `headroom_per` decimal(4,2) default NULL,
  PRIMARY KEY  (`game`,`date`, `pool_name`)
);


DROP TABLE IF EXISTS report.instance_class_name;
CREATE TABLE report.instance_class_name
(
  `class_id` TINYINT(2) NOT NULL,
  `class_name` varchar(255) NOT NULL,
  PRIMARY KEY  (`class_id`,`class_name`)
);

LOCK TABLES  report.instance_class_name WRITE;
INSERT INTO report.instance_class_name VALUES('1','mqueue');
INSERT INTO report.instance_class_name VALUES('2','consumer');
INSERT INTO report.instance_class_name VALUES('3','msched');
INSERT INTO report.instance_class_name VALUES('4','mc');
INSERT INTO report.instance_class_name VALUES('5','proxy');
INSERT INTO report.instance_class_name VALUES('6','web');
INSERT INTO report.instance_class_name VALUES('7','db');
INSERT INTO report.instance_class_name VALUES('8','mb');
UNLOCK TABLES;
