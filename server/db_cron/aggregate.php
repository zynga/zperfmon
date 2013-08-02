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


include_once "/var/www/html/zperfmon/xhprof_lib/utils/xhprof_lib.php";
include_once "/var/www/html/zperfmon/xhprof_lib/utils/xhprof_runs.php";
include_once "/var/www/html/zperfmon/include/game_config.php";

$game_config_path = "/etc/zperfmon/";
$per_game_config_path = "/etc/zperfmon/conf.d/";

ini_set('memory_limit', '1G');

error_reporting(E_ALL|E_STRICT);

$selfPath = dirname(realpath(__FILE__));
set_include_path(get_include_path() . ":$game_config_path:$per_game_config_path");

include_once 'server.cfg';

#
# Return an array of function name as keys and their stats as the
# value, where the selected functions are present in the passed-in
# function list.
function extract_interesting_functions($flat_profile, $function_list)
{
	$extract = array();

	foreach ($function_list as $function_name) {
		if (array_key_exists($function_name, $flat_profile)) {
			$extract[$function_name] = $flat_profile[$function_name];
		} else {
			$extract[$function_name] = array($function_name =>
							 array("ct" => 0,
					       "wt" => 0, "excl_wt" => 0));
		}
	}

	return $extract;
}


#
# Return top 5 functions, wall time, count and exclusive wall time
# -wise from the given flat profile.
function get_top_functions($flat_profile, $how_many)
{
	$top_wall_time = create_function('$a, $b',
				 'return ($a["wt"] == $b["wt"]) ? 0 :
					($a["wt"] < $b["wt"]) ? 1 : -1;');

	$top_count = create_function('$a, $b',
				 'return ($a["ct"] == $b["ct"]) ? 0 :
					 ($a["ct"] < $b["ct"]) ? 1 : -1;');

	$top_excl_wall_time = create_function('$a, $b',
			     'return ($a["excl_wt"] == $b["excl_wt"]) ? 0 :
				     ($a["excl_wt"] < $b["excl_wt"]) ? 1 : -1;');

	$top_functions = array();

	uasort($flat_profile, $top_wall_time);
	$top_functions["wall time"] = array_slice($flat_profile, 0, 5);

	uasort($flat_profile, $top_count);
	$top_functions["count"] = array_slice($flat_profile, 0, 5);

	uasort($flat_profile, $top_excl_wall_time);
	$top_functions["excl wall time"] = array_slice($flat_profile, 0, 5);

	return $top_functions;
}

function extract_functions($files, $game_name, $run_id, $aggregate_dir)
{
	// XXX: xhprof depends on this global for doing entry counts
	global $display_calls;
	$display_calls = true;
	$functions_to_extract = array( "MC::set", "MC::get", 
				       "ApcManager::get", "ApcManager::set",
				       "serialize", "unserialize",
				       "AMFBaseSerializer::serialize", 
				       "AMFBaseDeserializer::deserialize");
	
	if($game_cfg = load_game_config($game_name)) 
	{
		//$game_cfg = $game_cfg[$game_name];
		if(isset($game_cfg["tracked_functions"]))  
			$functions_to_extract = $game_cfg["tracked_functions"];
	}

	$prof_obj = new XHProfRuns_Default();

	$runs = xhprof_aggregate_runs_list($prof_obj, $files);

	if($runs['raw']){	
		$aggregate = $runs['raw'];
	}
	else{
		return;
	}
	

	# keep this in sync with the aggregate_files
	# $aggregate_file_name = "{$run_id}.xhprof";

	$overall_totals = null;

	$flattened_profile = xhprof_compute_flat_info($aggregate, $overall_totals);
	$interesting_funcs = 
		extract_interesting_functions($flattened_profile,
					      $functions_to_extract);

	$top_funcs = get_top_functions($flattened_profile, 5);

	$xhprof = array(
		"interesting" => $interesting_funcs,
		"top functions" => $top_funcs,
		"files" => $files);
	
	file_put_contents("$aggregate_dir/$run_id.extract", 
						serialize($xhprof));
}

function aggregate_files($files, $game_name, $run_id, $aggregate_dir)
{
	// XXX: xhprof depends on this global for doing entry counts
	global $display_calls;
	$display_calls = true;

	$prof_obj = new XHProfRuns_Default();

	$runs = xhprof_aggregate_runs_list($prof_obj, $files);

	$aggregate = $runs['raw'];
	if($aggregate){
		$run_id = $prof_obj->save_run_fqpn($aggregate,
				"xhprof", $run_id, $aggregate_dir);
	}
}

function combine_files($files, $game_name, $pattern, $output)
{
	$combined = array();
	$matches = null;
	#$pattern = "/[0-9]+\.(.*)\.extract/";
	foreach($files as $file) {
		if(preg_match($pattern, basename($file), $matches)) {
			$key = $matches[1];
			$combined[$key] = XHProfRuns_Default::load_profile($file);
		}
	}
	file_put_contents($output, serialize($combined));
}

function main($server_cfg)
{
	$command = $_SERVER['argv'][0]; # for future use
	switch(basename($command)) {
		case 'aggregate_runs.php':
		{
			$game_name = $_SERVER['argv'][1];
			$run_id = $_SERVER['argv'][2];
			$aggregate_dir = $_SERVER['argv'][3];
			$files = array_slice($_SERVER['argv'],4);
			aggregate_files($files, $game_name, $run_id, $aggregate_dir);
		}
		break;
		case 'extract_functions.php':
		{
			$game_name = $_SERVER['argv'][1];
			$run_id = $_SERVER['argv'][2];
			$aggregate_dir = $_SERVER['argv'][3];
			$files = array_slice($_SERVER['argv'],4);
			extract_functions($files, $game_name, $run_id, $aggregate_dir);
		}
		break;
		case 'combine_files.php':
		{
			$game_name = $_SERVER['argv'][1];
			$pattern = $_SERVER['argv'][2];
			$output = $_SERVER['argv'][3];
			$files = array_slice($_SERVER['argv'],4);
			combine_files($files, $game_name, $pattern, $output);
		}
		break;
	}
}

main($server_cfg);
