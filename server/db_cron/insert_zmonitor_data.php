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
 zMonitor Vertica upload post-processing.

 cpu, nw, mem and various other metrics would be queried from vertica for zmonitor
 and uploaded to a file whose path is keyed to the game name. This function will
 massage that data and insert into the vertica_stats_30min table with the
 primary key as the current timestamp.
*/

ini_set("memory_limit", -1);
include_once 'eu-collector.php';
include_once 'rightscale.php';

function createQuery($table, $data_array)
{
	$timestamp = array_keys($data_array);
	$keys = "timestamp ";
        $values = "from_unixtime({$timestamp[0]}) ";
	
	$keys .= ", " . implode(",", array_keys($data_array[$timestamp[0]]));
	$values .= "," . implode(",", array_values($data_array[$timestamp[0]]));
	$query = "REPLACE INTO $table ($keys) VALUES ($values)";
	return $query;
}

function insert_zmonitor_data($server_cfg, $game_cfg, $time_slots='*')
{
	$game_name = $game_cfg["name"];
        $table = $game_cfg['zmonitor_table'];
	$array_name = null;

	// Check if passed game is an array game. If yes change parameters for get_eu accordingly
	if(isset($game_cfg['id'])) {

		$array_id = $game_cfg['id'];
		$rsObj = new RightScale($server_cfg, $game_cfg);
		$array_id_name = $rsObj->get_array_id_name();
		$game_name = $game_cfg['parent'];
		
		if (!empty($array_id_name)) {	
			$array_name = $array_id_name[$array_id];
		}
	}

	// Fetch curent eu from eu database
	$euObj = new EUAdapter($server_cfg);
	$data_array = $euObj->get_current_eu($game_name, $array_name);

	$query = createQuery($table, $data_array);
	echo "$query\n";
	$query_res = execute_queries($server_cfg, $game_cfg, array($query));
	
	if ($query_res == 0) {
		$game_cfg['logger']->log("insert_zmonitor_data","zmonitor data for ".
				$game_cfg['name']." successfully inserted", Logger::INFO);
	} else {
		$game_cfg['logger']->log("insert_zmonitor_data","zmonitor data for ".
				$game_cfg['name']." not inserted", Logger::ERR);
	}
}

?>
