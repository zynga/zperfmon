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

$core_include = "/var/www/html/zperfmon/include/";

set_include_path(get_include_path() . ":$core_include");
include_once 'setup_page.php';

$game_name = $game_cfg["name"];
include_once('XhProfModel.php');
include_once('XhProfJSONView.php');


function ProcessRequest($server_cfg, $game_cfg) {
	
	$query = $_GET["query"];
	if (!$query) {
		$query = 'bd_chart_range';
	}

	if (!$query) {
		return json_encode("Illegal query.");
	}

	$xhprofModelObject = new XhProfModel($server_cfg, $game_cfg);

	date_default_timezone_set('UTC');
	$end_time = time();
	$start_time = ($end_time -  12 * 7 * 24 * 60 * 60); # 12 weeks ago

	$chart_result = $xhprofModelObject->generic_execute_get_query_detail($query,
			array('table' => $game_cfg["db_stats_table"],
			      'end_time' => $end_time,
			      'start_time' => $start_time,
			      'extra_params' => ""));
 
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

