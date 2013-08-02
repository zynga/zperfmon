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
// @author : uprakash
//

include_once "PDOAdapter.php";
include_once "profilepie.inc.php";

//
// @class Report
// This class provides helper methods to get data for the zPerfmon report
//
class Report extends PDOAdapter
{

	private $game_cfg;
	private $server_cfg;
	function __construct($server_cfg, $game_cfg)
	{
		$db_server = $game_cfg["db_host"];
		$db_user = $game_cfg["rpt_user"];
		$db_pass = $game_cfg["rpt_pass"];
		$db_name = $game_cfg["db_name"];

		parent::__construct($db_server, $db_user, $db_pass, $db_name);

		$this->game_cfg = $game_cfg;
		$this->server_cfg = $server_cfg;
	}

	public function get_times($ts_tday = null) {

		$ret_times = array(
				"tday"=>array("st"=>null,
					"et"=>null,
					"ts"=>null,
					),

				"yday"=>array("st"=>null,
					"et"=>null,
					"ts"=>null,
					),

				"wday"=>array("st"=>null,
					"et"=>null,
					"ts"=>null,
					)
				);

		if(empty($ts_tday)) {
			$ts_tday = (int)((int)time() / 1800); //current timeslot
		}

		$ts_yday = $ts_tday - 48;
		$ts_wday = $ts_tday - (48 * 7);

		foreach ($ret_times as $date=>$times) {
			$ret_times[$date]["et"] = (int)${"ts_$date"} * 1800;
			$ret_times[$date]["st"] = (int)$ret_times[$date]["et"] - (24*3600);
		}

		return $ret_times;
	}

	private function get_recent_tslot($tslot) {
		//$daily_dir = "/db/zperfmon/city/xhprof.daily/";
		$daily_dir = sprintf($this->server_cfg['daily_upload_directory'], $this->game_cfg['name']);
		$ret = null;
		for ($i = $tslot; $i > $tslot - 48; $i--)
		{
			if(is_dir("$daily_dir/$i")) {
				$ret = $i;
				break;
			}
		}
		return $ret;
	}

	private function get_timeslots($tstamp = null)
	{
		$tslot_cur = (int)(time() / 1800);

		if(!empty($tstamp))
		{
			$tslot_cur = (int)($tstamp / 1800);
		}


		$tslot_tday = $this->get_recent_tslot($tslot_cur);
		$tslot_yday = $this->get_recent_tslot($tslot_tday - 48);
		$tslot_wday = $this->get_recent_tslot($tslot_tday - (7 * 48));

		$ret = array();
		$ret['tday'] = $tslot_tday;
		$ret['yday'] = $tslot_yday;
		$ret['wday'] = $tslot_wday;

		return $ret;
	}


	private function create_query($type, $columns, $table) {

		$typed_cols = null;

		foreach ($columns as $column) {

			$typed_cols .= " $type(`$column`) `$column`,";
		}

		$typed_cols = trim($typed_cols, ",");

		$query = "SELECT $typed_cols FROM $table 
		WHERE timestamp > FROM_UNIXTIME(:start_time) AND timestamp <= FROM_UNIXTIME(:end_time);";
		
		return $query;
	}


