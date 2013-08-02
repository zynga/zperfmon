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


//
// Fetches all server arrays and ips in each array for current timeslot.
// And put this in a file named iplist in the timeslot directory.
// Also fetches machine counts to be used in inserting bd metrics.
// This can also be called with paramtere as game names.
// Default is the games listed in server configuration.
//

ini_set("memory_limit", -1);

include_once "server.cfg";
include_once "game_config.php";
include_once "curl_prefetch.php";
include_once "logger.inc.php";


class MachineDetail {
	public $nickName, $internalIP, $awsID, $nodeType, $href, $machineName, $isDynamic, $apiHref;

	private static $urlModifyRegex = array(
			'/\/api\/.*\/servers/' => '/servers',
			'/\/api\/.*\/ec2_instances/' => '/clouds/1/ec2_instances',
			);
	
	
	function MachineDetail($machineName, $nickName, $internalIP, $awsID, $href, $nodeType, $isDynamic) {	       
		$this->nickName = $nickName;
		$this->internalIP = $internalIP;
		$this->awsID = $awsID;
		$this->apiHref = $href;
		$this->nodeType = $nodeType;
		$this->machineName = $machineName;
		$this->isDynamic = $isDynamic;
		$this->href = preg_replace(array_keys(self::$urlModifyRegex),
					   array_values(self::$urlModifyRegex), $href);
	}
	
	function getTranslatedNickname($regex, $replacement) {
		$this->nickName = preg_replace($regex, $replacement, $this->nickName);
		if ($this->isDynamic) {
			$this->nickName = "{$this->nickName}-" .  str_replace(".", "-", $this->internalIP);
		}
	}

}


function getMachineCount($machine_split)
{
	$line = "web_count=" . count($machine_split["web"]).
		",db_count=" . count($machine_split["db"]).
		",mc_count=" . count($machine_split["mc"]).
                ",mb_count=" . count($machine_split["mb"]).
		",admin_count=" . count($machine_split["admin"]).
		",proxy_count=" . count($machine_split["proxy"]).
		",queue_count=" . count($machine_split["queue"]);
	return $line;
}


