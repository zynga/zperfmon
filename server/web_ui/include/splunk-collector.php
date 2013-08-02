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

class SplunkAdapter extends PDOAdapter
{
	public function __construct($game_cfg)
	{
		$db_server = $game_cfg["db_host"];
		$db_user = $game_cfg["db_user"];
		$db_pass = $game_cfg["db_pass"];
		$db_name = $game_cfg["db_name"];
		parent::__construct($db_server, $db_user, $db_pass, $db_name);
	}


	public function store_splunk_count($timestamp, $name, $value)
	{
		$query = "REPLACE INTO splunk_stats (timestamp, query_name, value) VALUES (from_unixtime(:timestamp), :query_name, :value)";
		$stmt = $this->prepare($query);
		$parameters = array(
			"timestamp" => array($timestamp, PDO::PARAM_INT),
			"query_name" => array($name, PDO::PARAM_STR),
			"value" => array($value, PDO::PARAM_INT),);
		if(!$this->store($stmt, $parameters)) {
			print ("splunk-collector: Could not insert splunk count for $name");
		}
	}

	public function get_splunk_count($start_time, $end_time)
	{
		$query = "SELECT unix_timestamp(max(timestamp)) as timestamp, query_name, SUM(value) as count
					from splunk_stats WHERE timestamp >= from_unixtime(:start_time) AND timestamp < from_unixtime(:end_time) GROUP BY query_name";

		$parameters = array(
			"start_time" => array($start_time, PDO::PARAM_INT),
			"end_time" => array($end_time, PDO::PARAM_INT),
		);

		$stmt = $this->prepare($query);

		if($stmt)
		{
			$rows = $this->fetchAll($stmt, $parameters);
			$results = array("fatals" => -1, "warnings" => -1, "infos" => -1, "OOS" => -1);
			error_log(print_r($rows, true));
			foreach($rows as $row) {
				$name = $row["query_name"];
				$results[$name] = $row["count"];
			}

			return $results;
		}

		return null;
	}
	
}

include "Splunk/Saved.php";
include "Splunk/Search/Result.php";

class SplunkCollector
{
	public function __construct($splunk_url, $splunk_user, $splunk_password)
	{
		$this->splunk_url = $splunk_url;
		$this->splunk_user = $splunk_user;
		$this->splunk_password = $splunk_password;
	}

	public function run_query($query, $start_time)
	{

		$query_options = array (
				'search' => $query,
				'start_time' => $start_time,
				'max_count' => 50000,
				'timeout' => 60
				);

		
		$search = new Splunk_Search_Result($this->splunk_url);
		if(!$search->login($this->splunk_user, $this->splunk_password))
		{
			echo "login failed\n";
			return false;
		}
	
		$job = $search->dispatchJob($query, $query_options);

		if(!$job)
		{
			error_log("Error dispatching splunk job - $query");
		}

		$count = 0;
		while(!$search->isDone()) {
			if($count == 60) {
                        	break;
                        }
                        $count++;
			sleep(1);
		}

		$resultParams = array(
				"output_mode" => "array",
				"count"           => 0, 
				);
		
		$results = $search->getResults($resultParams);

		return $results;
	}
}

