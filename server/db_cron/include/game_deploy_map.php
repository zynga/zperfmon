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


include_once "game_config.php";

function scan_game_cfgs($server_cfg) {

	$map = array();

	foreach ($server_cfg["game_list"] as $game) {
		$gcfg = load_game_config($game);
		$deploy_id = $gcfg['deployIDs'][0];
		
		$map[$deploy_id] = $game;
	}

	return $map;
}

function set_game_deploy_map($server_cfg, $map) {

	$game_dep_map_file = $server_cfg['game_deploy_map'];
	file_put_contents($game_dep_map_file, json_encode($map));
}



function get_game_deploy_map($server_cfg) {
	$game_dep_map_file = $server_cfg['game_deploy_map'];

	return json_decode(file_get_contents($game_dep_map_file), true);
}

