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
include_once "dau-collector.php";
include_once "splunk-collector.php";
include_once "profilepie.inc.php";

/**
* @class OverviewAdapter
* This class provides helper methods to get data for the zPerfmon overview page
*/

class OverviewAdapter extends PDOAdapter
{
	function __construct($game_cfg)
	{
		$db_server = $game_cfg["db_host"];
		$db_user = $game_cfg["rpt_user"];
		$db_pass = $game_cfg["rpt_pass"];
		$db_name = $game_cfg["db_name"];
		parent::__construct($db_server, $db_user, $db_pass, $db_name);

		$this->game_cfg = $game_cfg;
	}

	private function get_latest_release()
	{
		//$query = "select unix_timestamp(start) as start, text from events where type='release' 
					//and start in (select max(start) from events where type ='release') LIMIT 1;";
		$query = "select unix_timestamp(start) as start, text from events where type='release' OR type='tag' 
				and start in (select max(start) from events where type='release' OR type='tag') order by start desc LIMIT 1;";

		$stmt = $this->prepare($query);

		$rows = $this->fetchAll($stmt, array());

		if($rows)
		{
			$row = $rows[0];
			return array($row["start"], $row["text"]);
		}

		return array(0,"unknown");
	}

	public function get_game_info() {
		// TODO: fix this
		$id = $this->game_cfg["name"];
		$name = $this->game_cfg["sn_game_name"];

		$release = $this->get_latest_release();

		$ret = array(
			"id" => $id,
			"name" => $name,
			"release_version" => $release[1],
			"release_timestamp" => $release[0]
		);
		return $ret;
	}

	public function get_dau() {
		$gid = $this->game_cfg["gameid"];
		$snid = $this->game_cfg["snid"] ? $this->game_cfg["snid"] : null;
		$cid = $this->game_cfg["cid"] ? $this->game_cfg["cid"] : null;
                
		$end_time = time();
		$start_time = $end_time - (1800);
		$daustore = new DAUAdapter();
		// TODO: put a limit on this and desc sort?
		$dau_now = $daustore->get_dau($gid, $start_time, $end_time, $snid, $cid);
		$dau_yest = $daustore->get_dau($gid, $start_time - (86400), $end_time - (86400), $snid, $cid); //one day has 86400 seconds
		$last2 = array_splice($dau_now, -2, 2);
		$last2_yest = array_splice($dau_yest, -2, 2);
		if(count($last2) >= 1 && count($last2_yest) >= 1) {
			$ret = array(
				"previous" => $last2_yest[1]["DAU"],
				"current" => $last2[1]["DAU"]
			);
		} else if(count($last2) >= 1) {
			$ret = array(
				"previous" => -1,
				"current" => $last2[1]["DAU"]
			);
		} else  {
			$ret = array(
				"previous" => -1,
				"current" => -1);
		}

		return $ret;
	}

	public function get_web_eu_max() {
		$ret = array(
			"web_cpu" => 0,
			"web_mem" => 0,
			"web_nw" => 0
		);

		$query_cpu = "SELECT 1 - ((:cpu_threshold-(100-web_cpu_idle))/:cpu_threshold) as web_cpu
		FROM vertica_stats_30min
		WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) ORDER BY web_cpu_idle LIMIT 2";

