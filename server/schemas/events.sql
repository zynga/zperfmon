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

CREATE TABLE IF NOT EXISTS `events` (
  `start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, 
  `end` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `type` enum('tag','experiment','release') DEFAULT NULL,
  `text` varchar(252),
   PRIMARY KEY (start, type, text)
);

ALTER TABLE `events` 
	ADD PRIMARY KEY(start, type, text(252)), 
	DROP PRIMARY KEY;

drop procedure if exists create_events_table;

delimiter //
create procedure create_events_table() begin
    /* delete columns if they exist */
    if exists (select * from information_schema.columns where table_schema = DATABASE() and table_name = 'stats_30min' and column_name = 'tag') then
	replace into events (start, type, text) select MIN(timestamp), 'tag', tag from stats_30min group by tag; 
	replace into events (start, type, text) select MIN(timestamp), 'release', release_id from stats_30min group by release_id;
        alter table stats_30min drop column `tag`, drop column `release_id`;
    end if;
end//

delimiter ';'
call create_events_table();

drop procedure if exists create_events_table;
