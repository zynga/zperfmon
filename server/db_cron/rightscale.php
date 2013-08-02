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


include_once "PDOAdapter.php";
include_once "profilepie.inc.php";
include_once 'yml_conf.inc.php';
include_once "server.cfg";
include_once 'game_config.php';
//
// @class Rightscale
// This class provides helper methods to get array ids and machine ips for array split
// This class also has methods to get counts per class as well as per hostgroup(pool)
//
// @authors: Ujwalendu Prakash(uprakash@zynga.com)
//           Mahesh Gattani(mgattani@zynga.com)
//

function get_rs_config($rs_cof_file){

	$conf = parse_ini_file($rs_cof_file, true);	
	return $conf["DB"];	
}

function put_quotes($name) {

	return "'{$name}'";
}

class RightScale extends PDOAdapter
{

	private $game_cfg;
	private $server_cfg;
	function __construct($server_cfg, $game_cfg)
	{
		$conf = get_rs_config($server_cfg["rs_conf_file"]);
		$db_server = $conf["host"];
		$db_user = $conf["user"];
		$db_pass = $conf["password"];
		$db_name = $conf["database"];

		parent::__construct($db_server, $db_user, $db_pass, $db_name);

		$this->game_cfg = $game_cfg;
		$this->server_cfg = $server_cfg;
	}

	//given a array id, tells if the array game has been created before or not
        public function check_game_exist($arrayid) {

                $dir_base_path = sprintf($this->server_cfg["root_upload_directory"],$this->game_cfg["name"]);
                $dirpath = $dir_base_path."../../".$this->game_cfg["name"]."_".$arrayid;
                if ( !is_dir($dirpath) ) {
                        return false;
                }
                return true;
        }

	//function to make sudo games for each array id. The array ids are fetched from rightscale database.
        public function make_array_games() {
		$deploy_array = $this->game_cfg["deployIDs"];
		$deploy_id = $deploy_array[0];
		$hostConfigObj = new HostgroupConfig($this->server_cfg,$this->game_cfg["name"]);
		$hostgroup_config = $hostConfigObj->load_hostgroup_config();
		$class_hostgroups = $hostConfigObj->get_hostgroup_per_class($hostgroup_config);

		$array_names_str = implode(",", array_map("put_quotes", $class_hostgroups['web']));

		$query = "select distinct(array_id) from instances where deploy_id = (:deploy_id) and hostgroup in ( {$array_names_str} ) ";

                $parameters = array(
                        "deploy_id" => array($deploy_id, PDO::PARAM_INT),
            	);

                $stmt = $this->prepare($query);

                $rows = $this->fetchAll($stmt, $parameters);
		$arrays = array();
		foreach ($rows as $row)
		{	
                        $database_check = 0;
			$conf = get_rs_config($this->server_cfg["rs_conf_file"]);
                	$db_server = $conf["host"];
               		$db_user = $conf["user"];
                	$db_pass = $conf["password"];

			$db_name = "zprf_" . $this->game_cfg["name"] . "_" . $row["array_id"];
			try {
				//
				// Information about all databases are stored in information_schema under schemata table
				// A database name is stored under schema_name column of schemata table.
				//
				$pdo = new PDO( "mysql:host={$db_server};dbname=information_schema",$db_user, $db_pass);
				$db_query = "select schema_name from schemata where schema_name='{$db_name}'";
				
				$stmt = $pdo->prepare($db_query);
				$stmt->execute();
				$db_name = $stmt->fetchAll(PDO::FETCH_ASSOC);

				if(empty($db_name)) {
						
					echo "Database for " . $this->game_cfg["name"] . "_" . $row["array_id"]. "Does not exists\n";
					$database_check = 1;
				}
			} catch (Exception $e) {
                                   error_log("Database doesnt exist for array game $db_name\n", 3, sprintf($this->server_cfg['log_file'], $this->game_cfg['name']));
			}
			
			$retval = null;
			if(!$this->check_game_exist($row["array_id"]) or $database_check==1){
				if ( $row["array_id"] != "" ){
					$cmd = "yes | /usr/local/bin/zperfmon-add-game ".$this->game_cfg["name"]." ".$row["array_id"] . " 2>&1 > /tmp/split-log &";
				}
				$output = exec($cmd, $retval);

				array_push($arrays, $row["array_id"]);
				if($retval != 0){
					error_log("Couldn`t  make game with  $cmd\n$retval\n$output\n", 3, sprintf($this->server_cfg['log_file'], $this->game_cfg['name']));                        
					continue;
				}
			}
        	}
	}
	
	//reads the hostgroups.yml file to get the array  ids we want to process. 
	public function get_arrays_to_serve(){

                $deploy_array = $this->game_cfg["deployIDs"];
                $deploy_id = $deploy_array[0];
	
		$hostConfigObj = new HostgroupConfig($this->server_cfg,$this->game_cfg["name"]);
		$cfg = $hostConfigObj->load_hostgroup_config();
		
		$web_array = $hostConfigObj->get_web_array_name($cfg);
		$array_names_str = implode(",", array_map("put_quotes", $web_array));

                $query = "select distinct(array_id) from instances where deploy_id = (:deploy_id) and hostgroup in ( {$array_names_str} ) ";
	
                $parameters = array(
                        "deploy_id" => array($deploy_id, PDO::PARAM_INT),
                );
                $stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, $parameters);
	
