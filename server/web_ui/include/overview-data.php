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
* @class OverviewData
* This class provides helper methods to get data for the zPerfmon overview page
*/
class OverviewData {

	public function get_game_info() {
		$ret = array(
			"id" => "city",
			"name" => "cityville",
			"release_version" => "1.0.843",
			"release_timestamp" => 1304021521
		);
		return $ret;
	}

	public function get_dau() {
		$ret = array(
			"previous" => 4228345,
			"current" => 4239459
		);
		return $ret;
	}

	public function get_web_eu() {
		$ret = array(
			"memory" => array(
				"label" => "Web Memory",
				"previous" => 42,
				"current" => 36,
				"max" => 55
			),
			"cpu" => array(
				"label" => "Web CPU",
				"previous" => 25,
				"current" => 27,
				"max" => 51
			),
			"network" => array(
				"label" => "Web Network",
				"previous" => 57,
				"current" => 59,
				"max" => 75
			)
		);
		return $ret;
	}

	public function get_deployment_eu() {
		$ret = array(
			"consumer" => array(
				"label" => "Consumer",
				"previous" => 26,
				"current" => 25
			),
			"memcache" => array(
				"label" => "Memcache",
				"previous" => 70,
				"current" => 80
			),
			"db" => array(
				"label" => "DB",
				"previous" => 51,
				"current" => 55
			)
		);
		return $ret;
	}

	public function get_splunk() {
		$ret = array(
			"previous" => array(
				"fatal" => 6018,
				"warning" => 5291,
				"info" => 1041
			),
			"current" => array(
				"fatal" => 5891,
				"warning" => 5797,
				"info" => 789
			)
		);
		return $ret;
	}

	public function get_instances() {
		$ret = array(
			"previous" => array(
				"web" => 4759,
				"mysql" => 1025,
				"memcache" => 714,
				"membase" => 0,
				"proxy" => 83
			),
			"current" => array(
				"web" => 4765,
				"mysql" => 1026,
				"memcache" => 711,
				"membase" => 0,
				"proxy" => 83
			)
		);
		return $ret;
	}