		$query_pkts = "SELECT 1 - ((2 * :pkts_threshold-(web_nw_rx_pkts+web_nw_tx_pkts))/(2*:pkts_threshold)) as web_nw
		FROM vertica_stats_30min
		WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) ORDER BY (web_nw_tx_pkts + web_nw_rx_pkts) DESC LIMIT 2";

		/*$query_mem = "SELECT max(web_mem_used/:mem_threshold) as web_mem
		FROM vertica_stats_30min
		WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";// ORDER BY web_httpd_memory_util DESC LIMIT 2";*/

		$query_mem = "SELECT max(web_mem_used_util) as web_mem
		FROM vertica_stats_30min
		WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";// ORDER BY web_httpd_memory_util DESC LIMIT 2";
		$cpu_threshold = $this->game_cfg['cpu_threshold'];
		//$mem_threshold = $this->game_cfg['mem_threshold'];
		$pkts_threshold = $this->game_cfg['pkts_threshold'];
		
		$end_time = time() - (24 * 60 * 60); //one day before
		$start_time = $end_time - (8 * 24 * 60 * 60); //8 days before

		$stmt = $this->prepare($query_cpu);
		$parameters = array(
				"cpu_threshold" => array($cpu_threshold, PDO::PARAM_INT),
				"start_time" => array($start_time, PDO::PARAM_INT),
				"end_time" => array($end_time, PDO::PARAM_INT),
		);
		$rows = $this->fetchAll($stmt, $parameters);

		if(count($rows) >= 1) {
				$ret["web_cpu"] = $rows[0]["web_cpu"];
		}

		$stmt = $this->prepare($query_mem);
		$parameters = array(
				//"mem_threshold" => array($mem_threshold, PDO::PARAM_INT),
				"start_time" => array($start_time, PDO::PARAM_INT),
				"end_time" => array($end_time, PDO::PARAM_INT),
		);
		$rows = $this->fetchAll($stmt, $parameters);
		if(count($rows) >= 1) {
				$ret["web_mem"] = $rows[0]["web_mem"];
		}

		$stmt = $this->prepare($query_pkts);
		$parameters = array(
				"pkts_threshold" => array($pkts_threshold, PDO::PARAM_INT),
				"start_time" => array($start_time, PDO::PARAM_INT),
				"end_time" => array($end_time, PDO::PARAM_INT),
		);
		$rows = $this->fetchAll($stmt, $parameters);

		if(count($rows) >= 1) {
				$ret["web_nw"] = $rows[0]["web_nw"];
		}

		return $ret;
	}

	public function get_web_eu() {

		/*$query = "SELECT unix_timestamp(timestamp) as timestamp,	
		1 - ((:cpu_threshold-(100-web_cpu_idle))/:cpu_threshold) as web_cpu,
		(web_mem_used/:mem_threshold) as web_mem,
		1 - ((2 * :pkts_threshold-(web_nw_rx_pkts+web_nw_tx_pkts))/(2*:pkts_threshold)) as web_nw,
		web_rps 
		FROM vertica_stats_30min
		WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) and web_rps < 500 ORDER BY timestamp DESC LIMIT 1";*/

		$query = "SELECT unix_timestamp(timestamp) as timestamp,	
		1 - ((:cpu_threshold-(100-web_cpu_idle))/:cpu_threshold) as web_cpu,
		(web_mem_used_util) as web_mem,
		1 - ((2 * :pkts_threshold-(web_nw_rx_pkts+web_nw_tx_pkts))/(2*:pkts_threshold)) as web_nw,
		web_rps 
		FROM vertica_stats_30min
		WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) and web_rps < 500 ORDER BY timestamp DESC LIMIT 1";
		$stmt = $this->prepare($query);

		$cpu_threshold = $this->game_cfg['cpu_threshold'];
		//$mem_threshold = $this->game_cfg['mem_threshold'];
		$pkts_threshold = $this->game_cfg['pkts_threshold'];
		$end_time = time();
		$end_time_yest = $end_time - (24 * 60 * 60); //yesterday
		$start_time = $end_time - (3 * 1800); //1.5 hours window
		$start_time_yest = $start_time - (24 * 60 * 60); //yesterday

		//get current data
		$parameters = array(
			"cpu_threshold" => array($cpu_threshold, PDO::PARAM_INT), 
			//"mem_threshold" => array($mem_threshold, PDO::PARAM_INT), 
			"pkts_threshold" => array($pkts_threshold, PDO::PARAM_INT), 
			"start_time" => array($start_time, PDO::PARAM_INT), 
			"end_time" => array($end_time, PDO::PARAM_INT),
		);

		$rows = $this->fetchAll($stmt, $parameters);

		if(count($rows) >= 1) {
			$curr = $rows[0];
		} else {
			$curr = array(
				"timestamp" => 0,
				"web_cpu" => 0,
				"web_mem" => 0,
				"web_nw" => 0
			);
		}

		//get yesterday's data
		$parameters = array(
			"cpu_threshold" => array($cpu_threshold, PDO::PARAM_INT),
			//"mem_threshold" => array($mem_threshold, PDO::PARAM_INT),
			"pkts_threshold" => array($pkts_threshold, PDO::PARAM_INT),
			"start_time" => array($start_time_yest, PDO::PARAM_INT),
			"end_time" => array($end_time_yest, PDO::PARAM_INT),
		);

		$rows = $this->fetchAll($stmt, $parameters);

		if(count($rows) >= 1) {
			$prev = $rows[0];
		} else {
			$prev = $curr;
		}

		$maxes = $this->get_web_eu_max();

		$ret = array(
			"memory" => array(
				"label" => "Web Memory",
				"previous" => $prev["web_mem"]*100,
				"current" => $curr["web_mem"]*100,
				"max" => $maxes["web_mem"]*100,
			),
			"cpu" => array(
				"label" => "Web CPU",
				"previous" => $prev["web_cpu"]*100,
				"current" => $curr["web_cpu"]*100,
				"max" => $maxes["web_cpu"]*100,
			),
			"network" => array(
				"label" => "Web Network",
				"previous" => $prev["web_nw"]*100,
				"current" => $curr["web_nw"]*100,
				"max" => $maxes["web_nw"]*100,
			)
		);
		error_log(print_r($ret, true));
		return $ret;
	}

	public function get_proxy_eu_max($current_timestamp) {
			$ret = array(
					"proxy_nw" => 0
			);

			$query_pkts = "SELECT 1 - ((2 * :proxy_pkts_threshold-(proxy_nw_rx_pkts+proxy_nw_tx_pkts))/(2*:proxy_pkts_threshold)) as proxy_nw
			FROM vertica_stats_30min
			WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) ORDER BY (proxy_nw_tx_pkts + proxy_nw_rx_pkts) DESC LIMIT 2";

			$proxy_pkts_threshold = $this->game_cfg['proxy_pkts_threshold'];

			//$end_time = $current_timestamp - (24 * 60 * 60); //one day before
			$end_time = $current_timestamp - (2 * 60 * 60); //one day before
			$start_time = $end_time - (8 * 24 * 60 * 60); //8 days before


			$stmt = $this->prepare($query_pkts);
			$parameters = array(
							"proxy_pkts_threshold" => array($proxy_pkts_threshold, PDO::PARAM_INT),
							"start_time" => array($start_time, PDO::PARAM_INT),
							"end_time" => array($end_time, PDO::PARAM_INT),
			);
			$rows = $this->fetchAll($stmt, $parameters);

			if(count($rows) >= 1) {
							$ret["proxy_nw"] = $rows[0]["proxy_nw"];
			}

			return $ret;
	}

	public function get_proxy_eu() {
			$query = "SELECT unix_timestamp(timestamp) as timestamp,        
			1 - ((2 * :proxy_pkts_threshold-(proxy_nw_rx_pkts+proxy_nw_tx_pkts))/(2*:proxy_pkts_threshold)) as proxy_nw
			FROM vertica_stats_30min
			WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) and web_rps < 500 ORDER BY timestamp DESC LIMIT 1";

			$stmt = $this->prepare($query);

			$proxy_pkts_threshold = $this->game_cfg['proxy_pkts_threshold'];
			$end_time = time();
			$end_time_yest = $end_time - (24 * 60 * 60); //yesterday
			$start_time = $end_time - (3 * 1800); //1.5 hours window
			$start_time_yest = $start_time - (24 * 60 * 60); //yesterday

			//get current data
			$parameters = array(
					"proxy_pkts_threshold" => array($proxy_pkts_threshold, PDO::PARAM_INT),
					"start_time" => array($start_time, PDO::PARAM_INT),
					"end_time" => array($end_time, PDO::PARAM_INT),
			);

			$rows = $this->fetchAll($stmt, $parameters);

			if(count($rows) >= 1) {
					$curr = $rows[0];
			} else {
					$curr = array(
							"timestamp" => 0,
							"proxy_nw" => 0
					);
			}

			//get yesterday's data
			$parameters = array(
					"proxy_pkts_threshold" => array($proxy_pkts_threshold, PDO::PARAM_INT),
					"start_time" => array($start_time_yest, PDO::PARAM_INT),
					"end_time" => array($end_time_yest, PDO::PARAM_INT),
			);

			$rows = $this->fetchAll($stmt, $parameters);

			if(count($rows) >= 1) {
					$prev = $rows[0];
			} else {
					$prev = $curr;
			}

			$maxes = $this->get_proxy_eu_max($end_time);

			$ret = array(
				"proxy_network" => array(
						"label" => "HAProxy Network",
						"previous" => $prev["proxy_nw"]*100,
						"current" => $curr["proxy_nw"]*100,
						"max" => $maxes["proxy_nw"]*100,
				)
			);
			
			return $ret;
    }

	public function get_memcache_eu() {

		$query = "SELECT 1 - ((2 * :pkts_threshold-(mc_nw_rx_pkts+mc_nw_tx_pkts))/(2*:pkts_threshold))
			AS mc_nw FROM vertica_stats_30min
			WHERE timestamp > from_unixtime(:start_time) AND timestamp <= from_unixtime(:end_time) 
			ORDER BY timestamp DESC LIMIT 2";

		$stmt = $this->prepare($query);

		$pkts_threshold = $this->game_cfg['pkts_threshold'];

		$end_time = time();
		$start_time = $end_time - (3 * 1800);

		$parameters = array(
			"pkts_threshold" => array($pkts_threshold, PDO::PARAM_INT), 
			"start_time" => array($start_time, PDO::PARAM_INT), 
			"end_time" => array($end_time, PDO::PARAM_INT),
		);

		$rows = $this->fetchAll($stmt, $parameters);

		if(count($rows) > 1) {
			$prev = $rows[0];
			$curr = $rows[1];
		} else if(count($rows) == 1) {
			$prev = $curr = $rows[0];
		}
		else {
			$prev = $curr = array(
				"timestamp" => 0,
				"mc_nw" => 0
			);
		}

		$ret = array(
			"label" => "Memcache",
			"previous" => $prev["mc_nw"] * 100,
			"current" => $curr["mc_nw"] * 100
			);

		return $ret;
	}



	public function get_db_eu() {

		$query = "SELECT 1 - ((2 * :diskops_threshold-(db_md0_disk_ops_read + db_md0_disk_ops_write))/(2*:diskops_threshold))
			AS db_iops FROM vertica_stats_30min
			WHERE timestamp > from_unixtime(:start_time) AND timestamp <= from_unixtime(:end_time) 
			ORDER BY timestamp DESC LIMIT 2";

		$stmt = $this->prepare($query);

		$diskops_threshold = $this->game_cfg['diskops_threshold'];

		$end_time = time();
		$start_time = $end_time - (3 * 1800);

		$parameters = array(
			"diskops_threshold" => array($diskops_threshold, PDO::PARAM_INT), 
			"start_time" => array($start_time, PDO::PARAM_INT), 
			"end_time" => array($end_time, PDO::PARAM_INT),
		);

		$rows = $this->fetchAll($stmt, $parameters);

		if(count($rows) > 1) {
			$prev = $rows[0];
			$curr = $rows[1];
		} else if(count($rows) == 1) {
			$prev = $curr = $rows[0];
		}
		else {
			$prev = $curr = array(
				"db_iops" => 0
			);
		}

		$ret = array(
			"label" => "MySQL",
			"previous" => $prev["db_iops"] * 100,
			"current" => $curr["db_iops"] * 100
			);

		return $ret;
	}

	public function get_deployment_eu() {
		$ret = array(
			/*
			"consumer" => array(
				"label" => "Consumer (TBD)",
				"previous" => 26,
				"current" => 25
			),
			*/
			"memcache" => $this->get_memcache_eu(),

			"db" => $this->get_db_eu()

		);
		return $ret;
	}

	public function get_splunk() {
		$to = time();
		$from = $to - (1800);

		$splunkstore = new SplunkAdapter($this->game_cfg);
		$curr = $splunkstore->get_splunk_count($from, $to);

		$to = $to - (24 * 3600);
		$from = $to - (3 * 1800);
		$prev = $splunkstore->get_splunk_count($from, $to);

		$ret = array(
			"previous" => array(
				"fatal" => intval($prev["fatals"]),
				"warning" => intval($prev["warnings"]),
				"info" => intval($prev["info"]),
"OOS" => intval($prev["OOS"])
			),
			"current" => array(
				"fatal" => intval($curr["fatals"]),
				"warning" => intval($curr["warnings"]),
				"info" => intval($curr["info"]),
"OOS" => intval($prev["OOS"])
			)
		);
		return $ret;
	}



	public function get_instances_at($ts) {
		$query = "SELECT unix_timestamp(timestamp) as timestamp, web_count, db_count, mc_count, mb_count, proxy_count, queue_count from stats_30min
				WHERE timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) ORDER BY timestamp DESC LIMIT 1";

		$end_time = $ts;
		$start_time = $end_time - (3 * 1800);

		$stmt = $this->prepare($query);

		$parameters = array(
			"start_time" => array($start_time, PDO::PARAM_INT), 
			"end_time" => array($end_time, PDO::PARAM_INT),
		);

		$rows = $this->fetchAll($stmt, $parameters);
		
		if(count($rows) >= 1) {
			$curr = $rows[0];
		} else {
			$curr = array(
				"web_count" => -1,
				"db_count" => -1,
				"mc_count" => -1,
				"mb_count" => -1,
				"proxy_count" => -1,
				"queue_count" => -1,
			);
		}

		return array(
			"web" => intval($curr["web_count"]),
			"mysql" => intval($curr["db_count"]),
			"memcache" => intval($curr["mc_count"]),
			"membase" => intval($curr["mb_count"]),
			"proxy" => intval($curr["proxy_count"])
			);
	}


	public function get_instances() {
		$now = time();

		$current = $this->get_instances_at($now);
		error_log($now);
		$then = $now - (24 * 60 * 60); // 24 hours ago
		error_log($then);

		$previous = $this->get_instances_at($then);

		$ret = array(
			"previous" => $previous,
			"current" => $current
			);

		return $ret;
	}

	private function get_popular_pages($start_time, $end_time) {
		// TODO: change this to a more proper impl
		$query = "SELECT page FROM apache_stats_30min
						WHERE char_length(page) < 63 AND RIGHT(page, 4) = '.php' AND
						      timestamp > FROM_UNIXTIME(:start_time) AND timestamp <= FROM_UNIXTIME(:end_time)
						GROUP by page
						ORDER by SUM(count) DESC
						LIMIT 5;";

		$parameters = array(
			"end_time" => array($end_time, PDO::PARAM_INT),
			"start_time" => array($start_time, PDO::PARAM_INT)
		);

		$stmt = $this->prepare($query);

		$rows = $this->fetchAll($stmt, $parameters);
		$pages = array();

		foreach($rows as $row) $pages[] = $row["page"];

		return $pages;
	}

	/**
	* Page delivery times
	*/
	public function get_page_times() {
		$end_time = time();
		$start_time = $end_time - (24 * 3600);
		$pages = $this->get_popular_pages($start_time, $end_time);

		$cols = "`".implode($pages, "`,`")."`";

		$query = "SELECT unix_timestamp(timestamp) as timestamp, $cols from apache_stats_flip_avg where timestamp > FROM_UNIXTIME(:start_time) AND timestamp <= FROM_UNIXTIME(:end_time);";

		$parameters = array(
			"end_time" => array($end_time, PDO::PARAM_INT),
			"start_time" => array($start_time, PDO::PARAM_INT)
		);

		$stmt = $this->prepare($query);

		$curr_rows = $this->fetchAll($stmt, $parameters);

		$parameters = array(
			"end_time" => array($end_time - 24*3600, PDO::PARAM_INT),
			"start_time" => array($start_time - 24*3600, PDO::PARAM_INT)
		);

		$prev_rows = $this->fetchAll($stmt, $parameters);

		$ret = array();

		foreach($pages as $page) {

			$pdt = array("page" => $page);
			$curr = array();
			foreach($curr_rows as $row) {
				$curr[] = array((int)$row["timestamp"], (float)$row[$page]);
			}

			$prev = array();
			foreach($prev_rows as $row) {
				$prev[] = array((int)$row["timestamp"], (float)$row[$page]);
			}

			$pdt["previous"] = $prev;
			$pdt["current"] = $curr;


			$ret[] = $pdt;	
		}
	
		return $ret;
	}

	/* pass a array which contains the pages for which the pie needs to displayed */
	public function get_profile_data() {
		global $server_cfg;
       	$end_time = time();
        $start_time = $end_time - (24 * 3600);
        $pages = $this->get_popular_pages($start_time, $end_time);
		return get_pie($server_cfg, $this->game_cfg, "day", null, array("excl_wt" => "Exclusive Wall time","excl_cpu" => "Exclusive CPU time"), 6, null, $pages);
	}

	public function get_web_rps_max() {
        $query = "SELECT max(web_rps) as web_rps from vertica_stats_30min where timestamp > FROM_UNIXTIME(:start_time) AND timestamp <= FROM_UNIXTIME(:end_time)";

        $end_time = time() - (24 * 60 * 60); //one day before
        $start_time = $end_time - (8 * 24 * 60 * 60); //8 days before

        $stmt = $this->prepare($query);

		$params = array(
            "start_time" => array($start_time, PDO::PARAM_INT),
            "end_time" => array($end_time, PDO::PARAM_INT),
        );
        $rows = $this->fetchAll($stmt, $params);

        if(count($rows) >= 1) {
            return $rows[0]["web_rps"];
        } else {
            return 0;
        }
    }

	public function get_web_rps() {
        $query = "SELECT web_rps from vertica_stats_30min where timestamp > FROM_UNIXTIME(:start_time) AND timestamp <= FROM_UNIXTIME(:end_time) ORDER BY timestamp DESC LIMIT 1";

        $end_time = time();
        $end_time_yest = $end_time - (24 * 60 * 60); //yesterday
        $start_time = $end_time - (3 * 1800); //1.5 hours window
        $start_time_yest = $start_time - (24 * 60 * 60); //yesterday

        $stmt = $this->prepare($query);

        //current rps
        $params = array(
            "start_time" => array($start_time, PDO::PARAM_INT),
            "end_time" => array($end_time, PDO::PARAM_INT),
        );
        $rows = $this->fetchAll($stmt, $params);

        if(count($rows) >= 1) {
            $curr = $rows[0]["web_rps"];
        } else {
			$curr = 0;
        }

        //previous rps
        $params = array(
            "start_time" => array($start_time, PDO::PARAM_INT),
            "end_time" => array($end_time, PDO::PARAM_INT),
        );
        $rows = $this->fetchAll($stmt, $params);

        if(count($rows) >= 1) {
            $prev = $rows[0]["web_rps"];;
        } else {
            $prev = $curr;
        }

        $max = $this->get_web_rps_max();
        $ret = array(
            "label" => "RPS",
            "previous" => $prev,
            "current" => $curr,
            "max" => $max,
        );

        return $ret;
    }
}
