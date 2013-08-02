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

include_once 'server.cfg';
include_once 'spyc.php';
include_once 'yml_conf.inc.php';
include_once 'slack_api.php';
include_once 'game_config.php';

if(isset($_POST['game'])){
	$game = $_POST['game'];
}
else{
	echo "Game name is invalid or not set \n";
	exit;
}

$current_yaml_file = array();
	
function write_to_hostgroup_yml(){
	global $server_cfg,$game,$current_yaml_file;
	
//	Creating a copy of current yaml file
	$hostgroupConfigObj = new HostgroupConfig($server_cfg, $game);
        $current_yaml_file = $hostgroupConfigObj->load_hostgroup_config();

	$final_array= json_decode($_POST["json_final"],true);
	foreach($final_array as $regex => $config){
		if(!isset($config["class"]))
			$final_array[$regex] = "";
	}
	$file_path = sprintf($server_cfg["hostgroups_config"],$game);
        $yaml_str = Spyc::YAMLDump($final_array);
        file_put_contents($file_path,$yaml_str) or die("Unable to modify hostgroup.yml file. Exiting....");
	
}

function modify_hostgroup_in_db($final_array){
	global $server_cfg,$game;
 
	foreach($final_array as $regex => $config){
                if(!isset($config["class"]))
                        $final_array[$regex] = "";
        }

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
	
	$game_cfg = load_game_config($game);
        $deploy_id = $game_cfg["deployIDs"][0];
        foreach($final_array as $regex=>$metric){
                $mysql_regex = str_replace(".*","%",$regex);
                $hostgroup = $metric['hostgroup'];
                $query = "update instances set hostgroup='$hostgroup' where hostname like '$mysql_regex' and deploy_id=$deploy_id";
                $stmt = $mysql_pdo->prepare($query);
                $stmt->execute();
        }

}

function return_to_admin_tab(){
	global $server_cfg;
	$url = "/zperfmon/#tab-admin";
	header("Location: http://".$server_cfg["hostname"].$url);
}

function create_hostgroup_yml_backup(){
	global $server_cfg;
	$file_path = sprintf($server_cfg["hostgroups_config"],$_POST["game"]);
	copy($file_path,$file_path.".ORI") or die("Could not back up the hostgroup.yml file. Exiting...");
}

function restore_hostgroup_yml(){
	global $server_cfg;
        $file_path = sprintf($server_cfg["hostgroups_config"],$_POST["game"]);
        copy($file_path.".ORI",$file_path) or die("Could not restore the hostgroup.yml file. Please restore manually...");
}
function main($server_cfg){
	global $current_yaml_file;
	if(!isset($_POST["dry_run"])){
		write_to_hostgroup_yml();
		return_to_admin_tab();
	}
	else if(isset($_POST["print_data"])){
		echo calculate_slack($_POST["game"],false);
	}
	else if(isset($_POST["dry_run"])){
		$final_array= json_decode($_POST["json_final"],true);
		create_hostgroup_yml_backup();
		write_to_hostgroup_yml();
		modify_hostgroup_in_db($final_array);
		echo calculate_slack($_POST["game"],true);
		restore_hostgroup_yml();
		modify_hostgroup_in_db($current_yaml_file);
	}
}

main($server_cfg);
?>
