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
	author : @uprakash
*/

include_once 'PDOAdapter.php';
include_once 'server.cfg';
//include_once 'spyc.php';
include_once 'game_config.php';
include_once 'yml_conf.inc.php'; // yaml config parser class

function get_rs_cfg ($rs_conf_file) {

	$config = parse_ini_file($rs_conf_file, true);
	return array(
			"db_host" => $config["DB"]["host"],
			"db_user" => $config["DB"]["user"],
			"db_pass" => $config["DB"]["password"],
			"db_name" => $config['DB']["database"],
		    );
}


function get_eu_cfg($eu_conf_file)
{
	$config = parse_ini_file($eu_conf_file, true);
	return array(	
		"db_host" => $config["DB"]["host"],
		"db_user" => $config["DB"]["user"],
		"db_pass" => $config["DB"]["password"],
		"db_name" => $config['DB']["database"],
		"table" => $config['DB']["eu_table"]
		);
}

/* // Obsolete now please delete after Testing
function load_config($str_yaml) {

		return Spyc::YAMLLoad($str_yaml);
}
*/

class Rightscale extends PDOAdapter {

	public function __construct($server_cfg, $game)
	{
		$cfg = get_rs_cfg($server_cfg['rs_conf_file']);
		$db_server = $cfg["db_host"];
		$db_user = $cfg["db_user"];
		$db_pass = $cfg["db_pass"];
		$db_name = $cfg["db_name"];
		$this->rs_cfg = $cfg;	
		$this->game_cfg = load_game_config($game);
		parent::__construct($db_server, $db_user, $db_pass, $db_name);
	}

	public function get_value($query) {

		$stmt = $this->prepare($query);
		$rows = $this->fetchAll($stmt, array());
		return $rows;
	}
}

class EffectiveUtil extends PDOAdapter {

	private $game;
	private $max_metric = array();
	
	// Utilization constants
	const UNDER_UTIL = "under utilized";
	const OVER_UTIL = "over utilized";
	public function __construct($server_cfg, $game,$slack_now=false)
	{
		$cfg = get_eu_cfg($server_cfg['eu_conf_file']);
		$db_server = $cfg["db_host"];
		$db_user = $cfg["db_user"];
		$db_pass = $cfg["db_pass"];
		$db_name = $cfg["db_name"];
		$this->table = $cfg["table"];
		$this->game = $game;
		$this->game_cfg = load_game_config($game);
		$this->server_cfg = $server_cfg;
		parent::__construct($db_server, $db_user, $db_pass, $db_name);
		$this->max_metric = $this->get_max_metric($slack_now);
		$this->rs = new Rightscale($server_cfg, $game);
	}

	public function get_max_metric($slack_now=false) {

		$ret = array();
		if($slack_now){
			if(!isset($this->game_cfg["zmon_url"]) or $this->game_cfg["zmon_url"] == ""){
				exit;
			}
			$zmon_url = $this->game_cfg["zmon_url"];
			$url = sprintf($this->server_cfg["zmon_fetch_data_url"],$zmon_url);
			$options = array (CURLOPT_RETURNTRANSFER => true, // return web page
							CURLOPT_HEADER => false, // don't return headers
							CURLOPT_FOLLOWLOCATION => true, // follow redirects
							CURLOPT_ENCODING => "", // handle compressed
							CURLOPT_USERAGENT => "zperfmon", // who am i
							CURLOPT_AUTOREFERER => true, // set referer on redirect
							CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
							CURLOPT_TIMEOUT => 120, // timeout on response
							CURLOPT_MAXREDIRS => 10 ); // stop after 10 redirects
								
			$ch = curl_init ( $url );
			curl_setopt_array ( $ch, $options );
			$content = curl_exec ( $ch );
			$err = curl_errno ( $ch );
			$errmsg = curl_error ( $ch );
			$header = curl_getinfo ( $ch );
			$httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
			curl_close ( $ch );

			if($httpCode!=200){
				die("HTTP Error Code : ".$httpCode."\nCURL Error : $errmsg\n");
			}

			$content = trim($content);
			$content = explode("\n",$content);
			$rows = array();
			foreach($content as $key => $value){
				$content[$key] = explode(",",$value);
				$rows[$key]["game"] = $content[$key][1];
				$rows[$key]["class"] = $content[$key][2];
				$rows[$key]["hostgroup"] = $content[$key][3];
				$rows[$key]["plugin"] = $content[$key][4];
				$rows[$key]["metric"] = $content[$key][5];
				$rows[$key]["max_value"] = $rows[$key]["min_value"]  = $content[$key][6];
			}
		}else{
			$query = "select game, class, hostgroup, plugin, metric, max(value) as max_value, min(value) as min_value from ". $this->table .
			  " where timestamp > now() - interval 1 day and game='$this->game'
			  group by game, class, hostgroup, metric, plugin";
			$stmt = $this->prepare($query);
			$rows = $this->fetchAll($stmt, array());
		}
		
		if(empty($rows)) {
			return null;
		}

		foreach($rows as $row) {
			$name = $row['class'].":".$row['hostgroup'].":".$row['plugin'].":".$row['metric'].":max";
			$ret[$name] = $row['max_value'];
			$name = $row['class'].":".$row['hostgroup'].":".$row['plugin'].":".$row['metric'].":min";
			$ret[$name] = $row['min_value'];
		}
	        return $ret;
	}

