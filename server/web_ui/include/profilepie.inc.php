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

include_once "server.cfg";
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

	$profile = XHProfRuns_Default::load_profile($profile);
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
function get_direct_pie($profile)
{
	return get_pie(null, null, null, null, null, null, $profile);
}
/* parameter $pages are used to specify the page for which pie data has to be returned */
function get_pie($server_cfg, $game_cfg,
		 $period = "day",
		 $tstamp = null, 
		 $metrics = array("excl_wt" => "Exclusive Wall time",
				  "excl_cpu" => "Exclusive CPU time"),
		 $how_many = 6,
		 $profile = null,
		$pages =null)
{
	if ($profile != null) {
		$period = "absolute";
		$metrics = array("excl_wt" => "Exclusive Wall time",
                                  "excl_cpu" => "Exclusive CPU time");
		$how_many = 6;
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
			//echo "no profile in $period $prof_path/*.all.xhprof";
			return null;
		}

		$profile = end($profile_list);
	}
 	/* now create an array whih contains data,
	 *	 for all the pages and return an object,
	 *	 having page name as key and data as their value 
	*/

	$top_fn = array();
	$page_data = array();
	if ( $pages != null ){
		foreach ( $pages as $page) {
			$profile_list = glob("$prof_path/*$page.xhprof");
			if (empty($profile_list)) {
				//echo "no profile in $period $prof_path/*.all.xhprof";
				continue;
			}
			$profile = end($profile_list);
			$page_data [$page] = get_top_functions($profile, $metrics, $how_many);
			
			$top_fn = array_merge ($top_fn , $page_data);
		}	
		return $top_fn;
	}
	else {
		return get_top_functions($profile, $metrics, $how_many);
	}
}

//$test_mode = true;
if (isset($test_mode) && $test_mode) {
	$game_cfg = load_game_config("fluid");
	//$game_cfg = $game_cfg["fluid"];
	print_r(get_pie($server_cfg, $game_cfg));
}
