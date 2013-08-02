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
// This class turns on periodic profile collection for complete requests based
// on configuration settings.
//
// Configuration is delivered via /etc/zperfmon/zperfmon.ini. It specifies
// profiling interval, zperfmon server URL etc. For zRtime client there will
// be a list of IPs for which profiling should be enabled. If configuration is
// via ini, any client with ini file present is selected as a candidate.
//
// This client supports CLI launched apps. However profile collection happens
// only when the instance terminates or if the application hooks into the
// profiling start/stop APIs
//



//
// ZPerfmonCfg
//
// zPerfmon configuration class. Takes care of loading the configuration from
// two available options and finds out how the process was launched - CLI or
// otherwise. If we are running from CLI persistant cache will be maintained
// in files instead of APC.
// 
// Once initialized, this class will tell if zperfmon is turned on for this
// instance. It is upto the caller to decide whether profile collection has to
// be done.
//
class ZPerfmonCfg
{
	private $probe_interval = 60;
	private $game_id = 42;
	private $game_name = null;
	private $upload_url;
	private $url_params = "";
	private $is_cli = FALSE;
	private $config_source = "ZRT";
	private $cache_dir = "/tmp";
	private $has_apc = TRUE;
	private $my_ip = "127.0.0.1";
	private $hostname_append = false;	
        private $mem_slots = "8";
	private $slow_page_threshold = 5;
        private $mem_slots_array;
	private $zdmtd = null;
	private $profile_conf = null;
	private $hash_data = true;

	//
	// This is the mapping for the number of profiles the user wants to be profilled
	// to the particular timeslots
	//
        private $mapping = array();

	//
	// We enable profiling on object construction unless it is a CLI
	// invocation. If it is via a CLI we need finer grained control on
	// which part of the code is profiled and when profiles are dumped.
	// For CLI clients, profiling is enabled on initialization only if
	// PROFILE_CLI_BY_DEFAULT is turned on via zperfmon configuration.
	//
	private $profile_cli_by_default = FALSE; 
	private $disable_apc = FALSE;

	//
	// True if all mandatory config variables were read and if we can
	// enable profile collection if needed.
	//
	public $zperfmon_enabled = FALSE;

	//
	// Constant defined per page to be appended to the profile name
	//
	private $page_params = "";

	// Variable which can be set by user to append to profile name
	private $user_param = null;

	// File to pickup config variables from
	const ZPERF_INI_FILE_PATH = "/etc/zperfmon/zperfmon.ini";

	const ZRT_CONF_FILE = "/var/run/zperfmon/zperfmon-conf";
	const ZRT_CONF_TIME_OUT = 1800;

	// "XHPROF_ENABLE_IPLIST"
	private $conf_var_map = 
		array("XHPROF_PROBE_INTERVAL" => "probe_interval",
		      "ZPERFMON_GAME_ID" => "game_id",
		      "ZPERFMON_GAME_NAME" => "game_name",
		      "ZPERFMON_UPLOAD_URL" => "upload_url");

	private $conf_temp_map = 
		array("ZPERFMON_URL_PARAMS" => "url_params",
		      "ZPERFMON_APPEND_HOSTNAME" => "hostname_append",
		      "ZPERFMON_SLOW_PAGE_THRESHOLD" => "slow_page_threshold",
		      "ZPERFMON_MEM_SLOTS" => "mem_slots",
		      "ZPERFMON_PAGE_PARAMS" => "page_params",
		      "ZPERFMON_ZDMTD" => "zdmtd",
		      "ZPERFMON_PROFILE_CONF" => "profile_conf",
		      "ZPERFMON_HASH_DATA" => "hash_data");
	
	//
	// Tells whether profiling can be turned on.
	//
	public function zperfmon_enabled() {
		return $this->zperfmon_enabled;
	}

	//
	// How long to wait between profile collections
	//
	public function get_probe_interval() {
		return $this->probe_interval;
	}

        public function get_mem_slots() {
                return $this->mem_slots;
        }

        public function get_mem_slots_array() {
                return $this->mem_slots_array;
        }

        public function collect_hash_data() {
                return $this->hash_data;
        }

	//
	// IP of the machine where profiling will happening
	//
	public function get_my_ip() {
		return $this->my_ip;
	}

	//
	// URL parameters if any to key the profile dump with
	//
	public function get_url_params() {
		return $this->url_params;
	}