	public function get_popular_pages($start_time, $end_time) {
		// TODO: change this to a more proper impl
		$query = "SELECT page FROM apache_stats_30min  WHERE char_length(page) < 63 AND RIGHT(page, 4) = \".php\" AND 
			  timestamp > FROM_UNIXTIME(:start_time) AND timestamp < FROM_UNIXTIME(:end_time) GROUP by page
                          ORDER by SUM(count) DESC LIMIT 5";

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


	//
	// Page delivery times
	//
	public function get_pdt($start_time, $end_time, $columns, $table) {

		$ret = array();
		$rows_avg = array();
		$rows_max = array();
		$rows_min = array();
		

		$parameters = array(
			"end_time" => array($end_time, PDO::PARAM_INT),
			"start_time" => array($start_time, PDO::PARAM_INT)
		);

		$query = $this->create_query("AVG", $columns, $table);
		$stmt = $this->prepare($query);
		$rows_avg = $this->fetchAll($stmt, $parameters);

		$query = $this->create_query("MAX", $columns, $table);
		$stmt = $this->prepare($query);
		$rows_max = $this->fetchAll($stmt, $parameters);

		$query = $this->create_query("MIN", $columns, $table);
		$stmt = $this->prepare($query);
		$rows_min = $this->fetchAll($stmt, $parameters);

		$tmp_array = array();
		foreach ($columns as $column) {

			$tmp_array['page'] = $column;
			$tmp_array["max"] = isset($rows_max[0][$column]) ? $rows_max[0][$column] : 0;
			$tmp_array["min"] = isset($rows_min[0][$column]) ? $rows_min[0][$column] : 0;
			$tmp_array["avg"] = isset($rows_avg[0][$column]) ? $rows_avg[0][$column] : 0;
			$ret[]=$tmp_array;
		}
		return $ret;
	}

	//
	// returns profile data  of pages for given timeslots.
	// Among these timeslots picks last profile's data.
	//
	public function get_pages_profiles($pages, $tslot, $filter_functions = null) {

		$ret = array();

		$daily_profile_dir = sprintf($this->server_cfg['daily_upload_directory'], $this->game_cfg['name']);

		$blob_dir = $this->server_cfg['blob_dir'];

		$profiles = array();

		foreach ($pages as $page ) {
			// Find all aggregate profiles in dir  or previous timeslot dir and take the last
			$profile_list = glob("$daily_profile_dir/$tslot/$blob_dir/*${page}.xhprof");
			if (empty($profile_list)) {
				echo "no profile for $page in $daily_profile_dir/{$timeslots}/$blob_dir \n";
				
				continue;
			}

			$profile = end($profile_list);

			$ret[$page] = get_direct_pie($profile, $filter_functions);
		}

		return $ret;
	}

	public function get_pdt_and_profiles($timeslot_today = null, $filter_functions=null) {
		
		$pages_data = array();
		
		$times = $this->get_times($timeslot_today);
				
		// get current popular pages
		$pages = $this->get_popular_pages($times["tday"]["st"], $times["tday"]["et"]);

		$tslots = $this->get_timeslots();
		foreach ($times as $day=>$time) {
			$pdt = $this->get_pdt($time["st"], $time["et"], $pages, "apache_stats_flip_avg");
			$profs = $this->get_pages_profiles($pages, $tslots[$day], $filter_functions);
			$pages_data[$day] = array();
			$pages_data[$day]['pdt'] = $pdt;
			$pages_data[$day]['profiles'] = $profs;
		}

		return $pages_data;
	}


	public function get_web_eu($timeslot_today = null) {

		$web_eu = array();

		$times = $this->get_times($timeslot_today);

		$query_max  = "SELECT 1 - (min(web_cpu_idle)/:cpu_threshold) as web_cpu, (max(web_mem_used)/:mem_threshold) as web_mem, 
			     max(web_nw_rx_pkts+web_nw_tx_pkts)/(2*:pkts_threshold)  as web_nw FROM vertica_stats_30min WHERE 
			     timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";

		$query_min  = "SELECT 1 - (max(web_cpu_idle)/:cpu_threshold) as web_cpu, (min(web_mem_used)/:mem_threshold) as web_mem, 
			     min(web_nw_rx_pkts+web_nw_tx_pkts)/(2*:pkts_threshold)  as web_nw FROM vertica_stats_30min WHERE 
			     timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";
 
		$query_avg  = "SELECT 1 - (avg(web_cpu_idle)/:cpu_threshold) as web_cpu, (avg(web_mem_used)/:mem_threshold) as web_mem, 
			     avg(web_nw_rx_pkts+web_nw_tx_pkts)/(2*:pkts_threshold)  as web_nw FROM vertica_stats_30min WHERE 
			     timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";
		

		$cpu_threshold = (int)$this->game_cfg['cpu_threshold'];
		$mem_threshold = (int)$this->game_cfg['mem_threshold'];
		$pkts_threshold = (int)$this->game_cfg['pkts_threshold'];

		foreach ($times as $day => $time) {

			$parameters = array(
					"end_time" => array($time["et"], PDO::PARAM_INT),
					"start_time" => array($time["st"], PDO::PARAM_INT),
					"cpu_threshold" => array($cpu_threshold, PDO::PARAM_INT),
					"mem_threshold" => array($mem_threshold, PDO::PARAM_INT),
					"pkts_threshold" =>array($pkts_threshold, PDO::PARAM_INT)
				      );
		
			// avg data
			$stmt = $this->prepare($query_avg);
			$rows_avg = $this->fetchAll($stmt, $parameters);

			// max data
			$stmt = $this->prepare($query_max);
			$rows_max = $this->fetchAll($stmt, $parameters);

			// min data
			$stmt = $this->prepare($query_min);
			$rows_min = $this->fetchAll($stmt, $parameters);

			$web_eu[$day] = array();

			$web_eu[$day]["avg"] = $rows_avg[0];
			$web_eu[$day]["max"] = $rows_max[0];
			$web_eu[$day]["min"] = $rows_min[0];
		}

		return $web_eu;
	}
	
	public function get_db_eu($timeslot_today = null) {

		$db_eu = array();

		$times = $this->get_times($timeslot_today);
		
		$query_max  = "SELECT max(db_md0_disk_ops_read + db_md0_disk_ops_write) as db_md0_disk_ops, max(db_mysql_select) as db_select, 
			       max(db_mysql_insert) as db_insert FROM vertica_stats_30min  WHERE timestamp > from_unixtime(:start_time) 
			       AND timestamp < from_unixtime(:end_time)";

		$query_min  = "SELECT min(db_md0_disk_ops_read + db_md0_disk_ops_write) as db_md0_disk_ops, min(db_mysql_select) as db_select, 
			       min(db_mysql_insert) as db_insert FROM vertica_stats_30min  WHERE timestamp > from_unixtime(:start_time) 
			       AND timestamp < from_unixtime(:end_time)";
 
		$query_avg  = "SELECT avg(db_md0_disk_ops_read + db_md0_disk_ops_write) as db_md0_disk_ops, avg(db_mysql_select) as db_select, 
			       avg(db_mysql_insert) as db_insert FROM vertica_stats_30min  WHERE timestamp > from_unixtime(:start_time) 
			       AND timestamp < from_unixtime(:end_time)";

		
		foreach ($times as $day => $time) {

			$parameters = array(
					"end_time" => array($time["et"], PDO::PARAM_INT),
					"start_time" => array($time["st"], PDO::PARAM_INT)
				      );
		
			// avg data
			$stmt = $this->prepare($query_avg);
			$rows_avg = $this->fetchAll($stmt, $parameters);

			// max data
			$stmt = $this->prepare($query_max);
			$rows_max = $this->fetchAll($stmt, $parameters);

			// min data
			$stmt = $this->prepare($query_min);
			$rows_min = $this->fetchAll($stmt, $parameters);

			$db_eu[$day] = array();

			$db_eu[$day]["avg"] = $rows_avg[0];
			$db_eu[$day]["max"] = $rows_max[0];
			$db_eu[$day]["min"] = $rows_min[0];

		}

		return $db_eu;
	}

	public function get_mc_eu($timeslot_today = null) {

		$mc_eu = array();

		$times = $this->get_times($timeslot_today);
		
		$query_max  = "SELECT max(mc_gets) as mc_gets, max(mc_sets) as mc_sets, max(mc_hits) as mc_hits, max(mc_misses) as mc_misses, 
			       max(mc_nw_rx_pkts+mc_nw_tx_pkts)/(2*:pkts_threshold)  as mc_nw FROM vertica_stats_30min WHERE 
			       timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";

		$query_min  = "SELECT min(mc_gets) as mc_gets, min(mc_sets) as mc_sets, min(mc_hits) as mc_hits, min(mc_misses) as mc_misses, 
			       min(mc_nw_rx_pkts+mc_nw_tx_pkts)/(2*:pkts_threshold)  as mc_nw FROM vertica_stats_30min WHERE 
			       timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";
 
		$query_avg  = "SELECT avg(mc_gets) as mc_gets, avg(mc_sets) as mc_sets, avg(mc_hits) as mc_hits, avg(mc_misses) as mc_misses, 
			       avg(mc_nw_rx_pkts+mc_nw_tx_pkts)/(2*:pkts_threshold)  as mc_nw FROM vertica_stats_30min WHERE 
			       timestamp > from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time)";

		
		$pkts_threshold = (int)$this->game_cfg['pkts_threshold'];

		foreach ($times as $day => $time) {

			$parameters = array(
					"end_time" => array($time["et"], PDO::PARAM_INT),
					"start_time" => array($time["st"], PDO::PARAM_INT),
					"pkts_threshold" => array($pkts_threshold, PDO::PARAM_INT)
				      );
		
			// avg data
			$stmt = $this->prepare($query_avg);
			$rows_avg = $this->fetchAll($stmt, $parameters);

			// max data
			$stmt = $this->prepare($query_max);
			$rows_max = $this->fetchAll($stmt, $parameters);

			// min data
			$stmt = $this->prepare($query_min);
			$rows_min = $this->fetchAll($stmt, $parameters);

			$mc_eu[$day] = array();

			$mc_eu[$day]["avg"] = $rows_avg[0];
			$mc_eu[$day]["max"] = $rows_max[0];
			$mc_eu[$day]["min"] = $rows_min[0];

		}

		return $mc_eu;
	}

