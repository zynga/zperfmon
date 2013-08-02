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


//
// This script is meant to be used as a wrapper of clean.sh script
// It will take the inputed game name and run clean up script.
// on the game as well as on the array belonging to the game
//

include_once "server.cfg";
include_once 'game_config.php';
//include_once "yml_conf.inc.php";
include_once 'zpm_util.inc.php';



function get_options()
{
        $options = getopt("g:");
        return $options;
}

function main($server_cfg)
{
        $options = get_options();
        if (isset($options['g']) && $options['g'] !== '') {
                $game_names = explode(",",$options['g']);
        } else {
                $game_names = $server_cfg['game_list'];
        }

        foreach ($game_names as $game) {

		zpm_preamble($game);
                $game_cfg = load_game_config($game);
		$retval = null; // refs will start failing in 5.3.x if not declared
                $cleanup = "/usr/local/zperfmon/bin/clean.sh -g " . $game_cfg['name'] . " > /dev/null ";
                $output = system($cleanup, $retval);
		if($retval != 0){
			error_log("Couldn`t cleanup game $game", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
			continue;
		}

                $arrays_to_id = get_array_id_map($server_cfg, $game_cfg);
                foreach($arrays_to_id as $array=>$id){
			$game_cfg = load_game_config($game, $id);
        	        $cleanup = "/usr/local/zperfmon/bin/clean.sh -g " . $game_cfg['name'] . " > /dev/null ";
                	$output = system($cleanup, $retval);
	                if($retval != 0){
        	                error_log("Couldn`t cleanup game $game", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
                	        continue;
                	}
        
                }

		zpm_postamble($game);
        }
}

main($server_cfg);

?>
