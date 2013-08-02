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



include_once "PDOAdapter.php";
include_once "profilepie.inc.php";
//include_once 'spyc.php';
include_once 'get_yml_conf.php';
include_once "server.cfg";
include_once 'game_config.php';
//
// @class Rightscale
// This class provides helper methods to get array ids and machine ips for array split
//

function get_rs_config($rs_cof_file){

    $conf = parse_ini_file($rs_cof_file, true);    
    return $conf["DB"];    
}

class CreateArrrayGames extends PDOAdapter {

    private $server_cfg;
    function __construct($server_cfg)
    {
        $conf = get_rs_config($server_cfg["rs_conf_file"]);
        $db_server = $conf["host"];
        $db_user = $conf["user"];
        $db_pass = $conf["password"];
        $db_name = $conf["database"];

        parent::__construct($db_server, $db_user, $db_pass, $db_name);

        $this->server_cfg = $server_cfg;
    }

	
	//function to make sudo games for each array id. The array ids are fetched from rightscale database.
	public function make_array_games($game_name, $deploy_id) {
	
		$query = "select distinct(array_id) from instances where deploy_id = (:deploy_id) and array_name like '%web%'";

		$parameters = array(
				"deploy_id" => array($deploy_id, PDO::PARAM_INT),
		);

		$stmt = $this->prepare($query);

		$rows = $this->fetchAll($stmt, $parameters);

		$arrays = array();
			foreach ($rows as $array)
			{
				$retval = null;
				
				if ( $array["array_id"] != "" ) {
					$cmd = "echo 'yes' | /usr/local/bin/zperfmon-add-game ".$game_name." ".$array["array_id"] . " 2>&1 > /tmp/split-log &";
				}
				$output = system($cmd, $retval);
				
				array_push($arrays, $array["array_id"]);
				if($retval != 0){
					error_log("Couldn`t  make game with  $cmd\n$retval\n$output\n", 3, sprintf($this->server_cfg['log_file'], $game_name));                        
					continue;
				}
			}
		}
}	

?>
