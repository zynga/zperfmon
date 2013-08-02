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


$game_config_path = "/etc/zperfmon/";
$per_game_config_path = "/etc/zperfmon/conf.d/";

set_include_path(get_include_path() . ":$game_config_path:$per_game_config_path");

include_once 'server.cfg';
include_once 'game_config.php';

try {
	$game = null;
	$arrayid = null;
	if (isset($_SERVER['REQUEST_METHOD'])) {
		switch ($_SERVER['REQUEST_METHOD']) {
		case 'GET':
			$game = $_GET["game"];
			$arrayid = @$_GET["array"];
			break;
		case 'POST':
			$game = $_POST["game"];
			$arrayid = @$_POST["array"];
			break;
		default:
			$game = null;
			$arrayid = null;
			break;
		}
	} else {
		$options = getopt("g:");
		
		if (isset($options["g"])) {
			$game = $options["g"];
		}
	}
} catch (Exception $e) {
	$game = null;
}


if (!isset($game) || !$game) {
	echo "<body>Error: expected a game name parameter, eg.: <b>\"?game=fish\"</b></body></html>";
	exit(1);
}

if(isset($arrayid) && $arrayid == "all") {
	$arrayid = null;
}

$game_cfg = load_game_config($game, $arrayid);

if (!$game_cfg) {
	echo "<body>Error: invalid game name: <b>\"$game\"</b></body></html>";
	exit(1);
}

?>
