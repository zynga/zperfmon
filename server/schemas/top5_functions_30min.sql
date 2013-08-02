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

CREATE TABLE IF NOT EXISTS top5_functions_30min
(
timestamp   TIMESTAMP NOT NULL,
gameid	    SMALLINT,
page        VARCHAR(256) NOT NULL,
function    VARCHAR(255) NOT NULL,
count       INT DEFAULT 0,
incl_time   FLOAT(10,3), 
excl_time   FLOAT(10,3),
PRIMARY KEY(timestamp, page(64), function(144)),
INDEX top5_index (timestamp,gameid,page,function)
);

ALTER TABLE `top5_functions_30min` 
	ADD PRIMARY KEY(timestamp, page(64), function(144)), 
	DROP PRIMARY KEY;

CREATE TABLE IF NOT EXISTS top5_functions_by_excl_time
(
timestamp   TIMESTAMP NOT NULL,
page        VARCHAR(256) NOT NULL,
function    VARCHAR(255) NOT NULL,
excl_time   FLOAT(10,3),
PRIMARY KEY(timestamp, page(64), function(144)),
INDEX top5_excl_time_index (timestamp,page,function)
);


-- daily aggregated table

CREATE TABLE IF NOT EXISTS top5_functions_daily LIKE top5_functions_30min;

CREATE TABLE IF NOT EXISTS top5_functions_daily_by_excl_time LIKE top5_functions_by_excl_time;


delimiter //

DROP PROCEDURE IF EXISTS rank_top5_functions_full//

CREATE PROCEDURE rank_top5_functions_full(IN col TEXT)
BEGIN
	DECLARE query TEXT;
	
	SET @num := 0;
	SET @page := NULL;
	SET @timestamp := NULL;

	SET @query := CONCAT("SELECT timestamp, page, function, ", col, " ");
	SET @query := CONCAT(@query, " FROM ( ");
	SET @query := CONCAT(@query, "		 SELECT timestamp, page, function,",col,",");
	SET @query := CONCAT(@query, "		 @num := IF (@timestamp=timestamp and @page=page, @num + 1, 1) AS row_number,");
	SET @query := CONCAT(@query, "		 @timestamp := timestamp AS timestamp_dummy, ");
	SET @query := CONCAT(@query, "		 @page := page AS page_dummy");
	SET @query := CONCAT(@query, "		 FROM top5_functions_30min");
	SET @query := CONCAT(@query, "		 ORDER by timestamp, page,",col," desc");
	SET @query := CONCAT(@query, " ) AS x where x.row_number <= 5;");

	call materialize_view(CONCAT("top5_functions_by_",col), @query);
END//


DROP PROCEDURE IF EXISTS rank_top5_functions//


CREATE PROCEDURE rank_top5_functions(IN col TEXT)
BEGIN
	DECLARE query TEXT;	

	SET @num := 0;
        SET @page := NULL;
        SET @timestamp := NULL;
 	SET @query := CONCAT("INSERT into top5_functions_by_",col," (timestamp, page, function, ",col,") ");

        SET @query := CONCAT(@query, " SELECT timestamp, page, function, ", col, " ");
        SET @query := CONCAT(@query, " FROM ( ");
        SET @query := CONCAT(@query, "           SELECT timestamp, page, function,",col,",");
        SET @query := CONCAT(@query, "           @num := IF (@timestamp=timestamp and @page=page, @num + 1, 1) AS row_number,");
        SET @query := CONCAT(@query, "           @timestamp := timestamp AS timestamp_dummy, ");
        SET @query := CONCAT(@query, "           @page := page AS page_dummy");
        SET @query := CONCAT(@query, "           FROM top5_functions_30min WHERE  timestamp > (select IFNULL(MAX(timestamp),from_unixtime(1)) from top5_functions_by_",col,")");
        SET @query := CONCAT(@query, "           ORDER by timestamp, page,",col," desc");
        SET @query := CONCAT(@query, " ) AS x where x.row_number <= 5;");

	
	call run_query(@query);

END//

-- daily aggregated procedure

DROP PROCEDURE IF EXISTS rank_top5_functions_daily//


CREATE PROCEDURE rank_top5_functions_daily(IN col TEXT)
BEGIN
	DECLARE query TEXT;	

	SET @num := 0;
        SET @page := NULL;
        SET @timestamp := NULL;
 	SET @query := CONCAT("INSERT into top5_functions_daily_by_",col," (timestamp, page, function, ",col,") ");

        SET @query := CONCAT(@query, " SELECT timestamp, page, function, ", col, " ");
        SET @query := CONCAT(@query, " FROM ( ");
        SET @query := CONCAT(@query, "           SELECT timestamp, page, function,",col,",");
        SET @query := CONCAT(@query, "           @num := IF (@timestamp=timestamp and @page=page, @num + 1, 1) AS row_number,");
        SET @query := CONCAT(@query, "           @timestamp := timestamp AS timestamp_dummy, ");
        SET @query := CONCAT(@query, "           @page := page AS page_dummy");
        SET @query := CONCAT(@query, "           FROM top5_functions_daily WHERE  timestamp > (select IFNULL(MAX(timestamp),from_unixtime(1)) from top5_functions_daily_by_",col,")");
        SET @query := CONCAT(@query, "           ORDER by timestamp, page,",col," desc");
        SET @query := CONCAT(@query, " ) AS x where x.row_number <= 5;");

	
	call run_query(@query);

END//


delimiter ;
