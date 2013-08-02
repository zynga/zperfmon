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
 @author : uprakash
*/

include_once 'game_config.php';
include_once 'XhProfModel.php';
include_once 'server.cfg';

/*
 Monitors the given metrics on disk as well as into db.
 Also monitors about the uploaded ips which do not have apache stats
 and the ips which are not uploading the profiles but are enabled 
 for xhprof profiling (in zrt).

 constrcutor takes two parameters:-
 @param: server_cfg
 @param: game_cfg

 This script is called with the following parameters
 g : game name
 n : zrt namespace for the game i.e. prod
 p : zrt product name of the game i.e. fishville
*/
class Monitor
{
	private $server_cfg;
	private $game_cfg;
	private $xhProfModelObject;

	/*
	 alert levels
	*/	
	const OK = 0;
	const WARNING = 1;
	const CRITICAL = 2;
	const UNKNOWN = 3;

	/*
	 Exit status which is alert level. 
	 Default is OK. Its value changes 
	 only when alert level is not OK.
	*/
	public $exit_status;

	/*
	 Holds the ips which are enabled in zrt for xhprof profiling,
	 but missing in uploaded ips at server.
	*/
	private $missing_configured_ips;

	/*
	 These are the metrics to be monitored
	*/
	private $monitor_data = array( 
			'machine_counts'	=> array('file'=>'bd_metrics_file',
							'table'=>'db_stats_table'),
						  
			'xhprof_blob'		=> array('dir'=>'profile_upload_directory',
							'file'=>'xhprof_tbz_name',
							'table'=>'xhprof_blob_table'),

			'apache_stats'		=> array('function'=>'monitor_apache_stats',
							'table'=>'apache_stats_table'),

			'configured_ips'	=> array('function' => 'monitor_uploading_ips'),

			'zmonitor_data' 	=> array('file'=>'zmonitor_upload_file',
							'table'=>'zmonitor_table'),

			'function_analytics' 	=> array('table'=>'top5_functions_table'),

			'dau'			=> array('file'=>'dau_file',
				     			'table'=>'db_stats_table')
			);

	function __construct($server_cfg, $game_cfg)
	{
		$this->server_cfg = $server_cfg;
		$this->game_cfg = $game_cfg;
		$this->xhProfModelObject = new XhProfModel($server_cfg, $game_cfg, false);
		$this->exit_status = Monitor::OK;
	}

	function __destruct()
	{
		$mysql_error_msg = mysql_error()."\n";
		//
		// Nothing to do. XhProfModel takes care of 
		// mysql connection
		//
	}

	/*
	 set the exit status of the scipt
	 this status will be the alert level
	 @param : status
	*/
	private function set_exit_status($status)
	{
		if ($this->exit_status < $status) {
			$this->exit_status = $status;
		}
	}

	/*
	 creates the alert message
	 @param  : An array of alert messagaes;
	 @param  : time_slot
	 @return : alert message. This message will be sent to nagios 
	*/
	private function create_alert_message($alert_messages, $time_slot)
	{
		$alert_message = $this->game_cfg["name"].": ".$time_slot." : ";
		if (Monitor::OK == $this->exit_status) {
			$alert_message .= " OK";
			return $alert_message;
		}

		$alert_message .= implode(";", $alert_messages);

		return $alert_message;
	}

	/*
	 search database
	 @param  : query_name,table_name, time_slot
	 @return : true or false depending on whether 
	 	   data is there in db for the given timeslot.
	*/
	private function search_db($query_name, $table_name, $time_slot)
	{

		try {	
			$end_timestamp = ($time_slot) * 1800;
			$start_timestamp = $end_timestamp - 1800;
			$result = $this->xhProfModelObject->generic_execute_get_query($query_name, 
					array("table"=>$table_name,
						"start"=>$start_timestamp,
						"end"=>$end_timestamp), 
						false, true);

			return ($result ? true : false);

		} catch(Exception $ex) {
			//
			// DO nothing
			//
		}
	}

 	//
	// returns the file path for a metric under a timeslot directory
	// or under a directory under the timeslot. for certain metrics such as 
	// fucntion analytics no file name is specified hence a null value is returned.
	//
	private function get_file_name($metric_cfg, $time_slot, $game_name)
	{

		if ( !isset($metric_cfg['file']) ) {
			return null;
		}

		$timeslot_directory = sprintf($this->server_cfg['root_upload_directory'] . "/%s/", 
						$game_name, $time_slot);
		//
		// check for other level of directory hierarchy under timeslot.
		//
		if ( isset($metric_cfg['dir']) ) {
			$timeslot_directory = "$timeslot_directory/".
					$this->server_cfg[$metric_cfg['dir']];
		}

		$file_name = "$timeslot_directory/".
					$this->server_cfg[$metric_cfg['file']];;
		return $file_name;
	}

	/*
	 @param: product name. ex farmville, cafe etc
	 @param: namespace. for zperfmon it is only 'prod'

	 @return: xhprof enabled iplist from zrt
	*/
	private function get_xhprof_enabled_iplist($product, $namespace)
	{
		$xhprof_enable_iplist = array();
		define('ZRUNTIME_NAMESPACE_KEY', $namespace);
		define('ZRUNTIME_PRODUCT_KEY', $product);

		require_once('ZRuntime.class.php');

		$zrt = new ZRuntime(ZRUNTIME_PRODUCT_KEY);
		$zrt->load();
		$liveData = $zrt->getLive();

		if ( isset($liveData) ) {
			$xhprof_enable_iplist = $liveData['XHPROF_ENABLE_IPLIST'];
		}

		return $xhprof_enable_iplist;
	}
	
