
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

CREATE TABLE IF NOT EXISTS apache_stats_flip_avg
(
timestamp TIMESTAMP NOT NULL,
PRIMARY KEY(timestamp)
);

CREATE TABLE IF NOT EXISTS apache_stats_flip_max
(
timestamp TIMESTAMP NOT NULL,
PRIMARY KEY(timestamp)
);

CREATE TABLE IF NOT EXISTS apache_stats_flip_min
(
timestamp TIMESTAMP NOT NULL,
PRIMARY KEY(timestamp)
);

CREATE TABLE IF NOT EXISTS apache_stats_flip_count
(
timestamp TIMESTAMP NOT NULL,
PRIMARY KEY(timestamp)
);

CREATE TABLE IF NOT EXISTS apache_stats_30min
(
timestamp  TIMESTAMP NOT NULL,
gameid  SMALLINT,
page VARCHAR(255), -- (254 * 3) + 4 == 766 < 767 (MYSQL_MAX_KEYLEN)
count BIGINT NOT NULL,
max_load_time FLOAT(10,3) NOT NULL,
min_load_time FLOAT(10,3) NOT NULL,
avg_load_time FLOAT(10,3) NOT NULL,
PRIMARY KEY (timestamp, page)
);

ALTER TABLE `apache_stats_30min` 
	ADD PRIMARY KEY(timestamp, page(255)), 
	DROP PRIMARY KEY;

-- Daily aggregated tables

CREATE TABLE IF NOT EXISTS apache_stats_daily_flip_avg LIKE apache_stats_flip_avg;

CREATE TABLE IF NOT EXISTS apache_stats_daily_flip_max LIKE apache_stats_flip_max;

CREATE TABLE IF NOT EXISTS apache_stats_daily_flip_min LIKE apache_stats_flip_min;

CREATE TABLE IF NOT EXISTS apache_stats_daily_flip_count LIKE apache_stats_flip_count;

CREATE TABLE IF NOT EXISTS apache_stats_daily LIKE apache_stats_30min;


DELIMITER //

DROP PROCEDURE IF EXISTS add_new_count_column //

CREATE PROCEDURE add_new_count_column(IN tbl TEXT) BEGIN
	IF NOT EXISTS(SELECT * from information_schema.COLUMNS  WHERE COLUMN_NAME = 'count' AND TABLE_SCHEMA=DATABASE() AND TABLE_NAME=tbl) THEN
 			call run_query(CONCAT("ALTER TABLE `", tbl, "` ADD COLUMN `count` BIGINT NOT NULL;"));
 	END IF;
END//

DELIMITER ';'

-- upgrade old db to new format
call add_new_count_column('apache_stats_30min');
call add_new_count_column('apache_stats_daily');


DELIMITER //

DROP PROCEDURE IF EXISTS run_query//

CREATE PROCEDURE run_query(IN query TEXT)
BEGIN
	DECLARE query_text TEXT;

	
	SET @query_text := query;
	SELECT @query_text;
	PREPARE query_sql from @query_text;
	EXECUTE query_sql; 
	DEALLOCATE PREPARE query_sql;
END//

DROP PROCEDURE IF EXISTS materialize_view//

CREATE PROCEDURE materialize_view(IN tbl TEXT, IN query TEXT)
BEGIN
	DECLARE juggle_table TEXT;

	call run_query(CONCAT("DROP TABLE IF EXISTS tmp_",tbl,";"));
	call run_query(CONCAT("DROP TABLE IF EXISTS ",tbl,"_tmp;"));

	call run_query(CONCAT("CREATE TABLE ",tbl,"_tmp as ",query));

	-- in case the table doesn't exist, the rename fails
	call run_query(CONCAT("CREATE TABLE IF NOT EXISTS ",tbl," (timestamp TIMESTAMP NOT NULL);"));

	SET @juggle_table := "RENAME TABLE ";
	SET @juggle_table := CONCAT(@juggle_table, tbl," TO tmp_",tbl);
	SET @juggle_table := CONCAT(@juggle_table, ", ",tbl,"_tmp TO ",tbl);
	SET @juggle_table := CONCAT(@juggle_table, ";");

	call run_query(@juggle_table);

	call run_query(CONCAT("DROP TABLE IF EXISTS tmp_",tbl,";"));
