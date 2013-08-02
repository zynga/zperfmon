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


define("GAME_CFG_TMPLT", "/etc/zperfmon/conf.d/%s.cfg");

$game_cfg_new = array();

function load_db_config(&$game_cfg, $game)
{

	$query = "select name,value from game_config;";
	try {
		$db = new mysqli($game_cfg[$game]["db_host"],
				 $game_cfg[$game]["db_user"],
				 $game_cfg[$game]["db_pass"],
				 $game_cfg[$game]["db_name"]);
	
		if($result = $db->query($query)) {
				while (list($k, $v) = $result->fetch_array(MYSQLI_NUM))
				{
					if(strpos($k,'db_') === False ||
						strpos($k, 'db_') !== 0)
					{
						$game_cfg[$game][$k] = $v;
						// unquoted strings will fail this, but that's cool
						$v = json_decode($v, true); 
						if($v) $game_cfg[$game][$k] = $v;
					}
				}
				$game_cfg[$game]["initialized"] = true;
			$result->close();
		} else {
			error_log("Failed: ".$db->error);
			$db->close();
			return 1;
		}

		$db->close();
	} catch (Exception $e) {
		error_log("Error in setting up mysqli query:". $e->getMessage());
		return 1;
	}
}

function store_db_config($game_cfg, $game)
{
	$query = "replace into game_config (name, value) VALUES (?,?);";
	try {
		$db = new mysqli($game_cfg[$game]["db_host"],
				 $game_cfg[$game]["db_user"],
				 $game_cfg[$game]["db_pass"],
				 $game_cfg[$game]["db_name"]);

		$stmt = $db->prepare($query);
		$stmt->bind_param("ss", $name, $value);

		foreach($game_cfg[$game] as $name => $value) {
			if(strpos($name,'db_') === False ||
				strpos($name, 'db_') !== 0)
			{
				$value = json_encode($value);
				$stmt->execute();
			}
		}
		$stmt->close();
		$db->close();
	} catch (Exception $e) {
		echo "Error in setting up mysqli query:", $e->getMessage(), "\n";
		return 1;
	}

}

# returns a map of arrays to array ids. access the array_ids using foreach($arrays as $array=>$array_id)
function get_array_id_map($server_cfg, $game_cfg) {
        include_once('/usr/local/zperfmon/bin/rightscale.php');
        $rs = new RightScale($server_cfg, $game_cfg);
        $array_map = $rs->get_array_to_arrayid_mapping();
        return $array_map;
}

function load_game_config($game, $array = null)
{
        global $game_cfg_new;
	
	$game_include = sprintf(GAME_CFG_TMPLT, $game);

	if (!file_exists($game_include)) {
		return false;
	}

        include_once $game.".cfg";
        if (!isset($game_cfg_new[$game])) {
                $game_cfg_new[$game] = $game_cfg[$game];
        }

        if(!isset($game_cfg_new[$game])) {
                return false;
        }

        if(!isset($game_cfg_new[$game]["initialized"]))
        {
                load_db_config($game_cfg_new, $game);
        }

        $cfg = array();
        foreach ( $game_cfg_new[$game] as $a => $b ){
                $cfg[$a] = $b;
        }

        if(isset($array)) {
                $cfg["name"] = $cfg["name"]."_".$array;
                $cfg["db_name"] = $cfg["db_name"]."_".$array;
                $cfg["id"] = $array;
                $cfg["parent"] = $game;
        }
        return $cfg;
}