	public function get_instance($timeslot_today = null) {

		$times = $this->get_times($timeslot_today);

		$instance = array();

		$query_max = "SELECT MAX(DAU) as DAU, MAX(web_count) as web_count, MAX(db_count) as db_count, MAX(mc_count) as mc_count, 
			      MAX(mb_count) as mb_count, MAX(admin_count) as admin_count, MAX(proxy_count) as proxy_count, 
			      MAX(queue_count) as queue_count FROM stats_30min WHERE timestamp > from_unixtime(:start_time) 
			      AND timestamp < from_unixtime(:end_time) AND DAU != -1";

		$query_min = "SELECT MIN(DAU) as DAU, MIN(web_count) as web_count, MIN(db_count) as db_count, MIN(mc_count) as mc_count, 
			      MIN(mb_count) as mb_count, MIN(admin_count) as admin_count, MIN(proxy_count) as proxy_count, 
			      MIN(queue_count) as queue_count FROM stats_30min WHERE timestamp > from_unixtime(:start_time) 
			      AND timestamp < from_unixtime(:end_time) AND DAU != -1";

		$query_avg = "SELECT AVG(DAU) as DAU, AVG(web_count) as web_count, AVG(db_count) as db_count, AVG(mc_count) as mc_count, 
			      AVG(mb_count) as mb_count, AVG(admin_count) as admin_count, AVG(proxy_count) as proxy_count, 
			      AVG(queue_count) as queue_count FROM stats_30min WHERE timestamp > from_unixtime(:start_time) 
			      AND timestamp < from_unixtime(:end_time) AND DAU != -1";
		
		foreach ($times as $day => $time) {

			$parameters = array(
					"end_time" => array($time["et"], PDO::PARAM_INT),
					"start_time" => array($time["st"], PDO::PARAM_INT)
				      );
		
			// avg data
			$stmt = $this->prepare($query_avg);
			$rows_avg = $this->fetchAll($stmt, $parameters);

			// max data
			$stmt = $this->prepare($query_max);
			$rows_max = $this->fetchAll($stmt, $parameters);

			// min data
			$stmt = $this->prepare($query_min);
			$rows_min = $this->fetchAll($stmt, $parameters);

			$instance[$day] = array();

			$instance[$day]["avg"] = $rows_avg[0];
			$instance[$day]["max"] = $rows_max[0];
			$instance[$day]["min"] = $rows_min[0];

		}

		return $instance;
	}