	/*
	 @param: upload directory
	 	ex: /var/opt/zperfmon/farm/timeslots/{t1,t2,t3,t4,t5}/xhprof/
		t1, etc. are past five timeslots
	 @return: ip addresses whish have uploaded profiles in above timeslots
	*/
	private function get_uploaded_iplist($upload_directory)
	{ 
		$ips = array();
		foreach( glob("$upload_directory/*", GLOB_BRACE) as $dir )
		{
			$dir_name = basename($dir);     
			if ( preg_match("%^\d+.\d+.\d+.\d+$%", $dir_name) ) {
				$ips[] = $dir_name;
			}
		}
		return $ips;
	}

	/*
	 checks for missing ips. i.e. ips which are not uploading but have profiling enabled.
	 And sets the exit status accordingly.

	 @param: upload directory - to pass in get_uploaded_iplist
	 @param: time slot  - to pass in get_uploaded_iplist (previous 5 or 6 time slots)
	 @param: parameters - to pass in get_xhprof_enabled_iplist. as namespace and product are needed
	 			to read srt variables.

	 @return: a message string for missing ips
	*/
	private function monitor_uploading_ips($game_name, $time_slot, $parameters)
	{
		/* get the previous five time slots to look into the uploading ips 
		  and create upload directory with all five time slots with braces {t1,t2,...,t5}
		  to be used in glob
		*/

		$time_slots = "{" . implode(",", range($time_slot - 5, $time_slot)) . "}";

		$timeslots_directory = sprintf($this->server_cfg['root_upload_directory'] . "/%s/", 
						$game_name, $time_slots);

		$upload_directory = $timeslots_directory."/".$this->server_cfg['profile_upload_directory'];

		$uploaded_iplist = $this->get_uploaded_iplist($upload_directory);
		$xhprof_enabled_iplist = $this->get_xhprof_enabled_iplist($parameters['product'], $parameters['namespace']);

		if ( ($index = array_search("", $xhprof_enabled_iplist)) !== false) {
			unset($xhprof_enabled_iplist[$index]);
		}
		$this->missing_configured_ips = array_diff($xhprof_enabled_iplist, $uploaded_iplist);
		

		return empty($this->missing_configured_ips) ? null : "missing configured ips: " .
								      implode(",", $this->missing_configured_ips);
	}

	function monitor_apache_stats($game_name, $time_slot, $options)
	{
		$missing_apache_stats = array(); 

		$timeslot_directory = sprintf($this->server_cfg['root_upload_directory'] . "/%s/", 
						$game_name, $time_slot);

		$upload_directory = $timeslot_directory . "/" . $this->server_cfg['profile_upload_directory'];

		foreach(glob("$upload_directory/[0-9]*", GLOB_ONLYDIR) as $ip_dir) {

			$stats_files = glob("$ip_dir/*.stats");

			if (empty($stats_files)) {
				$missing_apache_stats[] = basename($ip_dir);
			}
		}
		
		return empty($missing_apache_stats) ? null : "apache_stats: missing for ips " .
								implode(",", $missing_apache_stats);
	}
	
	/*
	 Monitor for each monitored data of a game. Set the exit status as required.
	 @param  : time_slot
	 @param	 : parameters needed to read zrt variables;
	 @return : exit status
	*/
	public function monitor_metrics($time_slot, $parameters)
	{
		
		$query_name = 'monitor_table';
		$game_name = $this->game_cfg['name'];
		$alert_messages = array();

		foreach($this->monitor_data as $metric_name=>$metric_cfg){

			if ( isset($metric_cfg['function']) ) {

				$message = self::$metric_cfg['function']($game_name, $time_slot, $parameters);

				if ( $message !== null ) {
					$alert_messages[] = $message;
					$this->set_exit_status(Monitor::WARNING);
				}
			}

			$file_name = self::get_file_name($metric_cfg, $time_slot, $game_name);
					
			if ( !file_exists($file_name) && $file_name !== null ) {

				$alert_messages[] = "$metric_name: " . basename($file_name) . " file is missing ";
				$this->set_exit_status(Monitor::CRITICAL);
			}
		
			$table_name = @$this->game_cfg[$metric_cfg['table']];
			
			if ( isset($table_name) && ! self::search_db($query_name, $table_name, $time_slot) ) {

				$alert_messages[] = "$metric_name: db entry missing ";
				$this->set_exit_status(Monitor::WARNING);
			}
		}

		$alert_message = $this->create_alert_message($alert_messages, $time_slot);
		echo $alert_message;
		return $this->exit_status;
	}
}

function main($server_cfg)
{
	$game_cfg = null;
	$options = getopt("g:n:p:");

	//
	// parameters needed to read zrt varaibles;
	// such as XHPRO_ENABLE_IPLIST
	//
	$parameters['product'] = $options['p'];
	$parameters['namespace'] = $options['n'];

	if ( isset($options['g']) ) {
		$game_cfg = load_game_config($options['g']);
	} else {
		echo "pass game name as parameter with option -g";
		exit(Monitor::UNKNOWN);
	}

	if( !isset($game_cfg) ) {
		echo "game configuration is not loaded";
		exit(Monitor::UNKNOWN);	
	}

	$time_slot = (int)(time() / 1800);

	$monitor = new Monitor($server_cfg, $game_cfg);
	$result = $monitor->monitor_metrics($time_slot - 1, $parameters);
	exit($result);
}

main($server_cfg);
?>