	//
	// any parameters which is defined in the page
	//
	public function get_page_params() {
		if (!isset($this->page_param_array)) {
			if ($this->page_params) {
				$this->page_param_array = explode(",", $this->page_params);
			} else {
				$this->page_param_array = array();
			}
		}

		return $this->page_param_array;
	}

	public function get_user_param() {
		return $this->user_param;
	}

	public function set_user_param($param) {
		$param = preg_replace("/[~:!@#$%^&*()]/", "", $param);
		if (is_string($param)) {
			$this->user_param = $param;
		}
	}

	//
	// Request start time, populated from time() call. Could use
	// REQUEST_TIME as well. Cached here to prevent too many calls to
	// time().
	//
	public function get_time() {
		return $this->my_time;
	}

	//
	// Whether this process was launched from command line
	//
	public function is_cli() {
		return $this->is_cli;
	}

	//
	// set_time is need for the use-case where the same instance can dump
	// multiple profile like a consumer script would.
	//
	public function set_time($ts=NULL) {
		if ($ts) {
			$this->my_time = $ts;
		} else {
			$this->my_time = time();
		}
	}

	//Whether to append host name to the profile
	public function is_hostname_append_enabled(){
		return $this->hostname_append;
	}

	// slow page threshold
	public function slow_page_threshold(){
		return $this->slow_page_threshold;
	}

	public function get_zdmtd() {
		return $this->zdmtd;
	}

	public function get_prof_conf() {
		return $this->profile_conf;
	}

	//
	// Whether to try to turn on profiling by default.
	//
	public function profile_cli_by_default() {
		return $this->profile_cli_by_default;
	}

	//
	// Retrieve this machines IP and check if it is present in the
	// list of IPs to be monitored. If the configuration is read
	// from file instead of zRuntime the function returns "true" always.
	//
	private function my_ip_enabled() {
		//
		// Try to get IP from _SERVER array, else get it from
		// 'hostname' and gethostbyname(). Cache it if not present in
		// _SERVER.
		//
		$this->my_ip = null;
		if (!array_key_exists('SERVER_ADDR', $_SERVER)) {
			$status = FALSE;
			$this->my_ip = self::cache_get(".zperfmon_my_ip", $status);
			if (!$status || !$this->my_ip) {

				exec("/sbin/ifconfig", $op);
				$ifconf_op = implode("", $op);

				if (preg_match("|inet addr:(\d+\.\d+\.\d+\.\d+)|", 
					       $ifconf_op, $matches) == 1) {
					$this->my_ip = $matches[1];
				} else {
					$this->my_ip = "127.0.0.1";
				}

				if (!empty($this->my_ip)) {
					self::cache_put(".zperfmon_my_ip",
							$this->my_ip);
				}
			}
		} else {
			$this->my_ip = $_SERVER['SERVER_ADDR'];
		}

		// Abort if  we couldn't find our IP
		if  (empty($this->my_ip)) {
			return FALSE;
		}

		if ($this->config_source != "file") {
			//
			// Strict type check makes sure we catch an IP
			// starting at offset zero too. Check IP with an
			// appended comma to make sure we don't do partial IP
			// matches.
			//
			if ( defined("XHPROF_ENABLE_IPLIST") ) {
				$ip_list = constant("XHPROF_ENABLE_IPLIST") . ",";
			}

			if (strstr($ip_list, $this->my_ip . ",") === FALSE) {
				return FALSE;
			}
		}

		return TRUE;
	}


	private function log_once($msg) 
	{
		if (defined("RUNNING_HIPHOP") && isset($_SERVER["XHPROF_OUTPUTDIR"])) {
			$dir = $_SERVER["XHPROF_OUTPUTDIR"];
		} else {
			$dir = ini_get("xhprof.output_dir");
		}

		if (empty($dir)) {
			$dir = "/tmp";
		}

		$err_file = "$dir/zperfmon.error";

		if(file_exists($err_file)) {
			return;
		}

		$time = strftime("%Y-%m-%d %H:%M:%S %Y"); 
		
		$fp = @fopen($err_file, "w");
		fwrite($fp, "[$time] $msg.\n");
		fclose($fp);

		$old = @umask(0);
		@chmod($err_file, 0777);
		@umask($old);
	}