	public function get_dau_per_instance($timeslot_today = null) {

		$times = $this->get_times($timeslot_today);

		$dau_per_instance = array();

		$query_max = "SELECT MAX(DAU/web_count) AS web_count, MAX(DAU/db_count) AS db_count, MAX(DAU/mc_count) AS mc_count, 
			      MAX(DAU/mb_count) AS mb_count, MAX(DAU/admin_count) AS admin_count, MAX(DAU/proxy_count) AS proxy_count, 
                              MAX(DAU/queue_count) AS queue_count FROM stats_30min WHERE timestamp > from_unixtime(:start_time) 
			      AND timestamp < from_unixtime(:end_time)";

		$query_min = "SELECT MIN(DAU/web_count) AS web_count, MIN(DAU/db_count) AS db_count, MIN(DAU/mc_count) AS mc_count, 
			      MIN(DAU/mb_count) AS mb_count, MIN(DAU/admin_count) AS admin_count, MIN(DAU/proxy_count) AS proxy_count, 
			      MIN(DAU/queue_count) AS queue_count FROM stats_30min WHERE timestamp > from_unixtime(:start_time) 
			      AND timestamp < from_unixtime(:end_time)";

		$query_avg = "SELECT AVG(DAU/web_count) AS web_count, AVG(DAU/db_count) AS db_count, AVG(DAU/mc_count) AS mc_count, 
			      AVG(DAU/mb_count) AS mb_count, AVG(DAU/admin_count) AS admin_count, AVG(DAU/proxy_count) AS proxy_count, 
			      AVG(DAU/queue_count) AS queue_count FROM stats_30min WHERE timestamp > from_unixtime(:start_time) 
			      AND timestamp < from_unixtime(:end_time)";

		
		foreach ($times as $day => $time) {

			$parameters = array(
					"end_time" => array($time["et"], PDO::PARAM_INT),
					"start_time" => array($time["st"], PDO::PARAM_INT)
				      );
		
			// avg data
			$stmt = $this->prepare($query_avg);
			$rows_avg = $this->fetchAll($stmt, $parameters);

			// max data
			$stmt = $this->prepare($query_max);
			$rows_max = $this->fetchAll($stmt, $parameters);

			// min data
			$stmt = $this->prepare($query_min);
			$rows_min = $this->fetchAll($stmt, $parameters);

			$dau_per_instance[$day] = array();

			$dau_per_instance[$day]["avg"] = $rows_avg[0];
			$dau_per_instance[$day]["max"] = $rows_max[0];
			$dau_per_instance[$day]["min"] = $rows_min[0];

		}

		return $dau_per_instance;
	}