END//

DROP PROCEDURE IF EXISTS pivot_apache_stats_full//


CREATE PROCEDURE pivot_apache_stats_full(IN col TEXT, IN sla float(10,3))
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE page_name TEXT ;
	DECLARE page_col TEXT;
	DECLARE uniq_pages CURSOR FOR 
		SELECT DISTINCT page FROM apache_stats_30min WHERE page LIKE "%.php" LIMIT 4000;

	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	SET @col_name := REPLACE(col, "_load_time", "");

	OPEN uniq_pages;

 	SET @query := CONCAT("SELECT SQL_CACHE timestamp");
	
 	page_loop: loop
 		FETCH uniq_pages INTO page_name;
  		IF end_loop = 1 THEN
  			leave page_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, ", SUM(IF(page = '",page_name,"', ", col, ", 0)) as `", @page_col,"` ");
 	END LOOP page_loop;

 	SET @query := CONCAT(@query, " FROM apache_stats_30min GROUP BY timestamp;");

	call materialize_view(CONCAT("apache_stats_flip_",@col_name), @query);

  	CLOSE uniq_pages;
	
END//

DROP PROCEDURE IF EXISTS pivot_apache_stats//


CREATE PROCEDURE pivot_apache_stats(IN col TEXT, IN sla float(10,3))
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE page_name TEXT ;
	DECLARE page_col TEXT;
	DECLARE uniq_pages CURSOR FOR 
		SELECT DISTINCT replace(page, '`', '') as page FROM apache_stats_30min WHERE page LIKE "%.php" LIMIT 4000;

	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	call pivot_apache_add_columns(col, sla);

	SET @col_name := REPLACE(col, "_load_time", "");

	OPEN uniq_pages;

 	SET @query := CONCAT("INSERT into apache_stats_flip_",@col_name," (timestamp");

 	column_loop: loop
 		FETCH uniq_pages INTO page_name;
  		IF end_loop = 1 THEN
  			leave column_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, ", `", @page_col,"` ");
 	END LOOP column_loop;

	SET @query := CONCAT(@query, ") ");

  	CLOSE uniq_pages;

	-- re-open same cursor 

	OPEN uniq_pages;
	SET end_loop := 0;

 	SET @query := CONCAT(@query, " SELECT SQL_CACHE timestamp ");
	
 	page_loop: loop
 		FETCH uniq_pages INTO page_name;
  		IF end_loop = 1 THEN
  			leave page_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, ", SUM(IF(page = '",page_name,"', ", col, ", 0)) as `", @page_col,"` ");
 	END LOOP page_loop;

 	SET @query := CONCAT(@query, " FROM apache_stats_30min ");
	-- back-fill it
	SET @query := CONCAT(@query, " WHERE timestamp > (select IFNULL(MAX(timestamp),from_unixtime(1)) from apache_stats_flip_",@col_name,")");
 	SET @query := CONCAT(@query, " GROUP BY timestamp ;");

	call run_query(@query);

  	CLOSE uniq_pages;
	
END//

DROP PROCEDURE IF EXISTS pivot_apache_add_columns//

CREATE PROCEDURE pivot_apache_add_columns(IN col TEXT, IN sla float(10,3))
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE page_name TEXT ;
	DECLARE page_col TEXT;
	DECLARE missing_columns CURSOR FOR
		SELECT page FROM (SELECT DISTINCT replace(page, '`', '') as page from apache_stats_30min LIMIT 4000) as pages WHERE page LIKE "%.php" AND LEFT(page,64) 
			NOT IN 
			(select column_name from information_schema.columns where table_schema = DATABASE() and table_name = CONCAT("apache_stats_flip_",@col_name) and column_name <> "timestamp");
	
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	SET @col_name := REPLACE(col, "_load_time", "");
	OPEN missing_columns;

 	SET @query := CONCAT("ALTER TABLE `apache_stats_flip_",@col_name,"` ");
	
 	page_loop: loop
 		FETCH missing_columns INTO page_name;
  		IF end_loop = 1 THEN
  			leave page_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, " ADD COLUMN `", @page_col,"` double(20,3) DEFAULT 0, ");
 	END LOOP page_loop;

 	SET @query := CONCAT(@query, " ORDER BY `timestamp`;");

	call run_query(@query);

  	CLOSE missing_columns;
