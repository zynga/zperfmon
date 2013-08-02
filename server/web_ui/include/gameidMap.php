
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
<?php 

include_once('XhProfModel.php');
include_once('server.cfg');
function  get_report_cfg ($db_conf_file) {
	$config = parse_ini_file($db_conf_file, true);
	return array(
		"db_host" => $config["DB"]["host"],
		"db_user" => $config["DB"]["user"],
		"db_pass" => $config["DB"]["password"],
		"db_name" => 'config',
	);
}
function getGameMapping($server_cfg){
	$cfg = get_report_cfg($server_cfg['report_conf_file']);
	$xhprofModelObject = new XhProfModel($server_cfg, $cfg,false,false);
	$gameMap = array();
	$result = $xhprofModelObject->generic_execute_get_query("get_game_name_by_id", array());
	foreach ( $result as $value) {
		$gameMap[$value['id']] = $value['name'];
	}
	return  $gameMap;
}
?>