	/**
	* Page delivery times
	*/
	public function get_page_times() {
		$ret = array(
			array(
				"page" => "callback.php",
				"threshold" => 10,
				"previous" => array(
					array(1302373800, 620.560),
					array(1302375600, 633.985),
					array(1302377400, 616.548),
					array(1302379200, 608.803),
					array(1302381000, 607.787),
					array(1302382800, 611.381),
					array(1302384600, 614.873),
					array(1302386400, 612.666),
					array(1302388200, 613.116),
					array(1302390000, 616.39),
					array(1302391800, 618.401),
					array(1302393600, 625.118),
					array(1302395400, 626.653),
					array(1302397200, 622.12),
					array(1302399000, 609.16)
				),
				"current" => array(
					array(1302373800, 630.560),
					array(1302375600, 613.985),
					array(1302377400, 626.548),
					array(1302379200, 618.803),
					array(1302381000, 617.787),
					array(1302382800, 612.381),
					array(1302384600, 613.873),
					array(1302386400, 622.666),
					array(1302388200, 633.116),
					array(1302390000, 626.39),
					array(1302391800, 628.401),
					array(1302393600, 615.118),
					array(1302395400, 616.653),
					array(1302397200, 623.12),
					array(1302399000, 625.16)
				)
			),
			array(
				"page" => "get_gift_back_callback.php",
				"threshold" => 10,
				"previous" => array(
					array(1302373800, 620.560),
					array(1302375600, 633.985),
					array(1302377400, 616.548),
					array(1302379200, 608.803),
					array(1302381000, 607.787),
					array(1302382800, 611.381),
					array(1302384600, 614.873),
					array(1302386400, 612.666),
					array(1302388200, 613.116),
					array(1302390000, 616.39),
					array(1302391800, 618.401),
					array(1302393600, 625.118),
					array(1302395400, 626.653),
					array(1302397200, 622.12),
					array(1302399000, 621.16)
				),
				"current" => array(
					array(1302373800, 630.560),
					array(1302375600, 613.985),
					array(1302377400, 626.548),
					array(1302379200, 618.803),
					array(1302381000, 617.787),
					array(1302382800, 612.381),
					array(1302384600, 613.873),
					array(1302386400, 622.666),
					array(1302388200, 633.116),
					array(1302390000, 626.39),
					array(1302391800, 628.401),
					array(1302393600, 615.118),
					array(1302395400, 616.653),
					array(1302397200, 623.12),
					array(1302399000, 625.16)
				)
			),
			array(
				"page" => "gateway.php",
				"threshold" => 10,
				"previous" => array(
					array(1302373800, 620.560),
					array(1302375600, 633.985),
					array(1302377400, 616.548),
					array(1302379200, 608.803),
					array(1302381000, 607.787),
					array(1302382800, 611.381),
					array(1302384600, 614.873),
					array(1302386400, 612.666),
					array(1302388200, 613.116),
					array(1302390000, 616.39),
					array(1302391800, 618.401),
					array(1302393600, 625.118),
					array(1302395400, 626.653),
					array(1302397200, 622.12),
					array(1302399000, 621.16)
				),
				"current" => array(
					array(1302373800, 630.560),
					array(1302375600, 613.985),
					array(1302377400, 626.548),
					array(1302379200, 618.803),
					array(1302381000, 617.787),
					array(1302382800, 612.381),
					array(1302384600, 613.873),
					array(1302386400, 622.666),
					array(1302388200, 633.116),
					array(1302390000, 626.39),
					array(1302391800, 628.401),
					array(1302393600, 615.118),
					array(1302395400, 616.653),
					array(1302397200, 623.12),
					array(1302399000, 625.16)
				)
			),
			array(
				"page" => "get_gift_back.php",
				"threshold" => 10,
				"previous" => array(
					array(1302373800, 620.560),
					array(1302375600, 633.985),
					array(1302377400, 616.548),
					array(1302379200, 608.803),
					array(1302381000, 607.787),
					array(1302382800, 611.381),
					array(1302384600, 614.873),
					array(1302386400, 612.666),
					array(1302388200, 613.116),
					array(1302390000, 616.39),
					array(1302391800, 618.401),
					array(1302393600, 625.118),
					array(1302395400, 626.653),
					array(1302397200, 622.12),
					array(1302399000, 621.16)
				),
				"current" => array(
					array(1302373800, 630.560),
					array(1302375600, 613.985),
					array(1302377400, 626.548),
					array(1302379200, 618.803),
					array(1302381000, 617.787),
					array(1302382800, 612.381),
					array(1302384600, 613.873),
					array(1302386400, 622.666),
					array(1302388200, 633.116),
					array(1302390000, 626.39),
					array(1302391800, 628.401),
					array(1302393600, 615.118),
					array(1302395400, 616.653),
					array(1302397200, 623.12),
					array(1302399000, 625.16)
				)
			),
			array(
				"page" => "message_center.php",
				"threshold" => 10,
				"previous" => array(
					array(1302373800, 620.560),
					array(1302375600, 633.985),
					array(1302377400, 616.548),
					array(1302379200, 608.803),
					array(1302381000, 607.787),
					array(1302382800, 611.381),
					array(1302384600, 614.873),
					array(1302386400, 612.666),
					array(1302388200, 613.116),
					array(1302390000, 616.39),
					array(1302391800, 618.401),
					array(1302393600, 625.118),
					array(1302395400, 626.653),
					array(1302397200, 622.12),
					array(1302399000, 621.16)
				),
				"current" => array(
					array(1302373800, 630.560),
					array(1302375600, 613.985),
					array(1302377400, 626.548),
					array(1302379200, 618.803),
					array(1302381000, 617.787),
					array(1302382800, 612.381),
					array(1302384600, 613.873),
					array(1302386400, 622.666),
					array(1302388200, 633.116),
					array(1302390000, 626.39),
					array(1302391800, 628.401),
					array(1302393600, 615.118),
					array(1302395400, 616.653),
					array(1302397200, 623.12),
					array(1302399000, 625.16)
				)
			)
		);
		return $ret;
	}

	public function get_profile_data() {
		$ret = array(
			"Exclusive wall time" => array(
				array("MC::set","4.27"),
				array("apc_fetch","3.22"),
				array("curl_exec","2.20"),
				array("MC::get","1.13"),
				array("serialize","1.07"),
				array("*others","0.99")
			),
			"Exclusive cpu time" => array(
                array("MC::set","4.27"),
                array("apc_fetch","3.22"),
                array("curl_exec","2.20"),
                array("MC::get","1.13"),
                array("serialize","1.07"),
                array("*others","0.99")
            )
		);
		return $ret;
	}

}

?>