END//


-- daily aggregated procedures

DROP PROCEDURE IF EXISTS pivot_apache_stats_daily//


CREATE PROCEDURE pivot_apache_stats_daily(IN col TEXT, IN sla float(10,3))
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE page_name TEXT ;
	DECLARE page_col TEXT;
	DECLARE uniq_pages CURSOR FOR 
		SELECT DISTINCT page FROM apache_stats_daily WHERE page LIKE "%.php" LIMIT 4000;

	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	call pivot_apache_daily_add_columns(col, sla);

	SET @col_name := REPLACE(col, "_load_time", "");

	OPEN uniq_pages;

 	SET @query := CONCAT("INSERT into apache_stats_daily_flip_",@col_name," (timestamp");

 	column_loop: loop
 		FETCH uniq_pages INTO page_name;
  		IF end_loop = 1 THEN
  			leave column_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, ", `", @page_col,"` ");
 	END LOOP column_loop;

	SET @query := CONCAT(@query, ") ");

  	CLOSE uniq_pages;

	-- re-open same cursor 

	OPEN uniq_pages;
	SET end_loop := 0;

 	SET @query := CONCAT(@query, " SELECT SQL_CACHE timestamp ");
	
 	page_loop: loop
 		FETCH uniq_pages INTO page_name;
  		IF end_loop = 1 THEN
  			leave page_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, ", SUM(IF(page = '",page_name,"', ", col, ", 0)) as `", @page_col,"` ");
 	END LOOP page_loop;

 	SET @query := CONCAT(@query, " FROM apache_stats_daily ");
	-- back-fill it
	SET @query := CONCAT(@query, " WHERE timestamp > (select IFNULL(MAX(timestamp),from_unixtime(1)) from apache_stats_daily_flip_",@col_name,")");
 	SET @query := CONCAT(@query, " GROUP BY timestamp ;");

	call run_query(@query);

  	CLOSE uniq_pages;
	
END//

DROP PROCEDURE IF EXISTS pivot_apache_daily_add_columns//

CREATE PROCEDURE pivot_apache_daily_add_columns(IN col TEXT, IN sla float(10,3))
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE col_name TEXT;
	DECLARE query TEXT;
	DECLARE page_name TEXT ;
	DECLARE page_col TEXT;
	DECLARE missing_columns CURSOR FOR
		SELECT page FROM (SELECT DISTINCT page from apache_stats_daily LIMIT 4000) as pages WHERE page LIKE "%.php" AND LEFT(page,64) 
			NOT IN 
			(select column_name from information_schema.columns where table_schema = DATABASE() and table_name = CONCAT("apache_stats_daily_flip_",@col_name) and column_name <> "timestamp");
	
	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	SET @col_name := REPLACE(col, "_load_time", "");
	OPEN missing_columns;

 	SET @query := CONCAT("ALTER TABLE `apache_stats_daily_flip_",@col_name,"` ");
	
 	page_loop: loop
 		FETCH missing_columns INTO page_name;
  		IF end_loop = 1 THEN
  			leave page_loop;
  		END IF;
		SET @page_col := LEFT(CONCAT("",page_name), 64);
		SET @query := CONCAT(@query, " ADD COLUMN `", @page_col,"` double(20,3) DEFAULT 0, ");
 	END LOOP page_loop;

 	SET @query := CONCAT(@query, " ORDER BY `timestamp`;");

	call run_query(@query);

  	CLOSE missing_columns;
END//

DELIMITER ;
