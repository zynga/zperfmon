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

delimiter //

DROP PROCEDURE IF EXISTS insert_to_daily_table//

CREATE PROCEDURE insert_to_daily_table(IN tbl TEXT, IN start_ts INT, IN end_ts INT)
BEGIN
	DECLARE end_loop INT DEFAULT 0;
	DECLARE query TEXT;
	DECLARE col TEXT ;
	DECLARE cols CURSOR FOR 
		SELECT column_name FROM information_schema.columns  WHERE table_name=tbl and column_name!='timestamp' and table_schema=DATABASE();

	DECLARE CONTINUE HANDLER FOR NOT FOUND SET end_loop=1;

	SET @daily_table := REPLACE(tbl, "30min", "daily");

	OPEN cols;

 	SET @query := CONCAT("REPLACE INTO ", @daily_table  ," ( `timestamp` ");
	
 	cols_loop: loop
 		FETCH cols INTO col;
  		IF end_loop = 1 THEN
  			leave cols_loop;
  		END IF;
		SET @query := CONCAT(@query, ", `", col, "`");
 	END LOOP cols_loop;
		SET @query := CONCAT(@query, ")");
  	CLOSE cols;

	OPEN cols;

	SET end_loop := 0;
	SET @query := CONCAT(@query, " SELECT FROM_UNIXTIME(", end_ts, ") ");

	cols_loop: loop
                FETCH cols INTO col;
                IF end_loop = 1 THEN
                        leave cols_loop;
                END IF;
                SET @query := CONCAT(@query, ", AVG(", col, ") ", col);
        END LOOP cols_loop;

	SET @query := CONCAT(@query, "  FROM  ", tbl, " WHERE timestamp BETWEEN FROM_UNIXTIME(", start_ts, ") AND FROM_UNIXTIME(", end_ts, ")");
	CLOSE cols;

	call run_query(@query);
END//

delimiter ;
