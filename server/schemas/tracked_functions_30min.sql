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

CREATE TABLE IF NOT EXISTS tracked_functions_flip_count
(
timestamp TIMESTAMP NOT NULL,
page VARCHAR(255) NOT NULL,
PRIMARY KEY (timestamp,page)
);

CREATE TABLE IF NOT EXISTS tracked_functions_flip_excl_time
(
timestamp TIMESTAMP NOT NULL,
page VARCHAR(255) NOT NULL,
PRIMARY KEY (timestamp,page)
);

CREATE TABLE IF NOT EXISTS tracked_functions_flip_incl_time
(
timestamp TIMESTAMP NOT NULL,
page VARCHAR(255) NOT NULL,
PRIMARY KEY (timestamp,page)
);



DROP PROCEDURE IF EXISTS add_new_tracked_index;

DELIMITER //
CREATE PROCEDURE add_new_tracked_index(IN tbl TEXT) BEGIN
	IF NOT EXISTS(SELECT * from information_schema.COLUMNS  WHERE COLUMN_NAME = 'page' AND TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl) THEN
 			call run_query(CONCAT("ALTER TABLE `", tbl, "` ADD COLUMN `page` VARCHAR(255);"));
 	END IF;

	IF NOT EXISTS(SELECT * from information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'PRIMARY' AND TABLE_NAME= tbl) THEN
			call run_query(CONCAT("ALTER TABLE `", tbl, "` ADD PRIMARY KEY(timestamp, page(255));"));
	ELSE
			call run_query(CONCAT("ALTER TABLE `", tbl, "` ADD PRIMARY KEY(timestamp, page(255)), DROP PRIMARY KEY;"));
	END IF;
END//

DELIMITER ';'

CALL add_new_tracked_index('tracked_functions_flip_incl_time');
CALL add_new_tracked_index('tracked_functions_flip_excl_time');
CALL add_new_tracked_index('tracked_functions_flip_count');

DROP PROCEDURE IF EXISTS add_new_tracked_index;


CREATE TABLE IF NOT EXISTS tracked_functions_30min
(
timestamp   TIMESTAMP NOT NULL,
gameid	    SMALLINT,
page        VARCHAR(256) NOT NULL,
function    VARCHAR(255) NOT NULL,
count       INT DEFAULT 0,
incl_time   FLOAT(10,3), 
excl_time   FLOAT(10,3),
PRIMARY KEY (timestamp, function),
INDEX tracked_index (timestamp,gameid,page,function)
);

ALTER TABLE `tracked_functions_30min` 
	ADD PRIMARY KEY(timestamp, page(64), function(144)), 
	DROP PRIMARY KEY;


-- daily aggregated table

CREATE TABLE IF NOT EXISTS tracked_functions_daily_flip_count LIKE tracked_functions_flip_count;

CREATE TABLE IF NOT EXISTS tracked_functions_daily_flip_excl_time LIKE tracked_functions_flip_excl_time;

CREATE TABLE IF NOT EXISTS tracked_functions_daily_flip_incl_time LIKE tracked_functions_flip_incl_time;

CREATE TABLE IF NOT EXISTS tracked_functions_daily LIKE tracked_functions_30min;


delimiter //

DROP PROCEDURE IF EXISTS pivot_tracked_functions_full//

CREATE PROCEDURE pivot_tracked_functions_full(IN col TEXT)
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE func_name TEXT ;
	DECLARE func_col TEXT;
	DECLARE juggle_table TEXT;
	DECLARE uniq_functions CURSOR FOR 
		SELECT DISTINCT `function` FROM tracked_functions_30min LIMIT 100;

	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	OPEN uniq_functions;

 	SET @query := CONCAT("SELECT SQL_CACHE timestamp, page ");
	
 	func_loop: loop
 		FETCH uniq_functions INTO func_name;
  		IF end_loop = 1 THEN
  			leave func_loop;
  		END IF;
		SET @func_col := LEFT(CONCAT("",func_name), 64);
		SET @query := CONCAT(@query, ", SUM(IF(function = '",func_name,"', ", col, ", 0)) as `", @func_col,"` ");
 	END LOOP func_loop;
 
 	SET @query := CONCAT(@query, " FROM tracked_functions_30min GROUP BY timestamp,page;");

	call materialize_view(CONCAT("tracked_functions_flip_",col),@query);
	
  	CLOSE uniq_functions;
	
END//


DROP PROCEDURE IF EXISTS pivot_tracked_functions//

CREATE PROCEDURE pivot_tracked_functions(IN col TEXT)
BEGIN
        DECLARE end_loop INT DEFAULT 0;
        DECLARE query TEXT;
        DECLARE func_name TEXT ;
        DECLARE func_col TEXT;
        DECLARE uniq_functions CURSOR FOR 
                SELECT DISTINCT `function` FROM tracked_functions_30min LIMIT 100; 

        DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;
	
	call pivot_tracked_add_columns(col);
        OPEN uniq_functions;

        SET @query := CONCAT("INSERT into tracked_functions_flip_",col," (timestamp, page");

        column_loop: loop
                FETCH uniq_functions INTO func_name;
                IF end_loop = 1 THEN
                        leave column_loop;
                END IF;
                SET @func_col := LEFT(CONCAT("",func_name), 64);
                SET @query := CONCAT(@query, ", `", @func_col,"` ");
        END LOOP column_loop;

        SET @query := CONCAT(@query, ") ");
        SELECT @query;
        CLOSE uniq_functions;

        -- re-open same cursor 

        OPEN uniq_functions;
        SET end_loop := 0;

        SET @query := CONCAT(@query, " SELECT SQL_CACHE timestamp, page ");

        func_loop: loop
                FETCH uniq_functions INTO func_name;
                IF end_loop = 1 THEN
                        leave func_loop;
                END IF;
                SET @func_col := LEFT(CONCAT("",func_name), 64);
                SET @query := CONCAT(@query, ", SUM(IF(function = '",func_name,"', ", col, ", 0)) as `", @func_col,"` ");
        END LOOP func_loop;

        SET @query := CONCAT(@query, " FROM tracked_functions_30min");
        -- back-fill it
        SET @query := CONCAT(@query, " WHERE timestamp > (select IFNULL(MAX(timestamp),from_unixtime(1)) from tracked_functions_flip_",col,")");
        SET @query := CONCAT(@query, " GROUP BY timestamp, page ;");

        call run_query(@query);

        CLOSE uniq_functions;
        
