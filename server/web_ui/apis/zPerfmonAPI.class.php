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


/*
 * The zPerfmon API front face. This class interacts with 
 * internal structures of zPerfmon and display/modifies them .
 * All the error handling has also been done in this class 
 * 
 * @Author Gaurav (gkumar@zynga.com) and Nasrudheen Kakkadavath (nkakkadavath@zynga.com)
 */

include_once 'PDOAdapter.php';
include_once 'game_config.php';
include_once('server.cfg');

class zPerfmonAPI extends PDOAdapter {

	protected $client;
	protected $server_cfg,$rs_cfg;
	
	public $status = 0;
	public $lastError = '';
	
	/* 
	 * Include server_cfg 
	 */
	public function __construct(){
		global $server_cfg;
		$this->server_cfg = $server_cfg;
		
		$this->rs_cfg = $this->get_rs_ini($server_cfg['rs_conf_file']);

                $db_host = $this->rs_cfg['DB']['host'];
                $db_user = $this->rs_cfg['DB']['user'];
                $db_pass = $this->rs_cfg['DB']['password'];
                $db_name = $this->rs_cfg['DB']['database'];

                parent::__construct($db_host, $db_user, $db_pass, $db_name);
	}
	private function get_rs_ini($conf_file) {

                $conf = array();
                $conf = parse_ini_file($conf_file, true);
                return $conf;
        }

	/*
	* Function to get the cpu,memory and rps data from the zperfmon DB.
	*/

