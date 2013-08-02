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


include_once "server.cfg";
include_once 'game_config.php';

function main($server_cfg)
{
        if(isset($_GET['game'])){
                $game = $_GET['game'];
        }else{
                echo "Game name is invalid or not set \n";
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
	
	$regex = $_GET["regex"];
	$regex = str_replace(".*",'%',$regex);	

	$query = "select hostname from instances where hostname like '$regex' and deploy_id=$deploy_id";
        $stmt = $mysql_pdo->prepare($query);
        $stmt->execute();
        $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);	
	$count_hostnames = array();
	foreach($hosts as $value){
                $count_hostnames["hosts"][] = $value["hostname"];
        }

	
	$count_hostnames["count"] = count($count_hostnames["hosts"]);

	echo json_encode($count_hostnames);

}
main($server_cfg);
?>
