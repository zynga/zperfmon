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

header("MIME-Type:application/json");
//header("Content-Type:text/json");
//
// This script returns a json object containing events(tags and/or releases) 
// between given timestamp
//
// inputs:-
// game: name of the game
// start: start timestamp (default is current - 30 min)
// end: end timestamp ( default is current )
// type: type ofthe events ( default is release)
// version: api version(default is v 1.0)
//

include_once 'game_config.php';
include_once 'PDOAdapter.php';
include_once 'server.cfg';

$start = null;
$end = null;
$types = array("all", "tag", "release");
$type = null;
// If game name is not given return empty json.
// With message that game name should be given
if(empty($_GET['game'])) {
	echo json_encode(array("Please give a game name e.g. game=city"));
	exit(0);	
}

// basic checks ends

$game = $_GET['game'];


$game_cfg = load_game_config($game);
// check if the game config loaded or not .
// i.e. game exists or not
if ( empty($game_cfg) ) {

	echo json_encode("game {$game} doesn't exists in zperfmon server");
	exit(0);
}


function get_rs_db_conf($rs_cfg_file) {

	$rs_cfg = parse_ini_file($rs_cfg_file, true);
	return array( 'db_host'=>$rs_cfg['DB']['host'],
		      'db_name'=>$rs_cfg['DB']['database'],
		      'db_user'=>$rs_cfg['DB']['user'],
                      'db_pass'=>$rs_cfg['DB']['password']
		     );
}


class API extends PDOAdapter {

	public function __construct($server_cfg, $game_cfg) {
		$rs_conf_file = $server_cfg['rs_conf_file'];
		$game_db_conf = get_rs_db_conf($rs_conf_file);
		$db_host = $game_db_conf['db_host'];
		$db_name = 'rightscale';
		$db_user = $game_db_conf['db_user'];
		$db_pass = $game_db_conf['db_pass'];
		parent::__construct($db_host, $db_user, $db_pass, $db_name);
	}

	public function get_sketchy($game_cfg,$server_cfg) {
		$deploy_id = $game_cfg["deployIDs"][0];
		$deregistered_ip_file = sprintf($server_cfg["deregistered_ips_file"],$game_cfg["name"]);
	
		if(file_exists($deregistered_ip_file)){
			$ips = file_get_contents($deregistered_ip_file);
			$ips_array = explode("\n",$ips);
			$ips_list = implode(",",$ips_array);
			$ips_list = "'" . str_replace(",", "','", $ips_list) . "'";
			$ips_list = '('.$ips_list.')';
			$query = "select sketchy_id, deploy_id, hostname, status, private_ip from instances where private_ip not in $ips_list and deploy_id = $deploy_id";
		}
		else{
			$query = "select sketchy_id, deploy_id, hostname, status, private_ip from instances where deploy_id = $deploy_id";
		}
                error_log("get_sketchy_query: ".$query);
                $stmt = $this->prepare($query);
	
                $rows = $this->fetchAll($stmt, array());
		
                return $rows;

	}
}

$api = new API($server_cfg, $game_cfg);
$result = $api->get_sketchy($game_cfg,$server_cfg);

echo json_encode($result);

?>
