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
 
EUAdapter class to store EU data as well as fetch eu data from eu.common_eu table
@author: uprakash

*/


include_once 'server.cfg';
include_once 'XhProfDAO.php';
//include_once 'spyc.php';
include_once 'yml_conf.inc.php';
ini_set('memory_limit', '1G');
error_reporting(E_ALL|E_STRICT);


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

include_once "PDOAdapter.php";

class EUAdapter extends XhProfDAO  
{
	private $table;
	public function __construct($server_cfg)
	{
		$cfg = get_eu_cfg($server_cfg['eu_conf_file']);
		$db_server = $cfg["db_host"];
		$db_user = $cfg["db_user"];
		$db_pass = $cfg["db_pass"];
		$db_name = $cfg["db_name"];

		$this->table = $cfg["table"];
		$this->server_cfg = $server_cfg;
		$this->instance_counts = array();

		parent::__construct($db_server, $db_user, $db_pass, $db_name);
	}
	
	private function get_raw_data($query) {

		$this->connect();
		$rows = $this->prepare_and_query($query, false, true);
		$this->disconnect();

		return $rows;
	}

	private function get_max_timestamp($game) {
		$query = "select unix_timestamp(max(timestamp)) as timestamp from {$this->table} where game='{$game}'";
		$rows = $this->get_raw_data($query);

		return $rows[0]['timestamp'];
	}

	//
	// Gives the eu for a game for max timestamp
	// @param: game
	// @param: array_name
	// @return: an array containing mysql column name and corresponding eu values
	//
	public function get_current_eu($game=null, $array_name=null) {
		
		if ( empty($game) ) {
			return array();
		}
		$timestamp = $this->get_max_timestamp($game);

		return $this->get_eu($timestamp, $game, $array_name);
	}

	//
	// to collect eu data for a given game at a given timestamp
	// @param: timestamp
	// @param: game name of the given
	// @array_name:  name of the web array for array eu data
	//
	// @return: array containing mysql column name and corresponding values for each plugin and metric
	//
	public function get_eu( $timestamp, $game=null, $array_name=null ) {

		$return = array();
		if( $game === null ) {

			return $return;
		}

		$timestamp = $this->get_max_timestamp($game);	
		// we select web data separately
		$query = "select unix_timestamp(timestamp) as timestamp, game, class, hostgroup, plugin, metric, 
                          value from {$this->table} where timestamp=from_unixtime({$timestamp}) 
			  and game='{$game}' and class !='web' ";

                $rows = $this->get_raw_data($query);

                if(!empty($rows)) {
                	$eu_data_array = $this->get_formatted_column_names_values($game, $rows);
                	$web_eu = $this->get_web_eu($timestamp, $game, $array_name);
			if (isset($web_eu[$timestamp])) {
				$return[$timestamp] = array_merge($eu_data_array[$timestamp], $web_eu[$timestamp]);
			}
                }
				else{
                	$web_eu = $this->get_web_eu($timestamp, $game, $array_name);
			if (isset($web_eu[$timestamp])) {
				$return[$timestamp] =  $web_eu[$timestamp];
			}
				}
		return $return;
	}

	public function get_web_eu($timestamp, $game, $array_name=null) {


		if ( $array_name !== null ) { // collect web data only for the given web array

			$query = "select unix_timestamp(timestamp) as timestamp, game, class, hostgroup, plugin, metric, 
				value from {$this->table} where timestamp=from_unixtime({$timestamp})
				and game='{$game}' and class='web' and hostgroup='{$array_name}'";

		} else { // collect averaged data for web
			$query = "select unix_timestamp(timestamp) as timestamp , game, class, hostgroup, plugin, metric,
                                sum(value*instance_count)/sum(instance_count) as value from {$this->table}
                                where timestamp = from_unixtime({$timestamp}) and game = '{$game}' and
                                class = 'web'  group by class, plugin, metric";
		}

		$rows = $this->get_raw_data($query);

		if(empty($rows)) {
			return array();
		}

		$web_eu_data_array = $this->get_formatted_column_names_values($game, $rows, false);
		
		return $web_eu_data_array;
	}

