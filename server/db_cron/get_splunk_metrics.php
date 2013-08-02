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

//
// This is the cron job for zperfmon which processes and inserts
// splunk data into DB. It can be called with parameters as game name 
// and comma separated timeslots enclosed in braces.
//

ini_set('memory_limit', '48M');

error_reporting(E_ALL|E_STRICT);

include_once 'server.cfg';
include_once 'game_config.php';
include_once 'logger.inc.php';
include_once 'splunk-collector.php';
include_once 'zpm_util.inc.php';

function get_options()
{
	$options = getopt("g:");
	
	return $options;
}

function main($server_cfg)
{
	$options = get_options();
	if (isset($options['g']) && $options['g'] !== '') {
		$game_names = explode(",",$options['g']);
	} else {
		$game_names = $server_cfg['game_list'];
	}
	foreach ($game_names as $game_name) {
		zpm_preamble($game_name);
		try {
			$game_cfg = load_game_config($game_name);
			//$game_cfg = $game_cfg[$game_name];
			$now = time();
			$query_period = $now - 5*60; // 5 minutes ago
			if(isset($game_cfg['splunk_url'])) {
				$splunk_url = $game_cfg['splunk_url'];
				$splunk_user = $game_cfg['splunk_user'];
				$splunk_pass = $game_cfg['splunk_password'];
				$splunk_queries = $game_cfg['splunk_queries'];

				$splunk_collector = new SplunkCollector($splunk_url, $splunk_user, $splunk_pass);
				$splunk_store = new SplunkAdapter($game_cfg);

				foreach($splunk_queries as $query_name => $query) {
					$results = $splunk_collector->run_query($query, $query_period);
					if(is_array($results)) {
						$count = $results[0]["count"];
						$splunk_store->store_splunk_count($now, $query_name, $count); 
					} else {
						echo "Could not fetch $query_name for $game_name\n";
					}
				}
			}
		}
		catch(Exception $e) {
		}

		zpm_postamble($game_name);
		
	}
}

main($server_cfg);
