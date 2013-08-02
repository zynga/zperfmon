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


//
// Get daily aggregated data for all enabled metrics for a game and
// inserting into corresponding daily tables. It gets aggregated value
// for metrics from the given timestamp to past 24 hours from their
// corresponding 30 minute table.
// 
// Parameters:
// -g : It is game name for which this cron will run. Default is 
//	all games configured in zmonitor server.cfg
// -t : This is start time slot from which to past 48 slots(24 hours) data
//      are to be aggregated. Default is current time slot.
// 
// time slot = timestamp/(30*60); i.e. half hour slots 
//


ini_set("memory_limit", -1);

include_once 'logger.inc.php';
include_once 'game_config.php';
include_once 'XhProfModel.php';


function insert_daily_aggregated_data($table_30min, $timeslot, $xhprofModelObject)
{
	$table_daily = str_replace('30min', 'daily', $table_30min);
	$query_name = str_replace('30min', 'daily_aggregated_insert', $table_30min);
	
	$end   = (int)$timeslot * 1800;
	$start = ((int)$timeslot - 48) * 1800;
	
	//
	// Suppress any warning 
	//

	$result = $xhprofModelObject->generic_execute_get_query_multi(
								$query_name,
								array('table_30min'=>$table_30min,
								      'table_daily'=>$table_daily,
								      'start' => $start,
								      'end' => $end
								      )
								);
		
	return $result;
}


function insert_blob($server_cfg, $game_cfg, $tbz_file, $timestamp)
{
	//$table = $game_cfg["xhprof_blob_daily_table"];

	// Hardcoded
	$table = "xhprof_blob_daily";
	$db_server = $game_cfg["db_host"];
	$db_user = $game_cfg["db_user"];
	$db_pass = $game_cfg["db_pass"];
	$db_name = $game_cfg["db_name"];

	$mysql_pdo = new PDO( "mysql:host={$db_server};dbname={$db_name}",
			$db_user, $db_pass);

	if (!$mysql_pdo) {
		$game_cfg['logger']->log("Daily aggregation insert_tbz","Failed to create PDO object", Logger::ERR);
		error_log("Daily aggregation Failed to create new mysql PDO\n", 3, $game_cfg['log_file']);
		return 1;
	}

	$tbz_handle = fopen($tbz_file, "rb");
	if (!$tbz_handle) {
		$game_cfg['logger']->log("Daily aggregation insert_tbz","Failed to open tbz file ${$tbz_file}", Logger::INFO);
		error_log("Daily aggregation Failed to open tbz file {$tbz_file}.\n", 3, $game_cfg['log_file']);
		return 1;
	}

	$stmt = "REPLACE INTO {$table} (timestamp, xhprof_blob) VALUES (from_unixtime($timestamp), :tbz_handle)";
	error_log("$stmt\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
	$insert_statement = $mysql_pdo->prepare($stmt);
	if (!$insert_statement) {
		error_log("Daily aggregation Failed to create insert statement\n", 3, $game_cfg['log_file']);
		$game_cfg['logger']->log("Daily aggregation Insert_tbz","Failed to create insert statement", Logger::ERR);
		error_log(print_r($mysql_pdo->errorCode(), true), 3, $game_cfg['log_file']);
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, $game_cfg['log_file']);
		return 2;
	}

	if (!$insert_statement->bindParam(':tbz_handle', $tbz_handle, PDO::PARAM_LOB) ||
			!$mysql_pdo->beginTransaction() ||
			!$insert_statement->execute() ||
			!$mysql_pdo->commit()) {
		error_log("Daily aggregation Failed to insert blob from file handle into db\n", 3, $game_cfg['log_file']);
		error_log(print_r($mysql_pdo->errorCode(), true), 3, $game_cfg['log_file']);
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, $game_cfg['log_file']);
		$game_cfg['logger']->log("Daily aggregation Insert_tbz","Failed to insert blob from file handle into db", Logger::ERR);
		return 3;
	}

	$game_cfg['logger']->log("Daily aggregation Insert_tbz","${tbz_file} : blob is inserted", Logger::INFO);
	return 0;
}

