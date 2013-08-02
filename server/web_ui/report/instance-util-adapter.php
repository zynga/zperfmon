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


/**
* zPerfmon: Instances Email Report Adapter
* @authors 	Ujwalendu Prakash (uprakash@zynga.com)
*			Saurabh Odhyan (sodhyan@zynga.com)
*/

include_once "PDOAdapter.php";
//include_once "spyc.php";
include_once 'game_config.php';
include_once 'server.cfg';
include_once 'dau-collector.php';
include_once 'instance-eu-adapter.php';
include_once 'yml_conf.inc.php';

class InstanceUtilization extends PDOAdapter {

	private $server_cfg;
	private $rs_cfg;
	private $host_cfg;
	
	public function __construct($server_cfg) {

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


	public function get_max_timestamp($deploy_id) {

		$params = array();
		$query = "SELECT MAX(timestamp) AS timestamp FROM instances where deploy_id=$deploy_id";
		$stmt = $this->prepare($query);
		$rows = $this->fetchAll($stmt, $params);

		$max_timestamp = $rows[0]['timestamp'];
		return $max_timestamp;
	}

	public function get_matching_instances($deploy_id, $timestamp, $regexp, $invert=False) {
                if ($invert === true) {
                        $invert = "NOT";
                } else {
                        $invert = "";
                }

                $query = "select hostname from instances
                         where timestamp='$timestamp' and deploy_id={$deploy_id} and hostname $invert RLIKE '$regexp'";
                $stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, $params);

                $host_list = array();
                foreach ($rows as $host_struct) {
                        $host_list[] = $host_struct['hostname'];
                }

                return $host_list;
        }

	public function get_game_detail($config, $deploy_id, $timestamp) {

		$params = array();
		$detail = array();
		$overlaid_groups = array();
		$rejects = "";

		foreach ($config as $name=>$conf) {
			if(!isset($conf['hostgroup'])) {
				continue;
			}

			if ($rejects == "") {
                                $rejects = $name;
                        } else {
                                $rejects .= ("|" . $name);
                        }

			$like = str_replace(".*", "%", $name);
			$not_like = "";
			$pool = str_replace(".*","", $name);

			if (isset($conf['overlay'])) {
				$overlaid_groups[$pool] = $conf;
				// Until overlays are fixed properly we won't continue here
			}

			if(isset($conf['hostname_like'])) {
				//until we have a fix hostgroup corresponding to each hostname
				$like = $conf['hostname_like'];
				$rej =  str_replace("%", ".*", $like);
                                $rejects .= ("|" .$rej);
			}
			if(isset($conf['hostname_not_like'])) {
				//until we have a fix hostgroup corresponding to each hostname
				$not_like = $conf['hostname_not_like'];
			}

			$query = "select type, count(hostname) as count from instances
			          where deploy_id={$deploy_id} and hostname like '$like'
				  and hostname not like '$not_like' and status!='STOPPED' 
				  group by type order by count desc limit 1";
			$stmt = $this->prepare($query);
			$rows = $this->fetchAll($stmt, $params);
			
			$tmp = array();
			foreach($rows as $row) {
				$group_instances += $row['count'];
				$tmp['count'] = $row['count'];
				$tmp['type'] = $row['type'];
				$tmp['class'] = $conf['class'];
				//$tmp['hostgroup'] = $conf['hostgroup'];
				$tmp['hostgroup'] = $pool;
			}
			
			$detail[$pool] = $tmp;
		}
		foreach($overlaid_groups as $name => $conf) {
			$detail[$name]['count'] = $detail[$conf['overlay']]['count'];
			$detail[$name]['class'] = $detail[$conf['overlay']]['class'];
			$detail[$name]['type'] = $detail[$conf['overlay']]['type'];
		}

		# Track instances which don't fall into any group
                $rejected_instances = $this->get_matching_instances(
                                        $deploy_id, $timestamp, $rejects, True);
                $detail["ungrouped"]['count'] = count($rejected_instances);
                $detail["ungrouped"]['class'] = "generic";
                $detail["ungrouped"]['type'] = "blended";
                $detail["ungrouped"]['instances'] = $rejected_instances;


		return $detail;
	}

	public function get_machine_count_and_type($timestamp) {


		$query = "select deploy_name,deploy_id, type, count(hostname) as 
		count from instances where timestamp='$timestamp' and status!='STOPPED' group by deploy_id, type";

		$params = array();
		$stmt = $this->prepare($query);
		$rows = $this->fetchAll($stmt, $params);

		//reformat the row
		$deployments = array();
		foreach ($rows as $row)  {
		
			$deploy_id = $row['deploy_id'];
			$deploy_name = $row['deploy_name'];
			$type = $row['type'];
			$count = $row['count'];

			if (!isset($deployments[$deploy_id])) {
				$deployments[$deploy_id] = array();
			}

			$deployments[$deploy_id]['name'] = $deploy_name;

			if ( !isset($deployments[$deploy_id]['types']) ) {
				$deployments[$deploy_id]['types'] = array();
			}

			$deployments[$deploy_id]['types'][$type] = $count;
		}
		return $deployments;
	}

