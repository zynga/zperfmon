#!/usr/bin/php

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
include_once "daily_game_metrics.php";
include_once "generate_report.php";
include_once 'zpm_util.inc.php';

//
// Parses the command line parameters to get game names and timeslot
// If it is not passed default game names and timeslot is returned.
//
function get_options($server_cfg)
{
	$options = array();
	$params = getopt("g:t:p:j:");
	$period = "daily";
	$timeslot = time();
	$games = $server_cfg['game_list'];
	$job = "both";

	if ( isset($params['g']) ) {
		$games = explode(",", $params['g']);
	}

	if ( isset($params['t']) ) {
		$timeslot = (int)$params['t'];
	}

	if ( isset($params['p']) ) {
		$period = $params['p'];
	}

	if ( isset($params['j']) ) {
		$job = $params['j'];
	}

	$timeslot = (int)($timeslot/1800);

	$options['games'] = $games;
	$options['timeslot'] = $timeslot;
	$options['period'] = $period;
	$options['job'] = $job;
	return $options;
}



function main($server_cfg)
{
	$options = get_options($server_cfg);

	foreach ($options['games'] as $game) {

		zpm_preamble($game);
		$game_cfg = load_game_config($game);

		if (!$game_cfg) {
			error_log("Daily job: Failed to load config for $game\n",
				  3, sprintf($server_cfg['log_file'], $game));
			continue;
		}

		// log_file and logger object are used by all child functions.
		$game_cfg['log_file'] = sprintf($server_cfg['log_file'], $game);
		$game_cfg['logger'] = new Logger($server_cfg, $game_cfg);

		$job = $options['job'];

		if ($job == "both" or $job == "daily") {
			process_daily($server_cfg, $game_cfg,
				    $options['timeslot'], $options['period']);
		}

		//performance report
		/*
		if ($job == "both" or $job == "report") {
			generate_report($server_cfg, $game_cfg,
					$options['timeslot'], $options['period']);
		}*/
		zpm_postamble($game);
	}
}


main($server_cfg);

?>