//
// Aggregates profiles collected for given timeslot to past 48 timeslots
// puts that into /db/zperfmon/<game_name>/xhprof.daily/<day> directory
// return:  newly created xhprof.tbz (daily aggreagated xhprof file name)
// Which is then inserted to xhprof_blob_daily table
//
function insert_aggregated_xhprof_blob($server_cfg, $game_cfg, $timeslot)
{

	$daily_aggregate_command = $server_cfg['daily_aggregation_command'];

	$timestamp = ((int)$timeslot * 1800);
	
	$profile_upload_directory = sprintf($server_cfg['root_upload_directory'],
					    $game_cfg['name']);

	$cmd = implode(" ", array($daily_aggregate_command,
				  $game_cfg["name"],
				  $profile_upload_directory, 
				  $timestamp));

	error_log("Daily aggregation: pre-processing profiles\n", 3, 			
		   $game_cfg['log_file']);

	$game_cfg['logger']->log("Daily aggregation: profile_pre_processor", 
				 "pre-processing profiles", Logger::INFO);

	$retval = null; 
	$output = system($cmd, $retval);

	if ($retval != 0) {
		error_log("Daily aggregation: processing profiles failed $output \n",
			  3, $game_cfg['log_file']);

		$game_cfg['logger']->log("Daily aggregation: profile_processor", 
					  "processing profiles failed", Logger::ERR);
		return;
	}

	//
	// path of the daily aggregated xhprof.tbz file
	// /db/zperfmon/<game_name>/xhprof.daily/<timeslot>/xhprof.tbz
	//
	$aggregated_xhprof_path = sprintf($server_cfg['daily_upload_directory'], 
					  $game_cfg['name'])."/$timeslot/";

	$aggregate_blob = "$aggregated_xhprof_path{$server_cfg['xhprof_tbz_name']}";	

	if (file_exists($aggregate_blob)) {

		$ret = insert_blob($server_cfg, $game_cfg, $aggregate_blob, $timestamp);

		if ($ret == 0) {
			error_log("Daily aggregation: Inserted blob $aggregate_blob",
				  3, $game_cfg['log_file']);
		} else {
			error_log("Daily aggregation: Failed to insert blob $aggregate_blob",
				  3, $game_cfg['log_file']);
		}
	}

	// Make latest daily profile available via a constant path
	$aggregate_blobdir = "$aggregated_xhprof_path{$server_cfg['blob_dir']}";
	$daily_profile = sprintf($server_cfg['daily_profile'], $game_cfg['name']);
	
	if (file_exists($daily_profile)) {
		unlink($daily_profile);
	}

	@symlink($aggregate_blobdir, $daily_profile);
}

//
// "enabled_metrics" => array("rightscale_data", "xhprof_blob", "apache_stats",
//			"zmonitor_data", "function_analytics", "slow_page") as 
// given in game configuration. It runs for each each metrics enabled. Default 
// is all metrics. First processes/aggregates the xhprof.tbz files for all past
// 48 timeslots(24 hours), starting from the given timeslots and inserts the 
// aggregated xhprof.tbz file in xhprof_blobs_daily.For all other metrics gets 
// aggregated data for 48 timeslots from their 30 minutes table and inserts them
// into corresponding daily table.
//
function run_daily_aggregation($server_cfg, $game_cfg, $timeslot, $tables)
{

		
	error_log(sprintf("==>Daily aggregation Game: %s, timeslot: %s<==\n", 
		  $game_cfg["name"], $timeslot), 3, $game_cfg['log_file']);

	
	$xhprofModelObject = new XhprofModel($server_cfg, $game_cfg, false);

	error_log("Daily aggregation processing profiles", 3, $game_cfg['log_file']);
	insert_aggregated_xhprof_blob($server_cfg, $game_cfg, $timeslot);

	foreach ($tables as $table) {

		$result = insert_daily_aggregated_data($table, $timeslot, $xhprofModelObject);

		if (!$result) {

			echo mysql_error()."\n";
			error_log("Daily aggregation error in inserting $table data\n", 3, 
				   $game_cfg['log_file']);
		}
	}

	//
	// Now calling stored procedures to flip daily tables
	//
	$result = $xhprofModelObject->generic_execute_get_query_multi("daily_aggregate_flip", array());
	if (!$result) {
		echo "flip error: ".mysql_error()."\n";
	}
}


function usage($msg)
{
	echo "error: $msg\n";
	exit(1);
}


function process_daily($server_cfg, $game_cfg, $timeslot, $period)
{
	//
	// Tables for which daily data needs to be computed. Table names and
	// query names are derived from these 30min tables. eg.
	// 30min table name = stats_30min.
	// daily table name = stats_daily.
	// query name = stats_daily_aggregated_insert
	//
	$tables = array('stats_30min', 'apache_stats_30min','top5_functions_30min',
			'tracked_functions_30min','vertica_stats_30min');

	run_daily_aggregation($server_cfg, $game_cfg, $timeslot, $tables);
}

?>