	public function get_hosts_config($game_cfg) {

		/*
		$config_dir = sprintf($this->server_cfg['config_directory'],  
				$game_cfg['name']);
		*/

		$hostgroupConfigObj = new HostgroupConfig($this->server_cfg, $game_cfg['name']);
		/*$common_config = $this->server_cfg['common_config_file'];
        
	$game_config =  sprintf($this->server_cfg['hostgroups_config'], $game_cfg['name']);

		$str_yml = @file_get_contents($common_config);
		$str_yml .= @file_get_contents($game_config);

		$cfg = Spyc::YAMLLoad($str_yml);
		return $cfg;
		*/
		 return $hostgroupConfigObj->load_hostgroup_config();
	}

	public function get_nodetype_cost() {
		$query = "select * from nodetype";
		$stmt = $this->prepare($query);
		$params = array();
		$rows = $this->fetchAll($stmt, $params);
		return $rows;
	}
}

class InstanceUtilAdapter {
	
	public function __construct($game,$slack_now=false) {
		global $server_cfg;
		$this->server_cfg = $server_cfg;
		$this->instances_util_obj = new InstanceUtilization($server_cfg);
        $this->dauObj = new DAUAdapter();

		$this->game = $game;
		$this->instance_eu_obj = new EffectiveUtil($server_cfg, $game,$slack_now);

		$this->game_detail = $this->get_game_detail();
		$this->utilization = $this->instance_eu_obj->get_utilization();
		$this->rps = $this->instance_eu_obj->get_game_rps();
		$this->instances_breakup = $this->get_instances_breakup();
		$this->instances_detail = $this->get_instances_detail();
	}

	private function get_game_detail() {
		$game = $this->game;
		$game_cfg = load_game_config($game);
		$deployID = $game_cfg['deployIDs'][0];
		$gid = $game_cfg['gameid'];

		$ret = array();
		$ret['deploy_id'] = $deployID;
                $snid = isset($game_cfg['snid']) ? $game_cfg['snid'] : null;
                $cid = isset($game_cfg['cid']) ? $game_cfg['cid'] : null;
		$ret['dau'] = $this->dauObj->get_dau_day_boundry($gid, $snid, $cid);

		if(empty($deployID)) {
			return $ret;
		}

		$hosts_config = $this->instances_util_obj->get_hosts_config($game_cfg);
		$timestamp = $this->instances_util_obj->get_max_timestamp($deployID); // Temporary fix. Currently different deployments have different timestamps.
		$ret['deployment'] = $this->instances_util_obj->get_game_detail($hosts_config, $deployID, $timestamp);

		return $ret;
	}

	private function get_games_detail() {
		$games = $this->server_cfg['game_list'];

		$ret = array();

		foreach ($games as $game) {
			$this->game = $game;
			$ret[$game] = $this->get_game_detail();
		}

		return $ret;
	}

	private function get_nodetype_cost() {
		$rows = $this->instances_util_obj->get_nodetype_cost();
		$ret = array();
		for($i = 0; $i < sizeof($rows); $i++) {
			$one_time_cost = $rows[$i]["one_time_cost"];
			$one_time_cost_per_hour = $one_time_cost/(3 * 365 * 24); //one time cost is for a period of 3 years
			$ret[$rows[$i]["type"]] = $rows[$i]["cost_per_hour"] + $one_time_cost_per_hour;
		}
		return $ret;
	}

	private function get_instances_detail() {
		$dataArr = $this->game_detail;
		$dataArr = $dataArr["deployment"];
		
		$nodetype_cost = $this->get_nodetype_cost();
	
		$instances_breakup = $this->instances_breakup;
		
		$instances = array();
		foreach($dataArr as $pool => $data) {
			$type = $data["type"];
			$class = $data["class"];
			$count = $data["count"];
			$cost = $count * $nodetype_cost[$type] * 24; //DB stores cost per hour

			if(!array_key_exists($class, $instances)) {
				$instances[$class] = array(
					"count" => 0,
					"cost" => 0,
					"optimal_instance_count" => 0,
				);
			}

			$instances[$class]["count"] += $count;
			$instances[$class]["cost"] += $cost;

			if($instances_breakup[$class][$pool]["optimal_instance_count"] == null) { //if data is not available, assume actual count
				$instances[$class]["optimal_instance_count"] += $instances_breakup[$class][$pool]["count"];
                        } else {
                                $instances[$class]["optimal_instance_count"] += $instances_breakup[$class][$pool]["optimal_instance_count"];
                        }
                }
                if (isset($instances["generic"])) {
                        $instances["generic"]["ungrouped"]["instances"] = $dataArr["ungrouped"]["instances"];
                }

		return $instances;
	}

