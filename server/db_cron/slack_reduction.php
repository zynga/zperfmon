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


include_once 'server.cfg';
include_once 'game_config.php';
include_once 'logger.inc.php';
include_once 'slack_api.php';
include_once 'game_deploy_map.php';
include_once 'get_pdt_parallel.php';

date_default_timezone_set('UTC');

$conf_all = parse_ini_file($server_cfg["rs_conf_file"], true);
$conf = $conf_all["DB"];
$db_server = $conf["host"];
$db_user = $conf["user"];
$db_pass = $conf["password"];
$db_name = $conf["database"];

$pool_name = NULL;
$game_name = NULL;
$deregistered_ips = array();

$mysql_pdo = new PDO( "mysql:host={$db_server};dbname={$db_name}",$db_user, $db_pass);

if (!$mysql_pdo) {
       die("Failed to create new mysql PDO\n");
}

// Read the options given to the file
function get_options()
{

        $options = getopt("a:");

        return $options;
}

//Function returns the previous days lowest slack, instance count and optimal instance count
function get_instance_count_slack_offset($game_name,$pool_name){
	$slack_found = false;
	for($try = 1; $try < 60; $try++){
		$slack_api = json_decode(calculate_slack($game_name,false),true);
		if(isset($slack_api[$pool_name])  and $slack_api[$pool_name]['slack'] != 'NA'){
			$slack_found = true;
			break;
		}
		sleep(1);
	}
	if($slack_found){
		$pool_data = $slack_api[$pool_name];
		$pool_data['slack'] = str_replace('%','',$pool_data['slack']);
		return $pool_data;
	}
	die("Could not fetch the slack data from the database!\n");
}

//This function returns the current slack - data fetched from zmon
function get_current_slack($game_name,$pool_name,$instance_count){
	global $deregistered_ips;
	$slack_found = false;
	for($try = 1; $try < 60; $try++){
		$slack_api = json_decode(calculate_slack($game_name,true),true);
		if(isset($slack_api[$pool_name]) and $slack_api[$pool_name]['slack'] != 'NA'){
			$slack_found = true;
			break;
		}
		sleep(1);
	}
			
        if($slack_found){
//		$optimal_instance_count = round($instance_count*$slack_api[$pool_name]["optimal_count_factor"]);
		
//                $pool_slack = number_format((($instance_count - $optimal_instance_count) * 100)/$instance_count, 2,'.',''); 
		$optimal_count_factor = $slack_api[$pool_name]["optimal_count_factor"];
		$pool_slack = number_format(((1 - $optimal_count_factor) * 100), 2,'.','');
//		echo "Current Instance Count :- $instance_count\nOptimal Count fact :- ".$slack_api[$pool_name]['optimal_count_factor']."\nCurrent Slack : $pool_slack\n\n";

                return $pool_slack;
        }
        echo "Could not fetch the current slack from the zmon!\n";
	print_final_result(NULL,NULL,$deregistered_ips,false);	
}

//This function is used to print the final result - success or failure
function print_final_result($current_slack,$instance_count,$deregistered_ips,$success=true){
	global $pool_name,$game_name;
	$relative_path = realpath(dirname(__FILE__));
	if(!$success){
                $deregistered_ips = json_encode($deregistered_ips);
                $deregistered_ips = substr($deregistered_ips,1,-1);
		
                echo "\nSlack reduction process failed\n";
		if(empty($deregistered_ips))
			exit(0);
		echo "Registering all the deregistered IPs ($deregistered_ips)\n";
                system("php $relative_path/register_node.php -g $game_name -p $pool_name -i $deregistered_ips",$exit_code);

                if($exit_code == 0)
                        echo "Failed to complete the Registration process. Try registering it manually.Exiting.\n";
		exit(0);
        }

	else{
		echo "\nSlack reduction process completed successfully with :- \n";
		echo "Current Slack = $current_slack ,";
		echo "Current Instance Count = $instance_count";
		echo "\nDeregistered ips : ";
		if(empty($deregistered_ips))
			echo "NULL";
		else{
			foreach($deregistered_ips as $ip){
				echo "\n$ip";
			}
		}
		echo "\n";
		exit(1);
	}
}

