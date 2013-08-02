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


include_once 'server.cfg';

function error_out($message) {
	header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");

	echo "$message\n";
}



function main($server_cfg) {
	$game = "";

	if (isset($_GET["game"])) {

		$game = $_GET["game"];

		$candidate_path = sprintf($server_cfg["xhprof_ip_list"], $game);
		error_log($candidate_path . "\n");

		if (file_exists($candidate_path)) {
			header($_SERVER["SERVER_PROTOCOL"] . " 200 Ok");			
			header("Content-type: text/plain");

			echo file_get_contents($candidate_path);
			return;
		} else {
			error_out("$game maybe invalid, path $candidate_path does not exist.");
		}
	} else {
		error_out("No game name passed");
	}
}
 

main($server_cfg);
