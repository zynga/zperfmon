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


include_once "/var/www/html/zperfmon/xhprof_lib/utils/xhprof_lib.php";
include_once "/var/www/html/zperfmon/xhprof_lib/utils/xhprof_runs.php";

#
# For each page find how long main took. 
# If it took more than X seconds insert "page, IP, timestamp, time_taken, profile, top1...5 excl-wt"
#
# Drop rows which are older than 48 hours.
#

  /* slow_page table schema

CREATE TABLE slow_page
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
	profile		medium_blob,

	INDEX slow_page_index (page_time, page)
);


id, page, ip, timestamp, page_time, top_excl_wt_1, top_excl_wt_2, top_excl_wt_3, top_excl_wt_4, top_excl_wt_5, profile

Delete query syntax:

DELETE FROM slow_page WHERE unix_timestamp(timestamp) < unix_timestamp(current_timestamp() - INTERVAL 24 hour);

  */


#
# Return top 5 functions, wall time, count and exclusive wall time
# -wise from the given flat profile.
function get_top_5_ewt($flat_profile)
{
	$top_excl_wall_time = create_function('$a, $b',
			     'return ($a["excl_wt"] == $b["excl_wt"]) ? 0 :
				     ($a["excl_wt"] < $b["excl_wt"]) ? 1 : -1;');

	uasort($flat_profile, $top_excl_wall_time);
	$top5 = array_slice($flat_profile, 0, 5);

	$result = array();

	# Merge fn name, excl wall time, count and wall time
	foreach($top5 as $fn => $entry) {

		$ewt = $entry["excl_wt"];
		$wt = $entry["wt"];
		$ct = $entry["ct"];

		$result[] = "$fn,$ewt,$ct,$wt";
	}

	return $result;
}


function insert_prof_file_and_query($server_cfg, $game_cfg, $prof_file, $stmt)
{
	$table = $game_cfg["slow_page_table"];

	$db_server = $game_cfg["db_host"];
	$db_user = $game_cfg["db_user"];
	$db_pass = $game_cfg["db_pass"];
	$db_name = $game_cfg["db_name"];

	$mysql_pdo = new PDO( "mysql:host={$db_server};dbname={$db_name}",
			      $db_user, $db_pass);

	if (!$mysql_pdo) {
		$game_cfg['logger']->log("insert_slowpage",
					"Failed to create new mysql PDO", Logger::ERR);
		error_log("Failed to create new mysql PDO\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return -1;
	}

	$prof_handle = fopen($prof_file, "rb");
	if (!$prof_handle) {
		$game_cfg['logger']->log("insert_slowpage",
					"Failed to open profile {$prof_file}", Logger::ERR);
		error_log("Failed to open profile file {$prof_file}.\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return 1;
	}

	error_log($stmt. "\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));

	$insert_statement = $mysql_pdo->prepare($stmt);

	if (!$insert_statement) {
		$game_cfg['logger']->log("insert_slowpage", 
					"Failed to create insert statement", Logger::ERR);
		error_log("Failed to create insert statement\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log(print_r($mysql_pdo->errorCode(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
	}

	if (!$insert_statement->bindParam(':PROF_FILE', $prof_handle, PDO::PARAM_LOB)) {
		$game_cfg['logger']->log("insert_slowpage", "Bindparam failed", Logger::ERR);
		error_log("bindparam failed", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log(print_r($mysql_pdo->errorCode(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		//print_r($mysql_pdo->errorCode());
		//print_r($mysql_pdo->errorInfo());
		return -2;
	}

	if (!$mysql_pdo->beginTransaction()) {
		$game_cfg['logger']->log("insert_slowpage", "beginTransaction failed", Logger::ERR);
		error_log("beginTransaction failed", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log(print_r($mysql_pdo->errorCode(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		//print_r($mysql_pdo->errorCode());
		//print_r($mysql_pdo->errorInfo());
		return -2;
	}
 
	if (!$insert_statement->execute()) {
		$game_cfg['logger']->log("insert_slowpage", "execute failed", Logger::ERR);
		error_log("execute failed", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log(print_r($mysql_pdo->errorCode(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		//print_r($mysql_pdo->errorCode());
		//print_r($mysql_pdo->errorInfo());
		return -2;
	}

	$insertion_id = $mysql_pdo->lastInsertId(); 

	if (!$mysql_pdo->commit()) {
		$game_cfg['logger']->log("insert_slowpage", "commit failed. could 
					not insert blob from file handle into db", Logger::ERR);
		error_log("commit Failed could not insert blob from file handle into db\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log(print_r($mysql_pdo->errorCode(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		//print_r($mysql_pdo->errorCode());
		//print_r($mysql_pdo->errorInfo());
		return -2;
	}

	return $insertion_id;
}


function query_insert_page($server_cfg, $game_cfg, $name_split, 
			   $profile_file, $flat_profile, $page_time)
{
	$top_fns = get_top_5_ewt($flat_profile);
	
	$top_5 = "";
	foreach($top_fns as $fn) {
		if ($fn) {
			$top_5 .= "'$fn', ";
		}
	}
	
	$page_details = "'$name_split[3]', '$name_split[2]', from_unixtime({$name_split[1]})";

	$columns = "page, ip, timestamp, page_time, top_excl_wt_1, top_excl_wt_2, top_excl_wt_3, top_excl_wt_4, top_excl_wt_5, profile";

	$query = "INSERT INTO slow_page ($columns) VALUES ($page_details, $page_time, $top_5 :PROF_FILE)";
	
 	$insertion_id = insert_prof_file_and_query($server_cfg, $game_cfg, $profile_file, $query);
	if ($insertion_id >= 0) {
		$tgt_dir = sprintf($server_cfg["slow_page_dir"], 
				   $game_cfg["name"]);
		$file_name = $name_split[3];
		$target_file = "$tgt_dir/$insertion_id.$file_name.xhprof";

		symlink($profile_file, $target_file);

		error_log("Symlinking $target_file -> $profile_file\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	}
	return $insertion_id;
}


#
# Flatten the given profile and find wall-time for "main". If it is
# more than max-allowed return the time and flattened profile.
#
function slow_page($dir_path, $profile_name, $max_time, &$flat_profile)
{
	// XXX: xhprof depends on this global for doing entry counts
	global $display_calls;
	$display_calls = true;

	$profile = XHProfRuns_Default::load_profile("$dir_path/$profile_name");

	$dummy = null;
	$flat_profile = xhprof_compute_flat_info($profile, $dummy);

	return $flat_profile["main()"]["wt"];

}


#
# Scan all profiles in given direcetory, if any profile has a "main"
# function with inclusive wall-time more than 'max_time'
#
function scan_and_insert($server_cfg, $game_cfg, $dir_path)
{
	$xhprof_slow_dir = sprintf($server_cfg["slow_page_dir"], 
				   $game_cfg["name"]);
	$xhprof_symlinks = array();

	foreach(glob("$xhprof_slow_dir/*") as $lnk) {
		if(is_link($lnk)) {
			$xhprof_symlinks[readlink($lnk)] = $lnk;
		}
	}

	foreach (scandir($dir_path) as $profile) {
		# Basic duplicate check 
		# if the symlink has already been established, bail out
		if(array_key_exists("$dir_path/$profile", $xhprof_symlinks)) {
			continue;
		}
		$prof_components = explode(":", $profile);
		#
		# Basic check to see if this file was created by
		# zperfmon client.
		#
		if (count($prof_components) < 5 ) continue;
		if ($prof_components[count($prof_components) - 1] != 'xhprof') continue;
		if (count($prof_components) == 6){
			unset($prof_components[3]);
			$prof_components = array_values($prof_components);
		}
		$page_time = slow_page($dir_path, $profile, $game_cfg["slow_page_threshold"], $flat_profile);
		if ($page_time == 0) {
			continue;
		}

		$query_res = query_insert_page($server_cfg, $game_cfg, $prof_components, 
				  "$dir_path/$profile", $flat_profile, $page_time);
		if ($query_res >= 0) {
			$game_cfg['logger']->log("insert_slowpage",
						"slow_pages  for $dir_path/$profile inserted", Logger::INFO);
		} else if ($query_res != 0) {
			$game_cfg['logger']->log("insert_slowpage",
						"slow_pages for $dir_path/$profile not inserted", Logger::ERR);
		}	
	}
}


function drop_old_rows($server_cfg, $game_cfg, $current_timestamp)
{
	$ts_limit = ($current_timestamp * 1800) - ($game_cfg["slow_page_retention"] * 60 * 60);

	$table = $game_cfg["slow_page_table"];

	$query = "DELETE FROM $table WHERE unix_timestamp(timestamp) < $ts_limit";

	$query_res = execute_queries($server_cfg, $game_cfg, array($query));
	if ( $query_res === 0 ) {
		$game_cfg['logger']->log("insert_slowpage", "old rows of slow pages  for ".
                                         $game_cfg['name']." successfully  dropped", Logger::INFO);
	} else if ( $query_res !== 0 ) {
		$game_cfg['logger']->log("insert_slowpage","old rows of slowa pages for ".
                                         $game_cfg['name']." not dropped", Logger::ERR);
	}

}


function insert_slow_page($server_cfg, $game_cfg, $time_slots='*')
{
	$root_upload_directory = sprintf($server_cfg['root_upload_directory'], $game_cfg['name']);

	$slowpage_markers = glob("$root_upload_directory/$time_slots/".
				  $server_cfg['profile_upload_directory']."/.slowpages", GLOB_BRACE);

	if (empty($slowpage_markers)) {
		error_log("No slowpage for $time_slots\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		$game_cfg['logger']->log("insert_slowpages", "No slow pages for time slots $time_slots", Logger::INFO);
		return;
	}

	foreach($slowpage_markers as $marker){

		$profile_upload_directory = dirname($marker);

		$timestamp = (int)(basename(dirname($marker)) * 1800);
		error_log("slow pages for $profile_upload_directory\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));

		$timeslot = (int)(basename(dirname(dirname($marker))));
		$page_dir = sprintf($server_cfg["slow_page_dir_ts"],
                              $game_cfg['name'], $timeslot);

		scan_and_insert($server_cfg, $game_cfg, $page_dir);

		error_log("Deleting $marker... " . (unlink($marker) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));

	}
	$run_timestamp = max(explode(",",trim($time_slots,"{}")));
	drop_old_rows($server_cfg, $game_cfg, $run_timestamp);
}


?>
