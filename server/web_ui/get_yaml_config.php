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

header("Content-Type:text/plain");

include_once 'server.cfg';
include_once 'yml_conf.inc.php';

$game = $_GET['game'];

if(empty($game)) {
	return ;
}
#$common_config_file = $server_cfg['common_config_file'];

$hostgroupConfigObj = new HostgroupConfig($server_cfg, $game);
$common_config_file = $hostgroupConfigObj->get_config_file($game); 

$game_config_file = sprintf($server_cfg['hostgroups_config'], $game);

$str_yaml = null;
if (file_exists($common_config_file)) {
	$str_yaml = file_get_contents($common_config_file);
}

if (file_exists($game_config_file)) {

	$str_yaml .= file_get_contents($game_config_file);
}

echo $str_yaml;
