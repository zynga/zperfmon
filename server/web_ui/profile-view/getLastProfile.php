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


header("Content-Type: text/json");

include_once("setup_page.php");

$game_name = $game_cfg["name"];
include_once('XhProfModel.php');
include_once('XhProfJSONView.php');
function ProcessRequest($server_cfg, $game_cfg) {
	
	$xhprofModelObject = new XhProfModel($server_cfg, $game_cfg);

	date_default_timezone_set('UTC');
	$end_time = (int)$_GET["end_time"];
	$start_time = (int)$_GET["start_time"];

	$result = $xhprofModelObject->generic_execute_get_query("get_last_profile_slots",
			array('table' => $game_cfg["xhprof_blob_table"],
			      'end_time' => $end_time,
			      'start_time' => $start_time,
			      'extra_params' => ""));
 

        echo json_encode($result);
}

ProcessRequest($server_cfg, $game_cfg);
?>