	private function map_config($conf_array, &$fail_count, &$fail_list)
	{
		foreach($this->conf_var_map as $k => $v) {
                	if (!array_key_exists($k, $conf_array)) {
                        	$fail_count++;
                                $fail_list = "{$fail_list}{$k},";
                        } else {
	                        $this->$v = $conf_array[$k]; 
                        }
                }

		foreach($this->conf_temp_map as $k => $v) {
                        if (array_key_exists($k, $conf_array)) {
                                $this->$v = $conf_array[$k];
                        }
                }

	}


	//
	// Configuration can be delivered via zRuntime or a file. If the file
	// '/etc/zperfmon/zperfmon.ini' is present, zperfmon config is picked
	// up from there. Otherwise config entries are populated from zRuntime
	// defined constants. If that also fails, we log an error and give up.
	//
	public function __construct()
	{
		$this->zperfmon_enabled = FALSE;
		$this->mem_slots_array = array();
		
        	$this->mapping = array(
				1 => range(24,24,1),
				2 => range(0,47,24),
				3 => range(0,47,16),
				4 => range(0,47,12),
				6 => range(0,47,8),
				8 => range(0,47,6),	
				12 => range(0,47,4),
				16 => range(0,47,3),
				24 => range(0,47,2),
				48 => range(0,47,1)
				);

		if (!extension_loaded("xhprof")) {
			return;
		}

		//
		// Check if we are running as a service or CLI. This is needed
		// to decide whether to use APC or file as a data-store.
		//
		if (php_sapi_name() == "cli") {
			$this->is_cli = TRUE;
		}
	
		// Cache time for this request
		$this->my_time = time();

		// Track missing config entries
		$fail_count = 0;
		$fail_list = "";

		// Read config from INI file


		if ( file_exists(self::ZPERF_INI_FILE_PATH) ) {

                        $this->config_source = "file";

                        if ($ini_array = parse_ini_file(self::ZPERF_INI_FILE_PATH)) {
                                $this->zperfmon_enabled = TRUE;
                                $this->map_config($ini_array, $fail_count, $fail_list);
                        } else {
                                $fail_count++;
                                $fail_list = "Unable to parse ini file";
                        }
		} else {
                        // Collect ZRT config parameters	
			$this->map_config(get_defined_constants(), $fail_count, $fail_list);

			if (!defined("XHPROF_ENABLE_IPLIST")) {	
				$fail_count++;
                                $fail_list = "{$fail_list}XHPROF_ENABLE_IPLIST,";
			}
		}

		// Check if mandatory config-vars are set
		if ($fail_count > 0) {	
			$how = $this->is_cli() ? "CLI" : "apache";
			self::log_once("Bad zPerfmon config\n" .
				"conf src: {$this->config_source}\n".
				"exec mode: $how\n".
				"failed params: {$fail_list}");
			$this->zperfmon_enabled = FALSE;
			return;
		}

		// Check if this run should enable profiling
		if (!extension_loaded("apc") || 
			(isset($ini_array["DISABLE_APC"]) && $ini_array["DISABLE_APC"])) {
			$this->has_apc = false;
		}

		//
		// Always enable profiling if the header 'HTTP_X_XHPROF_DEBUG'
		// is set or config was delivered via file.
		//
		$this->zperfmon_enabled = self::my_ip_enabled();
		if (isset($_SERVER['HTTP_X_XHPROF_DEBUG']) && $this->my_ip) {
			$this->zperfmon_enabled = TRUE;
		}

		// Check and dump conf for page mining script every 30 seconds
		if ($this->my_time % 30 == 0) {
			self::check_dump_conf_to_file();
		}

		//
		// Check if should not try to turn on profiling in the
		// constructor for CLI clients.
		//
		if (isset($ini_array["PROFILE_CLI_BY_DEFAULT"])) {
			$this->profile_cli_by_default = $ini_array["PROFILE_CLI_BY_DEFAULT"];
		}

                //
                //Checking which all slots need to be memory profiled
                //
                $slots = explode("," , self::get_mem_slots());
                if(count($slots)==1){
                	// Number of slots we want to profile. we have to
                	// figure out which slots in the code itself. 
                        $this->mem_slots_array = self::get_slots($slots);
                }
                else if (count($slots) > 1) {
                	//Its the slot numbers we have to do memory profilling on
                        $this->mem_slots_array = $slots;
                        if(in_array("",$this->mem_slots_array)){
  	                      array_pop($this->mem_slots_array);
                        }
                }
	}