	public function get_configs() {
		
		$hostgroupConfigObj = new HostgroupConfig($this->server_cfg, $this->game);
		//$common_config = $this->server_cfg['common_config_file'];	
		//$hostgroup_config = sprintf($this->server_cfg['hostgroups_config'], $this->game);

		//$str_yaml = @file_get_contents($common_config);
		//$str_yaml .= @file_get_contents($hostgroup_config);

		//$config = load_config($str_yaml);
		
		//return $config;
		return $hostgroupConfigObj->load_hostgroup_config(); 
	}
	
		
	//
	// For each hostgroup defined for this game, calculate
	// utilization and return the result as a hash like:
	// <hostgroup> => utilization
	//
	/*
	   [db] => Array
	   (
	           [city-db-a] => Array
		   (
		           // new structure
		   	   [name] =>
			   [slack] => 
			   [utilization] =>
			   [underutilized_by] =>
			   [overutilized_by] =>

			   // earlier structure
			   [md0_ops_write] => 61.104991375151
		   )
	   )
	*/
	public function get_utilization() {

		$configs = $this->get_configs();
		$utilization = array();
		$overlaid_groups = array();
		// 
		// for each hostgroup call utilization calculator
		foreach ($configs as $hostgroup_name=>$hostgroup_cfg) {
			
			$hostgroup_name = str_replace('.*','',$hostgroup_name); // 'city-db-a-.*' to 'city-db-a'
			// calculate utilization only for those hostgroup which is a part of 
			// any class or hostgroup
			if(!isset($hostgroup_cfg['hostgroup']) || !isset($hostgroup_cfg['class'])) {
				continue;
			}
			
			if (isset($hostgroup_cfg['overlay'])) {
				$overlaid_groups[$hostgroup_name] = $hostgroup_cfg;
				continue;
			}

			$tmp = $this->calculate_utilization($hostgroup_cfg, $hostgroup_name);
			if(!empty($tmp)) {
				 $utilization[$hostgroup_cfg['class']][$hostgroup_name] = $tmp;	
			}
		}
//		var_dump($utilization);
		foreach($overlaid_groups as $hostgroup_name => $hostgroup_cfg) {

			$class = $hostgroup_cfg['class'];
			//$overlay_group = $hostgroup_cfg['overlay'];
			$master_group = $hostgroup_cfg['overlay'];
			//$utilization[$class][$hostgroup_name] = $utilization[$class][$overlay_group];
			$utilization[$class][$hostgroup_name]['master'] = $master_group;
		}
		return $utilization;
		//print_r($utilization);
	}

	//
	// For each metric in the hostgroup calculate utilization and
	// return whatever is the maximum.
	//
	public function calculate_utilization($hostgroup, $hostgroup_name) {
		$metric_utilizations = array();
		$class_name = $hostgroup['class'];
		foreach ($hostgroup as $metric_name=>$metric) {

			if(!is_array($metric) || !isset($metric['max_threshold'])) {
				continue;
			}

			$metric_utilizations[$metric_name] =
				$this->get_metric_utlization($metric, $hostgroup_name, $class_name);
		}
		//print_r($metric_utilizations);
		return $this->get_max_utils($metric_utilizations);
	}


