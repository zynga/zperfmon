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


#
# This script is meant to auto generate the hostgroups.yml file
# We fetch the hostgroups from rightscale database
# find out the class using regular expression and dump the final data
#

include_once "server.cfg";
include_once 'game_config.php';

function main($server_cfg)
{
	$options = getopt("g:");
	if (isset($options['g']) && $options['g'] !== '') {
		$game = $options['g'];
	} else {
		echo "Input not correct: Use <script> -g <game_name> \n";
		exit; 
	}

	$game_cfg = load_game_config($game);
	$deploy_id = $game_cfg["deployIDs"][0];

	# Getting the distinct hostgroups for the inputed game
	$conf_all = parse_ini_file($server_cfg["rs_conf_file"], true);
	$conf = $conf_all["DB"];
	$db_server = $conf["host"];
	$db_user = $conf["user"];
	$db_pass = $conf["password"];
	$db_name = $conf["database"];

	$mysql_pdo = new PDO( "mysql:host={$db_server};dbname={$db_name}",$db_user, $db_pass);

	if (!$mysql_pdo) {
		print "Failed to create new mysql PDO\n";
		return 1;
	}

	$query = "select distinct hostgroup from instances where deploy_id = $deploy_id and array_id = 0";

	$stmt = $mysql_pdo->prepare($query);
	$stmt->execute();
	$hostgroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	$query = "select distinct hostgroup from instances where deploy_id = $deploy_id and array_id != 0";

	$stmt = $mysql_pdo->prepare($query);
	$stmt->execute();
	$web_hostgroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
	# Regular expressions to find the class
	$re = "(spare|old|bad|consumer|mb|gh-mqs|mq|db|mc|proxy|nagios|msched|gib)";
	$re_gh = "(spare|old|bad|consumer|mb|db|mq-|mc|proxy|nagios|msched|gib)";
	$re_bad = "(spare|old|bad)";
	$yml = "";
	
	foreach($web_hostgroups as $hostgroup){
		$yml = $yml . "'" . $hostgroup['hostgroup'] . ".*':\n";
		$yml = $yml . "  <<:  *common_web_eu\n"; 
		$yml = $yml . "  class:  web\n";
		$yml = $yml . "  hostgroup:  " . $hostgroup['hostgroup'] . "\n\n"; 
	}
	foreach($hostgroups as $hostgroup){
		preg_match($re_bad, $hostgroup['hostgroup'], $matches_bad);
		preg_match($re, $hostgroup['hostgroup'], $matches);
	
		if( count($matches_bad) > 0 ){
			$yml = $yml . "'" . $hostgroup['hostgroup'] . ".*':\n\n";
		}
		else if( count($matches) == 0 ) {
			$yml = $yml . "'" . $hostgroup['hostgroup'] . ".*':\n\n";
			echo "Please define a class for {$hostgroup['hostgroup']}\n";
		}
		else {
			$class = $matches[0];
			# for greyhound class 
			if( $class == "gh-mqs" ) {				
				preg_match($re_gh, $hostgroup['hostgroup'], $matches);
				$class = $matches[0];			
			}
			# One off case of mq, it has to be renamed to mqueue
			else if( $class == "mq" ) {
				$class = "mqueue";
			}
			$host = explode("-", $hostgroup['hostgroup']);
			array_shift($host);
			$hostgroup_name = implode("-" , $host);
			$yml = $yml . "'" . $hostgroup['hostgroup'] . ".*':\n";
			$yml = $yml . "  <<:  *common_".$class."_eu\n"; 
			$yml = $yml . "  class:  " . $class . "\n";
			$yml = $yml . "  hostgroup:  " . $hostgroup_name . "\n\n"; 
		}
	}
	$yml_path = sprintf($server_cfg["hostgroups_config"], $game_cfg['name']);
	file_put_contents($yml_path, $yml);
}

main($server_cfg);
?>
