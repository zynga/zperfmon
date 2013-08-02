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
/*
Script to get random IPs will the following limits for games
to use with zperfmon

Usage: [-g game]
*/


include_once 'server.cfg';
include_once 'game_config.php';
include_once 'PDOAdapter.php';
include 'zRuntimeAPI.php';

function get_rs_config($rs_conf_file) {
	$config = parse_ini_file($rs_conf_file, true);
	return array(	
		"db_host" => $config["DB"]["host"],
		"db_user" => $config["DB"]["user"],
		"db_pass" => $config["DB"]["password"],
		"db_name" => $config['DB']["database"],
		);
}

class GetIPs extends PDOAdapter {

	private $MIN = 5; // Minimum array size to apply sampling
	private $AMIN = 10; // Minimum candidates to take if above $MIN
	private $MAX = 15; // Maximum instances from one array
	private $LIMIT = 45; // Maximum instances we want to select

	public $debug = False;
 
	public function __construct($server_cfg, $game)
	{
		$cfg = get_rs_config($server_cfg['rs_conf_file']);
		$db_server = $cfg["db_host"];
		$db_user = $cfg["db_user"];
		$db_pass = $cfg["db_pass"];
		$db_name = $cfg["db_name"];

		$this->server_cfg = $server_cfg;
		$this->game = $game;

		parent::__construct($db_server, $db_user, $db_pass, $db_name);

		$this->ip_list = array();

		// Query rightscale DB to get array wise IPs for 'game'
		$this->get_ips();

		// Apply selection criteria and find profiling candidate IPs
		$this->balance_ips();
	}

	private function get_ips() {

		$this->game_cfg = load_game_config($this->game);
		$deploy_id = $this->game_cfg['deployIDs'][0];

		$query = "select array_id, private_ip from instances where deploy_id={$deploy_id} and array_id != 0";
		$stmt = $this->prepare($query);

		if ($stmt) {
			$result = $this->fetchAll($stmt, array());
			
			foreach ($result as $item) {
				$array_id = $item['array_id'];
				$this->ip_list[$array_id][] = $item['private_ip'];
			}
		}
	}

	private function balance_ips() {
		$quota = max($this->LIMIT, count($this->ip_list) * $this->MIN);
		$candidates = array();
		$cand_count = 0;

		// Take all IPs from arrays with less than 'MIN' instances
		foreach ($this->ip_list as $array_id => $ips) {
			if (count($ips) <= $this->MIN) {
				if ($this->debug) {
					echo $array_id, " ", count($ips), "\n";
				}
				$quota -= count($ips);
				$candidates = array_merge($candidates, $ips);
				unset($this->ip_list[$array_id]);
			} else {
				$cand_count += count($ips);
			}
		}

		// Find the ratio required to reduce total IPs to required
		// count. Reduce each array by that ratio. Round off applying
		// MIN/MAX counts
		if ( $cand_count==0){		
			$ratio = 1;
		}
		else{
			$ratio = (float)$quota/(float)$cand_count;
		}
    		foreach ($this->ip_list as $array_id => $ips) {
			shuffle($ips);
			$ip_count = (int)(count($ips) * $ratio);
			$ip_count = ($ip_count > $this->MAX) ? $this->MAX :
				(($ip_count < $this->AMIN) ? $this->AMIN : $ip_count);
			$candidates = array_merge($candidates, 
					    array_slice($ips, 0, $ip_count));
			if ($this->debug) {
				echo $array_id, " ", count($ips), " " , $ip_count, "\n";
			}
		}
		$this->candidates = $candidates;
	}


	public function update_file() {

		$game_name = $this->game_cfg["name"];

		$xhprof_ip_list_file = sprintf($this->server_cfg["xhprof_ip_list"], $game_name);
		
		$iplist = implode("\n", $this->candidates);
		
		file_put_contents($xhprof_ip_list_file, $iplist);
	}

	public function update_zrt() {
		
		# game is configured for ini generation, don't update zRuntime
		if (in_array($this->game, $this->server_cfg["auto_ini_games"])) {
			return;
		}

		$env = $this->game_cfg["zrt_env"];
		$zrt_name = $this->game_cfg["zrt_game_name"];
		$zrt_uname = "zperfmon";
		$zrt_pwd = "zPerfm0n";
		
		$zrt = new zRuntimeAPI($zrt_uname, $zrt_pwd, $zrt_name, $env);
		
		$live = $zrt->zRTGetLive();
		$rev =  $live["rev"];
		
		# get key for XHPROF IP LIST
		$keys = array('XHPROF_ENABLE_IPLIST');
		$curr_list = $zrt->zRTGetLiveForKeys($keys);
		$output_array = $curr_list["output"];

		# checks for XHPROF_ENABLE_IPLIST key in ZRT
		if(isset($output_array["XHPROF_ENABLE_IPLIST"])){
			# XHPROF_ENABLE_IPLIST key exist so update the key
			$update = array("XHPROF_ENABLE_IPLIST" => $this->candidates);
			$zrt->zRTUpdateKeys($rev, $update);
			echo "IP list for {$this->game} updated.\n";
		} else{
			#XHPROF_ENABLE_IPLIST doesnt exist so creating the key
			$add = array("XHPROF_ENABLE_IPLIST" => $this->candidates);
			$zrt->zRTAddKeys($rev, $add);
			echo "XHPROF_ENABLE_IPLIST key for $game created and IP list added.\n";
        	}

	}
}
	
$options = getopt ("g:");
$game = $options['g'];

$getIPsObj = new GetIPs($server_cfg, $game);
$getIPsObj->update_file();
$getIPsObj->update_zrt();
