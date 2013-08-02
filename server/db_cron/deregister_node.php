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


	include_once "run_in_background.php";
	include_once "server.cfg";
	include_once 'game_config.php';

	// Function to deregister a node in background and return the pid	
	function deregister_node($ip,$f5_web_client_port,$f5_partition,$f5_user,$f5_pass,$f5_pool,$f5_ip1,$f5_ip2=NULL){
		global $server_cfg;
		$command = "/usr/local/bin/register.py -a $ip -p $f5_web_client_port -n $f5_partition remove $f5_user $f5_pass $f5_pool $f5_ip1 $f5_ip2";

		$target = sprintf($server_cfg['log_file'],$ip);

		$pid = run_in_background($command,$target,"+20");
		return $pid;
	}
	
	$options = getopt("i:g:p:");
        $ips = $options['i'];
        $ip_list = explode(',',$ips);
        $game_name = $options['g'];
        $pool_name = $options['p'];
	
	$game_cfg = load_game_config($game_name);
	
	//F5 Credentials
        $f5_credentials = $game_cfg["f5_credentials"][$pool_name];
        $f5_web_client_port = $f5_credentials["f5_web_client_port"];
        $f5_partition = $f5_credentials["f5_partition"];
        $f5_user = $f5_credentials["f5_user"];
        $f5_pass = $f5_credentials["f5_pass"];
        $f5_pool = $f5_credentials["f5_pool"];
        $f5_ip1 = $f5_credentials["f5_ip1"];
        $f5_ip2 = $f5_credentials["f5_ip2"];
	
	$fast_rollback_target = sprintf($server_cfg['config_directory'],$game_name);
        $fast_rollback_target = $fast_rollback_target . $pool_name."_rollback.sh";

	foreach($ip_list as $key => $ip){
		$rollback_command = "/usr/local/bin/register.py -a $ip -p $f5_web_client_port -n $f5_partition add $f5_user $f5_pass $f5_pool $f5_ip1 $f5_ip2 &\n";

		file_put_contents($fast_rollback_target,$rollback_command,FILE_APPEND | LOCK_EX);
		
		$pid[$ip] = deregister_node($ip,$f5_web_client_port,$f5_partition,$f5_user,$f5_pass,$f5_pool,$f5_ip1,$f5_ip2);		
		
	}

	// Waiting for the deregistrarion process to complete
	foreach($ip_list as $key => $ip){
                while(is_process_running($pid[$ip])){
                        sleep(1);
                }
		$ip_log_target = sprintf($server_cfg['log_file'],$ip);
		$log_contents[$ip] = file_get_contents($ip_log_target);
	}
	$failed = false;

	foreach($log_contents as $ip => $log){
		if(stripos($log,"Terminating with exit code 0") !== false){
			echo "Deregistered node $ip from F5... \n";	
		}
		else{
			$game_log = sprintf($server_cfg['log_file'],$game_name);
                        echo "Deregistration of node $ip failed. Check the game log. \n";
			error_log('['.date("F j, Y, g:i:s a e")."]\n".$log."\n",3,$game_log);
			$failed = true;
		}
	}

	if($failed)
		exit(0);
	else{
		echo "Deregistered ".count($ip_list)." nodes successfully\n\n";
		exit(1);		
	}
?>
