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
// API for profile pie view
//

include_once 'server.cfg';
include_once "xhprof_lib.php";
include_once "xhprof_runs.php";
include_once "game_config.php";


//////////////////////////////////
//
// What an ugly solution! We need functions to compare [, reduce] and filter
// xhprof data arrays. create_function is not good for caching. Defining those
// for all metrics is a readability and maintainance headache. Instead, we
// declare a global variable to hold the metric being processed at that time.
//
$metric = null;

function filter_metric($item)
{
	global $metric;

	return $item[$metric];
}

function compare($a, $b)
{
	return (($a == $b) ? 0 :
		($a < $b) ? 1 : -1);
}


//
// Return top 'x' functions for given metrics computed from the given flat
// profile.
//
function get_top_functions($profile, $metric_list, $how_many)
{
	global $metric;
	$tots = null;

	$profile = unserialize(file_get_contents($profile));
	$profile = xhprof_compute_flat_info($profile, $tots);

	$result = array();

	foreach ($metric_list as $metric => $blurb) {
		try {
			$metric_array = array_map("filter_metric", $profile);
			uasort($metric_array, 'compare');
			$top_x = array_slice($metric_array, 0, $how_many);

			$metric_total = array_sum($metric_array);
			$metric_sub_total = array_sum($top_x);
			
			$top_x["*others*"] = $metric_total - $metric_sub_total;

			$result[$blurb] = array_map(null, 
					   array_keys($top_x),
					   array_values($top_x));
		} catch (Exception $e) {
			error_log("Processing metric $m failed");
		}
	}

	return $result;
}


//
// get_pie() returns top-x functions for all metrics in '$metrics' with the
// value of each key as the index for the list if given. You can choose a
// period of 'daily' or '30min' for the extract. All parameters except game
// name is optional. If you pass a profile path via '$profile' then all other
// parametes are ignored and the top-x extracted from that profile.
//
//
//
function get_pie($game_name,
		 $period = "day",
		 $tstamp = null, 
		 $metrics = array("excl_wt" => "Exclusive Wall time",
				  "excl_cpu" => "Exclusive CPU time"),
		 $how_many = 6,
		 $profile = null)
{
	global $server_cfg;

	$game_cfg = load_game_config($game_name);
	//$game_cfg = $game_cfg["$game_name"];

	if ($profile != null) {
		$period = "absolute";
	}

	switch ($period) {
	case "day":
		$prof_path = sprintf($server_cfg["daily_profile"],
				     $game_cfg["name"]);
		break;
	case "30min":
		$prof_path = sprintf($server_cfg["daily_upload_directory"],
				     $game_cfg["name"]);
		break;
	case "absolute":
		break;
	default:
		echo "Unsupported period \'$period\' for get_pie()";
		error_log("Unsupported period \'$period\' for get_pie()");
		return null;
	}

	if ($period != "absolute") {
		// Find all aggregate profiles in dir and take the last
		$profile_list = glob("$prof_path/*all.xhprof");
		if (empty($profile_list)) {
			echo "no profile in $period $prof_path/*.all.xhprof";
			return null;
		}

		$profile = end($profile_list);
	}

	return get_top_functions($profile, $metrics, $how_many);
}

$test_mode = true;
if (isset($test_mode) && $test_mode) {
	print_r(get_pie("fluid"));
}