        private function get_slots($slots) {
		$perfect_divisor = 48;
		for($perfect_divisor=$slots[0]; $perfect_divisor<=48; $perfect_divisor++){
			if(48%$perfect_divisor == 0) break;
		}
		return $this->mapping[$perfect_divisor]; 
	}

	//
	// Dump all configuration variables read from zRuntime or from
	// the configuration file to a well known location.
	//
	private function check_dump_conf_to_file() {

		$status = FALSE;
		$value = $this->cache_get('ZPERFMON_WRITE_CONF', $status);

		// If entry is present last dumped config is still valid.
		if ($status == TRUE ) {
			return;
		}

		// make cache entry , to not write the file for next 30 min
		$this->cache_put('ZPERFMON_WRITE_CONF',
				'Zperfmon Conf write entry',
				self::ZRT_CONF_TIME_OUT);

 		$file_handle = fopen(self::ZRT_CONF_FILE, 'w');

		if ($file_handle) {
			 foreach($this->conf_var_map as $k => $v) {
                                $value=$this->$v;
                                fwrite($file_handle, "$k:=$value\n");
                        }
                         foreach($this->conf_temp_map as $k => $v) {
                                $value=$this->$v;
                                fwrite($file_handle, "$k:=$value\n");
                        }

			fclose($file_handle);

			//make the ZRT_CONF_FILE writable
			$oldmask = umask(0);
			@chmod(self::ZRT_CONF_FILE, 0666);
			umask($oldmask);
		}

		return;
	}


	/////////////////////////////////////////////////////////////////////////
	//
	// APC or file based cache
	//
	// cache_get() and cache_put() are the exposed methods.
	//

	//
	// Store 'value' into file at 'file_path' with an expiry time of
	// 'expire' seconds. Store will succeed only if exclusive lock can
	// be held on the file. 'value' will be stored in serialized form.
	//
	private function cache_file_store($file_path, $value, $expire) {


		$value_s = serialize($value);
		$len = strlen($value_s);

		$data = pack("La*", $this->my_time + $expire, $value_s);

		$retval = @file_put_contents($file_path, $data);

		$old = @umask(0);
		@chmod($file_path, 0777);
		@umask($old);

		return $retval;
	}

	//
	// Retrieve and return unserialized contents in file 'file_path'. The
	// first field is expected to be the expiry time of 'value'. If expiry
	// time is before current time, the method sets $status to FALSE .
	//
	private function cache_file_retrieve($file_path, &$status) {

		$status = FALSE;
		if (file_exists($file_path)) {
			$data = @file_get_contents($file_path);
		} else {
			$data = FALSE;
		}

		if ($data === FALSE) {
			return NULL;
		}

		$data = unpack('Lexpiry/a*value', $data);
		$expiry = $data['expiry'];

		$value = unserialize($data['value']);
		if ($expiry > $this->my_time) {
			$status = TRUE;
		}

		return $value;
	}

	//
	// Delete the file at 'file_path'. Non-existent paths are considered to
	// succeed by default.
	//
	private function cache_file_delete($file_path) {

		if (file_exists($file_path)) {
			@unlink($file_path);
		}
	}

	public function cache_put($name, $value, $expire=86400) {
		if ($this->has_apc) {
			return apc_store($name, $value, $expire);
		} else {
			return $this->cache_file_store("{$this->cache_dir}/$name",
						$value, $expire);
		}
	}

	public function cache_get($name, &$status) {
		if ($this->has_apc) {
			return apc_fetch($name, $status);
		} else {
			return $this->cache_file_retrieve("{$this->cache_dir}/$name",
						   $status);
		}
	}

	public function cache_delete($name) {
		if ($this->has_apc) {
			return apc_delete($name);
		} else {
			return $this->cache_file_delete("{$this->cache_dir}/$name");
		}
	}


}


//
// Singleton class to enable, disable and dump profiles as need be.
//
class XHProfAauto
{
	// Hold an instance of the class
	private static $instance;
	private static $profiling_on = False;
	private static $cfg;
	private static $enabler = "";
	
	private static $profile_start;
	private static $zid = null;
	private static $page = null;