	public function get_ungrouped_instances() {
                if (isset($this->game_detail["deployment"]['ungrouped'])) {
                        return $this->game_detail["deployment"]["ungrouped"]["instances"];
                } else {
                        return array();
                }
        }

	private function get_optimal_instance_count($count, $optimal_count_factor) {
		return ceil($count * $optimal_count_factor);
	}

	private function get_instances_breakup() {
		$dataArr = $this->game_detail;
		$dataArr = $dataArr["deployment"];
		$nodetype_cost = $this->get_nodetype_cost();
		
		$utilization = $this->utilization;
		//print_r($utilization);	
		$instances = array();
		foreach($dataArr as $pool => $data) {
			$class = $data["class"];
			$type = $data["type"];
			$total_cost = $nodetype_cost[$type] * $data["count"] * 24; //DB stores cost per hour
			//$optimal_instance_count = $this->get_optimal_instance_count($data["count"], $utilization[$class][$pool]); //instance count with optimal utilization
			//$optimal_cost = $nodetype_cost[$type] * $optimal_instance_count * 24;
			//$cost_per_user = $total_cost/$dau;

			if(isset($utilization[$class][$pool]["master"])) {
				$master = $utilization[$class][$pool]["master"];
				$util = $utilization[$class][$master];
				$optimal_count_factor = $utilization[$class][$master]["optimal_count_factor"];
			} else {
				$util = $utilization[$class][$pool];
				$optimal_count_factor = $utilization[$class][$pool]["optimal_count_factor"];
			}
			$optimal_instance_count = $this->get_optimal_instance_count($data["count"], $optimal_count_factor); //instance count with optimal utilization
			$optimal_cost = $nodetype_cost[$type] * $optimal_instance_count * 24;
			
			//echo "$pool: $count | $optimal_count_factor | $optimal_instance_count | $optimal_threshold <br>";

			$instances[$class][$pool] = array(
				"type" => $type,
				"hostgroup" => $data["hostgroup"],
				"count" => $data["count"],
				"cost" => $total_cost,
				"util" => $util,
				"optimal_instance_count" => $optimal_instance_count,
				"optimal_count_factor" => $optimal_count_factor,
				"optimal_cost" => $optimal_cost,
				"slack" => $slack,
			);
		}
		if (isset($instances["generic"])) {
                        $instances["generic"]["ungrouped"]["instances"] = $dataArr["ungrouped"]["instances"];
                }
		
		return $instances;
	}

	public function get_dau() {
		$dataArr = $this->game_detail;
        $dau = $dataArr["dau"][0]["dau"];
		return $dau;
	}

	public function get_instances_detail_data() {
		return $this->instances_detail;
	}

	public function get_instances_breakup_data() {
		return $this->instances_breakup;
	}

	public function get_instance_type_data() {
		$dataArr = $this->instances_breakup;		

		$ret = array();
		foreach($dataArr as $class => $class_data) {
			foreach($class_data as $pool => $data) {
				$type = $data["type"];
				
				if(!isset($ret[$type])) {
					$ret[$type] = array(
						"count" => 0,
						"optimal_instance_count" => 0,
					);
				}
				
				$ret[$type]["count"] += $data["count"];

				if($data["optimal_instance_count"] == null) { //if data is not available, assume actual count
                    $ret[$type]["optimal_instance_count"] += $data["count"];
                } else {
                    $ret[$type]["optimal_instance_count"] += $data["optimal_instance_count"];
                }
			}
		}

		return $ret;
	}

	public function get_game_summary() {
		$ret = array(
			"dau" => $this->get_dau(),
			"count" => 0,
			"cost" => 0,
			"optimal_instance_count" => 0,
		);

		$dataArr = $this->instances_breakup;

		foreach($dataArr as $class => $class_data) {
			foreach($class_data as $pool => $data) {
				$ret["count"] += $data["count"];
				$ret["cost"] += $data["cost"];
				if($data["optimal_instance_count"] == null) { //if data is not available, assume actual count
                    $ret["optimal_instance_count"] += $data["count"];
                } else {
                    $ret["optimal_instance_count"] += $data["optimal_instance_count"];
                }
			}
		}

		$ret["rps"] = $this->rps;

		return $ret;
	}
}
