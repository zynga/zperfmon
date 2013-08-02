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


$page_stat = array();

//
// Sample json dump of bucketed PDTs from client
//
// {'200': {'count': 2, 'time': 457.68799999999999}, '900': {'count': 0, 'time': 0}, '5000': {'count': 0, 'time': 0}, '600': {'count': 1, 'time': 605.04899999999998}, '6000': {'count': 0, 'time': 0}, '300': {'count': 2, 'time': 626.46500000000003}, '9000': {'count': 0, 'time': 0}, '700': {'count': 1, 'time': 732.19500000000005}, '0': {'count': 17, 'time': 8606.5550000000021}, '2000': {'count': 1, 'time': 2489.9670000000001}, '4000': {'count': 0, 'time': 0}, '3000': {'count': 0, 'time': 0}, '10001': {'count': 0, 'time': 0}, '400': {'count': 3, 'time': 1316.0999999999999}, '7000': {'count': 0, 'time': 0}, '100': {'count': 0, 'time': 0}, '800': {'count': 2, 'time': 1642.3609999999999}, '8000': {'count': 0, 'time': 0}, '500': {'count': 1, 'time': 555.5}, '1000': {'count': 0, 'time': 0}}
//

function time_count_cmp($a, $b)
{
	if ($a["time"] == $b["time"]) {
		return 0;
	}
	
	return ($a["time"] > $b["time"]) ? -1 : 1;
}

//
// We started off with 90-percentile and then shifted to 75th
// percentile and then found that outliers rejection based on
// cumulative time of a bucket is better. But then, the average PDT
// turned out to be not very different from what would be, if did no
// outlier rejection.
//
// For now that is what we are doing, add up all counts, add up all
// times and get the average: that is same as returning bucket "0".
//
function get_percentile($buckets, $percentile=90)
{
	if ($buckets["0"]["count"] > 0) {
		return $buckets["0"];
	}

	$total = $buckets["0"];
	unset($buckets["0"]);

	uasort($buckets, 'time_count_cmp');

	// we need counts for averaging
	$acmltr = array("time" => 0, "count" => 0);

	$target_time = $total["time"] * ($percentile/100); // almost percentile

	foreach($buckets as $b => $v) {

		$acmltr["time"] += $v["time"];
		$acmltr["count"] += $v["count"];

		if ($acmltr["time"] > $target_time) {
			break;
		}
	}

	//
	// If we didn't 'break' above, this page for sure has a uniform
	// distribution or it is broken!
	//
	return $acmltr;
}


//
// Add page times and counts from across machines.
//
function build_pdt($json_string, &$buckets)
{
	$page_hash = json_decode($json_string, true);

	foreach($page_hash as $page => $value) {

		$r = get_percentile($value, 75);

		if (!isset($buckets[$page])) {
			$buckets[$page] = array("time" => 0, "count" => 0);
		}

		$buckets[$page]["time"] += $r["time"];
		$buckets[$page]["count"] += $r["count"];

		// PHP will wrap at 2^64 - 1 or 2^32 - 1 (or 2^18 - 1 or
		// PDP-4?)  Check for time wrap, if we count wraps our real
		// problems lie elsewhere.
		if ($buckets[$page]["time"] < 0) {
			$buckets[$page]["time"] /= 10;
			$buckets[$page]["time"] *= -1;
			$buckets[$page]["count"] = 
				round($buckets[$page]["count"] / 10);
		}
	}
}


function insert_bucketed_apache_stats($server_cfg, $game_cfg,
				      $bucketed_pdt, $timestamp)
{
	$game_id = $game_cfg["gameid"];
	$table_name = $game_cfg["apache_stats_table"];

	$query_list = array();

	foreach ($bucketed_pdt as $page => $value) {
		try {

			if ( $value["count"] !=0 ) {  // Just to avoid Divide by Zero, as it cann't be caught in try..catch
				$pdt = $value["time"] / $value["count"]; 

				// max and min columns are ignored - set to 0
				$query_list[] = sprintf("REPLACE INTO %s (timestamp, gameid, page, count, max_load_time, min_load_time, avg_load_time) VALUES (from_unixtime(%d), %s, '%s', %d, 0, 0, %f)",
						$table_name, $timestamp, $game_id, $page, (int)$value["count"], round($pdt, 2));
			}

		} catch (Exception $e) {
			echo "Error while inserting apache-page stats. ";
			echo "Game: {$game_cfg["name"]} Page: $page_name\n";
		}
	}

	// Insert all entries into DB.
	execute_queries($server_cfg, $game_cfg, $query_list);

	return true;
}


