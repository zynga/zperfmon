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


CREATE DATABASE IF NOT EXISTS rightscale;

-- USE rightscale;

CREATE TABLE IF NOT EXISTS rightscale.instances
(
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `cloud_id` int(11) default '-1',
  `deploy_id` int(11) NOT NULL default '0',
  `deploy_name` varchar(255) default NULL,
  `array_id` int(11) NOT NULL default '0',
  `array_name` varchar(255) default NULL,
  `hostname` varchar(255) character set latin1 collate latin1_general_cs NOT NULL default '',
  `hostgroup` varchar(255) character set latin1 collate latin1_general_cs default NULL,
  `type` enum('UNKNOWN',
	      'C1.MEDIUM',
	      'C1.XLARGE',
	      'CC1.4XLARGE',
	      'CG1.4XLARGE',
	      'M1.LARGE',
	      'M1.SMALL',
	      'M1.XLARGE',
	      'M2.2XLARGE',
	      'M2.4XLARGE',
	      'M2.XLARGE',
	      'C1.M72.DR800',
	      'C1.M24.DR140',
	      'C1.M72.DR140') default 'UNKNOWN',
  `status` enum('UNKNOWN',
	      'BOOTING',
	      'CONFIGURING',
	      'DECOMMISSIONING',
	      'OPERATIONAL',
	      'RUNNING',
	      'PENDING',
	      'STOPPED',
	      'STRANDED_IN_BOOTING',
	      'STRANDED_IN_CONFIGURING',
	      'STRANDED_IN_OPERATIONAL',
	      'STRANDED_IN_DECOMMISSIONING') default 'UNKNOWN',
  `pricing` enum('UNKNOWN','ON_DEMAND') default 'UNKNOWN',
  `public_ip` varchar(16) default NULL,
  `private_ip` varchar(16) default NULL,
  `aws_id` varchar(32) default NULL,
  `sketchy_id` varchar(32) default NULL,
  `birthtime` timestamp NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`hostname`,`deploy_id`,`array_id`,`timestamp`,`aws_id`)
);

CREATE TABLE IF NOT EXISTS rightscale.deployments
(
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `cloud_id` int(11) default '-1',
  `id` int(11) NOT NULL,
  `name` varchar(255) default NULL,
  `href` varchar(1024) default NULL,
  PRIMARY KEY  (`id`,`timestamp`)
);


CREATE TABLE IF NOT EXISTS rightscale.templates
(
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `id` int(11) NOT NULL,
  `type` enum('UNKNOWN',
	      'C1.MEDIUM',
	      'C1.XLARGE',
	      'CC1.4XLARGE',
	      'CG1.4XLARGE',
	      'M1.LARGE',
	      'M1.SMALL',
	      'M1.XLARGE',
	      'M2.2XLARGE',
	      'M2.4XLARGE',
	      'M2.XLARGE',
              'C1.M72.DR800',
              'C1.M24.DR140',
              'C1.M72.DR140') default 'UNKNOWN',
  `name` varchar(255) default NULL,
  PRIMARY KEY  (`id`,`timestamp`)
);

-- Create the pseudo-static nodetype table. Since we don't have any
-- generated data, the table is dropped and re-created.

DROP TABLE IF EXISTS rightscale.nodetype;
CREATE TABLE rightscale.nodetype (
  `id` TINYINT(2) NOT NULL,
  `type` varchar(16) NOT NULL,
  `name` varchar(60) default NULL,
  `one_time_cost` int(10) unsigned default NULL,
  `cost_per_hour` float default NULL,
  `num_of_cpu` smallint(5) unsigned default NULL,
  `platform` varchar(10) default NULL,
  `memory_mb` float unsigned default NULL,
  `network` float unsigned default NULL
);

--
-- Populate nodetype table with pre-computed costs, thresholds and limits
--

LOCK TABLES rightscale.nodetype WRITE;

INSERT INTO rightscale.nodetype VALUES
(1,'M1.LARGE','LARGE',0,1,2,'64',7680,10240),
(2,'M1.SMALL','SMALL',0,1,1,'32',1740.8,1024),
(3,'M1.XLARGE','XLARGE',0,1,4,'64',15360,10240),
(4,'M2.XLARGE','HMEM XL',0,1,2,'64',17510.4,1024),
(5,'M2.2XLARGE','HMEM XXL',0,1,4,'64',35020.8,10240),
(6,'M2.4XLARGE','HMEMXXXXL',0,1,8,'64',70041.6,10240),
(7,'C1.MEDIUM','HCPUMEDIUM',0,1,2,'32',1740.8,1024),
(8,'C1.XLARGE','HCPUXL',0,1,8,'64',7168,10240),
(9,'CC1.4XLARGE','CCXXXXL',0,1,16,'64',23552,10240),
(10,'CG1.4XLARGE','CGXXXXL',0,1,16,'64',22528,10240),
(11,'T1.MICRO','micro',0,1,2,'32/64',613,100),
(12,'C1.M24.DR140','web',0,1,16,'64',22528,10240),
(13,'C1.M72.DR140','db',0,1,16,'64',71680,10240),
(14,'C1.M72.DR800','memcache',0,1,16,'64',71680,10240);
UNLOCK TABLES;

DROP TABLE IF EXISTS rightscale.clouds;
CREATE TABLE rightscale.clouds (
  `id` int(11) NOT NULL,
  `name` varchar(60) default NULL,
  PRIMARY KEY  (`id`)
);

--
-- Populate clouds table with pre-known values
--

LOCK TABLES rightscale.clouds WRITE;

INSERT INTO rightscale.clouds VALUES
('1','ec2'),
('858','zc1-858'),
('1384','zc1-1384');
UNLOCK TABLES;