//Function to wait for a specified amount of time
function wait_for_change($time_min){
	echo "Waiting for $time_min minutes.";
	while(true){
		sleep(60);
		$time_min--;
		if($time_min == 0)
			break;
		echo ".";
	}		
	echo "\n";
}

// Function to delete the rollback file( file that logs all the F5 deregistration with the respective registration commands)
function unlink_rollback_file($server_cfg,$game_name,$pool_name){
	$fast_rollback_target = sprintf($server_cfg['config_directory'],$game_name);
        $fast_rollback_target = $fast_rollback_target . $pool_name."_rollback.sh";

	if(file_exists($fast_rollback_target)){
		if(!unlink($fast_rollback_target))
			die("Unable to clear the rollback script file $fast_rollback_target. Terminating.\n");
	}
}

//Curl call to update the machineDetails.inc.php file in zmon
function update_zmon_machine_details($game_cfg){
        global $server_cfg;

        $url = sprintf($server_cfg["update_zmon_machines"],$game_cfg["zmon_url"]);

        $options = array (CURLOPT_RETURNTRANSFER => true, // return web page
                                        CURLOPT_HEADER => false, // don't return headers
                                        CURLOPT_FOLLOWLOCATION => true, // follow redirects
                                        CURLOPT_ENCODING => "", // handle compressed
                                        CURLOPT_USERAGENT => "zperfmon", // who am i
                                        CURLOPT_AUTOREFERER => true, // set referer on redirect
                                        CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
                                        CURLOPT_TIMEOUT => 60, // timeout on response
                                        CURLOPT_MAXREDIRS => 10);
        $ch = curl_init ( $url );
        curl_setopt_array ( $ch, $options );
        $content = curl_exec ( $ch );

        $httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
        curl_close ( $ch );
        if($httpCode!=200){
                die("HTTP Error Code : ".$httpCode."\nCURL Error : $errmsg\n");
        }

        $content = trim($content);

        if($content == "DONE")
                return true;
        else
                return false;
}

function update_zmon_machines($game_cfg,$deregistered_ips){
	global $server_cfg;
	
	$deregistered_ip_file = sprintf($server_cfg["deregistered_ips_file"],$game_cfg["name"]);
	$content = implode("\n",$deregistered_ips);

	if(file_put_contents($deregistered_ip_file,$content) === false){
		die("Unable to populate the deregistered ip list file $deregistered_ip_file \n");
	}
	chmod($deregistered_ip_file,0777);
	$result = update_zmon_machine_details($game_cfg);
	if($result === true)
		return;
	die("Unable to update the machineDetails.inc.php file : \n$result\n");
	
}

