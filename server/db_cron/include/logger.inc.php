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


include_once "XhProfModel.php";

/*
* Logger class to insert logs by different zperfmon module
* @param : server_cfg : server configuration 
* @param : game_cfg : game configuration
* 
* @author : uprakash
*/

class Logger
{
	/*
	* server configuration
	*/
	private $server_cfg;

	/*
	* game configuration
	*/
	private $game_cfg;
	
	/*
	* XhprofModel object to connect to database and execute the query
	*/
	private $xhprofModelObject;

	/*
	* Possible log levels
	*/
	const INFO = "INFO"; /* informational */

	const ALERT = "ALERT"; /* action must be taken immediately */ 

	const EMERG = "EMERG"; /* system is unusable */

	const CRIT = "CRIT"; /* critical conditions */

	const WARNING = "WARNING"; /* warning conditions */

	const ERR = "ERR"; /* error conditions */

	const DEBUG = "DEBUG"; /* debug-level messages */

	const NOTICE = "NOTICE"; /* normal but significant condition */


	function __construct($server_cfg,$game_cfg)
	{
		$this->server_cfg = $server_cfg;
		$this->game_cfg = $game_cfg;
		$this->xhprofModelObject = new XhProfModel($server_cfg,$game_cfg,false);
	}
	
	/*
	* inserts the log message to log table.
	* @param : module : name of the module  which logs
	* @param : message : log message which is to be logged
	* @param : log_level : the level of log e.g. critical(CRIT) of informative (INFO) etc.
	*/
	public function log($module, $message, $log_level=self::INFO)
	{
		if(!defined('self::'.$log_level)){
			error_log("undefined log level \n", 3, sprintf($this->server_cfg['log_file'], $this->game_cfg['name']));
			return;
		}
		$query_name = 'insert_log';
		$result = $this->xhprofModelObject->generic_execute_get_query($query_name,
									array('module'=>$module,
									      'message'=>$message,
									      'log_level'=>$log_level),
									false,
									false
									);	
		if(!$result){
			error_log("Error in inserting log : ".mysql_error()."\n", 3, sprintf($this->server_cfg['log_file'], $this->game_cfg['name']));
			//echo "Error in inserting log : ".mysql_error()."\n";
		}
	}

	function __destruct()
	{
		$query_name = 'clean_log';
		$retention_time = $this->game_cfg['log_retention_time'];
		$result = $this->xhprofModelObject->generic_execute_get_query($query_name,array('retention_time'=>$retention_time),false,false);
		
		if(!$result){
			error_log("Error in cleaning log : ".mysql_error()."\n", 3, sprintf($this->server_cfg['log_file'], $this->game_cfg['name']));
			//echo "Error in cleaning log : \n";
		}
	}
}

?>
