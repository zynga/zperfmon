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


include_once 'xhprof_runs.php';
/*
   Module level magic strings
*/
$xhprof_extract_top_function_signature = "top functions";
$xhprof_extract_tracked_function_signature = "interesting";
$xhprof_extract_wall_time_signature = "wall time";
$xhprof_extract_count_signature = "count";
$xhprof_extract_exclusive_wall_time_signature = "excl wall time";


/*
    Top level function for inserting top function analytics data to db
*/
function insert_function_analytics($server_cfg, $game_cfg, $time_slots='*')
{
	$root_upload_directory = sprintf($server_cfg["root_upload_directory"], $game_cfg["name"]);

	$function_markers = glob("$root_upload_directory/$time_slots/".
				      $server_cfg['profile_upload_directory']."/.functions", GLOB_BRACE);

	if (empty($function_markers)) {
		
		$game_cfg['logger']->log("insert_function_analytics","function_analytics for $time_slots".
					" are not enabled", Logger::INFO);
		error_log("function_analytics for $time_slots are not enabled\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return;
	}

	foreach($function_markers as $marker){

		$profile_upload_directory = dirname($marker);

		$timestamp = (int)(basename(dirname($profile_upload_directory)) * 1800);

		$profile_blob_directory = $profile_upload_directory."/".$server_cfg['blob_dir'];
		$top_functions_analytics_file = $profile_blob_directory."/".$server_cfg['profile_blob_filename'];
	
		//Check for validity of the file/path
		if (!file_exists($top_functions_analytics_file))
		{
			$game_cfg['logger']->log("insert_function_analytics",
						"$top_functions_analytics_file doesn't exist",
						Logger::ERR);
			error_log("$top_functions_analytics_file doesn't exist\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			continue;
		}

		//Get the multi-D array of analytics
		$analytics_array = to_array($top_functions_analytics_file);

		if ($analytics_array == null)
		{
			$game_cfg['logger']->log("insert_function_analytics",
						"Invalid xhprof_extract file at $top_functions_analytics_file",
						Logger::ERR);
			error_log("Invalid xhprof_extract file at $top_functions_analytics_file\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
			continue;
		}

		//Remove duplicates
		remove_duplicates_from_top_functions_and_insert_analytics($analytics_array, 
				$server_cfg, 
				$game_cfg, 
				$timestamp);
		error_log("Deleting $marker... " . (unlink($marker) ? "OK" : "FAILED")."\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	}

	$queries = array(
			//"call pivot_tracked_functions('count')",
			//"call pivot_tracked_functions('excl_time')",
			"call pivot_tracked_functions('incl_time')",
			//"call rank_top5_functions('count')",
			"call rank_top5_functions('excl_time')");
			//"call rank_top5_functions('incl_time')");

	try
	{

		$query_res = execute_queries($server_cfg, $game_cfg, $queries);
		if ( $query_res == 0 ) {
			$game_cfg['logger']->log("insert_function_analytics",
						"Call to pivot functions and rank functions are successful", Logger::INFO);
		} else {
			$game_cfg['logger']->log("insert_function_analytics",
						"Call to pivot functions and rank functions are unsuccessful", Logger::ERR);
		}	
	}
	catch (Exception $ex)
	{
		//TODO:
		//      syslog it. For now echo-ing it
		error_log("Stack Trace: " . print_r($ex->getTrace(). TRUE), "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		$game_cfg['logger']->log("insert_function_analytics",
					"Error in inserting function analytics for ".$game_cfg['name'],	Logger::ERR);
	}
}

	
/*
    Remove duplicates from the "top functions" list of each page and insert them to db
*/
function remove_duplicates_from_top_functions_and_insert_analytics($analytics_array, 
									$server_cfg, 
                                        				$game_cfg, 
		                                        		$current_timestamp)
{
	global $xhprof_extract_tracked_function_signature;
	global $xhprof_extract_top_function_signature;
	global $xhprof_extract_wall_time_signature;
	global $xhprof_extract_count_signature;
	global $xhprof_extract_exclusive_wall_time_signature;

	foreach (array_keys($analytics_array) as $page_name)
	{
		//Get the embedded array() item which is 
		$item = $analytics_array[$page_name];


		# array to collect inserts into db for a batched insert later.
		$queries = array();

		//Get the "interesting functions" from the item
		$tracked_functions = $item[$xhprof_extract_tracked_function_signature];
		foreach(array_keys($tracked_functions) as $tracked_fn_name)
		{
			$tracked_fn = $tracked_functions[$tracked_fn_name];

			//
			//Bug:
			//	  Sometimes tracked function names would be at level-1 nesting or leve-2 nesting
			//     from "interesting" key. What I mean is either one of the following can be seen,
			//	
			//	  [interesting] => Array
			//     (
			//         [MC::set] => Array
			//             (
			//                 [ct] => 1
			//                 [wt] => 2561
			//                 [excl_wt] => 18.333333333333
			//             )
			// OR
			//
			//	 [interesting] => Array
			//      (
			//	    [MC::set] => Array
			//           (
			//         	[MC::set] => Array
			//             	(
			//                 		[ct] => 1
			//                 		[wt] => 2561
			//                 		[excl_wt] => 18.333333333333
			//             	)

			if (!array_key_exists("ct", $tracked_fn))
			{
				$tracked_fn = $tracked_fn[$tracked_fn_name];
			}  

			$q = insert_into_interesting_functions_table($server_cfg,
					$game_cfg,
					$current_timestamp,
					$page_name,
					$tracked_fn_name,
					$tracked_fn["ct"],
					$tracked_fn["wt"],
					$tracked_fn["excl_wt"]);

			# accumulate queries
			if ($q) {
				$queries[] = $q;
			}
		}

		//execute_queries($server_cfg, $game_cfg, $queries);

		//Get the "top functions" list from the item
		$top_functions = $item[$xhprof_extract_top_function_signature];

		$top_fn_walltime = $top_functions[$xhprof_extract_wall_time_signature];   //function list sorted on "wall time"
		$top_fn_count = $top_functions[$xhprof_extract_count_signature];   //function list sorted on "count"
		$top_fn_excl_walltime = $top_functions[$xhprof_extract_exclusive_wall_time_signature];   //function list sorted on "excl wall time"

		//Remove duplicates based on keys
		$top_fn = union_based_on_key(union_based_on_key($top_fn_count, $top_fn_walltime), $top_fn_excl_walltime);

		foreach (array_keys($top_fn) as $fn_name)
		{
			$q = insert_into_top5_functions_table($server_cfg,
					$game_cfg,
					$current_timestamp,
					$page_name,
					$fn_name,
					$top_fn[$fn_name]["ct"],
					$top_fn[$fn_name]["wt"],
					$top_fn[$fn_name]["excl_wt"]);


			# accumulate queries
			if ($q) {
				$queries[] = $q;
			}
		}

		$query_res = execute_queries($server_cfg, $game_cfg, $queries);
		if ( $query_res == 0 ) {
			$game_cfg['logger']->log("insert_function_analytics","function_analytics for ".$game_cfg['name'] .
						" successfully inserted", Logger::INFO);
		} else {
			$game_cfg['logger']->log("insert_function_analytics","function_analytics for ".$game_cfg['name'].
						" not inserted", Logger::ERR);
		}
	}   
}

/*
    Inserts a single row to the "tracked_functions_table"
    No log insertion since it is only returning a query string 
*/
function insert_into_interesting_functions_table($server_cfg, 
						$game_cfg, 
						$current_timestamp, 
						$page_name, 
						$fn_name, 
						$ct, 
						$wt, 
						$excl_wt)
{
	$query = null;

	$game_id = $game_cfg["gameid"];
    	try
        {
            $values = implode(",", array("from_unixtime($current_timestamp)",
                                        $game_id,
                                        "'" . $page_name . "'",		//page name
                                        "'". $fn_name . "'",    	//function name
                                        $ct,    			//function count
					$wt,				//wall time of the function
                                        $excl_wt));  			//exclusive wall time of the function
                                        
            $table_name = $game_cfg["tracked_functions_table"];
            $query = "REPLACE INTO $table_name VALUES ({$values})";
        }
        catch (Exception $ex)
        {
            //TODO:
            //      syslog it. For now echo-ing it
		error_log("Error while inserting into tracked_functions_table. Offending entry -  Game Id:" .$game_id. ", Page:" . $page_name, "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log("Stack Trace: " . print_r($ex->getTrace(). TRUE), "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
        }

	return $query;
}

/*
    Inserts a single row to the table "top5_functions_table"
    No log insertion since it is only returning a query string
*/
function insert_into_top5_functions_table($server_cfg, 
						$game_cfg, 
						$current_timestamp, 
						$page_name, 
						$fn_name, 
						$ct, 
						$wt, 
						$excl_wt)
{
	$query = null;

	$game_id = $game_cfg["gameid"];
    	try
        {
            $values = implode(",", array("from_unixtime($current_timestamp)",
                                        $game_id,
                                        "'" . $page_name . "'",		//page name
                                        "'". $fn_name . "'",    	//function name
                                        $ct,    			//function count
					$wt,				//wall time of the function
                                        $excl_wt));  			//exclusive wall time of the function
                                        
            $table_name = $game_cfg["top5_functions_table"];
            $query = "REPLACE INTO $table_name VALUES ({$values})";
        }
        catch (Exception $ex)
        {
            //TODO:
            //      syslog it. For now echo-ing it
		error_log("Error while inserting into top5_functions_table. Offending entry -  Game Id:" .$game_id. ", Page:" . $page_name. "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log("Stack Trace: " . print_r($ex->getTrace(), TRUE). "\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
        }

	return $query;
}

/*
    Reads the content of the file and unserialize it to an array
*/
function to_array($filename)
{
    if (!file_exists($filename))
    {
        return null;
    }

    $array = null;
    try
    {
        $array = XHProfRuns_Default::load_profile($filename);
    }
    catch(Exception $ex)
    {
       var_dump($ex->getTrace());
    }

    return $array;
}

/*
    Given two arrays/lists, calculate union based on the identity of key. For identity based on value
    one should use array_unique(array_merge($a, $b))
*/
function union_based_on_key($a, $b)
{
    return $a + $b;
}


?>