		//print_r($rows);	
		$output = array();
		foreach ( $rows as $arr ) {
			array_push($output, $arr["array_id"]);
		}

		return $output;	
	}

	//
	// @return: array('array_id'=> <array_name>)
	//
	public function get_array_id_name() {

		$deploy_array = $this->game_cfg["deployIDs"];
		$deploy_id = $deploy_array[0];

		if(!isset($this->game_cfg['id'])) { 
			// This is called for a game
			$query = "select distinct hostgroup, array_id from instances where deploy_id = 
			  	  $deploy_id and array_id != 0";
		} else {
			// This is called for a particular array game			
			$array_id = $this->game_cfg['id'];	
			$query = "select distinct hostgroup, array_id from instances where deploy_id = 
				  $deploy_id and array_id = $array_id";
		}

		$stmt = $this->prepare($query);
		$rows = $this->fetchAll($stmt, array());
		$ret = array();
		foreach($rows as $row){
			$ret[$row['array_id']] = $row['hostgroup'];
		}
		return $ret;	
	}
	
        //reads the hostgroups.yml file to get the array  ids we want to process. 
        public function get_array_to_arrayid_mapping(){

                $deploy_array = $this->game_cfg["deployIDs"];
                $deploy_id = $deploy_array[0];

		$hostConfigObj = new HostgroupConfig($this->server_cfg,$this->game_cfg["name"]);
                $cfg = $hostConfigObj->load_hostgroup_config();
		$class_hostgroups = $hostConfigObj->get_hostgroup_per_class($cfg);
                $servers = array();
                $servers = $class_hostgroups['web'];

		$query = "select distinct array_name ,hostgroup,array_id from instances where deploy_id = (:deploy_id) and array_id != 0";

                $parameters = array(
                        "deploy_id" => array($deploy_id, PDO::PARAM_INT),
                );
                $stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, $parameters);

		$output = array();
                foreach ( $rows as $arr ) {
                        if(in_array($arr["hostgroup"],$servers)){
                                $output[$arr["array_name"]] = $arr["array_id"];
                        }
                }

		return $output;
        }


	//Outputs the array id to rivate ip mapping of all the arrays. Fetches it from rightscale database.
	public function get_array_ip_mapping() {

                $deploy_array = $this->game_cfg["deployIDs"];
                $deploy_id = $deploy_array[0];
		$hostConfigObj = new HostgroupConfig($this->server_cfg,$this->game_cfg["name"]);
		$hostgroup_config = $hostConfigObj->load_hostgroup_config();
		$class_hostgroups = $hostConfigObj->get_hostgroup_per_class($hostgroup_config);
		$array_names_str = implode(",", array_map("put_quotes", $class_hostgroups['web']));
                $query = "select distinct array_id, private_ip from instances where deploy_id = (:deploy_id) and hostgroup in ( {$array_names_str} )";
		$parameters = array(
                        "deploy_id" => array($deploy_id, PDO::PARAM_INT),
                 );

                $stmt = $this->prepare($query);

                $rows = $this->fetchAll($stmt, $parameters);
		
		$mapping = array();
		foreach ($rows as $array){
		$mapping[$array["private_ip"]] = $array["array_id"];
		}

		return $mapping;
	}	

	// method to get host counts per array
	// @param: array_id
	public function get_host_count_per_pool($array_id, $deploy_id) {

		$query = "select count(hostname) as count from instances where deploy_id = (:deploy_id) and array_id = (:array_id)";

		$parameters = array(
				"deploy_id" => array($deploy_id, PDO::PARAM_INT),
				"array_id" => array($array_id, PDO::PARAM_INT)
				);

		$stmt = $this->prepare($query);

		$rows = $this->fetchAll($stmt, $parameters);

		return $rows[0]['count'];
	}

	//
	// method to get host counts per class
	// @param: deploy_id
	// @param: game_name
	// @return : array of hosts classes with the counts of hosts of each hostgroups of the class
	//
	public function get_host_count_per_class($deploy_id, $game_name) {
		
		$hostConfigObj = new HostgroupConfig($this->server_cfg, $game_name);

		$hostgroup_config = $hostConfigObj->load_hostgroup_config();

		$params = array();
		$return = array();

		foreach ($hostgroup_config as $name=>$conf) {

			if(!isset($conf['class'])) {
				continue;
			}

			$like = str_replace(".*", "%", $name);
			$pool = str_replace(".*","", $name);
			
			if(isset($conf['hostname_like'])) {
				//until we have a fix hostgroup corresponding to each hostname
				$like = $conf['hostname_like'];
			}
			if(isset($conf['hostname_not_like'])) {
				//until we have a fix hostgroup corresponding to each hostname
				$not_like = $conf['hostname_not_like'];
			}

			$query = @"select  count(hostname) as count from instances where hostname like '$like' and 
					hostname not like '$not_like'  and hostname not like '%bad' and status!='STOPPED' and deploy_id=$deploy_id"; 
			
			$stmt = $this->prepare($query);
			$rows = $this->fetchAll($stmt, $params);
			
			$tmp = array();
			if(!isset($return[$conf['class']])) {
				$return[$conf['class']] = array();
			}
			
			$return[$conf['class']][$pool] = $rows[0]['count'];
	
		}
		foreach($return as $class=>$pool) {
			$total = 0;
			foreach($pool as $name=>$count) {
				$total += $count;
			}
			$return[$class]['total'] = $total;
		}
		
		return $return;
	}
}

?>
