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
* zPerfmon: report database handler
* @author Gaurav (gkumar@zynga.com)
*/

include_once('XhProfModel.php');
include_once('server.cfg');
include_once('game_config.php');

/*
 * provide method to read data from report database and also store data in report database
 * the data read is can also returned in CSV format to facilate creation of csv file
*/
class ReportCollector {
	private $report_cfg;	
	private $instance_class;	
	private $instance_type;	
	private $cloud_name;	
	private $cloud_id;	
	private $class_id;	
	private $type_id;	
	private $xhprofModelObject;
	private $header;
	
	/* read cfg from report.ini file */
	private function  get_report_cfg ($report_conf_file) {
		$config = parse_ini_file($report_conf_file, true);
		return array(
			"db_host" => $config["DB"]["host"],
			"db_user" => $config["DB"]["user"],
			"db_pass" => $config["DB"]["password"],
			"db_name" => $config['DB']["database"],
		);
	}
	
	/* To initialize configuration, instance type and instance class*/
	public function __construct($server_cfg) {
		$this->report_cfg = $this->get_report_cfg($server_cfg['report_conf_file']);
		$this->header =  array('Cloud','Date','Game','DAU','Total Instance Count','DAU/Instance','Optimal Instance Count','Slack %','Instance Class','Instances per Class','DAU/Instance Class','Optimal Instances per Class','Slack % per Class');	
		date_default_timezone_set('UTC');
		$this->xhprofModelObject = new XhProfModel($server_cfg, $this->report_cfg,false,false);
		$this->instance_type = array();
		$this->instance_class = array();
		$result = $this->xhprofModelObject->generic_execute_get_query("get_instance_type_name", array());
		foreach ( $result as $value) {
			$this->instance_type[$value['type_id']] = $value['type_name'];
		}
		$result =  $this->xhprofModelObject->generic_execute_get_query("get_instance_class_name", array());
		foreach ( $result as $value) {
			$this->instance_class[$value['class_id']] = $value['class_name'];
		}
		$result =  $this->xhprofModelObject->generic_execute_get_query("get_cloud_name", array());
		foreach ( $result as $value) {
			$this->cloud_name[$value['cloud_id']] = $value['cloud_name'];
		}
		$this->class_id = array_flip($this->instance_class);
		$this->type_id = array_flip($this->instance_type);
		$this->cloud_id = array_flip($this->cloud_name);
	}
	
	private function get_cloud_name ($id) {
		return $this->cloud_name[$id];
	}	
	private function get_class_name($id) {
		return $this->instance_class[$id];
	}
	private function get_type_name($id) {
		 return $this->instance_type[$id];
	}
	private function get_type_id ($typeName) {
		return $this->type_id[$typeName];
	}
	private function get_class_id ($className){
		return $this->class_id[$className];
	}
	private function get_cloud_id($name) {
		return $this->cloud_id[$name];
	}		

	
	public function readInstanceUtilization () {
		$result = $this->xhprofModelObject->generic_execute_get_query("report_detail",
			array('table' => 'instance_utilization'));
		return $result;
	}
	public function getCloudIdGame($game) {
		$game_cfg = load_game_config($game);
		$deploy_id = $game_cfg['deployIDs'][0];
		$result  = $this->xhprofModelObject->generic_execute_get_query("get_cloud_id",
			array('deploy_id' => $deploy_id));
		return $result[0]['cloud_id'];
	}
	/* insert into instance detail report table */
	public function insertInstanceUtilization($data) {
		$cloud_id = $this->getCloudIdGame($data['game']);
		$result = $this->xhprofModelObject->generic_execute_get_query("insert_report_detail",
			array('table' => 'instance_utilization',
			'game' => $data['game'],
			'total_instance' => $data['total_instance'],
			'DAU' => $data['DAU'],
			'DAU_per_instance' => $data['DAU_per_instance'],
			'optimal_instance_count' => $data['optimal_instance_count'],
			'slack_per' => $data['slack_per'],
			'cloud_id' => $cloud_id));
	}

	/* insert into instance class summary table */
	public function insertInstanceClassSummary ($data) {
		$data['class_id'] = $this->get_class_id($data['class_name']);
		$result = $this->xhprofModelObject->generic_execute_get_query("insert_report_instance_class",
			array('table' => 'instance_class_summary',
			'game' => $data['game'],
			'class_id' => $data['class_id'],
			'total_instance' => $data['total_instance'],
			'DAU_per_instance' => $data['DAU_per_instance'],
			'optimal_instance_count' => $data['optimal_instance_count'],
			'slack_per' => $data['slack_per']));
		
	}

	/* insert into instance pool report table */
	public function insertInstancePool ($data) {
		$data['class_id'] = $this->get_class_id($data['class_name']);
		$data['type_id'] = $this->get_type_id($data['type_name']);
		$result = $this->xhprofModelObject->generic_execute_get_query("insert_report_pool",
			array('table' => 'instance_pool_summary',
                        'game' => $data['game'],
                        'class_id' => $data['class_id'],
                        'pool_name' => $data['pool_name'],
                        'type_id' => $data['type_id'],
                        'total_instance' => $data['total_instance'],
                        'DAU_per_instance' => $data['DAU_per_instance'],
                        'utilization_per' => $data['utilization_per'],
                        'optimal_instance_count' => $data['optimal_instance_count'],
                        'slack_per' => $data['slack_per'],
                        'bottleneck' => $data['bottleneck'],
                        'underutilized' => $data['underutilized'],
                        'headroom_per' => $data['headroom_per']));
	}
	
	/* Read the database and return a joined result of instance summary and detail */
	public function readDetailedReportData() {
		$result = $this->xhprofModelObject->generic_execute_get_query("detaile_report_data",
			array());
		return $result;
	}

	/* to return the result as CSV with headers */
	public function generateCSV() {
		$CSVStr = "";
		$dataArr = $this->readDetailedReportData();
		foreach ( $this->header as $header ) {
			$CSVStr .= $header.",";
		}
                $CSVStr .= "\n";
		if ( isset($dataArr )) {
			foreach ($dataArr as $data){
				foreach($data as $key => $values){
					if ( $key == 'cloud_id'){
						$CSVStr .= $this->get_cloud_name($values).",";
					}
					elseif ( $key == 'class_id' ){
						$CSVStr .= $this->get_class_name($values).",";
					}
					elseif ( $key == 'type_id' ){
						$CSVStr .= $this->get_type_name($values).",";
					}
					elseif ( $key == 'unix_timestamp(instance_utilization.date)' ){
						$CSVStr .= date("m-d-Y", $values).",";
					}
					else {
						$CSVStr .= $values.",";
					}
				}
				$CSVStr .= "\n";
			}
		}
		return $CSVStr;
	}
};

?>