function main($server_cfg){
	global $mysql_pdo,$pool_name,$game_name,$deregistered_ips;
	$relative_path = realpath(dirname(__FILE__));
        $options = get_options();
	
	if (isset($options['a']) && $options['a'] !== '') {
                $array_id = $options['a'];
        }
        else
                die("Array ID not provided\n");	
		

	$query = "select distinct hostgroup,deploy_id,private_ip from instances where array_id=$array_id";
        $stmt = $mysql_pdo->prepare($query);
        $stmt->execute();	
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if($rows)
        {
		$ip_list = array();
                $row = $rows[0];
		$pool_name = $row['hostgroup'];
		$deploy_id = $row['deploy_id'];
		foreach($rows as $key=>$row){
			$ip_list[] = $row['private_ip'];
		}
        }
	else
		die("Could not map array id to hostgroup\n");
	
	//Fetching the game name from the game_deploy_map.json file
	$game_deploy_map = get_game_deploy_map($server_cfg);
	if(isset($game_deploy_map[$deploy_id]))
		$game_name = $game_deploy_map[$deploy_id];
	else
		die("Unable to map game deployment id to game name\n");

	$game_cfg = load_game_config($game_name);
	// Getting the ip of loadbalancer to be hit	
	$f5_credentials = $game_cfg["f5_credentials"][$pool_name];
	$f5_vip = $f5_credentials["f5_vip"];
	
	//Fetching the target slack	
	$target_slack = $game_cfg["target_slack"][$pool_name];
	if($target_slack <= 0)
		 die("Unable to proceed as the target slack is less than or equal to zero\n");	
	
	$pool_data = get_instance_count_slack_offset($game_name,$pool_name);
	
	//Delete any previous rollback file existing
	unlink_rollback_file($server_cfg,$game_name,$pool_name);
		
	$instance_count = count($ip_list);
	$slack_offset = $pool_data['slack'];
	$opt_inst_count = $pool_data['optimal_instance_count'];

	//Getting the current slack via api from the zmon machine.	
	$current_slack = get_current_slack($game_name,$pool_name,$instance_count);

	if($current_slack < $slack_offset)
		die("Unable to proceed as the current slack is less than the 1 day lowest slack.\n");
	//Setting the target slack relative to the slack offset and finding the optimal instance count for acheving it.
	$target_slack = $target_slack + ($current_slack - $slack_offset);
	if($target_slack >= $current_slack)
		die("Unable to proceed as the target slack is greater than current slack\n");

	//Calculating the optimal instance count for the target slack
	$opt_inst_count_target = ($target_slack * ($instance_count - $opt_inst_count))/$slack_offset + $opt_inst_count;
        $opt_inst_count_target = round($opt_inst_count_target);

	if($opt_inst_count_target <= 0)
		die("Unable to proceed as the optimal instance count required for target slack is less then or equal to 0\n");

	echo "Current slack = $current_slack%\nCurrent Instance Count = $instance_count\nTarget slack = $target_slack%\nOptimal count for target slack = $opt_inst_count_target\n\n";	

	$iteration_count = 0;
	/*      FETCH THE INITIAL PRT from all the nodes and store it */
	echo "Calculating initial page response times...\n";
	$initial_page_pdt_array = get_pdt_per_page($f5_vip,count($ip_list));
	$prev_pdt = $initial_page_pdt_array;
	foreach($initial_page_pdt_array as $page=>$pdt_data){
		echo "Page Delivery time for page $page is ".$pdt_data[2]." ms\n";
	}
	echo "\n";
	//Fetching the tolerable pdt limit percent from the game cfg file
	$pdt_diff_tol_percent = $game_cfg["pdt_diff_tol_percent"];

	while($iteration_count < 5){
		$instance_count = count($ip_list);
		$count_overflow = $instance_count - $opt_inst_count_target;
		$count_deregister = (int)($count_overflow/2);
		//Deregister the first (count_overflow) / 2 nodes from the ip_list and wait for 5 minutes
		if($count_overflow > 2){
			echo "Deregistering $count_deregister nodes from F5...\n";	
			$deregister_ips = array_slice($ip_list,0,$count_deregister);
			$deregister_ip_list = json_encode($deregister_ips);
			$deregister_ip_list = substr($deregister_ip_list,1,-1);

			//Deregister $count_deregister nodes from F5 parallelly
			system("php $relative_path/deregister_node.php -g $game_name -p $pool_name -i $deregister_ip_list",$exit_code);

			if($exit_code == 0){
				echo "Failed to complete the deregistration process. Exiting.\n";
				exit(0);
			}
			$ip_list = array_slice($ip_list,$count_deregister);
			$deregistered_ips = array_merge($deregistered_ips,$deregister_ips);	
			update_zmon_machines($game_cfg,$deregistered_ips);
		}

		else{
			echo "The current instance count ($instance_count) is almost optimal for the target slack.\n";
			$current_slack = get_current_slack($game_name,$pool_name,$instance_count);
			print_final_result($current_slack,count($ip_list),$deregistered_ips);
		}
				
		//Wait for 5 minutes for the changes to take place
		wait_for_change(5);
		
		//Calculate the current slack
		$current_slack_after_dereg = get_current_slack($game_name,$pool_name,count($ip_list));

		/*      FETCH THE PRT from all the nodes and store it. Also Compare the PRT */
		//Flag to check if the pdt diff is huge so as to terminate the workflow in this iteration
		$pdt_stop_iteration = false;
		while(true){
			$pdt_huge_diff = false;
			$current_page_pdt_array = get_pdt_per_page($f5_vip,count($ip_list),$prev_pdt);
			$prev_pdt = $current_page_pdt_array;
			foreach($current_page_pdt_array as $page => $pdt_data){
				$pdt_diff = $pdt_data[2] - $initial_page_pdt_array[$page][2];
				$diff_tol = ($pdt_diff_tol_percent/100)* $initial_page_pdt_array[$page][2];
				echo "Checking PDT for $page .... (".$pdt_data[2]." ms) .... ";
				if($pdt_diff > $diff_tol){
					echo "\nPDT (".$pdt_data[2]." ms) increased more than $pdt_diff_tol_percent % for page $page.\n\n";
					$pdt_stop_iteration = true;
					$pdt_huge_diff = true;
				}
				else
					echo "OK\n";
					
			}
			//If the pdt difference is intolerable then  register $count_deregister/2 nodes wait for 5 mins and check again.
			if($pdt_huge_diff){
				$count_register = ceil($count_deregister/2);
				if(count($deregistered_ips) < $count_register){
					$count_register = count($deregistered_ips);
				}
				if(empty($deregistered_ips)){
					echo "All the nodes have been registerd.Still pdt difference is high...\n";
					print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips);
				}
				echo "Registering $count_register nodes .... \n";
				$register_ips = array_slice($deregistered_ips,0,$count_register);
				$register_ip_list = json_encode($register_ips);
				$register_ip_list = substr($register_ip_list,1,-1);

				system("php $relative_path/register_node.php -g $game_name -p $pool_name -i $register_ip_list",$exit_code);
				
				if($exit_code == 0){
						echo "Failed to complete the Registration process. Exiting.\n";
						print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips,false);
				}
				$ip_list = array_merge($ip_list,$register_ips);
				$deregistered_ips = array_slice($deregistered_ips,$count_register);
				update_zmon_machines($game_cfg,$deregistered_ips);
				//Wait for 5 minutes for the changes to take place
				wait_for_change(5);
			}
			else{
				break;
			}
		}

		$current_slack_after_dereg = get_current_slack($game_name,$pool_name,count($ip_list));

		if($current_slack_after_dereg > $target_slack)
			echo "Current slack ($current_slack_after_dereg %) is greater than target slack ($target_slack %). continuing .....\n";
		else if($current_slack_after_dereg == $target_slack){
			echo "Current slack ($current_slack_after_dereg %) is equal to target slack. Slack reduction completed successfully...\n";
			print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips);
		}
		else{
			// Current slack is less than the target slack - add $count_deregister/2 nodes, wait for 5 mins and check slack again.
			echo "Current slack ($current_slack_after_dereg %) is less than target slack ($target_slack %)\n";
			$count_register = ceil($count_deregister/2);

			while($current_slack_after_dereg < $target_slack){
				if(count($deregistered_ips) < $count_register){
                         	       $count_register = count($deregistered_ips);
                        	}
				if(empty($deregistered_ips)){
					echo "All the ips have been registered...\n";
					break;
				}
				echo "Registering $count_register nodes .... \n";
				$register_ips = array_slice($deregistered_ips,0,$count_register);
				$register_ip_list = json_encode($register_ips);
				$register_ip_list = substr($register_ip_list,1,-1);
				
				system("php $relative_path/register_node.php -g $game_name -p $pool_name -i $register_ip_list",$exit_code);

				if($exit_code == 0){
					echo "Failed to complete the Registration process. Exiting.\n";
					print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips,false);
				}
				$ip_list = array_merge($ip_list,$register_ips);
				$deregistered_ips = array_slice($deregistered_ips,$count_register);
				update_zmon_machines($game_cfg,$deregistered_ips);
				//Wait for 5 minutes for the changes to take place
				wait_for_change(5);
				$current_slack_after_dereg = get_current_slack($game_name,$pool_name,count($ip_list));
			}
			
			print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips);
		}
		if($pdt_stop_iteration){
			print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips);
		}		
		$iteration_count++;
	}
	if($iteration_count >= $limit){
		echo "$iteration_count iterations completed!\n";
		print_final_result($current_slack_after_dereg,count($ip_list),$deregistered_ips);	
	}
}

main($server_cfg);	

?>