	public function get_metric_utlization($metric, $hostgroup_name, $class_name) {
		
		$util;
		$min_max = $this->get_min_max($hostgroup_name, $class_name,
				       $metric['vertica_plugin'], $metric['metric']);
		if ( $min_max == null ) {
			return null;
		}

		// TODO: For web servers free memory please take avg instead of min value.
                // As it depends on the slow start apache module. Once this is available we can do it.

		$min = $min_max['min'];
		$max = $min_max['max'];

		if(isset($metric['complement']) ) {

			$max_threshold = $metric['max_threshold'];
			$min_threshold = $metric['min_threshold'];
			$optimal_pct = $metric['optimal_pct'];
			$optimal_pct = $optimal_pct / 100;
			
			$optimal_threshold = $min_threshold + (($max_threshold - $min_threshold) * $optimal_pct); 
			$max_value = @$metric['max_value']; // metric's maximum possible value e.g for mem 7G

			//$value = $max; // value from DB
			if($metric['complement']) {
				$value = $max_value - $min;
			} else {
				$value = $max;
			}	

			$max_util = ($value / $max_threshold) * 100; 

			$bottleneck = $value - $optimal_threshold; // >0 overutilized
			$underutilized = $min_threshold - $value;// >0 underutilized
			

			$optimal_delta = $value - $optimal_threshold;
			$optimal_count_factor = 1 + ( $optimal_delta / $max_threshold );
		}
	
		return array('value'=>$value,'max_util'=>$max_util,'bottleneck'=>$bottleneck, 'underutilized'=>$underutilized,'optimal_count_factor'=>$optimal_count_factor,'optimal_threshold'=>$optimal_threshold);
	}

	public function get_min_max($hostgroup,$class, $plugin, $metric) {

		if ( $this->max_metric == null || // data for given game is not there 
			// or data for given metric is not there
			!isset($this->max_metric["$class:$hostgroup:$plugin:$metric:max"])) {
			// in both cases just return null
			return null;
		}

		$ret = array(); 
		$ret['max'] = $this->max_metric["$class:$hostgroup:$plugin:$metric:max"];
		$ret['min'] = $this->max_metric["$class:$hostgroup:$plugin:$metric:min"];

		return $ret;
	}
	
	// Returns the max of utilization,slack etc... for each plugin(metric)
	public function get_max_utils($metric_utilizations) {

		$ret = array();
		$max_util_val = -1;
		$max_util_key = "";
		
		$bottleneck_key = "";
		$underutil_key = "";
		$bottleneck_val = -1;
		$underutil_val = -1;

		foreach($metric_utilizations as $metric=>$values) {
			
			if ( $values['max_util'] > $max_util_val) {
				$max_util_val = $values['max_util'];
				$max_util_key = $metric;
			}

			if ( $values['bottleneck'] > $bottleneck_val) {
				$bottleneck_val = $values['bottleneck'];
				$bottleneck_key = $metric;
			}

			if ( $values['underutilized'] > $underutil_val) {
				$underutil_val = $values['underutilized'];
				$underutil_key = $metric;
			}

		}

		if($max_util_val == -1) {
			return NULL;
		}

		$ret['name'] = $max_util_key;
		$ret['utilization'] = $max_util_val;
		$ret['optimal_count_factor'] = $metric_utilizations[$max_util_key]['optimal_count_factor'];
		$ret['optimal_threshold'] = $metric_utilizations[$max_util_key]['optimal_threshold'];
		$ret['bottleneck_key'] = $bottleneck_key;
		$ret['bottleneck_val'] = $bottleneck_val;
		$ret['underutil_key'] = $underutil_key;
		$ret['underutil_val'] = $underutil_val;

		return $ret;
	}
	
	public function get_nodetype_max_value($hostgroup, $key) {

		$query = "select t1.hostgroup, t1.type, t2.memory_mb*1024*1024 as memory, t2.network*1024*1024 as network from 
			(SELECT hostgroup, type, count(hostname) as count FROM instances where hostgroup in ('$hostgroup') 
			group by type order by count desc limit 1) as t1 , (select * from nodetype) as t2 where t1.type=t2.type";


		$result = $this->rs->get_value($query);
		return $result[0][$key];
	}

	public function get_game_rps() {

		$query = "select game, class, hostgroup, plugin, metric, avg(value) as value from ". 
			  $this->table . " where timestamp > now() - interval 1 day and 
			  plugin='apache-apache_requests' and game='" . $this->game . "'  
			  and value < 200 group by game, class, hostgroup,plugin,metric";
		//echo $query."\n";
		$stmt = $this->prepare($query);
                $rows = $this->fetchAll($stmt, array());
		//print_r($rows);
		$sum_rps = 0;
		$web_pool_count = 0;

		foreach($rows as $row) {
			$sum_rps += $row['value'];
			$web_pool_count += 1;
		}

		$avg_rps = ($sum_rps / $web_pool_count);

		return $avg_rps;
	}
}