function insert_apache_stats($server_cfg, $game_cfg, $time_slots='*')
{
	//Scan only directories bearing IP address signatures. Strange knowledge
	//while aggregating server-side apache-stats
	$untarred_ip_folder_regex = "/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)$/";
	$root_upload_directory = sprintf($server_cfg["root_upload_directory"], $game_cfg["name"]);

	$apache_stats_markers = glob("$root_upload_directory/$time_slots/".
					  $server_cfg['profile_upload_directory']."/.apache_stats", GLOB_BRACE);
	if (empty($apache_stats_markers)) {
		error_log("no apache stats for time slots $time_slots\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		$game_cfg['logger']->log("insert_apache_stats", "no apache stats for time slots $time_slots", Logger::INFO);
		return;
	}

	foreach($apache_stats_markers as $marker) {

		$profile_upload_directory =  dirname($marker);
		$bucketed_pdt = array();
	
		$timestamp = (int)(basename(dirname($profile_upload_directory)) * 1800);

		//scan the folder for folders like '174.129.107.25'
		$folders = scan_filesystem_entries($profile_upload_directory,
						$untarred_ip_folder_regex, TRUE);

		if (count($folders) == 0)
		{
			continue;
		}
		global $page_stat;

		$page_stat = array(); // reset it between runs
		$bucketed_pdt = array(); // ensure references are kept

		//Scan each client folder
		foreach($folders as $folder => $client_folder)
		{
			//Get the apache-page.stats stat file
			$page_stat_files = glob($client_folder . "/*apache-page.stats");

			if (count($page_stat_files) == 0) {
				continue;
			}
	
			// Extract and aggregate PDT from each apache-page.stat file
			foreach ($page_stat_files as $pdt_file) {
				$pdt_result = extract_apache_stat_client_internal($pdt_file);
			
				// This is a bucketed PDT json dump, treat appropriately
				if (is_string($pdt_result)) {
					build_pdt($pdt_result, $bucketed_pdt);
				}
			}
		}

		if(count(array_keys($page_stat)) == 0 && count($bucketed_pdt) == 0)
		{
			error_log("There are no page stats in $profile_upload_directory\n");
			$game_cfg['logger']->log("insert_apache_stat",
						 "There are no page stats in $profile_upload_directory", Logger::ERR);
			if(count($folders) == 0){
				error_log("Deleting $marker... " . (unlink($marker) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			}
			continue;
		}

		// Give precedence to bucketed PDT
		if (count($bucketed_pdt) > 0) {
			if(insert_bucketed_apache_stats($server_cfg, $game_cfg,
						     $bucketed_pdt, $timestamp))
			{
				error_log("Deleting $marker... " . (unlink($marker) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			}
		} else {
			// Compute Median on an avg list to reduce noise 
			compute_median();
			//Handle data insertion to db per client
			if(insert_apache_stats_client_internal($page_stat,
							       $server_cfg,
							       $game_cfg,
							       $timestamp) == 0)
			{
				error_log("Deleting $marker... " . (unlink($marker) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			}
		}
	}

	try
	{
		$queries = array(
			"call pivot_apache_stats('avg_load_time', 0.00)",
			"call pivot_apache_stats('count', 0.00)");

		$query_res = execute_queries($server_cfg, $game_cfg, $queries);
		if ($query_res == 0) {
			$game_cfg['logger']->log("insert_apache_stat",
						 "Call to pivot_apache_stats(avg_load_time, 0.00) is successfull", Logger::INFO);
		} else {
			$game_cfg['logger']->log("insert_apache_stat",
						  "Call to pivot_apache_stats(avg_load_time, 0.00) is unsuccessfull", Logger::ERR);
		}
	}
	catch (Exception $ex)
	{
		$msg = "Error in insert_apache_stats_client_internal:: " . $ex->getTraceAsString();
		$game_cfg['logger']->log("insert_apache_stats", "Error in inserta_apache_stats_client_internal", Logger::INFO);

		error_log($msg, 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		$retval = 1;
	}
}


function compute_median() {
    global $page_stat;
    foreach (array_keys($page_stat) as $page_name) {
			sort($page_stat[$page_name][3]);
			$avg_array_len = count($page_stat[$page_name][3]);
            $page_stat[$page_name][3] = $page_stat[$page_name][3][$avg_array_len/2];
	}
}
		
			

function extract_apache_stat_client_internal($client_stat_file)
{
	$file_handle = NULL;
	global $page_stat;

	try
	{
		//Read the file and accumulate page-stats
		$file_handle = fopen($client_stat_file, "r");

		while (!feof($file_handle)) 
		{
			$line_of_text = fgets($file_handle);

			if ($line_of_text === FALSE && !feof($file_handle))
			{
				error_log("Error processing apache-stat file: ", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
				continue;
			}

			if (trim($line_of_text) == "")
			{
				continue;
			}

			//
			// Check if this is new-style bucketed PDT. If yes return what we
			// read from the file sans magic string. The file should only have
			// one line - JSON encoded bucketed PDTs with page as key.
			//
			if (substr($line_of_text, 0, 8) == "BUCKETED") {
			        fclose($file_handle);
			        return substr($line_of_text, 8);
			}

			$parts = explode(',', $line_of_text);

			//Check for malformed URL (although comma is valid char, ideally it
			//shouldn't appear)
			if (count($parts) != 4)
			{
				continue;
			}

			//The implicit format is as follows,
			//
			//     parts[0] => 'page name'
			//     parts[1] => 'max page load time'
			//     parts[2] => 'min page load time'
			//     parts[3] => 'avg page load time'
			//
			//Ideally we should have structured data like JSON or other XML-formatted data
			//instead of crude CSV
			//

            //The implicit format is as follows,
            //
            //     parts[0] => 'page name'
            //     parts[1] => 'max page load time'
            //     parts[2] => 'min page load time'
            //     parts[3] => 'avg page load time'
            //
            //Ideally we should have structured data like JSON or other
            //XML-formatted data
            //instead of crude CSV
            //

            $page_name = $parts[0];
            $max_page_load_time = $parts[1];
            $min_page_load_time = $parts[2];
            $avg_page_load_time = $parts[3];
			if(!$max_page_load_time || !$avg_page_load_time) {
				continue;
			}

			//Page name is not empty
			if (!empty($page_name))
			{
				//Reduce full page path to just filename
				//
				// /fish/user/index.php => index.php
				$page_name = basename($page_name);

				if (array_key_exists($page_name, $page_stat))
				{
					//Maintain a running min, max build an average list
					$page_stat[$page_name][0] += 1;
					$page_stat[$page_name][1] = max($page_stat[$page_name][1], $max_page_load_time);
					$page_stat[$page_name][2] = min($page_stat[$page_name][2], $min_page_load_time);
					$page_stat[$page_name][3][] = $avg_page_load_time;
				}
				else
				{
					$page_stat[$page_name] = array();
					$page_stat[$page_name][0] = 1;
					$page_stat[$page_name][1] = $max_page_load_time;
					$page_stat[$page_name][2] = $min_page_load_time;
					$page_stat[$page_name][3] = array($avg_page_load_time);
				}
			}
		}

		fclose($file_handle);
	}
	catch (Exception $e)
	{
		if (!empty($file_handle))
		{
			fclose($file_handle);
		}
	}
   
}

function insert_apache_stats_client_internal($page_stat, $server_cfg, $game_cfg, $current_timestamp)
{
	$retval = 0;
	$game_id = $game_cfg["gameid"];

	foreach (array_keys($page_stat) as $page_name)
	{
		try
		{
			$values = implode(",", array("from_unixtime($current_timestamp)",
						$game_id,
						"'" . $page_name . "'",
						"-1", // count
						$page_stat[$page_name][1],    //max
						$page_stat[$page_name][2],    //min
						$page_stat[$page_name][3]));  //avg.

			$table_name = $game_cfg["apache_stats_table"];
			$query = "REPLACE INTO $table_name (timestamp, gameid, page, count, max_load_time, min_load_time, avg_load_time) VALUES ({$values})";
			$queries = array($query); 

			$query_res = execute_queries($server_cfg, $game_cfg, $queries);
			if ($query_res == 0) {
				$game_cfg['logger']->log("insert_apache_stat",
							"apache_stat for " . $game_cfg['name'] . " successfully inserted", Logger::INFO);
			} else {
				$game_cfg['logger']->log("insert_apache_stat",
							"apache_stat for " . $game_cfg['name'] . " not inserted", Logger::ERR);
			}

		}
		catch (Exception $ex)
		{
			//TODO:
			//      syslog it. For now echo-ing it
			error_log("Error while inserting apache-page stats to db. Offending entry -  Game Id:" .$game_id. ", Page:", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			$game_cfg['logger']->log("insert_apache_stats",
					         "Error while inserting apache_page stats for " . $game_cfg['name'] . " into db", Logger::INFO);
			$retval = 1;
		}
	}
	return $retval;
}
/*
    Scans the given directory for yielding a list of files OR directories (depending on
    $is_directory variable) which matches the reg-ex pattern given by $pattern
*/
function scan_filesystem_entries($rootDir, $pattern, $is_directory) 
{
	//Trim rootDir
	$rootDir = rtrim($rootDir, '/') . '/';

	// set filenames invisible if you want
	$invisibleFileNames = array(".", 
			"..", 
			".htaccess", 
			".htpasswd");
	$fso_entries = array();

	// run through content of root directory
	$dirContent = scandir($rootDir);
	foreach($dirContent as $key => $content) 
	{
		// filter all files not accessible
		$path = $rootDir . '/' . $content;
		if(!in_array($content, $invisibleFileNames)) 
		{
			// if content is file & readable, add to array
			if (($is_directory && is_dir($path)) ||
					(!$is_directory && is_file($path))) 
			{
				if (preg_match($pattern, $content))
				{
					$fso_entries[] = $path;
				}
			}
		}
	}

	return $fso_entries;
}

?>