	private $ignored_functions = array(
					"XHProfAauto::__destruct",
					"ZPerfmonCfg::parse_ini_file",
					"zperfmon_enable",
					"XHProfAauto::profiling_on",
					"zperfmon_disable",
					"XHProfAauto::check_dump_profile",
					"xhprof_disable",
					"XHProfAauto::note_zid",
					"ZPerfmonCfg::get_zdmtd",
					"XHProfAauto::note_memory",
					"ZPerfmonCfg::get_page_params"
					);

	public function profiling_on() {
		return self::$profiling_on;
	}

	// Check if current time is after next probe interval and if yes return true
	private function must_run_xhprof()
	{
		$current_time = (int)self::$cfg->get_time();

		$status = FALSE;
		$xhprof_next_profile_tick = (int)self::$cfg->cache_get(
					'xhprof_next_profile_tick', $status);

		if (!$status or !$xhprof_next_profile_tick) {
			//
			// If there is no cache entry create one. Next page
			// will pick it up.
			//
			self::$cfg->cache_put('xhprof_next_profile_tick', $current_time);
			return FALSE;
		}
		
		// If current time is before next scheduled probe do nothing.
		if ($current_time < $xhprof_next_profile_tick) {
			return FALSE;
		}
		
		$probe_step = self::$cfg->get_probe_interval();
		if (!self::$cfg->cache_put('xhprof_next_profile_tick',
					   $current_time + $probe_step)) {
			//
			// Don't ignore failure, next time we will profile
			// since timestamp is not updated.
			//
			return FALSE;
		}

		return TRUE;
	}


	public function get_current_page() {
		if (self::$page != null) {
			return self::$page;
		}

		$php_file = basename($_SERVER["SCRIPT_NAME"]);
		$url_params = self::$cfg->get_url_params();
		if (!empty($url_params)) {
			foreach (explode(",", $url_params) as $name) {
				if (isset($_GET[$name]) && !empty($_GET[$name])) {
					$php_file = $_GET[$name] . "^" . $php_file;
				}
			}
		}

		self::$page = $php_file;
		return self::$page;
	}

	// A private constructor; prevents direct creation of object
	private function __construct() 
	{
	    try {
		//
		// If the configuration could not be loaded or if profile
		// collection enabling criteria was not met, we cannot profile
		//
		self::$cfg = new ZPerfmonCfg();
		if (!self::$cfg || !self::$cfg->zperfmon_enabled()) {
			return;
		}

		//
		// We enable profiling on construction only if this is not a
		// command line request or if this is a command line request
		// and PROFILE_CLI_BY_DEFAULT is 'True'.
		//
		if (self::$cfg->is_cli() &&
		    !self::$cfg->profile_cli_by_default()) {
			return;
		}

		$this->check_enable_profile("implicit");
	    } catch (Exception $e) {
		    // Nothing we can really do
	    }
	}


	public function create_prescription($prof_conf) {
		$item_list = explode(",", $prof_conf);
		if (empty($item_list)) {
			return null;
		}

		$prescription = array();
		foreach($item_list as $page_n_cnt) {
			list($page, $cnt) = explode(":", $page_n_cnt);
			if (!$page || empty($page)) {
				continue;
			}
			if ($cnt <= 0 || $cnt >= 30) {
				$cnt = 1;
			}
			$prescription[$page] = $cnt;
		}

		if (empty($prescription)) {
			return null;
		}

		asort($prescription);
		self::$cfg->cache_put($prof_conf, $prescription);
		return $prescription;
	}


	public function create_slots($prescription, $slot_start, $slot_end) {
		if (!is_array($prescription)) {
			return array();
		}

		$probe = self::$cfg->get_probe_interval();
		$slots = array();

		for ($i = $slot_start + 1; $i < $slot_end; $i += $probe) {
			$slots[(int)($i/$probe) * $probe] = "*";
		}

		$slot_count = count($slots);
		foreach($prescription as $page => $pcount) {

			$step = round($slot_count/$pcount) * $probe;

			for ($slt = $slot_start; $slt < $slot_end; $slt += $step) {
				$index = (int)($slt/$probe) * $probe;
				while ($index <= $slot_end && $slots[$index] != "*") {
					$index += $probe;
				}
				if ($index > $slot_end) {
					return $slots;
				}
				$slots[$index] = $page;
			}
		}

		return $slots;
	}

