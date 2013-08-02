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


define("ZPERF_APACHE_ACCESS_LOG", "/var/log/httpd/zperfmon.log");
if (file_exists("/usr/share/php/zperfmon.inc.php")) {
	include_once("/usr/share/php/zperfmon.inc.php");
}
define("ZPM_INIT_VAL", -1);
$zpm_utime = ZPM_INIT_VAL; //no shared access. (no threads in php)

function skip() 
{
	if ((function_exists("zperfmon_enable") && 
		function_exists("zperfmon_set_user_param") &&
		function_exists("zperfmon_disable"))) {
		return false; //zperfmon-client has those
	} 
	return true;
}

function zpm_preamble($ggame) 
{
	if (skip()) {
		return;
	}
	zperfmon_enable();
	if (isset($ggame) && ($ggame != "")) {
		zperfmon_set_user_param("$ggame");
	}

	global $zpm_utime;
	if ($zpm_utime == ZPM_INIT_VAL) {
		$zpm_utime = microtime(true);
	}

	return; 
}

function log_entry($name, $time_t, $start_t_f) 
{
	$ffile = fopen(constant("ZPERF_APACHE_ACCESS_LOG"),'a');
	if ($ffile) {
		//existing process_line() needs GET and 200 
		fwrite($ffile, "GET$name$time_t$start_t_f200-------\n");
		fclose($ffile);
	}
	return;
}

function zpm_postamble($ggame) 
{
	if (skip()) {
		return;
	}
	global $zpm_utime;
	$end_t = microtime(true);
	$time_t = floor(($end_t - $zpm_utime) * 1000000);
	$start_t_f = floor($zpm_utime);
	$name = basename($_SERVER['SCRIPT_NAME']);
	if ((isset($ggame)) && ($ggame != "")) {
		$name = $ggame . "^" . $name;
	}
	log_entry($name, $time_t, $start_t_f);
	zperfmon_disable();
	$zpm_utime = ZPM_INIT_VAL;
	return;
}