	// 
	// This method is called for each row of the data array fetched from common_eu table
	// returns the plugin configuration which includes all threshold values and mysql column name
	// @param: config the hostgroup configuration parsed from hostgroup.yml
	// @param: class, plugin, metric, hostgroup all are the corresponding names
	//
	// @return: an array containg all the values
	//
	private function get_config_name($config, $class, $plugin, $metric, $hostgroup=null) {

		foreach($config as $hostgroup_name=>$hostgroup_config) {

			if(!isset($hostgroup_config['hostgroup']) && !isset($hostgroup_config['class'])){
				continue;
			}
			$conf_hostgroup = str_replace('.*','',$hostgroup_name);
			$conf_class = $hostgroup_config['class'];
			if($class!==$conf_class) continue;
			if(!empty($hostgroup) && $hostgroup !== $conf_hostgroup) continue;

			foreach ($hostgroup_config as $name=>$conf) {

				if(!is_array($conf)) continue;

				// supress any warnings 
				$conf_plugin = @$conf['vertica_plugin'];
				$conf_metric = @$conf['metric'];
				$max_threshold = @$conf['max_threshold'];
				$min_threshold = @$conf['min_threshold'];
				$max_value = @$conf['max_value'];
				$complement = @$conf['complement'];
				$mysql_col_name = @$conf['mysql_column_name'];
				$col_prefix = $hostgroup_config['hostgroup'];

				if ( $plugin==$conf_plugin && $conf_metric == $metric ) {

					return array('name' => $name, 
						     'max_threshold' => $max_threshold,
						     'min_threshold' => $min_threshold,
						     'max_value' => $max_value,
						     'complement' => $complement,
						     'mysql_col_name' => $mysql_col_name,
						     'col_prefix' => str_replace("-","_",$col_prefix),
						    );
				}
			}
		}
	}

	//
	// Loads the hostgroups configuration for a given game
	// @param: game name
	//
	private function load_config($game) {

		$return = array();
		if(empty($game)) {
			return $return;
		}

		$hostgroupConfigObj = new HostgroupConfig($this->server_cfg, $game);
		return $hostgroupConfigObj->load_hostgroup_config();
	}
	//
	// method: get_formatted_column_names
	// Reads the config for the game and returns the formatted column names for each plugin
	// 
	// @param: game name of the game
	// @param: data_array query results
	// @return: array  containing formatted column names and values
	//
	private function get_formatted_column_names_values($game,  $data_rows, $is_per_pool = true) {
			
		$hostgroups_config = self::load_config($game);

		if(empty($hostgroups_config)) {
			return null;
		}

		$return = array();

		foreach ( $data_rows as $row) {

			$hostgroup = null;
			$timestamp = $row['timestamp'];
			$class = $row['class'];
			$hostgroup = @$row['hostgroup']; // ignore warning as it may not present for per_class_row
			$plugin = $row['plugin'];
			$metric = $row['metric'];
			$value = $row['value'];

			$conf = $this->get_config_name($hostgroups_config, $class, $plugin, $metric, $hostgroup);

			if(empty($conf['mysql_col_name'])) continue;

			// calculate utilization
			$max_threshold = 1;
			if(isset($conf['max_threshold'])) {
				$max_threshold = $conf['max_threshold'];
			}
			if($conf['complement']) {
				$util = ($conf['max_value'] - $value) / $max_threshold;
			} else {
				$util = $value / $max_threshold;
			}
			
			if(!isset($return[$timestamp])) {
				$return[$timestamp] = array();
			}

			if ($is_per_pool === true) {
				$value_col_name = "{$conf['col_prefix']}_{$conf['mysql_col_name']}";
				$util_col_name = "{$conf['col_prefix']}_{$conf['mysql_col_name']}_util";
			} else {
				$value_col_name = "{$class}_{$conf['mysql_col_name']}";
				$util_col_name = "{$class}_{$conf['mysql_col_name']}_util";
			}
			$return[$timestamp][$util_col_name] = $util;
			$return[$timestamp][$value_col_name] = $value;
		}
		return $return;
	}

	public function store_eu($rows)
	{
		$this->connect();
		//1308313800,city,db,city-db-c,disk-md0_disk_ops,write,128.122439834798,25.9370728787278
		foreach ($rows as $row) {
			
			$instance_counts = 0;
			list($timestamp, $game, $class,  $hostgroup, $plugin, $metric, $value, $stddev, $instance_counts) = $row;

			$query = "REPLACE INTO ". $this->table . " (timestamp, game, class, hostgroup,
				plugin,metric,value,stddev,instance_count)  VALUES (from_unixtime(" ;
			$query .= " '$timestamp' ), '$game', '$class', '$hostgroup', '$plugin', '$metric', 
				$value, $stddev, $instance_counts)" ;
			//error_log("(uploaded from csv) instance_counts: $instance_counts");
			//error_log($query);
			$ret = $this->prepare_and_query($query, false, false);
			if ( !$ret ) {
				error_log("eu-collector: Could not insert EU for $game , $query"); 
			}
			$query = null;
		}
		$this->disconnect();
	}
}

