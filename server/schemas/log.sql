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

DROP PROCEDURE IF EXISTS drop_old_logs_table;

DELIMITER //
CREATE PROCEDURE drop_old_logs_table() BEGIN
    /* delete columns if they exist */
    IF (EXISTS (SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'log')) AND (NOT EXISTS (SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'log' AND column_name = 'log_id')) THEN
		DROP TABLE `log`;
    END IF;
END//

DELIMITER ';'
CALL drop_old_logs_table();

DROP PROCEDURE IF EXISTS drop_old_logs_table;



CREATE TABLE IF NOT EXISTS log
(
	log_id	     int 		NOT NULL AUTO_INCREMENT,
	timestamp    timestamp         	NOT NULL DEFAULT CURRENT_TIMESTAMP, -- logging time 
	log_level     ENUM(
			'EMERG',	/* system is unusable */
			'ALERT',	/* action must be taken immediately */
			'CRIT',		/* critical conditions */
			'ERR',		/* error conditions */
			'WARNING',	/* warning conditions */
			'NOTICE',	/* normal but significant condition */
			'INFO',		/* informational */
			'DEBUG'		/* debug-level messages */
			) 		NOT NULL DEFAULT 'INFO',
	module_name  varchar(64)       	NOT NULL, -- name of the module (e.g. uploader, blob processor etc)
	message      varchar(1024)      NOT NULL, -- log message
	PRIMARY KEY(log_id,timestamp)
);
