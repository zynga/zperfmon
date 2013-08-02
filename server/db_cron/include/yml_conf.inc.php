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


include_once 'spyc.php';
include_once 'game_config.php';

class HostgroupConfig { //TODO extends Spyc

	public function __construct($server_cfg, $game_name) {

		$this->server_cfg = $server_cfg;
		$this->game_name = $game_name;
	}

	public function load_hostgroup_config() {
		#$common_config = $this->server_cfg["common_config_file"];
		$common_config = $this->get_config_file($this->game_name);
		$hostgroup_config = sprintf($this->server_cfg["hostgroups_config"], $this->game_name);

		$str_yaml = file_get_contents($common_config);
		$str_yaml .= file_get_contents($hostgroup_config);

		return Spyc::YAMLLoad($str_yaml);
	}

	public function get_config_file($game){

        	$game_cfg = load_game_config($game);
	        if(isset($game_cfg['cloud_name']) && $game_cfg['cloud_name']=='zcloud')
	                return $this->server_cfg['common_config_zcloud_file'];
        	else
	                return $this->server_cfg['common_config_file'];
	}
	
	public function get_hostgroup_per_class($hostgroup_configs) {

		$return = array();

		foreach ( $hostgroup_configs as $hostgroup_name=>$hostgroup_conf ) { 

			if(!isset($hostgroup_conf['class'])) continue; // we will consider only those conf whose class name is given

			$class = $hostgroup_conf['class'];

			if(!isset($return[$class])) {
				$return[$class] = array();
			}
			if(!isset($hostgroup_conf['hostgroup'])){
                                $hostgroup_name = str_replace('.*','',$hostgroup_name);
                        }else{
                                $hostgroup_name =$hostgroup_conf['hostgroup'];
                        }
                        $return[$class][] = $hostgroup_name;
		}

		return $return;
	}
	
	public function get_class_name() {
		$class = array();
		$hostgroups_config = $this->load_hostgroup_config();
		foreach ($hostgroups_config as $group=>$class_name){
			if ( is_array($class_name)) {
				foreach ($class_name as $name=>$value){
					if ( $name == 'class'){
						array_push($class,$value);
					}
				}
			}
		}
		return $class;
	}
	
	public function get_config_column_names() {

		$hostgroups_config = $this->load_hostgroup_config();
		$config_columns = array();
		foreach($hostgroups_config as $hostgroup_name=>$hostgroup_conf) {

			if(!isset($hostgroup_conf['class'])) continue;
			$col_prefix = str_replace("-","_",$hostgroup_conf['hostgroup']);
			$class = $hostgroup_conf['class'];
			if($class == "web") {
				$col_prefix = "web";
			}

			foreach($hostgroup_conf as $plugin=>$plugin_conf) {
				$mysql_column_type = "float";
				$mysql_column_default = 0;	
				if(!is_array($plugin_conf) or !isset($plugin_conf['mysql_column_name'])) {
					continue;
				}
				$mysql_column_name_val = "{$col_prefix}_{$plugin_conf['mysql_column_name']}";
				$mysql_column_name_util = "{$col_prefix}_{$plugin_conf['mysql_column_name']}_util";
				if(isset($plugin_conf['mysql_column_type'])) {
					$mysql_column_type = $plugin_conf['mysql_column_type'];
				}
				if(isset($plugin_conf['mysql_column_default'])) {
					$mysql_column_default = $plugin_conf['mysql_column_default'];
				}

				if (!isset($config_columns[$mysql_column_name_val])) {
					$config_columns[$mysql_column_name_val] = array();
					$config_columns[$mysql_column_name_util] = array();
				}
				$config_columns[$mysql_column_name_val]['type'] = $mysql_column_type;
				$config_columns[$mysql_column_name_val]['default'] = $mysql_column_default;
				$config_columns[$mysql_column_name_util]['type'] = $mysql_column_type;
				$config_columns[$mysql_column_name_util]['default'] = $mysql_column_default;
			}
		}
		return $config_columns;
	}

	public function get_web_array_name($hostgroups_config) {

		if(empty($hostgroups_config)) {
			return array();
		}

		$class_hostgroups = $this->get_hostgroup_per_class($hostgroups_config);
		return $class_hostgroups['web'];
	}
	
	public function get_master_hostgroups($class_names=array()) {
		$hostgroup_configs = $this->load_hostgroup_config();
		$return = array();
		foreach ( $hostgroup_configs as $hostgroup_name=>$hostgroup_conf ) {
			// we will consider only those conf whose class name is given
			if(!isset($hostgroup_conf['class'])) continue; 

			$class = $hostgroup_conf['class'];

			if(!isset($return[$class]) && !isset($hostgroup_conf['overlay']) && 
				in_array($class, $class_names)) {

				$hostgroup = str_replace('.*','',$hostgroup_conf['hostgroup']);
				$return[] = $hostgroup;
			}
		}

		return $return;

	}
}