	//
	// "*" get any page not in prescription
	// "#" get any page in pending list
	//
	public function decide_on_candidate($now, $slots, $page, $prof_conf) {
		$ENABLE = 0x1;
		$STORE_SLOT = 0x2;
		$STORE_PEND = 0x4;

		$status = 0;
		$probe = self::$cfg->get_probe_interval();

		$cur_slot = (int)($now/$probe) * $probe;
		$prev_slot = $cur_slot - $probe;

		if (!isset($slots[$cur_slot]) && !isset($slots[$prev_slot])) {
			return False; // Fast path
		}

		$ret = False;
		$pending_list = self::$cfg->cache_get("ZPRFMN_PND_LST", $ret);
		if (!$ret) { $pending_list = False; }

		// Rollover of micro slot
		if (isset($slots[$prev_slot])) {
			$pending_page = $slots[$prev_slot];
			if ($pending_page != "*" && $pending_page != "#") {
				if (!is_array($pending_list)) {
					$pending_list = array($pending_page);
				} else {
					$pending_list[] = $pending_page;
				}
				$status |= $STORE_PEND;
			}

			unset($slots[$prev_slot]);
			$status |= $STORE_SLOT;
		}


		if (isset($slots[$cur_slot])) {
			$want_page = $slots[$cur_slot];
			if ($page == $want_page) {
				if ($pending_list) {
					$slots[$cur_slot] = "#";
				} else {
					unset($slots[$cur_slot]);
				}
				$status |= ($STORE_SLOT | $ENABLE);
			} else {
				if ($want_page == "*" || $want_page == "#" || is_array($pending_list)) {
					if (!is_array($pending_list)) {
						if ($want_page != "#" &&
						    strpos($prof_conf, $page) === False) {
							unset($slots[$cur_slot]);
							$status |= ($STORE_SLOT | $ENABLE);
						}
					} else {
						$index = array_search($page, $pending_list);
						if ($index !== False) {
							unset($pending_list[$index]);
							if (empty($pending_list) && $want_page == "#") {
								unset($slots[$cur_slot]);
								$status |= $STORE_SLOT;
							}
							$status |= ($STORE_PEND | $ENABLE);
						} else if ($want_page == "*" &&
							   strpos($prof_conf, $page) === False) {
							$slots[$cur_slot] = "#";
							$status |= ($STORE_SLOT | $ENABLE);
						} 
						
					}
				}
			}
		}

		if ($status & $STORE_PEND) {
			if (!is_array($pending_list) || empty($pending_list)) {
				self::$cfg->cache_delete("ZPRFMN_PND_LST");
			} else {
				self::$cfg->cache_put("ZPRFMN_PND_LST", $pending_list);
			}
		}
		if ($status & $STORE_SLOT) {
			if (empty($slots)) {
				self::$cfg->cache_delete("ZPRFMN_SLT_LST");
			} else {
				self::$cfg->cache_put("ZPRFMN_SLT_LST", $slots);
			}
		}

		return (bool)($status & $ENABLE);
	}

	public function roll_prescription($now, $page, $prof_conf) {
		$ret = False;
		$slot_stored = self::$cfg->cache_get("ZPERFMON_HLF_HR_SLOT", $ret);
		if (!$ret) { $slot_stored = False; }

		if ($slot_stored === False || $now > $slot_stored) {
			$ret = False;
			$prescription = self::$cfg->cache_get($prof_conf, $ret);
			if (!$ret || $prescription === False) {
				$prescription = self::create_prescription($prof_conf);
			}
		
			$slot_end = (int)($now/1800) * 1800 + 1500;
			$slot_start = $slot_end - 1800;
			$slots = self::create_slots($prescription,
						    $slot_start, $slot_end);
			self::$cfg->cache_put("ZPRFMN_SLT_LST", $slots);
			self::$cfg->cache_delete("ZPRFMN_PND_LST");
			self::$cfg->cache_put("ZPERFMON_HLF_HR_SLOT", $slot_end);
		}

		if (!isset($slots)) {
			$ret = False;
			$slots = self::$cfg->cache_get("ZPRFMN_SLT_LST", $ret);
			if (!$ret) { $slots = False; }
		}

		if (is_array($slots) && !empty($slots)) {
			return self::decide_on_candidate($now, $slots, $page, $prof_conf);
		} else {
			return False;
		}
	}

