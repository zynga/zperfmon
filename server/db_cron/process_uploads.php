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
// This is the cron job for zperfmon which processes uploaded xhprof profiles. 
// In processing it does not aggreagtes the proccessed profiles.  
// It can be called with parameters as game name 
// and comma separated timeslots enclosed in braces.
//

ini_set('memory_limit', '48M');

error_reporting(E_ALL|E_STRICT);

include_once 'server.cfg';
include_once 'game_config.php';
include_once 'logger.inc.php';
include_once 'array_wise_split.php';
include_once 'rightscale.php';
include_once 'zpm_util.inc.php';

function create_command($cmd_name, $server_cfg, $game_cfg, $timeslot, $ips=null) 
{
	$game_name = $game_cfg['name'];
	$root_path = sprintf($server_cfg["root_upload_directory"], $game_name);
	$upload_dir_name = $server_cfg['profile_upload_directory'];
	$upload_path = "$root_path/{$timeslot}/{$upload_dir_name}";
	$cmd = $server_cfg[$cmd_name];

	$timestamp = $timeslot * 1800;

	if ($cmd_name === "unzipping_command") {
		return "$cmd  $game_name  $upload_path  $timestamp";
	}

	if ($cmd_name === "upload_processing_command" and !empty($ips)) {
		$cmd .= " -g $game_name -d $upload_path -t $timestamp";
		$cmd .= " --no-aggregate --ip-list " . implode(",", $ips); 
		return $cmd;
	}
}

function process_profiles($server_cfg, $game_cfg, $ip_list, $grouped_ips, $timeslot) {

	echo "processing for games\n";
	$game_name = $game_cfg['name'];
	$cmd = create_command("upload_processing_command", $server_cfg, 
				$game_cfg, $timeslot, $ip_list);
	//echo "$cmd\n"; 
	$retval = null;
	$output = system($cmd, $retval);
	if( $retval != 0 ) {
		echo"Couldn`t process profiles of  $timeslot for {$game_name}\n";
	}

	//print_r($grouped_ips);
	foreach ( $grouped_ips as $array_id=>$ip_list) {
		$game_cfg = load_game_config($game_name, $array_id);
		$cmd = create_command("upload_processing_command", $server_cfg, 
					$game_cfg, $timeslot, $ip_list);
		$output = system($cmd, $retval);
		if( $retval != 0 ) {
			echo"Couldn`t process profiles of  $timeslot for {$game_name}\n";
		}
	}
}
//
// process the uploaded profiles. calls massage_profile.py command to do this.
// for each given timeslot creates php page directories and ip directories 
// for each page and ips(which are uploading).
//
function process_uploads($server_cfg, $game_cfg, $time_slots='*')
{
	$game_name = $game_cfg['name'];

	$root_upload_directory = sprintf($server_cfg["root_upload_directory"], $game_name);
	$profiles_markers = glob("$root_upload_directory/$time_slots/" .
			$server_cfg['profile_upload_directory'] . "/.profiles", GLOB_BRACE);

	if ( empty($profiles_markers) ) {
		echo "no profile markers\n";
		return;
	}

	foreach ( $profiles_markers as  $marker ) {

		$timeslot = (int)(basename(dirname(dirname($marker))));

		$retval = null; // refs will start failing in 5.3.x if not declared
		$output = null;
		$cmd = create_command("unzipping_command", $server_cfg,	$game_cfg, $timeslot);
		//echo $cmd."\n";
		$out = exec($cmd, $output, $retval);
		if($retval != 0){
			echo "Couldn`t unzip " . dirname($marker). "\n";
			continue;
		}
		$ip_list = explode(",", $output[0]);
		//print_r($ip_list);
		if(count($ip_list) > 1 or $ip_list[0] !== "") {
			$grouped_ips = splitarraywise($server_cfg, $ip_list, $game_name, $timeslot);	
			process_profiles($server_cfg, $game_cfg, $ip_list, $grouped_ips, $timeslot);
		}
	}
}

//
// sets the timeslot to current timeslot if it is null.
//
function run_upload_processing($server_cfg)
{
	date_default_timezone_set('UTC');
	$current_timestamp = $_SERVER['REQUEST_TIME'];

	$options = getopt("g:t:a");
	if (isset($options['g']) && $options['g'] !== '') {
		$game_names = explode(",",$options['g']);
	} else {
		$game_names = $server_cfg['game_list'];
	}

	$time_slots = null;
	if (empty($options['t'])) {
		$time_slots = (int)($current_timestamp/1800);
		$time_slots = "{".$time_slots."}";
	} else {
		$time_slots = $options['t'];
	}

	foreach ($game_names as $game_name) {
	
		zpm_preamble($game_name);
		$game_cfg = load_game_config($game_name);
		// Process each array of the game
		$rsObj = new RightScale($server_cfg, $game_cfg);
		$rsObj->make_array_games();
		process_uploads($server_cfg, $game_cfg, $time_slots, $rsObj);
		zpm_postamble($game_name);
	}
}

run_upload_processing($server_cfg);

?>