	public function getEU($values,$game_name,$metric){

                if(is_numeric($game_name)){
                        include_once 'gameidMap.php';
                        $gidmap = getGameMapping($this->server_cfg);
                        if(isset($gidmap[$game_name]))
                                $game_name = $gidmap[$game_name];
                }
                
		$return_array = $this->parse_inputs_timestamp($values);
                if($return_array === false)
                        return;

		if(isset($return_array['array']))
			$game_name = $game_name."_".$return_array['array'];


                if(isset($return_array["ts"])){
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $metric FROM zprf_".$game_name.".vertica_stats_30min  where unix_timestamp(timestamp) = ".$return_array['ts'];
                }else if(isset($return_array["tstart"]) and isset($return_array["tend"])){
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $metric FROM zprf_".$game_name.".vertica_stats_30min  where unix_timestamp(timestamp) between ".$return_array['tstart']." and ".$return_array["tend"];
                }else{
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $metric FROM zprf_".$game_name.".vertica_stats_30min  order by timestamp desc limit 1";
                }

                $stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, NULL);
                if(isset($rows[0]) and isset($rows[0]) != NULL){
                        $this->status = 0;
                        foreach($rows as $key => $time_val){
                                $timestamp = $rows[$key]["timestamp"];
                                $web_data = $rows[$key][$metric];
                                $this->lastError[$timestamp] =  $web_data;
                        }
                }
                else{
                        $this->status = 1;
                        $this->lastError = "Unable to get data from DB or invalid timestamp/array given.";
                }
        }
	
	/*
        * Function to get the instance count data from the zperfmon DB.
        */

	public function getInstances($values,$game_name){
		if(is_numeric($game_name)){
                        include_once 'gameidMap.php';
                        $gidmap = getGameMapping($this->server_cfg);
                        if(isset($gidmap[$game_name]))
                                $game_name = $gidmap[$game_name];
                }	
		$instance_map = array("web" => "web_count",
					"db" => "db_count",
					"mc" => "mc_count",
					"mb" => "mb_count",
					"admin" => "admin_count",
					"queue" => "queue_count",
					"proxy" => "proxy_count");


		$return_array = $this->parse_inputs_timestamp($values);
		if($return_array === false)
                        return;
		$return_array["entity"] = $this->parse_inputs_entity($values);
		if($return_array["entity"] === false){
                        $mysql_class = implode(",",$instance_map);
		}
		else{
			$class_array = explode(",",$return_array["entity"]);
			foreach($class_array as $class){
				if(!isset($instance_map[$class])){
					$this->status = 1;
					$this->lastError = "Invalid entity $class specified!!!";
					return;
				}
				$mysql_class[] = $instance_map[$class];		
			}
			$mysql_class = implode(",",$mysql_class);		
		}
		if(isset($return_array["ts"])){
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $mysql_class FROM zprf_".$game_name.".stats_30min  where unix_timestamp(timestamp) = ".$return_array['ts'];
                }else if(isset($return_array["tstart"]) and isset($return_array["tend"])){
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $mysql_class FROM zprf_".$game_name.".stats_30min  where unix_timestamp(timestamp) between ".$return_array['tstart']." and ".$return_array["tend"];
                }else{
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $mysql_class FROM zprf_".$game_name.".stats_30min  order by timestamp desc limit 1";
                }

                $stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, NULL);
                if(isset($rows[0]) and isset($rows[0]) != NULL){
                        $this->status = 0;
                        foreach($rows as $val){
                                $timestamp = $val["timestamp"];
				foreach($instance_map as $key => $class){
					if(isset($val[$class]))
	                                $this->lastError[$key][$timestamp] =  $val[$class];
				}
                        }
                }
                else{
                        $this->status = 1;
                        $this->lastError = "Unable to get data from DB or invalid timestamp given.";
                }	
		
	}

	public function getPagetime($values,$game_name){
		if(is_numeric($game_name)){
                        include_once 'gameidMap.php';
                        $gidmap = getGameMapping($this->server_cfg);
                        if(isset($gidmap[$game_name]))
                                $game_name = $gidmap[$game_name];
                }
		$return_array = $this->parse_inputs_timestamp($values);
                if($return_array === false)
                        return;
		
		$default_pages=false;

		if(!isset($values["entity"]))
			$default_pages=true;
		else
			$return_array["entity"] = $this->parse_inputs_entity($values);

		if($default_pages){
			$query = "SELECT page FROM zprf_".$game_name.".apache_stats_30min WHERE char_length(page) < 63 AND RIGHT(page, 4) = '.php' AND timestamp > DATE_SUB(NOW(),INTERVAL 1 DAY) GROUP by page ORDER by SUM(count) DESC  LIMIT 15";
			$stmt = $this->prepare($query);
			$rows = $this->fetchAll($stmt, NULL);
			if(isset($rows[0]) and isset($rows[0]) != NULL){
				$this->status = 0;
				foreach($rows as $key => $page){
					$page_array[] = $rows[$key]["page"];
				}
			}
			else{
				$this->status = 1;
				$this->lastError = "Unable to get data from DB or invalid timestamp given.";
			}
			$page_list = implode(",",$page_array);
			$page_list = "'" . str_replace(",", "','", $page_list) . "'";
		}else{
			$query = "SELECT distinct page FROM zprf_".$game_name.".apache_stats_30min WHERE char_length(page) < 63 AND RIGHT(page, 4) = '.php' AND timestamp > DATE_SUB(NOW(),INTERVAL 1 DAY)";
                        $stmt = $this->prepare($query);
                        $rows = $this->fetchAll($stmt, NULL);
                        if(isset($rows[0]) and isset($rows[0]) != NULL){
                                $this->status = 0;
                                foreach($rows as $key => $page){
                                        $page_array[] = $rows[$key]["page"];
                                }
                        }
                        else{
                                $this->status = 1;
                                $this->lastError = "Unable to get 1 day page data from DB";
                        }
			$input_pages = explode(",",$return_array["entity"]);
			foreach($input_pages as $page){
				if(!in_array($page,$page_array)){
					$this->status = 1;
					$this->lastError = "Page $page not found.";
					return;
				}
			}
			$page_list = "'" . str_replace(",", "','", $return_array["entity"]) . "'";
		}
	

		if(isset($return_array["ts"])){
			$query = "SELECT unix_timestamp(timestamp) as timestamp,page,avg_load_time FROM zprf_".$game_name.".apache_stats_30min WHERE page in ($page_list) and unix_timestamp(timestamp)=".$return_array["ts"];
		}else if(isset($return_array["tstart"]) and isset($return_array["tend"])){
			$query = "SELECT unix_timestamp(timestamp) as timestamp,page,avg_load_time FROM zprf_".$game_name.".apache_stats_30min WHERE page in ($page_list) and unix_timestamp(timestamp) between ".$return_array["tstart"]." and ".$return_array["tend"];
		}else{
			$current_time = (int)(time()/1800) * 1800;
			
			$query = "SELECT unix_timestamp(timestamp) as timestamp,page,avg_load_time FROM zprf_".$game_name.".apache_stats_30min WHERE page in ($page_list) group by timestamp,page,avg_load_time order by timestamp desc limit ".count(explode(",",$page_list));
		}
		$stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, NULL);
		
		if(isset($rows[0]) and isset($rows[0]) != NULL){
                        $this->status = 0;
			$this->lastError = array();
                        foreach($rows as $key => $data){
                                $this->lastError[$data["page"]][$data["timestamp"]] = $data["avg_load_time"];
                        }
                }
                else{
                        $this->status = 1;
                        $this->lastError = "Unable to get data from DB or invalid timestamp given.";
                }
	}

	public function getTrackedFunction($values,$game_name){
		if(is_numeric($game_name)){
                        include_once 'gameidMap.php';
                        $gidmap = getGameMapping($this->server_cfg);
                        if(isset($gidmap[$game_name]))
                                $game_name = $gidmap[$game_name];
                }
                $return_array = $this->parse_inputs_timestamp($values);
                if($return_array === false)
                        return;

		
		$tracked_functions = array("MC::set",
						"MC::get",
						"ApcManager::get",
						"ApcManager::set",
						"serialize",
						"unserialize",
						"AMFBaseSerializer::serialize",
						"AMFBaseDeserializer::deserialize");

		$return_array["entity"] = $this->parse_inputs_entity($values);
                if($return_array["entity"] === false){
			$wrapped_functions = $this->wrap_escape($tracked_functions, "`");
                        $tracked = implode(",",$wrapped_functions);
                }	
		else{
			$wrapped_functions = $this->wrap_escape(explode(",",$return_array["entity"]),"`");
			$tracked = implode(",",$wrapped_functions);
		}
		if(isset($return_array["ts"])){
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $tracked FROM zprf_".$game_name.".tracked_functions_flip_incl_time where page='all' and unix_timestamp(timestamp) = ".$return_array['ts'];
                }else if(isset($return_array["tstart"]) and isset($return_array["tend"])){
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $tracked FROM zprf_".$game_name.".tracked_functions_flip_incl_time where page='all' and unix_timestamp(timestamp) between ".$return_array['tstart']." and ".$return_array["tend"];
                }else{
                        $query = "SELECT unix_timestamp(timestamp) as timestamp, $tracked FROM zprf_".$game_name.".tracked_functions_flip_incl_time where page='all' order by timestamp desc limit 1";
                }
		$stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, NULL);
                if(isset($rows[0]) and isset($rows[0]) != NULL){
                        $this->status = 0;
                        foreach($rows as $val){
                                $timestamp = $val["timestamp"];
                                foreach($tracked_functions as $key => $function){
                                        if(isset($val[$function]))
	                                        $this->lastError[$function][$timestamp] =  $val[$function];
                                }
                        }
                }
                else{
                        $this->status = 1;
                        $this->lastError = "Unable to get data from DB or invalid timestamp given.";
                }
	}

	public function wrap_escape($vector, $escape_char)
	{
	    $new_vector = array();
	    foreach ($vector as $key => $item)
	    {
		$new_vector[] = $escape_char . $item . $escape_char;
	    }

	    return $new_vector;
	}

	public function parse_inputs_entity($values) {
                if( !isset($values['entity']) ) {
                	return false;
		}

		return($values['entity']);

	}

	public function parse_inputs_timestamp($values) {
                if( isset($values['ts']) and (isset($values['tstart']) or isset($values['tend']))) {
			$this->status = 1;
                        $this->lastError = 'You cannot give ts with tstart and tend parameters';
                        return false;                        
                }

                if( isset($values['ts']) && !is_int(intval($values['ts'])) ){
                        $wrong_inputs[] = 'ts';
                }

                if( isset($values['tstart']) && !is_int(intval($values['tstart'])) ){
                        $wrong_inputs[] = 'tstart';
                }
		if( isset($values['tend']) && !is_int(intval($values['tend'])) ){
                        $wrong_inputs[] = 'tend';
                }

                if( count($wrong_inputs) >0 ){
                        $this->status = 1;
                        $this->lastError = 'Some required paramters are of wrong type in the input : ' . implode(',', $wrong_inputs);
                        return false;
                }

		if((isset($values['tstart']) && !isset($values['tend'])) or (!isset($values['tstart']) && isset($values['tend']))){
			$this->status = 1;
			$this->lastError = 'You cannot give tstart or tend alone';
			return false;
		}
	        $return_array = array();
		if(isset($values['ts']))
	                $return_array['ts'] = $values['ts'];
		else if(isset($values['tstart']) and isset($values['tend'])){
			$return_array['tstart'] = $values['tstart'];
			$return_array['tend'] = $values['tend'];
		}
		if(isset($values['array']))
			$return_array['array'] = $values['array'];
		return $return_array;

	}

	function get_util($utilArr) {
            $ret = NA;
            if($utilArr !== null) {
                    $utilKey = $utilArr["name"];
                    $utilVal = min(100, round($utilArr["utilization"]));
                    $ret = "$utilKey: $utilVal%";
            }
            return $ret;
    	}

    	function get_headroom($util) {
            return 100 - round($util);
    	}
		
	public function computeSlack($values){
		
		include_once "../report/instance-util-adapter.php";
		include_once "../report/report-collector.php";

		$return_array = $this->parse_inputs_computeSlack($values);
		if( !$return_array ){
			return false;
		}

		$game = $return_array['game_name'];
		if (!in_array($game, $this->server_cfg['game_list'])){
			$this->status = 5;
			return false;
		}

		$dataArr = array();

		$slack_now = $return_array['slack_now'];
		$instance_util_obj = new InstanceUtilAdapter($game,$slack_now);
		$instances = $instance_util_obj->get_instances_breakup_data();
		$dau = $instance_util_obj->get_dau();
//		$class_data = $instances["web"];
			
		$pool_slack = array();
		foreach($instances as $class => $class_data) {
			foreach($class_data as $pool => $data) {
				if($data["count"] == null || $data["count"] == 0) { //if instance count is 0, don't show in report
					continue;
				}
				$dau_per_instance = round($dau/$data["count"]);
				$util = $this->get_util($data["util"]);
				$bottleneck = $data["util"]["bottleneck_key"];
				$underutilized = $data["util"]["underutil_key"];
				if($bottleneck == "") {
					$bottleneck = "-";
				}
				if($underutilized == "") {
					$underutilized = "-";
				}

				if($util == NA) {
					$optimal_count = NA;
					$optimal_cost = NA;
					$optimal_cost_year = NA;
					$slack = NA;
					$headroom = NA;
				} else {
					$optimal_count = $data["optimal_instance_count"];
					$optimal_count_factor = $data["optimal_count_factor"];
					$optimal_cost = number_format($data["optimal_cost"]);
					$optimal_cost_year = number_format($data["optimal_cost"] * 30 * 365);
					$slack = number_format((($data["count"] - $data["optimal_instance_count"]) * 100)/$data["count"], 2,'.','')."%";
					$headroom = $this->get_headroom($data["util"]["utilization"])."%";
				}
				$pool_slack[$pool]["slack"] = $slack;
				$pool_slack[$pool]["count"] = $data["count"];
				$pool_slack[$pool]["optimal_instance_count"] = $optimal_count;
				$pool_slack[$pool]["optimal_count_factor"] = $optimal_count_factor;
			}
		}	
		$pool_slack = json_encode($pool_slack);

		echo $pool_slack."\n";
		$this->status = 6; 
	}
	
	public function parse_inputs_computeSlack($values){
		$possible_slack_now = array("true","false");
		$missing_input = array();
		if( !isset($values['game_name']) ) {
			$missing_input[] = 'game_name';
		}
		
		if( count($missing_input) >0 ){
			$this->status = 1;
			$this->lastError = 'Some required paramters are missing in the input : ' . implode(',', $missing_input);
			return false;
		}
		
		$wrong_inputs = array();
		if( !is_string($values['game_name']) ){
			$wrong_inputs[] = 'game_name';
		}
		if( isset($values['slack_now']) && !in_array(strtolower($values['slack_now']), $possible_slack_now)) {             
                        $wrong_inputs[] = 'slack_now';                                                                      
        }
		if( count($wrong_inputs) >0 ){
			$this->status = 1;
			$this->lastError = 'Some required paramters are of wrong type in the input : ' . implode(',', $wrong_inputs);
			return false;
		}
		
		$return_array = array();
		$return_array['game_name'] = $values['game_name'];
		if( isset($values['slack_now']) ){                                                                     
            $return_array['slack_now'] = $values['slack_now'] == "true"?true:false;
		}else{
			$return_array['slack_now'] = true;
		}
		return $return_array;
	}
	
	
	/* 
	 * Function to add game in zperfmon
	 * Gearman should be initailized before it can be used 
	 * Parameters : parameters required value array with fields game_name, gid, deploy_id
	 * Optional parameters : cloud_name in value array
	 * External scripts called : /usr/local/bin/zperfmon-add-game-automate 
	 */
	public function addGame($values){

		$this->prepare_gearmanClient();
		$return_array = $this->parse_inputs_add($values);
		
		$game = $return_array['game_name'];
		if ( in_array($game, $this->server_cfg['game_list'])){
			$this->status = 3;
			return false;
		}

		else {
			if ( !isset ( $return_array['cloud_name'])){
				$return_array['cloud_name'] = 'zcloud';	
			}
			if ( !isset ( $return_array['zrt_game_name'])){
				$return_array['zrt_game_name'] = $game;
			}
			if ( !isset ( $return_array['env'])){
				$return_array['env'] = 'dev';	
			}
			if ( !isset ( $return_array['auto_ini'])){                                                    
                                $return_array['auto_ini'] = 0;                                                                            }
			$cmd = "sudo /usr/local/bin/zperfmon-add-game-automate -g {$return_array['game_name']} -gid {$return_array['gid']} -c {$return_array['cloud_name']} -did {$return_array['deploy_id']}  -env {$return_array['env']}  -zrt {$return_array['zrt_game_name']} -ini {$return_array['auto_ini']}";
			$result = $this->client->do("shell_execute", $cmd);
		}
		//New code added to restart the server after adding/deleting a game
                $this->client->do("shell_execute", "sudo /etc/init.d/httpd graceful");	
		if ($result){
			if ( strpos($result, 'failed') == true){
				$this->send_mail('Game addition failed with parameters ' . print_r($values, true).'\n '.$result);
				$this->lastError = 'Game addition failed with parameters ' . print_r($values, true).'\n '.$result;
				return false;
			}
			else {
				error_log('Game added succesfully with parameters ' . print_r($values, true) ,3 , '/var/log/add-game.log');
				$this->send_mail('Game added succesfully with parameters ' . print_r($values, true));
				return true;
			}
		}
		else{
			$this->status = 4;
			$this->lastError = 'zPerfmon error , try again later.(Gearman error)';
			return false;
		}
	}

	/* 
	 * Function to Send mail
	 * Params : info ( string ) containg info to be send in mail
	 *
	 */
	public function send_mail($info)
	{
		$to = "";
		$bcc = $this->server_cfg["zperfmon_add_game_mail_recipients"];
		$subject = "zperfmon game automation";
		$headers = 'From: '.$this->server_cfg["sender"] . "\r\n" .
			'Bcc: ' . $bcc . "\r\n" .
			'Content-Type: text/HTML' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
		$message = "<br /> ".$info;
		mail($to, $subject, $message, $headers);
	}
        
	
	public function parse_inputs_add($values) {
		$possible_env = array("dev","prod","stage");
		$possible_ini = array("true","false");
		$missing_input = array();
		if( !isset($values['game_name']) ) {
			$missing_input[] = 'game_name';
		}
		if( !isset($values['gid']) ) {
			$missing_input[] = 'gid';
		}
		if( !isset($values['deploy_id']) ) {
			$missing_input[] = 'deploy_id';
		}
		
		if( count($missing_input) >0 ){
			$this->status = 1;
			$this->lastError = 'Some required paramters are missing in the input : ' . implode(',', $missing_input);
			return false;
		}
		
		$wrong_inputs = array();
		if( !is_string($values['game_name']) ){
			$wrong_inputs[] = 'game_name';
		}
		
		if( !is_int(intval($values['gid'])) ){
			$wrong_inputs[] = 'gid';
		}
		
		if( !is_int(intval($values['deploy_id'])) ){
			$wrong_inputs[] = 'deploy_id';
		}               
		
		if( isset($values['cloud_name']) && !is_int(intval($values['cloud_name'])) ){
			$wrong_inputs[] = 'cloud_name';
		}
		if( isset($values['zrt_game_name']) && !is_string($values['zrt_game_name']) ){
			//TODO: validate the game name
			$wrong_inputs[] = 'zrt_game_name';
		}
		if( isset($values['env']) && !in_array(strtolower($values['env']), $possible_env)) {
			$wrong_inputs[] = 'env';
		}
		if( isset($values['auto_ini']) && !in_array(strtolower($values['auto_ini']), $possible_ini)) {             
                        $wrong_inputs[] = 'auto_ini';                                                                      
                }
		if( count($wrong_inputs) >0 ){
			$this->status = 1;
			$this->lastError = 'Some required paramters are of wrong type in the input : ' . implode(',', $wrong_inputs);
			return false;
		}
		
		$return_array = array();
		$return_array['game_name'] = $values['game_name'];
		$return_array['gid'] = $values['gid'];
		$return_array['deploy_id'] = $values['deploy_id'];
		if( isset($values['cloud_name']) ){
			$return_array['cloud_name'] = $values['cloud_name'];
		}
		if( isset($values['zrt_game_name']) ){
			$return_array['zrt_game_name'] = $values['zrt_game_name'];
		}
		if( isset($values['env']) ){
			$return_array['env'] = $values['env'];
		}
		if( isset($values['auto_ini']) ){                                                                     
                        $return_array['auto_ini'] = $values['auto_ini'] == "true"?1:0;                                              		      }	
		return $return_array;
	}
	
		
	/* 
	 * Function to delete game in zperfmon
	 * Gearman should be initailized before it can be used 
	 * Parameters : parameters required value array with fields game_name
	 * External scripts called : /usr/local/bin/zperfmon-delete-game
	 */
	public function deleteGame($values) {
		$this->prepare_gearmanClient();
		$return_array = $this->parse_inputs_delete($values);
		if( !$return_array ){
			return false;
		}
		
		$game = $return_array['game_name'];
		if ( ! in_array($game, $this->server_cfg['game_list'])){
			$this->status = 3;
			$this->lastError = 'Game '.$return_array['game_name'].' does not exist';
			return false;
		}
		else {
			$result = $this->client->do("shell_execute", "sudo /usr/local/bin/zperfmon-delete-game -g {$return_array['game_name']}");
		}		
		//New code added to restart the server after adding/deleting a game
                $this->client->do("shell_execute", "sudo /etc/init.d/httpd graceful");	
		if ( $result){
			if ( strpos($result, 'failed') == true){
				$this->send_mail('Game deletion failed with parameters ' . print_r($values, true).'\n '.$result);
				$this->lastError = 'Game deleted failed with parameters ' . print_r($values, true).'\n '.$result;
				return false;
			}
			else {
				error_log('Game deleted succesfully with parameters ' . print_r($values, true) ,3 , '/var/log/delete-game.log');
				$this->send_mail('Game deleted succesfully with parameters ' . print_r($values, true));
				$this->lastError = 'Game deleted Successfully';
				return true;
			}
		}
		else{
			$this->status = 4;
			$this->lastError = 'zPerfmon error , try again later.(Gearman error)';
			return false;
		}
	}
	
	
	public function parse_inputs_delete($values){

		$missing_input = array();
		if( !isset($values['game_name']) ) {
			$missing_input[] = 'game_name';
		}

		if( count($missing_input) >0 ){
			$this->status = 1;
			$this->lastError = 'Some required paramters are missing in the input : ' . implode(',', $missing_input);
			return false;
		}
		
		$wrong_inputs = array();
		if( !is_string($values['game_name']) ){
			$wrong_inputs[] = 'game_name';
		}
		
		if( count($wrong_inputs) >0 ){
			$this->status = 1;
			$this->lastError = 'Some required paramters are of wrong type in the input : ' . implode(',', $wrong_inputs);
			return false;
		}
		
		return array('game_name' => $values['game_name']);                
	}

	/* 
	 * Function to prepare Gearman Client
	 */
	public function prepare_gearmanClient(){
		# Create our client object.
		$this->client= new GearmanClient();

		# Add default server (localhost).
		$this->client->addServer('localhost', 4730);
	}
	
}
