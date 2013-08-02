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


/**
@author: Ujwalendu Prakash(uprakash@zynga.com)
*/

include_once 'dau-collector.php';
include_once 'rightscale.php';

$class_column_map = array('mqueue'=>'queue');

function create_query($table, $timestamp, $dau, $counts_array, $gid)
{

	$keys = "timestamp, gameid ";
	$values = "from_unixtime($timestamp), $gid ";

	if(!empty($dau)) {
		$keys .=  ",  DAU";
		$values .= ", $dau";
	} 

	foreach($counts_array as $key=>$value) {
		$keys .= "," . $key;
		$values .= "," . $value;
	}

	$query = "REPLACE INTO $table ($keys) VALUES ($values)";
	return $query;

}

function insert_rightscale_data($server_cfg, $game_cfg, $time_slots='*') {

	// TODO take the names from config and expand table as and when new machine class is added to config
	$machine_class_default = array( 'web_count'=>0,
					'mb_count'=>0,
					'mc_count'=>0,
					'db_count'=>0,
					'queue_count'=>0,
					'proxy_count'=>0,
					'admin_count'=>0);


	$game_name = $game_cfg["name"];
	$table = $game_cfg['db_stats_table'];

	$gid = $game_cfg['gameid']; // to query for the dau_5min table and to insert into stats_30min;
	$snid = $game_cfg['snid'] ? $game_cfg['snid'] : null;
        $cid = $game_cfg['cid'] ? $game_cfg['cid'] : null;
        
	$deploy_array = $game_cfg['deployIDs'];
	$deploy_id = $deploy_array[0];

	//
	// get the max value of the supplied timeslots
	// expects that time_slots are given as follows
	// {ts1,ts2}
	//
	preg_match("/\{(.*)\}/",$time_slots,$m);
	$timeslot = max((explode(",",$m[1])));

	if(empty($timeslot)) {
		$game_cfg['logger']->log("insert_bd_metric", "null is given as  timeslot", Logger::ERR);
		error_log("null is given as timeslots", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return;
	}

	$timestamp = $timeslot * 1800;

	$dauObj = new DAUAdapter();

	$dau = $dauObj->get_timed_dau($timestamp, $gid, $snid, $cid );

	$rsObj = new RightScale($server_cfg, $game_cfg);
	
	$web_count = null;
	if(isset($game_cfg['id'])) {

		$web_count = $rsObj->get_host_count_per_pool($game_cfg['id'], $deploy_id);
		$game_name = $game_cfg['parent'];	// to query other machine counts
	}

	$machine_counts = $rsObj->get_host_count_per_class($deploy_id, $game_name);

	$counts_array = get_counts($machine_class_default, $machine_counts, $web_count);

	$query = create_query($table, $timestamp, $dau, $counts_array, $gid);
	
	$queries = array($query);
	$query_res = execute_queries($server_cfg, $game_cfg, $queries);

	if ( $query_res == 0) {
		$game_cfg['logger']->log("insert_bd_metrics","bd_metrics metrics for ".$game_cfg['name'].
				" successfully inserted", Logger::INFO);
		error_log("bd_metrics metrics for {$game_cfg['name']} successfully inserted", 
				3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	} else {
		$game_cfg['logger']->log("insert_bd_metrics","bd_metrics metrics for ".$game_cfg['name'].
				" not inserted", Logger::ERR);
		error_log("bd_metrics metrics for {$game_cfg['name']} successfully inserted", 
                                3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	}
}

function get_counts($machine_class_default, $machine_counts, $web_count) {

	global $class_column_map;

        $counts_array = array();

        foreach($machine_counts as $class=>$counts) {

                if(isset($class_column_map[$class])) {
                        $column_name = "{$class_column_map[$class]}_count";
                } else {
                        $column_name = "{$class}_count";
                }
		if(in_array($column_name, array_keys($machine_class_default))) {
			$machine_class_default[$column_name] = $counts['total'];
		}
        }

        if(!empty($web_count)) {
                $machine_class_default['web_count'] = $web_count;
        }

	return $machine_class_default;
}

?>