	public function get_tracked_functions_wt($timeslot_today = null) {

		$tracked_fn_incl_times = array();
		$times = $this->get_times($timeslot_today);

		$tracked_functions = $this->game_cfg['tracked_functions'];

		// hard coded tracked functions
		if (empty($tracked_functions)) {

			$tracked_functions = array( "MC::set", "MC::get", 
				       "ApcManager::get", "ApcManager::set",
				       "serialize", "unserialize",
				       "AMFBaseSerializer::serialize", 
				       "AMFBaseDeserializer::deserialize");
		}
		
		$tracked_fn_tbl = "tracked_functions_flip_incl_time";


		foreach ($times as $day=>$time) {

			$parameters = array(
					"end_time" => array($time["et"], PDO::PARAM_INT),
					"start_time" => array($time["st"], PDO::PARAM_INT)
				      );
			// get AVG data
			$query = $this->create_query("AVG", $tracked_functions, $tracked_fn_tbl);
			$stmt = $this->prepare($query);
			$rows_avg = $this->fetchAll($stmt, $parameters);

			// get MAX data
			$query = $this->create_query("MAX", $tracked_functions, $tracked_fn_tbl);
			$stmt = $this->prepare($query);
			$rows_max = $this->fetchAll($stmt, $parameters);

			// get MIN data
			$query = $this->create_query("MIN", $tracked_functions, $tracked_fn_tbl);
			$stmt = $this->prepare($query);
			$rows_min = $this->fetchAll($stmt, $parameters);
			
			$tracked_fn_incl_times[$day] = array();

			foreach ($tracked_functions as $function) {
				$tracked_fn_incl_times[$day][$function] = array();
				$tracked_fn_incl_times[$day][$function]["max"]=$rows_max[0][$function];
				$tracked_fn_incl_times[$day][$function]["min"]=$rows_min[0][$function];
				$tracked_fn_incl_times[$day][$function]["avg"]=$rows_avg[0][$function];
			}
		}

		return $tracked_fn_incl_times;
	}
}
