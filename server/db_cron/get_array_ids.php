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

$game_config_path = "/etc/zperfmon/";
$per_game_config_path = "/etc/zperfmon/conf.d/";
$core_include = "/var/www/html/zperfmon/include/";

set_include_path(get_include_path() . ":$game_config_path:$per_game_config_path:$core_include");

include_once 'server.cfg';
include_once 'game_config.php';
include_once 'XhProfDAO.php';

function get_options()
{
        $options = array();
        $params = getopt("g:t:p:j:");
        if ( isset($params['g']) ) {
                return $params['g'];
        }

}


$game = get_options();
if (!file_exists($per_game_config_path.$game.".cfg")) {
exit(0);
}
$game_cfg = load_game_config($game);
$user = $game_cfg['db_user'];
$pass = $game_cfg['db_pass'];
$host = $game_cfg['db_host'];
$db = 'rightscale';
$deploy_id = $game_cfg['deployIDs'][0];
$XhProfDAO = new XhProfDAO($host, $user, $pass,$db );

$XhProfDAO->connect();
$array_id =  $XhProfDAO->prepare_and_query('select distinct array_id from instances where deploy_id ='.$deploy_id.';');
$ids = Array();
foreach ( $array_id as $id)
{
        array_push($ids,$id['array_id']);
}
foreach ( $ids as $id) {
        echo $id."\n";
}
$XhProfDAO->disconnect();