	public function check_enable_profile($caller)
	{
		//
		// For on-demand profiling of CLI apps, profile-time should
		// bet for each invocation.
		//
		if (self::$cfg->is_cli() &&
		    !self::$cfg->profile_cli_by_default()) {
			self::$cfg->set_time();
		}

		//
		// If next scheduled profile collection is not due don't
		// profile, otherwise, if profile collection is through
		// prescription, run that logic.
		//
		if (!self::$cfg->zperfmon_enabled()) {
			return;
		}

		$prof_conf = self::$cfg->get_prof_conf();
		if (!empty($prof_conf) && self::$cfg->get_probe_interval() >= 30) {
			if (!self::roll_prescription((int)self::$cfg->get_time(),
						     self::get_current_page(),
						     $prof_conf)) {
				return;
			}
		} else if (!self::must_run_xhprof()) {
			return;
		}

		self::$profiling_on = TRUE;
		self::$enabler = $caller;

                $ts = self::$cfg->get_time();
		//the output of the statement is on the scale 0-47
		$slot = floor(($ts%86400)/1800);	
		$minute = floor((($ts%86400)%3600)/60);
		$slow_page_threshold = self::$cfg->slow_page_threshold();

		// updating the slot if we are in the threshold period
		//
		// if next slot is to be profiled and we are in the threshold period of the current time slot
		// update the slot number to next slot so memory profiling would happen to profiles from now on
		//
                //
                // considering slow page threshold as 6 mins
                //
                // memory profiling enabled if current slot is meant to be profiled
                // or
                // we are in between 24-30 and the next time slot between 30-60 is meant to be profiled
                // or
                // we are in between 54-60 and the next time slot between 0-30 is meant to be profiled
                //

		if( in_array(($slot+1)%48,self::$cfg->get_mem_slots_array()) && 
		    (($minute >= (30 - $slow_page_threshold) && $minute<=30) ||
		     ($minute >= 60 - $slow_page_threshold && $minute <= 60)) ) {
			$slot = ($slot + 1)%48; 
		}

                if(in_array($slot, self::$cfg->get_mem_slots_array())){ 
		        xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY,
                                        array('ignored_functions' => $this->ignored_functions));
                }
                else{
                        xhprof_enable(XHPROF_FLAGS_CPU, array('ignored_functions' => $this->ignored_functions));
                }
		self::$profile_start = microtime(true);
	}

	// The singleton method
	public static function singleton() 
	{
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	// Prevent users from cloning the instance
	public function __clone()
	{
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}

	public function note_zid()
	{
		$zid_method = self::$cfg->get_zdmtd();
		if ($zid_method != null && defined($zid_method)) {
			self::$zid = constant($zid_method);
		}

		apache_note("zid", self::$zid);
	}

	public function note_memory()
	{
		$mem_pk = memory_get_peak_usage();
		apache_note("mpk", $mem_pk);

		$mem_use = memory_get_usage();
		apache_note("muse", $mem_use);
	}

	//
	// Destruct should attempt to dump profile if profiling was enabled.
	// It needn't worry about the enabling criteria.
	//
	public function __destruct() {
		try {
			if (function_exists('apache_note')) {
				self::note_zid();
				self::note_memory();
			}

			$page_params = self::$cfg->get_page_params();

			if (function_exists('apache_note') &&
			    !empty($page_params) && defined($page_params[0])) {
				apache_note("pparam", constant($page_params[0]));
			}

			self::check_dump_profile("implicit");
		} catch (Exception $e) {
			// Nothing to do
		}
	}

	public function check_dump_profile($caller)
	{
		if (!self::$profiling_on ||
		    self::$enabler != $caller) {
			return;
		}
		
		self::$profiling_on = FALSE;
		
		$profile_data = xhprof_disable();

		// Make the profile dump file unique and also key it with
		// name of the script which is executing this code.
		$php_file = self::get_profile_file_name();
		$run_id = self::save_run($profile_data, ":" . 
					 self::$cfg->get_time() . ":" . 
					 self::$cfg->get_my_ip() . 
					 ":{$php_file}:xhprof");
	}

	//
	// If URL parameters (ZPERFMON_URL_PARAMS) or (ZPERFMON_APPEND_HOSTNAME) is configured, make the
	// file name unique based on the values of those parameters, otherwise
	// return the sanitized filename
	// OR if Page parameters (ZPERFMON_PAGE_PARAMS) is configured aapend the 
	// parameter value to the file name to make it unique	
	// Pre-Condition: $php_file shouldn't be NULL/empty
	//
	private function get_profile_file_name()
	{
		$php_file = self::get_current_page();

		// appending constants defined in the page
		$page_params = self::$cfg->get_page_params();
		foreach ($page_params as $name) {
			if (defined($name)) {
				$value = constant($name);
				$php_file = $value . "^" . $php_file;
			}
		}

	        // append user specified profile name component
		$user_param = self::$cfg->get_user_param();
		if ($user_param !== null && strlen($user_param) > 0) {
			$php_file = $user_param . "^" . $php_file;
		}

                //adding hostname to profile
                if(self::$cfg->is_hostname_append_enabled() && isset($_SERVER['SERVER_NAME'])){
        	       $server_name = $_SERVER['SERVER_NAME'];
		       $server = explode(".",$server_name);
 	               $php_file = $server[0] . "@" . $php_file;
                }

		//
		// ':' is the field separator in our profile dump-file
		// name, strip all occurances.
		//
		$php_file = str_replace(":", "", $php_file);

		$wall_time = (microtime(true) - self::$profile_start);
		if (self::$zid != null && $wall_time > self::$cfg->slow_page_threshold()) {
			$php_file = (string)self::$zid . "~" . $php_file;
		}
		
		return $php_file;
	}


	public function save_run($xhprof_data, $type) {
		$run_id = uniqid();
		if (defined("RUNNING_HIPHOP")) {
			$run_id = "H$run_id";
		}

		if (defined("RUNNING_HIPHOP") && isset($_SERVER["XHPROF_OUTPUTDIR"])) {
			$dir = $_SERVER["XHPROF_OUTPUTDIR"];
		} else {
			$dir = ini_get("xhprof.output_dir");
		}

		$dir = ini_get("xhprof.output_dir");
		if(empty($dir)) { /* error */
			$dir = "/tmp";
		}
		if (function_exists("igbinary_serialize")) {
			$xhprof_data = igbinary_serialize($xhprof_data);
		} else {
			$xhprof_data = serialize($xhprof_data);
		}
		$file_name = "$dir/$run_id.$type";
		$file = @fopen($file_name, 'w');

		if ($file) {
			fwrite($file, $xhprof_data);
			fclose($file);
		}

		return $run_id;
	}

	public function set_user_param($param) {
		self::$cfg->set_user_param($param);
	}

	//used by hiphop to properly destruct the instance at end of run
	public static function kill() {
		if (isset(self::$instance)) {
			self::$instance = null;
		}
	}
};

