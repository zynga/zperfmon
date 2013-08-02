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


#
#This script will take the game name and the time slot
#and will return the list of slow profiles as a json string
#and also move them to slow_pages directory
#

include_once "server.cfg";
include_once "game_config.php";
include_once "/var/www/html/zperfmon/xhprof_lib/utils/xhprof_lib.php";
include_once "/var/www/html/zperfmon/xhprof_lib/utils/xhprof_runs.php";

include_once "insort.php";

#
# For each page find how long main took. 
# If it took more than X seconds move the xhprof profile file to slow_pages dir. 
#


#
# Move the slow pages to slow_pages directory
#
function query_move_slow_page($server_cfg, $game_name, $name_split, 
			   $profile_file, $flat_profile, $page_time, $time_slot, &$output)
{
	$target_dir = sprintf($server_cfg["slow_page_dir_ts"],
			      $game_name, $time_slot);	

	if(!is_dir($target_dir)){
		mkdir($target_dir);
	}

	$file_name = basename($profile_file);
	$target_file = "$target_dir$file_name";

	$result = rename($profile_file, $target_file);

	if($result == FALSE){
		return 0;
	}

	array_push($output, $profile_file);
	
	error_log("Moving $target_file -> $profile_file\n", 3, sprintf($server_cfg['log_file'],$game_name));
	return 1;
}


/* create this array once, not per call */
$metrics = array("ct", "excl_wt", "excl_cpu", "excl_mu", "excl_pmu");

/*
 * Format for top-5 metrics for all pages
 *
 * [ "cpu" => [
 * "profile path 1", top1-fn, top1-val, top2-fn, top2-val, .....
 * "profile path 2", top1-fn, top1 .....
 * ...]
 * 
 * "excl_wt" => [
 * "profile path 1", top1-fn, top1-val, top2-fn, top2-val, .....
 * "profile path 2", top1-fn, top1 .....
 * ...]
 * 
 * ...]
 */
function collapse($row) {
	return array("n1" => $row[0]->name, "v1" => $row[0]->metric,
		     "n2" => $row[1]->name, "v2" => $row[1]->metric,
		     "n3" => $row[2]->name, "v3" => $row[2]->metric,
		     "n4" => $row[3]->name, "v4" => $row[3]->metric,
		     "n5" => $row[4]->name, "v5" => $row[4]->metric);
}


function add_to_top5($profile, $fpath, &$top5) {
	global $metrics;

	$tops = array();
	$need_metrics = array();
	$has_metrics = $profile["main()"];

	/* Get list of metrics we can extract from this profile */
	foreach ($metrics as $m) {
		$tops[$m] = new TopX();
		if (isset($has_metrics[$m])) {
			$need_metrics[] = $m;
		}
	}

	# Build top5 for each metric
	foreach ($profile as $func => $data) {
		foreach ($need_metrics as $m) {
			$tops[$m]->insert($func, $data[$m]);
		}
	}

	// Insert top5s into the master list for this timeslot
	foreach ($tops as $metric => $data) {
		$top5[$metric][$fpath] = collapse($data);
	}

}

#
# Flatten the given profile and find wall-time for "main". If it is
# more than max-allowed return the time and flattened profile.
#
function slow_page($dir_path, $profile_name, $max_time, &$flat_profile, &$top5, $game_name, $server_cfg)
{
	// XXX: xhprof depends on this global for doing entry counts
	global $display_calls;
	$display_calls = true;

	$profile = XHProfRuns_Default::load_profile("$dir_path/$profile_name");

	$dummy = null;
	$flat_profile = xhprof_compute_flat_info($profile, $dummy);

	add_to_top5($flat_profile, $profile_name, $top5);

	if ($flat_profile["main()"]["wt"] > $max_time) {
		return $flat_profile["main()"]["wt"];
	}

	$flat_profile = null;
	return 0;
}


#
# Scan all profiles in given direcetory, if any profile has a "main"
# function with inclusive wall-time more than 'max_time'
#
function scan($server_cfg, $game_name, $slow_page_threshold, $dir_path, $time_slot,
	      &$output, &$top5)
{
	$xhprof_slow_dir = sprintf($server_cfg["slow_page_dir"], 
				   $game_name);
	$xhprof_symlinks = array();

	foreach(glob("$xhprof_slow_dir/*") as $lnk) {
		if(is_link($lnk)) {
			$xhprof_symlinks[readlink($lnk)] = $lnk;
		}
	}

	error_log(sprintf("top5 list has %d lines\n", count($top5)), 3, sprintf($server_cfg['log_file'],$game_name));
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

		$page_time = slow_page($dir_path, $profile, $slow_page_threshold,
				       $flat_profile, $top5, $game_name, $server_cfg);
		if ($page_time == 0) {
			continue;
		}
		$query_res = query_move_slow_page($server_cfg, $game_name, $prof_components, 
				  "$dir_path/$profile", $flat_profile, $page_time, $time_slot,$output);
	}
	error_log(sprintf("in scan top5 list has %d lines\n", count($top5)), 3, sprintf($server_cfg['log_file'],$game_name));
}


