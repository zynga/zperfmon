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


/**
 * Update the instance_class_name table in report database after any new class are added
 * @author Gaurav (gkumar@zynga.com)
*/

include_once 'yml_conf.inc.php';
include_once 'game_config.php';
include_once '/etc/zperfmon/server.cfg';
include_once 'XhProfModel.php';
include_once 'zpm_util.inc.php';

zpm_preamble("");


function get_config_file($game,$server_cfg){
	$game_cfg = load_game_config($game);
	if(isset($game_cfg['cloud_name']) && $game_cfg['cloud_name']=='zcloud')
		return $server_cfg['common_config_zcloud_file'];
	else
		return $server_cfg['common_config_file'];
}
function  get_report_cfg ($report_conf_file) {
	$config = parse_ini_file($report_conf_file, true);
	return array(
		"db_host" => $config["DB"]["host"],
		"db_user" => $config["DB"]["user"],
		"db_pass" => $config["DB"]["password"],
		"db_name" => $config['DB']["database"],
	);
}

$report_cfg = get_report_cfg($server_cfg['report_conf_file']);
$xhprofModelObject = new XhProfModel($server_cfg, $report_cfg ,false ,false);
$result =  $xhprofModelObject->generic_execute_get_query("get_instance_class_name", array());

foreach ( $result as $value) {
	$db_class[$value['class_id']] = $value['class_name'];
}


/*
 * load config for all the games
*/
$games = $server_cfg['game_list'];
$class = Array();

/* 
 * cycle through all games and find all class name by reading hostgroup
*/
foreach ( $games as $game){
	$hostgroup = new HostgroupConfig($server_cfg, $game);
	$class = array_merge($class, $hostgroup->get_class_name());
}

/*
 * find diff between these two array and insert into database if any new class is found in hostgroup files of any game
*/
$diff = (array_diff(array_unique($class),$db_class));
$max = count($db_class);

if ( count($diff) > 0 )
{
	foreach ($diff as $name=>$value){
		$max +=1;
		$xhprofModelObject->generic_execute_get_query("insert_instance_class_name", 
			array('table' => 'instance_class_name',
				'max' => $max,
				'value' => $value
			)
		);
	}
}
zpm_postamble("");
?>