function write_to_file($server_cfg, $game_cfg, $file_base_name, $timestamp, $data)
{	

	if(null == $data){
		$game_cfg['logger']->log("get_rightscale_data","No data to write",Logger::WARNING);
		error_log("There is no rightscale_data to write\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return;
	}

	$time_slot = (string)(int)($timestamp / (30*60));
	$game_dir = sprintf($server_cfg['root_upload_directory'], $game_cfg['name']);
	$file_dir = $game_dir."/".$time_slot;
	$oldmask = umask(0); // to set the mod to 777
	if(!is_dir($file_dir) && !mkdir($file_dir, 0777, true)){

		$game_cfg['logger']->log("get_rightscale_data","$file_dir is not created",Logger::ERR);
		error_log("$file_dir not creared\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return false;
	}
	umask($oldmask);
	$file_name = $file_dir."/".$file_base_name;
	if(file_put_contents($file_name,$data)){
		
		$game_cfg['logger']->log("get_rightscale_data","Data is written to file $file_name",Logger::INFO);
		error_log("rightscale_data is written to file ${file_name}\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return $file_dir;
	}else{
		$game_cfg['logger']->log("get_rightscale_data","Data is not written to file $file_name",Logger::ERR);
		error_log("rightscale_data is not written to file ${file_name}\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	}

	return false;
}

function getAllArrayId($server_cfg, $game_cfg, $prefetch, $deploymentID)
{
	error_log("fetching array ids for deployment $deploymentID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	$array_id = array();	
	$retval = null;
	$output = $prefetch->wait("deploy_arrays_$deploymentID", $retVal);

	if ($retVal) {

		$game_cfg['logger']->log("get_rightscale_data","Arrayids are not fetched for $deploymentID",Logger::ERR);
		return $array_id;
	}

	$game_cfg['logger']->log("get_rightscale_data","Arrayids are fetched for $deploymentID",Logger::INFO);
	$serverArrays = new SimpleXMLElement($output);
	$array_href = $serverArrays->xpath("//server-array[@type='ServerArray']/href[contains(../deployment-href/text(),'$deploymentID')]/text()");

	foreach($array_href as $href){
		$array_id[] = preg_replace("%https:\/\/my.rightscale.com\/api\/acct\/[0-9]+\/server_arrays\/%","",$href);
	}

	return $array_id;		
}

function getAllArrayMachines($server_cfg, $game_cfg, $prefetch, $arrayID, $timestamp, &$ips, $instance_type="") 
{

	$varAwsID = 'resource-uid';
	$varExtIP = 'ip-address';
	$varIntIp = 'private-ip-address';
	
	error_log("Fetching server array $arrayID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));

	$retval = null;
	$output = $prefetch->wait("array_$arrayID", $retVal);

	if ($retVal) { 
		$game_cfg['logger']->log("get_rightscale_data","Machine details are not fetched for $arrayID",Logger::ERR);
		error_log("right scale fetch failed for array $arrayID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log($retVal."\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		return array();
	}
	
	$game_cfg['logger']->log("get_rightscale_data","Machine details are fetched for $arrayID",Logger::INFO);
	$machineDetails = array();

	if ($retVal == 0) {
        	$allServers = simplexml_load_string($output);
		foreach($allServers as $server) {
			if ($server->state != "operational" or
			    preg_match('%^(-bad)%',$server->nickName)) {
				# error_log("{$server->nickname} isn't ready. Skipped! \n", 0);
				$game_cfg['logger']->log("get_rightscale_data","{$server->nickname} is not ready. Skipped!",Logger::WARNING);
				continue;
			}

			$machineDetail = new MachineDetail($server->nickname, $server->nickname, 
							$server->$varIntIp, $server->$varAwsID,
							$server->href, $instance_type, true);
			array_push($machineDetails, $machineDetail);
			$ips[(string)$server->$varIntIp] = array("arrayId" => $arrayID, "awsId" => (string)$server->$varAwsID);
		}
	}
	return $machineDetails;		
}

function getAllDeployedMachines($server_cfg, $game_cfg, $prefetch, $deploymentID)
{
	$varAwsID = 'aws-id';
	$varExtIP = 'ip-address';
	$varIntIp = 'private-ip-address';
	error_log("Fetching deployment $deploymentID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
	
	$retval = null;
	$output = $prefetch->wait("deploy_$deploymentID", $retVal);

	if ($retVal) {
		$game_cfg['logger']->log("get_rightscale_data","Machine details are not fetched for $deploymentID",Logger::ERR);
		error_log("right scale fetch failed for deploy $deploymentID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		error_log($retVal."\n", 3, sprintf($server_cfg['log_file'], $game_cfg['name']));
		return array();
	}

	$game_cfg['logger']->log("get_rightscale_data","Machine details are fetched for $deploymentID",Logger::INFO);
	$machineDetails = array();
	if ($retVal == 0) {
        	$allServers = simplexml_load_string($output);
		foreach($allServers as $deployment) {
			foreach($deployment as $server) {
				if ($server->state != "operational" or preg_match('%^(-bad)%',$server->nickName)) {
					# error_log("{$server->nickname} isn't ready. Skipped! \n", 0);
					$game_cfg['logger']->log("get_rightscale_data","{$server->nickname} is not ready . Skipped!",Logger::WARNING);
					continue;
				}	
				$settings = $server->settings;
				$instance_type = "";
				$ip = "";
				$aws_id = "";

				if($settings) 
				{
					$instance_type = null;
					foreach($settings->children() as $val)
					{
						if($val->getName()=='ec2-instance-type')
						{					
							$instance_type = $val;
						}	
					}
					$ip = $settings->$varIntIp;
					$aws_id = $settings->$varAwsID;
				}

				$machineDetail = new MachineDetail($server->nickname, $server->nickname, 
							$ip, $aws_id,
							$server->href, $instance_type, false);
				array_push($machineDetails, $machineDetail);
			}
		}
	}

	return $machineDetails;		
}

/*
function create_games_for_arrays($server_cfg, $game_name, $arrayIDs)
{
	$root_upload_directory = sprintf($server_cfg["root_upload_directory"], $game_name);
	$timeslots_dir = basename($root_upload_directory);
	$top_dir = $root_upload_directory."../../"; // /var/opt/zperfmon/
	$count = 0;
	foreach($arrayIDs as $arrayID){
		$top_game_array_dir = "$top_dir/$game_name.$arrayID/";
		if ( !is_dir($top_game_array_dir) ){
			print("yes | zperfmon-add-game ".$game_name."_webarray_".$arrayID."\n");
			//mkdir("$top_game_array_dir/$timeslots_dir",0777,true);
			$output = shell_exec("yes | zperfmon-create-dir ".$game_name.$count);
			if(!$output){ 
				error_log("Couldnt create directories for web sever array $arrayID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
				break;
			}
			$output = shell_exec("zperfmon-create-gamedb ".$game_name.$count);
			if(!$output){ 
                                error_log("Couldnt create databases for web sever array $arrayID\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
                                break;
                        }
		}
	}
}
*/

function get_rightscale_data($server_cfg, $game_cfg, $current_timestamp)
{
	$api_prefix = $server_cfg["api_href_prefix"];
	$user = $server_cfg["rightscale_user"];
	$pass = $server_cfg["rightscale_passwd"];

	$ips = array(); // to hold the iplist with their corresponding array ids

	$prefetch = new Curl_Prefetch(array(
			CURLOPT_HTTPHEADER => array('X-API-VERSION: 1.0'),
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERPWD => "$user:$pass",
		)
	);
	
	$logger = new Logger($server_cfg, $game_cfg);
	$game_cfg['logger'] = $logger;

	error_log("=====>Fetching Rightscale Data =====>\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));

	foreach($game_cfg["deployIDs"] as $deployID) {
		$prefetch->add("deploy_$deployID","$api_prefix/deployments/{$deployID}.xml");
		
		// To get array ids
		$prefetch->add("deploy_arrays_$deployID","$api_prefix/server_arrays");	
		// append "?server_settings=true" if you want MachineDetail
	}
	
	if(isset($game_cfg["arrayIDs"])) {
		$arrayIDs = $game_cfg["arrayIDs"];
	}

	if(empty($arrayIDs)) $arrayIDs = array();

	$allMachines = array();
	foreach($game_cfg["deployIDs"] as $deployID) {
		$allMachines = array_merge($allMachines,
					   getAllDeployedMachines($server_cfg, $game_cfg, $prefetch, $deployID));
		if(empty($game_cfg["arrayIDs"])) {
			$arrayIDs = array_merge($arrayIDs, getAllArrayId($server_cfg, $game_cfg, $prefetch, $deployID));	
		}
	}
	
	$game_cfg["arrayIDs"] = $arrayIDs;
	
	foreach($game_cfg["arrayIDs"] as $arrayID) {
		$prefetch->add("array_$arrayID", "$api_prefix/server_arrays/$arrayID/instances");
	}

	foreach($game_cfg["arrayIDs"] as $arrayID) {
		$allMachines = array_merge($allMachines, 
					   getAllArrayMachines($server_cfg, $game_cfg, $prefetch, 
							       $arrayID, $current_timestamp, $ips));
	}
	//print("test");
	//create_games_for_arrays($server_cfg, $game_cfg["name"], $arrayIDs);

	$machine_split = array('web'=>array(),
			       'mc'=>array(),
			       'db'=>array(),
			       "mb"=>array(),
			       'admin'=>array(),
			       'proxy'=>array(),
			       'queue'=>array());

	$pregmatch=null;
	foreach ($allMachines as $machine)
	{
		if (preg_match('%-(web|db|mc|mb|admin|proxy|queue)%', 
			       $machine->nickName, $pregmatch)) {
			$machine_split[$pregmatch[1]][] = $machine;
		}
	}
	
	$game_dir = sprintf($server_cfg['root_upload_directory'], $game_cfg['name']);
	$file_name = $server_cfg['bd_metrics_file'];
	$machine_counts = getMachineCount($machine_split);
	if(($marker_dir = write_to_file($server_cfg, $game_cfg, $file_name, $current_timestamp, $machine_counts)) !== false )
	{
		touch($marker_dir."/.machine_counts");
	}

        $file_name = $server_cfg['iplist_file'];
	if(($marker_dir = write_to_file($server_cfg, $game_cfg, $file_name, $current_timestamp, json_encode($ips))) !== false)
	{
		// top level profile
		$top_iplist  = "$game_dir/../iplist.json"; # timeslots/../iplist.json
		if(file_exists($top_iplist)) {
			unlink($top_iplist); 
		}
		symlink("$marker_dir/$file_name",$top_iplist);
	}

}

function main($server_cfg)
{
	$timestamp = time();
	$options = getopt("g:");
	if ( isset($options['g']) && $options !== '') {
		$game_names = explode(",",$options['g']);
	} else {
		$game_names = $server_cfg['game_list'];
	}

	foreach($game_names as $game_name){
		$game_cfg = load_game_config($game_name);
		get_rightscale_data($server_cfg, $game_cfg, $timestamp);
	}
}

main($server_cfg);

?>
