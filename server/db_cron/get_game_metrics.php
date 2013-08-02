#!/usr/bin/php

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
// This is the cron job for zperfmon which processes and inserts
// uploaded data into DB. It can be called with parameters as game name 
// and comma separated timeslots enclosed in braces.
//

ini_set('memory_limit', '48M');

error_reporting(E_ALL|E_STRICT);

include_once 'server.cfg';
include_once 'get_apache_metrics.php';
include_once 'get_top5_functions.php';
include_once 'insert_zmonitor_data.php';
include_once 'insert_bd_metrics.php';
include_once 'slowpage.php';
include_once 'game_config.php';
include_once 'logger.inc.php';
include_once 'array_wise_split.php';
include_once 'rightscale.php';
include_once 'compress.php';
include_once 'zpm_util.inc.php';
//
// process the uploaded profiles. calls massage_profile.py command to do this.
// for each given timeslot creates php page directories and ip directories 
// for each page and ips(which are uploading).
//
function process_xhprof_uploads($server_cfg, $game_cfg, $time_slots='*')
{
	$unzipping_command = $server_cfg["unzipping_command"];
	$upload_processing_command = $server_cfg["upload_processing_command"];
	$root_upload_directory = sprintf($server_cfg["root_upload_directory"], 
					    $game_cfg["name"]);
	$profiles_markers = glob("$root_upload_directory/$time_slots/" .
				      $server_cfg['profile_upload_directory'] . "/.profiles", GLOB_BRACE);
	
	if ( empty($profiles_markers) ) {
		error_log("There is no profiles to process for timeslots $time_slots\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		$game_cfg['logger']->log("profile_pre_processor", "There is no profiles to process 
								  for timeslots $time_slots", Logger::INFO);
		return;
	}

	foreach($profiles_markers as  $marker){

		$profile_upload_directory = dirname($marker);
		error_log("profile_upload_directory : ".$profile_upload_directory."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));

	        $timeslot = (int)(basename(dirname($profile_upload_directory)) );
        	$file_list = scandir("$root_upload_directory/$timeslot/" .
                	                      $server_cfg['profile_upload_directory'] );

	        $ip_list = array();
        	if( !isset($game_cfg["id"]) ){

                	foreach ( $file_list as $file){
                        	$pattern = "([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)";
	                        $filename = basename($file);
	
        	                if(preg_match($pattern, $filename) == 0) continue;
                	        $breakup = split("__",$filename);

                        	if( count($breakup) == 1 ) continue;
	                        $ip = $breakup['1'];

        	                if (!in_array($ip, $ip_list)){
                	                array_push($ip_list, $ip);
                        	}
                	}
        	}

		$timestamp = (int)(basename(dirname($profile_upload_directory)) * 1800);

		$unzip = implode(" ", array($unzipping_command,
					$game_cfg["name"],
					$profile_upload_directory, 
					$timestamp));
                $process = implode(" ", array($upload_processing_command,
                                        "-g {$game_cfg['name']} ",
                                        "-d $profile_upload_directory ",
                                        "-t $timestamp"));

		error_log("pre-processing $profile_upload_directory\n", 
			   3, sprintf($server_cfg['log_file'],$game_cfg['name']));

		$game_cfg['logger']->log("profile_pre_processor", "pre-processing 
					  $profile_upload_directory/", Logger::INFO);

		$retval = null; // refs will start failing in 5.3.x if not declared

		if ( !isset($game_cfg["id"]) ){
			$output = system($unzip, $retval);
			if($retval != 0){
				error_log("Couldn`t Unzip files from $profile_upload_directory/
					   $dir_timestamp/ \n", 3, 
					   sprintf($server_cfg['log_file'], $game_cfg['name']));
				continue;
			}		
			splitarraywise($server_cfg, $ip_list, $game_cfg["name"], 
					basename(dirname($profile_upload_directory)) );
		}
	
		echo "$process\n";
		$output = system($process, $retval);
		if ($retval != 0) {
			error_log("processing files from: $profile_upload_directory/$dir_timestamp/ 
				  \n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
			error_log("xhprof post-processing failed: $output\n", 3, 
				   sprintf($server_cfg['log_file'], $game_cfg['name']));
			$game_cfg['logger']->log("profile_pre_processor", "xhprof post-processing for 
						  $profile_upload_directory/$dir_timestamp/ failed", Logger::ERR);
			continue;
		}
		// ensure the functions analytics gets run off this new xhprof_tbz
		touch("$profile_upload_directory/.profiles");
		touch("$profile_upload_directory/.functions");
		// ensure that the compression will run for this directory
		touch("$profile_upload_directory/.compress");
	}
}


function insert_xhprof_blob($server_cfg, $game_cfg, $time_slots='*')
{	
	$root_upload_directory = sprintf($server_cfg['root_upload_directory'],$game_cfg['name']);

	$xhprof_tbz_name = $server_cfg["xhprof_tbz_name"];

	$profiles_markers = glob("$root_upload_directory/$time_slots/" .
				      $server_cfg['profile_upload_directory'] . "/.profiles", GLOB_BRACE);
	if ( empty($profiles_markers) ) {
		error_log("There is no xhprof blob for timeslots $time_slots\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		$game_cfg['logger']->log("insert_blob", "There is no xhprof blob for timeslots $time_slots", Logger::INFO);
		return;
	}

	foreach($profiles_markers as $marker){

		$profile_upload_directory = dirname($marker);
		$timestamp = (int)(basename(dirname($profile_upload_directory)) * 1800);
		$blob_file = "{$profile_upload_directory}/{$xhprof_tbz_name}";

		# figure out memory profiling was enabled or not or partially enabled. Use manifest.json to get this value.
		$manifest_file = $profile_upload_directory . "/" . $server_cfg['blob_dir'] . "/manifest.json";		
		$manifest = file_get_contents($manifest_file);
		$obj = json_decode($manifest);
		$all_array_manifest = $obj->{"all"};
		$memory_profiling_info = $all_array_manifest[2];

		if( filesize($blob_file) < 256 && /* but only for backfills */
	            ($run_timestamp - $timestamp) > 1800) {

			$game_cfg['logger']->log("insert_blob","File $blob_file seems to be too small 
						to contain profile info", Logger::INFO);
			error_log("File $blob_file seems to be too small to contain profile info\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
			continue;
		}

		if(insert_tbz($server_cfg, $game_cfg, $blob_file, $timestamp, $memory_profiling_info) == 0) {
			error_log("Deleting $marker... " . (unlink($marker) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			// Hard-coded please note!
			$blob_webroot = "/var/www/html/zperfmon/blobs/" . $game_cfg["name"];
			link($blob_file, $blob_webroot."/blob.$timestamp.tbz");
		}
	}
}


function insert_events($server_cfg, $game_cfg)
{
	$event_upload_directory = sprintf($server_cfg["event_directory"], $game_cfg["name"]);
	$queries = array();
	foreach(glob($event_upload_directory."/*.*") as $event_file) {
		$event_file_name = basename($event_file);
		$matches = array();
		if(preg_match("%(\d+).(.*)%", $event_file_name, $matches)){
			list(,$timestamp, $type) = $matches;
			$text = addslashes(trim(file_get_contents($event_file))); // escape this better
			$queries[] = "REPLACE INTO `events`(start, type, text) VALUES
					 (from_unixtime($timestamp), '$type', '$text')";
	
			error_log("Deleting $event_file... " . (unlink($event_file) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		}
	}

	if($queries) execute_queries($server_cfg, $game_cfg, $queries);
}

function showUsage($errorMessage) {
	$myname = basename(__FILE__);
	die("$errorMessage\nUSAGE: $myname\n");
}

//
// Execute the query and return 0 for success or 1 for failure
// These return values are used for inserting logs into log table
//
function execute_queries($server_cfg, $game_cfg, $queries)
{
	if (count($queries) > 1) {
		return batch_query($server_cfg, $game_cfg, $queries);
	}

	$mysql_connection = mysql_connect($game_cfg["db_host"],
					  $game_cfg["db_user"],
					  $game_cfg["db_pass"]);
	if (!$mysql_connection) {
		error_log(mysql_error() . "\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		return 1;
	}
	
	if (!mysql_select_db($game_cfg["db_name"], $mysql_connection)) {
                error_log(mysql_error() . "\n", 3, sprintf($server['log_file'], $game_cfg['name']));
		mysql_close($mysql_connection);
		return 1;
        }

	$query = array_pop($queries);
	if (!mysql_query($query)) {
		error_log(mysql_error() . "\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		return 1;
	}
	
	error_log(substr($query, 0, 1024). "\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));

	mysql_close($mysql_connection); 
	return 0;
}


// 
// Use mysqli for batched insert
//
function batch_query($server_cfg, $game_cfg, $queries)
{
	$combined_query = implode(";", $queries);
	error_log($combined_query . "\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));

	try {
		$db = new mysqli($game_cfg["db_host"],
				$game_cfg["db_user"],
				$game_cfg["db_pass"],
				$game_cfg["db_name"]);

		if (!$db->multi_query(implode(";", $queries))) {
			error_log("Failed!", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			$db->close();
			return 1;
		}

		do {
			if ($result = $db->store_result()) {
				while ($row = $result->fetch_row()) {
					printf("%s\n", $row[0]);
				}
				$result->free();
			}
		} while ($db->next_result());

		if ($db->errno) {
			error_log("Stopped while retrieving result : ", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			$db->close();
			return 1;
		} 
		
		$db->close();
	} catch (Exception $e) {
		error_log("Error in setting up mysqli multi-query:". $e->getMessage(). " \n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return 1;
	}

	return 0;
}


//////////////////////////////////////////////
//
// xhprof blob processing 
//
//
// Insert the given file into db as a blob, the row to insert in is
// identified by the given unix timestamp
//
function insert_tbz($server_cfg, $game_cfg, $tbz_file, $current_timestamp, $flag)
{
	$table = $game_cfg["xhprof_blob_table"];

	$db_server = $game_cfg["db_host"];
	$db_user = $game_cfg["db_user"];
	$db_pass = $game_cfg["db_pass"];
	$db_name = $game_cfg["db_name"];

	$mysql_pdo = new PDO( "mysql:host={$db_server};dbname={$db_name}",
			      $db_user, $db_pass);

	if (!$mysql_pdo) {
		$game_cfg['logger']->log("insert_tbz","Failed to create PDO object", Logger::ERR);
		error_log("Failed to create new mysql PDO\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return 1;
	}

	$tbz_handle = fopen($tbz_file, "rb");
	if (!$tbz_handle) {
		$game_cfg['logger']->log("insert_tbz","Failed to open tbz file ${$tbz_file}", Logger::INFO);
		error_log("Failed to open tbz file {$tbz_file}.\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return 1;
	}

	list($mon_p, $hour_p, $min_p, $sec_p) = split("-", date('m-G-i-s',$current_timestamp));
	$p_tag = $mon_p; 
	if (!((($hour_p == 0) || ($hour_p == 2) || ($hour_p == 4) || ($hour_p == 6) || ($hour_p == 8) || 
		($hour_p == 10) || ($hour_p == 12) || ($hour_p == 14) || ($hour_p == 16) || ($hour_p == 18) || ($hour_p == 20) || ($hour_p == 22) ) 
		&& ($min_p == 0) && ($sec_p == 0))) {
		$p_tag = 0 - $p_tag;
	}
	$stmt = "REPLACE INTO {$table} (timestamp, xhprof_blob, flags, p_tag) VALUES (from_unixtime($current_timestamp), :tbz_handle, :flags, $p_tag)";
	error_log("$stmt\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
	$insert_statement = $mysql_pdo->prepare($stmt);
	if (!$insert_statement) {
		error_log("Failed to create insert statement\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		$game_cfg['logger']->log("insert_tbz","Failed to create insert statement", Logger::ERR);
		print_r($mysql_pdo->errorCode());
		print_r($mysql_pdo->errorInfo());
		return 2;
	}
	
	if (!$insert_statement->bindParam(':tbz_handle', $tbz_handle, PDO::PARAM_LOB) ||
	    !$insert_statement->bindParam(':flags', $flag, PDO::PARAM_STR) || 	
	    !$mysql_pdo->beginTransaction() ||
	    !$insert_statement->execute() ||
	    !$mysql_pdo->commit()) {
		error_log("Failed to insert blob from file handle into db\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log(print_r($mysql_pdo->errorCode(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		error_log(print_r($mysql_pdo->errorInfo(), true), 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		$game_cfg['logger']->log("insert_tbz","Failed to insert blob from file handle into db", Logger::ERR);
		return 3;
	}
	
	$game_cfg['logger']->log("insert_tbz","${tbz_file} : blob is inserted", Logger::INFO);
	return 0;
}


//
// Open the passed file in rw mode, take and exclusive lock on it and
// write the current pid into it.
// 
// If any of the above steps fail return an error, else return the file
// handle.
//
function grab_lock($lock_file, $server_cfg, $game_cfg)
{
	$handle = fopen($lock_file, "c");
	if ($handle == FALSE) {
		error_log("Opening $lock_file failed\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return FALSE;
	}

	if (flock($handle, LOCK_EX | LOCK_NB)) {
		ftruncate($handle, 0);
		fwrite($handle, strval(getmypid()));
		return $handle;
	}

	error_log("Taking lock on $lock_file failed\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	fclose($handle);
	return FALSE;
}

//
// unlock, close and remove the passed file.
//
function drop_lock($handle, $lock_file)
{
	flock($handle, LOCK_UN); // release the lock
	fclose($handle);
	unlink($lock_file);
}


function run_cron_for_game($server_cfg, $game_cfg, $time_slots)
{
	date_default_timezone_set('UTC');
	$current_timestamp = $_SERVER['REQUEST_TIME'];

	//
	// Logger object has to be passed to all child functions, 
	// put it in the game configuration array
	//
	$game_cfg['logger'] = new Logger($server_cfg, $game_cfg);

	// All metrics are enabled by default
	if (!array_key_exists("enabled_metrics", $game_cfg)) {
		$enabled_metrics = array("rightscale_data", "xhprof_blob", 
					 "apache_stats", "zmonitor_data", 
					 "function_analytics", "slow_page");
	} else {
		$enabled_metrics = $game_cfg["enabled_metrics"];
	}

	//
	// If time slots are passed as parameter it should be passed
	// in correct format i.e. {t1,t2},where t1 etc are timeslots,
	// so that it can be used in glob with BRACE. If there is no
	// timestamp we assume processing is happening for the current
	// timeslot. We need the current time slot and the previous one
	// as we may have crossed the 30 min boundary after the cron
	// job was launched.
	//
	if ( empty($time_slots) ) {
		$slot = (int)($current_timestamp/1800);
		$time_slots = sprintf("{%d,%d}", $slot, $slot-1);
	}

	error_log(sprintf("==> Game: %s, time_slots: %s<==\n", $game_cfg["name"], $time_slots), 
			3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		     

	//
	// we need to look at xhprof uploads only if any of the enabled
	// metrics need it.
	//
	if (in_array("xhprof_blob", $enabled_metrics) ||
	    in_array("function_analytics", $enabled_metrics)) {
		process_xhprof_uploads($server_cfg, $game_cfg, $time_slots);
	}

	insert_events($server_cfg, $game_cfg);
	foreach($enabled_metrics as $metric) {
		try {
			$metric_function = "insert_{$metric}";
			//
			// calls insert_apache_stats
			//
			$metric_function($server_cfg, $game_cfg, $time_slots);
		} catch (Exception $e) {
			$game_cfg['logger']->log("Cron Run","Failed to process and 
						  insert data for $metric", Logger::CRIT);
			error_log("Failed to process and insert data for $metric\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			error_log("Exception says: ". $e->getMessage(). "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		}
	}
}

function get_options()
{
	/*$options = array('game'=>'','timeslots'=>'');
	for($i = 0; $i < count($GLOBALS['argv']); $i++ ){
		$option_name = trim($GLOBALS['argv'][$i],"-");
		if(array_key_exists($option_name,$options)){
			$i++;
			$options[$option_name] = $GLOBALS['argv'][$i];
		}
	}*/

	$options = getopt("g:t:");
	
	return $options;
}

function create_directory($dir_name,$current_time_slot,$server_cfg,$game_cfg)
{
        if (!$current_time_slot) {
                return null;
        }

        //
        // Make sure any minute inside given half hour slot goes into that
        // timeslot. If we round(), till 15th minute will go into the previous
        // timeslot and 15-30 will go into the next timeslot. To avoid that,
        // we cast to int and get the mantissa.
        //
        $time_slot_1 = (int)$current_time_slot + 1;
	$time_slot_2 = $time_slot_1 + 1;
        $dir_name_1 = sprintf($dir_name, (string)$time_slot_1);
	$dir_name_2 = sprintf($dir_name, (string)$time_slot_2);
        $oldmask = umask(0); // to set the chmod of directory as 777

	$user = "apache";	

	if (!is_dir($dir_name_1)  && !mkdir($dir_name_1, 0777, true)){
                return null;
        }

	if (!is_dir($dir_name_2)  && !mkdir($dir_name_2, 0777, true)){
                return null;
        }

	$base = "/".$server_cfg['ram_disk_base_path']."/";
        $check_ram_disk = shell_exec("df -h");

        if(preg_match("/".$server_cfg['ram_disk_base_path']."/",$check_ram_disk)){
                $dir_name_ram_1 = $base.$dir_name_1;
		$dir_name_ram_2 = $base.$dir_name_2;
                if (!is_dir($dir_name_ram_1)  && !mkdir($dir_name_ram_1, 0777, true)){
                          return null;
                 }
		if (!is_dir($dir_name_ram_2)  && !mkdir($dir_name_ram_2, 0777, true)){
                          return null;
                }
                shell_exec("mount --bind $dir_name_ram_1 $dir_name_1");
		$a = shell_exec("echo $?");
		if($a != 0){
			error_log("Mount Error no : $a", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		}
		shell_exec("mount --bind $dir_name_ram_2 $dir_name_2");
		$b = shell_exec("echo $?");
		if($b != 0){
			error_log("Mount Error no : $b", 3, sprintf($server_cfg['log_file'],$game_cfg['name'])); 		
		}
        }
	else{
		error_log("NO Ramdisk present at $base", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return 0;
	}	
	chown($dir_name_1,$user);
	chown($dir_name_2,$user);
	chown($dir_name_ram_1,$user);
	chown($dir_name_ram_2,$user);	

	chgrp($dir_name_1,$user);	
	chgrp($dir_name_2,$user);	
	chgrp($dir_name_ram_1,$user);	
	chgrp($dir_name_ram_2,$user);	
        umask($oldmask);
	$dir_name_array = array();
	$dir_name_array[] = $dir_name_1;
	$dir_name_array[] = $dir_name_2;
        return $dir_name_array;

}

function delete_ramdisk_data($game_name,$time_slots,$timestamp,$server_cfg,$game_cfg){
	if (empty($time_slots) ) {
                        $slot = (int)($timestamp/1800);
                        $time_slots_array[] = $slot;
                        $time_slots_array[] = $slot - 1;
                }
                else{
                        $search = array("{","}");
                        $time_slots = str_replace($search,'',$time_slots);
                        $time_slots_array[] = $time_slots;
                }

                $base = "/".$server_cfg['ram_disk_base_path']."/";
                $game_cfg = load_game_config($game_name);
                $root_upload_directory = sprintf($server_cfg["root_upload_directory"],
                                          $game_cfg["name"]);
                foreach($time_slots_array as $timeslot_val){
                        $path_timeslot = $root_upload_directory.$timeslot_val."/".$server_cfg["profile_upload_directory"]."/";
                        $check_mount_point = trim(shell_exec("mountpoint ".$path_timeslot));
                        $a = 0;
                        while($check_mount_point == "$path_timeslot is a mountpoint"){

                                shell_exec("umount -l $path_timeslot");
                                $a = shell_exec("echo $?");
                                if($a != 0){
                                        error_log("Umount Error No : $a", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                                        break;
                                }

                                $check_mount_point = trim(shell_exec("mountpoint ".$path_timeslot));
                        }
                        if($a ==0){
                                $user = "apache";
                                $oldmask = umask(0); // to set the chmod of directory as 777
                                shell_exec("cp -r ".$base.$path_timeslot." ".$root_upload_directory.$timeslot_val."/");
                                chown($path_timeslot,$user);
                                chown($path_timeslot.".profiles",$user);
                                chown($path_timeslot.".slowpages",$user);
                                chown($path_timeslot.".apache_stats",$user);

                                chgrp($path_timeslot,$user);
                                chgrp($path_timeslot.".profiles",$user);
                                chgrp($path_timeslot.".slowpages",$user);
                                chgrp($path_timeslot.".apache_stats",$user);

                                umask($oldmask);

                                shell_exec("rm -rf ".$base.$root_upload_directory.$timeslot_val."/");
                        }
                        else{
                                error_log("[FATAL ERROR]Skipping unmount and copying data from ramdisk!!", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                        }
                }
}

//
// Main processing loop: scan upload directories for each configured
// game and do post-processing.
//
function main($server_cfg)
{
	$options = get_options();
	if (isset($options['g']) && $options['g'] !== '') {
		$game_names = explode(",",$options['g']);
	} else {
		$game_names = $server_cfg['game_list'];
	}
	$time_slots = null;
	if (!empty($options['t'])) {
		$time_slots = $options['t'];
	}
	$timestamp = $_SERVER['REQUEST_TIME'];
	$current_time_slot = (int)($timestamp / (30 * 60));
	foreach ($game_names as $game_name) {
		zpm_preamble($game_name);
		
		// MODIFIED!!!! Create and mount next two timeslots !!!
		$game_cfg = load_game_config($game_name);
		if(!$game_cfg){
                       error_log("configuration for ".$game_name." is not loaded\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                       continue;
                }

		$target_dir = sprintf($server_cfg['root_upload_directory'],$game_cfg["name"]);
		$target_dir = $target_dir."/%s/".$server_cfg["profile_upload_directory"];
		$dir_name_array = create_directory($target_dir,$current_time_slot,$server_cfg,$game_cfg);
		if($dir_name_array === null){
		       error_log("Directory creation failed for the game ".$game_cfg['name'], 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                       continue;
		}	
		if($dir_name_array === 0){
                       error_log("No Ram disk Exist!!!".$game_cfg['name'], 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                }
	
		//creating new games and getting the list of web arrays to process data for
	        $rs = new RightScale($server_cfg, load_game_config($game_name));

        	$rs->make_array_games();

		$array_ids = $rs->get_arrays_to_serve();

                //
                // Wrap processing for each game inside a file lock
                //
                $game_lock_file = sprintf($server_cfg["zperfmon_lock_file"],
                                     $game_name);

                $game_lock_handle = grab_lock($game_lock_file, $server_cfg, $game_cfg);
                if (!$game_lock_handle) {
                       error_log("Could not get exclusive lock for \"$game_name\"\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		       delete_ramdisk_data($game_name,$time_slots,$timestamp,$server_cfg,$game_cfg);
                       continue;
                }

		//processing parent game
                try {
                        //
                        // process uploaded data
                        //
                        run_cron_for_game($server_cfg, $game_cfg, $time_slots);

                } catch (Exception $e) {
                        error_log("Upload processing for $game_name failed\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                        error_log("Exception says: ". $e->getMessage(). "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                }

		// loop to process data for each web array
		foreach ($array_ids as $array){
        	        try {
                        	$game_cfg = load_game_config($game_name, $array);
                        	if(!$game_cfg){
                                	error_log("configuration for ".$game_name." is not loaded\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                               		continue;
                        	}
                        	//
                        	// process uploaded data
                        	//
                        	run_cron_for_game($server_cfg, $game_cfg, $time_slots);

                	} catch (Exception $e) {
                        	error_log("Upload processing for $game_name failed\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                        	error_log("Exception says: ". $e->getMessage(). "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                	}

		}
		// compress for parent game 
		// This implementation forces us to always pass a timeslot
		// while calling this script. otherwise compression will fail.
		$game_cfg = load_game_config($game_name);
		compress_unziped_profiles($server_cfg, $game_cfg, $time_slots);
		// Now compress the array games 
		foreach($array_ids as $array) {
			$game_cfg = load_game_config($game_name, $array);

			compress_unziped_profiles($server_cfg, $game_cfg, $time_slots);
		}
		// cleanup the lock
		drop_lock($game_lock_handle, $game_lock_file);

		zpm_postamble($game_name);

		//MODIFIED!!!!
		if($dir_name_array != 0){
			delete_ramdisk_data($game_name,$time_slots,$timestamp,$server_cfg,$game_cfg);
		}		
	}
}

main($server_cfg);



?>