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
include_once 'rightscale.php';
include_once 'game_config.php';

//given a game name and ip list, this script creates symlinks of the ip directories to the array games 
function splitarraywise($server_cfg, $ip_list, $game_name, $time_slot)
{

	$game_cfg = load_game_config($game_name);
	$rs = new RightScale($server_cfg, $game_cfg);
	$mapping = $rs->get_array_ip_mapping();
	$return  = array();	
	foreach($ip_list as $ip)
	{
		$dir_base_path = sprintf($server_cfg["root_upload_directory"],$game_name);
		$base_ip_dir = $dir_base_path.$time_slot."/" .
                                      $server_cfg['profile_upload_directory'] ;
		$ip_dir	= $base_ip_dir."/".$ip;
	
		//$filelist = scandir($ip_dir);
			
		$array_id = $mapping[$ip];
		
		// To prevent game_ folder to be created due to 5 minute processing 
		if ( !isset($array_id) || $array_id ==''){
			continue;
		}

		$return[$array_id][] = $ip;
		$dir_base_path_array = sprintf($server_cfg["root_upload_directory"],$game_name."_".$array_id);
		
		//creating the timeslot directory
		if ( !is_dir($dir_base_path_array.$time_slot) )
		{
			mkdir($dir_base_path_array.$time_slot,0777,true);
		}

		//creating the xhprof directory
		if ( !is_dir( $dir_base_path_array.$time_slot."/" .
			      $server_cfg['profile_upload_directory'] ) )
		{
			mkdir( $dir_base_path_array.$time_slot."/" .
			      $server_cfg['profile_upload_directory'] ,0777,true);
		}

		$base_ip_dir_array = $dir_base_path_array.$time_slot."/" .
			      $server_cfg['profile_upload_directory'] ;
		
		$ip_directory_array = $base_ip_dir_array."/".$ip ;
		// Check if symlink exists already. In case of frequent call of the function in a timeslot
		if(!is_dir($ip_directory_array)) {
			symlink($ip_dir, $ip_directory_array);
		}

		if (!file_exists($base_ip_dir_array."/".".profiles")) {
			// put the ip uploading ip addresses which should be segragated while massaging.

			file_put_contents("$base_ip_dir_array/.profiles",$ip, FILE_APPEND | LOCK_EX); 
			//touch(($base_ip_dir_array."/".".profiles")); 
			error_log(".profiles created" . "\n" . sprintf($server_cfg['log_file'],$game_cfg['name']));
		}

		if (!file_exists($base_ip_dir_array."/".".slowpages")) {
			touch(($base_ip_dir_array."/".".slowpages"));
			error_log(".slowpages created" . "\n" . sprintf($server_cfg['log_file'],$game_cfg['name']));
		}
		if (!file_exists($base_ip_dir_array."/".".apache_stats")) {
			touch(($base_ip_dir_array."/".".apache_stats"));
			error_log(".apache_stats created" . "\n" . sprintf($server_cfg['log_file'],$game_cfg['name']));
		}
		
	}
	return $return;
}

?>
