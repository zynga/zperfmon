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

CREATE TABLE IF NOT EXISTS slow_page
(
	id		MEDIUMINT NOT NULL AUTO_INCREMENT,
	page		VARCHAR(256)NOT NULL,
	ip		CHAR(16) NOT NULL,
	timestamp	timestamp NOT NULL,
	page_time	INT NOT NULL,
	top_excl_wt_1	VARCHAR(512),
	top_excl_wt_2	VARCHAR(512),
	top_excl_wt_3	VARCHAR(512),
	top_excl_wt_4	VARCHAR(512),
	top_excl_wt_5	VARCHAR(512),
	`profile`	mediumblob,
	KEY (ID),
	INDEX slow_page_index (page, page_time)
);