END//

DROP PROCEDURE IF EXISTS pivot_tracked_add_columns//

CREATE PROCEDURE pivot_tracked_add_columns(IN col TEXT)
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE function_name TEXT ;
	DECLARE function_col TEXT;
	DECLARE missing_columns CURSOR FOR
		SELECT `function` FROM (SELECT DISTINCT `function` from tracked_functions_30min LIMIT 4000) as functions WHERE `function` NOT IN (select column_name from information_schema.columns where table_schema = DATABASE() and table_name = CONCAT("tracked_functions_flip_",col) and column_name <> "timestamp" and column_name <> "page");
	
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	OPEN missing_columns;

 	SET @query := CONCAT("ALTER TABLE `tracked_functions_flip_",col,"` ");
	
 	function_loop: loop
 		FETCH missing_columns INTO function_name;
  		IF end_loop = 1 THEN
  			leave function_loop;
  		END IF;
		SET @function_col := CONCAT("",function_name);
		SET @query := CONCAT(@query, " ADD COLUMN `", @function_col,"` double(20,3) DEFAULT 0, ");
 	END LOOP function_loop;

 	SET @query := CONCAT(@query, " ORDER BY `timestamp`;");

	call run_query(@query);

  	CLOSE missing_columns;
END//

-- Daily aggregated procedures 

DROP PROCEDURE IF EXISTS pivot_tracked_functions_daily//

CREATE PROCEDURE pivot_tracked_functions_daily(IN col TEXT)
BEGIN
        DECLARE end_loop INT DEFAULT 0;
        DECLARE query TEXT;
        DECLARE func_name TEXT ;
        DECLARE func_col TEXT;
        DECLARE uniq_functions CURSOR FOR 
                SELECT DISTINCT `function` FROM tracked_functions_daily LIMIT 100; 

        DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;
	
	call pivot_tracked_daily_add_columns(col);
        OPEN uniq_functions;

        SET @query := CONCAT("INSERT into tracked_functions_daily_flip_",col," (timestamp, page");

        column_loop: loop
                FETCH uniq_functions INTO func_name;
                IF end_loop = 1 THEN
                        leave column_loop;
                END IF;
                SET @func_col := LEFT(CONCAT("",func_name), 64);
                SET @query := CONCAT(@query, ", `", @func_col,"` ");
        END LOOP column_loop;

        SET @query := CONCAT(@query, ") ");
        SELECT @query;
        CLOSE uniq_functions;

        -- re-open same cursor 

        OPEN uniq_functions;
        SET end_loop := 0;

        SET @query := CONCAT(@query, " SELECT SQL_CACHE timestamp, page ");

        func_loop: loop
                FETCH uniq_functions INTO func_name;
                IF end_loop = 1 THEN
                        leave func_loop;
                END IF;
                SET @func_col := LEFT(CONCAT("",func_name), 64);
                SET @query := CONCAT(@query, ", SUM(IF(function = '",func_name,"', ", col, ", 0)) as `", @func_col,"` ");
        END LOOP func_loop;

        SET @query := CONCAT(@query, " FROM tracked_functions_daily");
        -- back-fill it
        SET @query := CONCAT(@query, " WHERE timestamp > (select IFNULL(MAX(timestamp),from_unixtime(1)) from tracked_functions_daily_flip_",col,")");
        SET @query := CONCAT(@query, " GROUP BY timestamp, page ;");

        call run_query(@query);

        CLOSE uniq_functions;
        
END//

DROP PROCEDURE IF EXISTS pivot_tracked_daily_add_columns//

CREATE PROCEDURE pivot_tracked_daily_add_columns(IN col TEXT)
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE function_name TEXT ;
	DECLARE function_col TEXT;
	DECLARE missing_columns CURSOR FOR
		SELECT `function` FROM (SELECT DISTINCT `function` from tracked_functions_daily LIMIT 4000) as functions WHERE `function` NOT IN (select column_name from information_schema.columns where table_schema = DATABASE() and table_name = CONCAT("tracked_functions_daily_flip_",col) and column_name <> "timestamp" and column_name <> "page");
	
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	OPEN missing_columns;

 	SET @query := CONCAT("ALTER TABLE `tracked_functions_daily_flip_",col,"` ");
	
 	function_loop: loop
 		FETCH missing_columns INTO function_name;
  		IF end_loop = 1 THEN
  			leave function_loop;
  		END IF;
		SET @function_col := CONCAT("",function_name);
		SET @query := CONCAT(@query, " ADD COLUMN `", @function_col,"` double(20,3) DEFAULT 0, ");
 	END LOOP function_loop;

 	SET @query := CONCAT(@query, " ORDER BY `timestamp`;");

	call run_query(@query);

  	CLOSE missing_columns;
END//



delimiter ;
