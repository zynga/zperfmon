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

header("Content-Type:text/json");

ini_set('memory_limit', '48M');

set_include_path(get_include_path() . ":../include/");
include_once 'setup_page.php';

$game_name = $game_cfg["name"];
include_once('XhProfModel.php');
include_once('XhProfJSONView.php');

function wrap_escape($vector, $escape_char)
{
    $new_vector = array();
    foreach ($vector as $key => $item)
    {
        $new_vector[] = $escape_char . $item . $escape_char;
    }

    return $new_vector;
}

function ProcessRequest($server_cfg, $game_cfg) {

	$columns=$_GET["columns"];	
	//echo $columns;

	$wrapped_column_names = wrap_escape(explode(",", $columns), "`");
	//print_r($wrapped_column_names);
	$query = $_GET["query"];
	$xhprofModelObject = new XhProfModel($server_cfg, $game_cfg);

	date_default_timezone_set('UTC');
	$end_time = time();
	$start_time = ($end_time - 12 * 7 * 24 * 60 * 60); # 12 weeks ago
	$implode_columns=implode(",",$wrapped_column_names);
	if(!($query) || $query == "cto_get_top_pages_avg_load_time")
	{

    	$chart_result = $xhprofModelObject->generic_execute_get_query_detail($query,
	                    array('table' => "apache_stats_flip_avg",
            		    'end_time' => $end_time,
	    	    	    'start_time' => $start_time,
		    		    'columns'=>$implode_columns));
     }
     else if($query == "cto_get_tracked_functions_by_column")
     {
        $page = $_GET['page'];
    	$chart_result = $xhprofModelObject->generic_execute_get_query_detail($query,
	                    array('table' => "tracked_functions_flip_incl_time",
            		    'end_time' => $end_time,
	    	    	    'start_time' => $start_time,
                        'page' => $page,
		    		    'columns'=>$implode_columns));
     }
     else
     {
        echo json_encode("Illegal Query!");
		return;
     }

     $tags = $xhprofModelObject->generic_execute_get_query("get_tag_range",
                        array('table' => "events",
                                'end_time' => $end_time,
                                'start_time' => $start_time,
                                'extra_params' => ""));
     $chart_result["tags"] = $tags;

     echo json_encode($chart_result);
}

ProcessRequest($server_cfg, $game_cfg);

?>