//
// If the singleton instance which does xhprof setup and profiling arbitration
// exists, ask it to check and if needed enable profile collection.
//
function zperfmon_enable()
{
	global $xhprof_singleton_instance;
 
	if (isset($xhprof_singleton_instance) &&
	    !$xhprof_singleton_instance->profiling_on()) {
		$xhprof_singleton_instance->check_enable_profile("explicit");
		$xhprof_singleton_instance->set_user_param(null);
	}
}


//
// If the singleton instance which does xhprof setup and profiling arbitration
// exists, ask it to check and if needed dump profile data.
//
function zperfmon_disable()
{
	global $xhprof_singleton_instance;

	if (isset($xhprof_singleton_instance) && 
	    $xhprof_singleton_instance->profiling_on()) {
		$xhprof_singleton_instance->check_dump_profile("explicit");
	}
}

//
// Set a string which will be part of the profile file name.
//
function zperfmon_set_user_param($param)
{
	global $xhprof_singleton_instance;

	if (isset($xhprof_singleton_instance)) {
		$xhprof_singleton_instance->set_user_param($param);
	}
}

function zperfmon_destroy() 
{
	global $xhprof_singleton_instance;

	XHProfAauto::kill();
	if (isset($xhprof_singleton_instance) && 
		$xhprof_singleton_instance->profiling_on()) {
		$xhprof_singleton_instance = null;
		unset($xhprof_singleton_instance);
	}
}

if (defined("RUNNING_HIPHOP")) {
	register_shutdown_function('zperfmon_destroy');
}

////////////////////////////////////////////////////
///
/// Create the singleton instance which controls xhprof enabling, disabling
/// and profile dumping.
///
$xhprof_singleton_instance = XHProfAauto::singleton();

?>
