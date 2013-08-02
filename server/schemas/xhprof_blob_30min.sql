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

CREATE TABLE IF NOT EXISTS xhprof_blob_30min
(	
	`timestamp`     timestamp   NOT NULL DEFAULT '0000-00-00 00:00:00',
	xhprof_blob	mediumblob,
	`flags`		SET('MemDisabled',
			    'MemEnabled',
			    'MemPartial') default 'MemDisabled',
	`p_tag`		smallint DEFAULT 0,

	PRIMARY KEY (timestamp, p_tag),
	INDEX USING BTREE(timestamp)
) partition by list(p_tag) (
	partition p000 values in (0),
	partition p001 values in (1,7, -1,-7),
	partition p002 values in (2,8, -2,-8),
	partition p003 values in (3,9, -3,-9),
	partition p004 values in (4,10,-4,-10),
	partition p005 values in (5,11,-5,-11),
	partition p006 values in (6,12,-6,-12)
)
;


-- daily aggregated table

CREATE TABLE IF NOT EXISTS xhprof_blob_daily LIKE xhprof_blob_30min;