function move_slow_page($server_cfg, $game_name, $time_slot='*', &$top5)
{
	
	$output = array();
	$root_upload_directory = sprintf($server_cfg['root_upload_directory'], $game_name);

	//checking if the input game_name corresponds to a array of a parent game 
        try {
		$game_cfg = array();
		if(!in_array($game_name,$server_cfg["game_list"])){
			/*$game_split = split("_", $game_name);
			$game_name_array = array();
			for ( $counter = 0; $counter < count($game_split)-1; $counter ++) {
				array_push($game_name_array, $game_split[$counter]);
			}
			$game_name = implode($game_name_array);
			$array_id = $game_split[count($game_split)-1];*/
			$last_tok_index = strrpos($game_name, "_");
			$array_id = substr($game_name, $last_tok_index + 1 , strlen($game_name) - $last_tok_index );
			$game_name = substr($game_name, 0,$last_tok_index );
			$game_cfg = load_game_config($game_name, $array_id);
		}
		else{
			$game_cfg = load_game_config($game_name);
		}
		
		$game_name = $game_cfg["name"];
             	if(!$game_cfg){
                	            error_log("configuration for ".$game_name." is not loaded\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                        	    return $output;
             	}
        } catch (Exception $e) {
                 error_log("configuration loading for $game_name failed\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                 error_log("Exception says: ". $e->getMessage(). "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
        }

	$slowpage_markers = glob("$root_upload_directory$time_slot/".
				  $server_cfg['profile_upload_directory']."/.slowpages", GLOB_BRACE);
	if (empty($slowpage_markers)) {
		error_log("No slowpage for $time_slot\n", 3, sprintf($server_cfg['log_file'],$game_name));
		return $output;
	}
	
	foreach($slowpage_markers as $marker){
		$profile_upload_directory = dirname($marker);
		$timestamp = (int)(basename(dirname(dirname($marker))) * 1800);
		error_log("slow pages for $profile_upload_directory\n", 3, sprintf($server_cfg['log_file'],$game_name));
		foreach (glob("$profile_upload_directory/*php") as $page_dir) {
			if (!is_dir($page_dir)) {
				continue;
			}
			scan($server_cfg, $game_name, $game_cfg["slow_page_threshold"],
			     $page_dir, $time_slot, $output, $top5);
		}
	}
	
	error_log(sprintf("in move_slow_page top5 list has %d lines\n", count($top5)), 3, sprintf($server_cfg['log_file'],$game_name));
	return $output;
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


function main($server_cfg){

	$options = get_options();
	if (isset($options['g']) && $options['g'] !== '') {
	           $game_names = explode(",",$options['g']);
	} else {
        	$game_names = $server_cfg['game_list'];
	}


	$time_slot = null;

	if (!empty($options['t'])) {
        	$time_slot = $options['t'];
	}
        
	foreach ($game_names as $game_name) {
		// Top5 metrics file for this time slot
		$top5_file = sprintf($server_cfg['top5_file'],
				     $game_name, (string)$time_slot);
		error_log("top5 file is $top5_file\n", 3, sprintf($server_cfg['log_file'],$game_name));
		
		if (file_exists($top5_file)) {
			$top5 = igbinary_unserialize(file_get_contents($top5_file));
			error_log(sprintf("top5 file exists: %d byte\n", filesize($top5_file), 3, sprintf($server_cfg['log_file'],$game_name)));
		} else {
			$top5 = array();
			error_log("No top5 file\n", 3, sprintf($server_cfg['log_file'],$game_name));
		}

		$output = move_slow_page($server_cfg, $game_name, $time_slot, $top5);

		error_log(sprintf("in main: top5 list has %d lines\n", count($top5)), 3, sprintf($server_cfg['log_file'],$game_name));
		// Dump updated top5 to file
		file_put_contents($top5_file, igbinary_serialize($top5));

		print json_encode($output);
	}
}

main($server_cfg);
?>
