<?php

#
# Copyright 2013 Zynga Inc.
# 
# Licensed under the Apache License, Version 2.0 (the "License");
#    you may not use this file except in compliance with the License.
#    You may obtain a copy of the License at
# 
#    http://www.apache.org/licenses/LICENSE-2.0
# 
#    Unless required by applicable law or agreed to in writing, software
#      distributed under the License is distributed on an "AS IS" BASIS,
#      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#    See the License for the specific language governing permissions and
#    limitations under the License.
# 


/* Query map for zPerfmon
 * 
 */

$query_map = array(
			'get_release_list' => 'SELECT 1800*floor(unix_timestamp(start)/1800) as timestamp,text FROM %table$s order by start desc',
			'get_last_profile_slots' => 'SELECT distinct 1800*floor(unix_timestamp(timestamp)/1800) as timestamp FROM %table$s ORDER BY timestamp desc limit 1',
			# Report database
			'insert_instance_class_name' => 'INSERT INTO %table$s(class_id, class_name) VALUES (%max$s, \'%value$s\')',
			'get_game_name_by_id' => 'SELECT id, name from gameidmap',
			'get_instance_type_name' => 'SELECT id as type_id, type as type_name from rightscale.nodetype',
			'get_cloud_id' => 'select cloud_id from rightscale.instances where deploy_id=%deploy_id$s',
			'get_instance_class_name' => 'SELECT class_id, class_name from instance_class_name',
			'get_cloud_name' => 'SELECT id as cloud_id, name as cloud_name from rightscale.clouds',
			'get_deployment_from_ip' => 'SELECT * from rightscale.instances where private_ip like "%ip$s" or public_ip like "%ip$s"',
			'insert_report_detail' => 'INSERT INTO %table$s (date, game, total_instance, DAU, DAU_per_instance, optimal_instance_count, slack_per, cloud_id ) 
								VALUES (NOW(), \'%game$s\', %total_instance$s, %DAU$s, %DAU_per_instance$s, %optimal_instance_count$s, %slack_per$s, %cloud_id$s)',
			'insert_report_instance_class' => 'INSERT INTO %table$s (date, game, class_id, total_instance, DAU_per_instance, optimal_instance_count, slack_per ) 
								VALUES (NOW(), \'%game$s\', %class_id$s, %total_instance$s, %DAU_per_instance$s, %optimal_instance_count$s, %slack_per$s)',
			'insert_report_pool' => 'INSERT INTO %table$s (date, game, class_id, pool_name, type_id, total_instance, DAU_per_instance, utilization_per, optimal_instance_count,
								slack_per, bottleneck, underutilized, headroom_per ) VALUES (NOW(), \'%game$s\', %class_id$s, \'%pool_name$s\', %type_id$s, %total_instance$s, 
								%DAU_per_instance$s, %utilization_per$s, %optimal_instance_count$s, %slack_per$s, \'%bottleneck$s\', \'%underutilized$s\', \'%headroom_per$s\')',
			'report_detail' => 'SELECT unix_timestamp(date), game, total_instance, DAU, DAU_per_instance, optimal_instance_count, slack_per, cloud_id  from %table$s 
								where date BETWEEN NOW()-INTERVAL 7 DAY  and NOW() ORDER BY game;',
			'report_class_summary' => 'SELECT unix_timestamp(date), game, class_id total_instance, DAU, DAU_per_instance, optimal_instance_count, slack_per from %table$s 
								where date BETWEEN NOW()-INTERVAL 7 DAY  and NOW() ORDER BY game;',
			'report_pool_summary' => 'SELECT unix_timestamp(date), game, class_id, pool_name, type_id, total_instance, DAU_per_instance, utilization_per, optimal_instance_count, 
								slack_per, bottleneck, underutilized, headroom_per from %table$s where  date BETWEEN NOW()-INTERVAL 7 DAY  and NOW() ORDER BY game;',
			'detaile_report_data' => ' select instance_utilization.cloud_id, unix_timestamp(instance_utilization.date), instance_utilization.game, 
										instance_utilization.DAU, instance_utilization.total_instance, instance_utilization.DAU_per_instance,
										instance_utilization.optimal_instance_count, instance_utilization.slack_per,instance_class_summary.class_id,
										instance_class_summary.total_instance as class_total_instance, instance_class_summary.DAU_per_instance as 
										class_DAU_per_instance, instance_class_summary.optimal_instance_count as class_optimal_instance_count, 
										instance_class_summary.slack_per as class_slack_per from instance_class_summary as instance_class_summary 
										JOIN instance_utilization as instance_utilization  where  instance_utilization.date = instance_class_summary.date 
										and instance_class_summary.game=instance_utilization.game  and instance_utilization.date BETWEEN NOW()-INTERVAL 7 DAY  
										and NOW() ORDER by instance_utilization.game,instance_utilization.date;',

            #
            # Developer View queries
            'get_profile_slots' => 'SELECT distinct 1800*floor(unix_timestamp(timestamp)/1800) as timestamp, char_length(xhprof_blob) as blob_length, flags
                                                    FROM %table$s
                                                    WHERE timestamp >= from_unixtime(%start_time$s) AND timestamp < from_unixtime(%end_time$s) AND char_length(xhprof_blob) > 0',
     	    'get_last_profile_slots' => 'SELECT distinct 1800*floor(unix_timestamp(timestamp)/1800) as timestamp FROM %table$s ORDER BY timestamp desc limit 1',
            #
            # Business Dashboard queries
	    'bd_chart_range' => 'SELECT unix_timestamp(timestamp) AS timestamp,DAU,web_count,db_count,mc_count,mb_count,admin_count,queue_count,proxy_count 
				 FROM stats_30min WHERE timestamp > from_unixtime(%start_time$s) AND timestamp < from_unixtime(%end_time$s) %extra_params$s',

	    'bd_chart_range_per_dau' => 'SELECT unix_timestamp(timestamp) as timestamp, DAU,
		round(DAU/(web_count + db_count + mc_count + mb_count + proxy_count + admin_count + queue_count)) AS dau_all_count,
		round(DAU/web_count) AS dau_web_count, 
		round(DAU/db_count) AS dau_db_count,
		round(DAU/mc_count) AS dau_mc_count, 
		round(DAU/mb_count) AS dau_mb_count, 
		round(DAU/proxy_count) AS dau_proxy_count, 
		round(DAU/admin_count) AS dau_admin_count, 
		round(DAU/queue_count) AS dau_queue_count
		FROM %table$s 
		WHERE timestamp > from_unixtime(%start_time$s) AND timestamp < from_unixtime(%end_time$s) %extra_params$s',
		# Tag queries
		#'get_tag_range' => 'SELECT unix_timestamp(start) as start, text from %table$s where type = "tag" and start >= from_unixtime(%start_time$s) AND start < from_unixtime(%end_time$s) %extra_params$s',
		'get_tag_range' => 'SELECT unix_timestamp(start) as start, text from %table$s where start >= from_unixtime(%start_time$s) AND start < from_unixtime(%end_time$s) %extra_params$s',
	    
		# EU chart queries
	    'eu_web_chart_range' =>'SELECT unix_timestamp(timestamp) as timestamp, web_cpu_idle_util as web_cpu, web_mem_used_util  
	    			    as web_mem,web_rps FROM vertica_stats_30min  WHERE timestamp > from_unixtime(%start_time$s) AND 
				    timestamp < from_unixtime(%end_time$s) AND web_rps < 500', 

	    'eu_db_chart_range' => 'SELECT unix_timestamp(timestamp) as timestamp, db_a_cpu_idle_util as db_a_cpu,
				    db_a_md0_disk_ops_read_util as db_a_md0_disk_ops_read, db_a_md0_disk_ops_write_util 
				    as db_a_md0_disk_ops_write	FROM vertica_stats_30min WHERE timestamp > from_unixtime(%start_time$s) 
				    AND timestamp < from_unixtime(%end_time$s)',

	    'eu_mc_chart_range' => 'SELECT unix_timestamp(timestamp) as timestamp, mc_user_df_cache_used_util as mc_user_df_cache_used, 
				    mc_user_nw_pkts_rx_util as mc_user_nw_pkts_rx, mc_user_nw_pkts_tx_util as mc_user_nw_pkts_tx, 
				    mc_user_gets , mc_user_sets  FROM vertica_stats_30min WHERE timestamp > from_unixtime(%start_time$s) 
				    AND timestamp < from_unixtime(%end_time$s)',

	    'eu_mb_chart_range' => 'SELECT unix_timestamp(timestamp) as timestamp, mb_user_a_nw_pkts_rx_util as mb_user_a_nw_pkts_rx, 
				    mb_user_a_nw_pkts_tx_util as mb_user_a_nw_pkts_tx, mb_user_a_gets , mb_user_a_sets
				    FROM vertica_stats_30min WHERE timestamp > from_unixtime(%start_time$s) 
				    AND timestamp < from_unixtime(%end_time$s)',

	    'eu_host_chart_range' => 'SELECT unix_timestamp(timestamp) as timestamp, %prefix$s_nw_pkts_rx_util as %prefix$s_nw_pkts_rx, 
				    %prefix$s_nw_pkts_tx_util as %prefix$s_nw_pkts_tx, %prefix$s_gets , %prefix$s_sets
				    FROM vertica_stats_30min WHERE timestamp > from_unixtime(%start_time$s) 
				    AND timestamp < from_unixtime(%end_time$s)',
            'cto_get_tracked_functions_by_column' => 'SELECT unix_timestamp(timestamp) as timestamp, 
                                                            %columns$s
                                                    FROM %table$s
                                                    WHERE timestamp >= from_unixtime(%start_time$s)
                                                        AND timestamp < from_unixtime(%end_time$s)
                                                        AND page = \'%page$s\'
                                                    ORDER BY timestamp',

              #
              # Flipping queries (row => column transpose)

              #PDT
             'flip_apache_stats_30min' => 'SELECT DISTINCT page, unix_timestamp(timestamp) as timestamp
                                            FROM apache_stats_30min
                                            WHERE unix_timestamp(timestamp) = %start_time$s
                                            AND page NOT IN (SELECT column_name FROM information_schema.columns
                                                            WHERE table_name = "apache_stats_flip_avg"
                                                            AND column_name != "timestamp")
                                            GROUP BY timestamp, page',

              'flip_apache_stats_30min_old_pages' => 'SELECT column_name FROM information_schema.columns
                                                            WHERE table_name = "apache_stats_flip_avg"
                                                            AND column_name != "timestamp"',

              'flip_apache_stats_30min_data' => 'SELECT DISTINCT page, unix_timestamp(timestamp) as timestamp,
                                                                max(max_load_time) as max_load_time,
                                                                avg(avg_load_time) as avg_load_time,
                                                                min(min_load_time) as min_load_time
                                                FROM apache_stats_30min
                                                WHERE unix_timestamp(timestamp) = %start_time$s
                                                GROUP BY timestamp, page',

             'flip_apache_alter' => 'ALTER TABLE apache_stats_flip_%metric$s ADD COLUMN (%column_names$s)',

             'flip_apache_insert' => 'INSERT INTO apache_stats_flip_%metric$s(timestamp, %column_names$s)
                                        VALUES(from_unixtime(%timestamp$s), %metric_values$s)',

             'lock_table' => 'LOCK TABLES %table$s %mode$s',

             'unlock_table' => 'UNLOCK TABLES',

             'dtx_flip_apache_stats_get_all_timestamps' => 'SELECT DISTINCT unix_timestamp(timestamp) as timestamp
                                                             FROM apache_stats_30min',

             'create_apache_flip_tables' => 'CREATE TABLE IF NOT EXISTS apache_stats_flip_%metric$s(timestamp TIMESTAMP NOT NULL)',


             # Tracked functions
             #
             # Being aggressive here, creating all the columns in one shot without paramatrizing %page$s
             # (only %start_time$s)
             'flip_tracked_functions_30min_columns' => 'SELECT DISTINCT function
                                                        FROM tracked_functions_30min
                                                        WHERE unix_timestamp(timestamp) = %start_time$s
                                                        AND function NOT IN (SELECT column_name FROM information_schema.columns
                                                                            WHERE table_name = "tracked_functions_flip_count"
                                                                            AND column_name != "timestamp"
                                                                            AND column_name != "page")',

              'flip_tracked_functions_30min_old_functions' => 'SELECT column_name FROM information_schema.columns
                                                                            WHERE table_name = "tracked_functions_flip_count"
                                                                            AND column_name != "timestamp"
                                                                            AND column_name != "page"',

             'flip_tracked_functions_30min_data' => 'SELECT DISTINCT unix_timestamp(timestamp) as timestamp,
                                                                     function,
                                                                     count, 
                                                                     incl_time,
                                                                     excl_time
                                                    FROM tracked_functions_30min
                                                    WHERE unix_timestamp(timestamp) = %start_time$s
                                                    AND page = \'%page$s\'',

             'flip_tracked_function_alter' => 'ALTER TABLE tracked_functions_flip_%metric$s ADD COLUMN (%column_names$s)',

             'flip_tracked_function_insert' => 'INSERT INTO tracked_functions_flip_%metric$s(timestamp, page, %column_names$s)
                                            VALUES(from_unixtime(%timestamp$s), \'%page$s\', %metric_values$s)',

             'dtx_flip_tracked_functions_get_all_timestamps' => 'SELECT DISTINCT unix_timestamp(timestamp) as timestamp, page
                                                                 FROM tracked_functions_30min',

             'create_tracked_functions_flip_tables' => 'CREATE TABLE IF NOT EXISTS tracked_functions_flip_%metric$s(timestamp TIMESTAMP NOT NULL, page VARCHAR(256) NOT NULL)',
            
	     #cto-view queries
	    'cto_get_top_pages_by_delivery_time' => 'SELECT page 
						     FROM apache_stats_30min
						     WHERE char_length(page) < 63 AND RIGHT(page, 4) = ".php" AND timestamp > DATE_SUB(NOW(),INTERVAL 1 DAY)
						     GROUP by page ORDER by SUM(count) DESC  LIMIT 15',

	     'cto_get_top_pages_avg_load_time' => 'SELECT unix_timestamp(timestamp) as timestamp,%columns$s
				   FROM %table$s WHERE timestamp > from_unixtime(%start_time$s) AND timestamp < from_unixtime(%end_time$s)',

	    #
	    # Top-5 queries
	    #
	    
            # top-5 by exclusive wall time
	    'top5_functions_ewt_range' => 
		' SELECT unix_timestamp(timestamp) AS timestamp, page, function, excl_time FROM top5_functions_by_excl_time
			 WHERE timestamp > from_unixtime(%start_time$s) AND timestamp < from_unixtime(%end_time$s)
			 ORDER by timestamp, page, excl_time desc ',

            #
            # Slow page table queries
            'get_slow_page_data' => 'SELECT page,(page_time/1000) as page_time,top_excl_wt_1,top_excl_wt_2,top_excl_wt_3,ip,(unix_timestamp(timestamp) * 1000) as ts,id FROM %slow_page_table$s',

            'get_slow_page_list' => 'SELECT DISTINCT page from %slow_page_table$s',

	    #
	    # Insert and Clean log queries
	    'insert_log' => 'INSERT INTO log (module_name,message, log_level) VALUES (\'%module$s\',\'%message$s\',\'%log_level$s\')',
	    'clean_log' => 'DELETE FROM log WHERE UNIX_TIMESTAMP(timestamp) < UNIX_TIMESTAMP(NOW()) - (%retention_time$s)*24*3600',
	    
	    #Monitor_table
	    'monitor_table'=>'SELECT UNIX_TIMESTAMP(timestamp) from %table$s WHERE UNIX_TIMESTAMP(timestamp) BETWEEN %start$s AND %end$s',

	    #Get log view data
	    'get_log_data'=>'SELECT UNIX_TIMESTAMP(timestamp) timestamp, log_level, module_name, message FROM %log_table$s ORDER BY timestamp DESC LIMIT 10000',
	    'get_log_level_list'=>'SELECT DISTINCT log_level FROM %log_table$s',
	    'get_log_module_list'=>'SELECT DISTINCT module_name FROM %log_table$s',

	    # Insert events ex. tags etc...
	    'event_insert'=>'REPLACE INTO events (start,type,text) VALUES (FROM_UNIXTIME(%start$s), \'%type$s\', \'%text$s\')',

	    # Get last inerted event
	    'get_last_event'=>'SELECT type , text FROM events WHERE start=(SELECT MAX(start) FROM events)',

	    # Get latest release event
	    'get_latest_release' => 'SELECT text FROM events WHERE type = "release"',

	    # Get timestamp of first occurance of given release
	    'get_time_of_release' => 'SELECT start FROM events WHERE type = "release" AND text = "%release$s" ORDER BY start LIMIT 1',
	    
	    # daily aggregated insertion 
	    'stats_daily_aggregated_insert' => 'REPLACE INTO %table_daily$s (timestamp, gameid, DAU, web_count, db_count, mc_count,
	    					mb_count, admin_count, proxy_count, queue_count) SELECT FROM_UNIXTIME(%end$s), 
						gameid, round(avg(DAU)) DAU, round(avg(web_count)) web_count, round(avg(db_count)) 
						db_count, round(avg(mc_count)) mc_count, round(avg(mb_count)) mb_count, 
						round(avg(admin_count)) admin_count, round(avg(proxy_count)) proxy_count, 
						round(avg(queue_count)) queue_count  FROM %table_30min$s WHERE timestamp BETWEEN 
						FROM_UNIXTIME(%start$s) AND FROM_UNIXTIME(%end$s) AND DAU != -1',

	    'apache_stats_daily_aggregated_insert' => 'REPLACE INTO %table_daily$s (timestamp, gameid, page, count, max_load_time, min_load_time,
	    					       avg_load_time) SELECT FROM_UNIXTIME(%end$s), gameid, page, SUM(count) count, AVG(max_load_time) max_load_time, 
						       AVG(min_load_time) min_load_time, AVG(AVG_load_time) AVG_load_time   FROM %table_30min$s 
						       WHERE timestamp BETWEEN FROM_UNIXTIME(%start$s) AND FROM_UNIXTIME(%end$s) GROUP BY page',

	    'top5_functions_daily_aggregated_insert' => 'REPLACE INTO %table_daily$s (timestamp, gameid, page, function, count, incl_time, excl_time)
	    						 SELECT FROM_UNIXTIME(%end$s), gameid, page, function, AVG(count) count , AVG(incl_time) incl_time, 
							 AVG(excl_time) excl_time FROM %table_30min$s WHERE timestamp BETWEEN FROM_UNIXTIME(%start$s) 
							 AND FROM_UNIXTIME(%end$s) GROUP BY page,function',

	    'tracked_functions_daily_aggregated_insert' => 'REPLACE INTO %table_daily$s (timestamp, gameid, page, function, count, incl_time, excl_time)
	    						 SELECT FROM_UNIXTIME(%end$s), gameid, page, function, AVG(count) count , AVG(incl_time) incl_time, 
							 AVG(excl_time) excl_time FROM %table_30min$s WHERE timestamp BETWEEN FROM_UNIXTIME(%start$s) 
							 AND FROM_UNIXTIME(%end$s) GROUP BY page,function',

	    'vertica_stats_daily_aggregated_insert' => 'call insert_to_daily_table("%table_30min$s", %start$s, %end$s)',

	   'daily_aggregate_flip' => "call pivot_apache_stats_daily('avg_load_time', 0.00);
	   			      call pivot_tracked_functions_daily('incl_time');
				      call rank_top5_functions_daily('excl_time')",
	   'get_columns' => 'show columns from %table$s',

	   // add_missing_column query is being generated in vertica_table_add_column.php script
	   'add_missing_columns' => '%query$s'
	);

?>
