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

header("MIME-Type:application/json");
//header("Content-Type:text/json");
//
// This script returns a json object containing events(tags and/or releases) 
// between given timestamp
//
// inputs:-
// game: name of the game
// start: start timestamp (default is current - 30 min)
// end: end timestamp ( default is current )
// type: type ofthe events ( default is release)
// version: api version(default is v 1.0)
//

include_once 'game_config.php';
include_once 'PDOAdapter.php';
include_once 'server.cfg';

$start = null;
$end = null;
$types = array("all", "tag", "release");
$type = null;
// If game name is not given return empty json.
// With message that game name should be given
if(empty($_GET['game'])) {
	echo json_encode(array("Please give a game name e.g. game=city"));
	exit(0);	
}


// basic checks for parameters passed
if(!empty($_GET['start'])) {
	$start = $_GET['start'];
	if( !is_numeric($start)){
		echo json_encode(array("start timestamp should be numeric"));
		exit(0);
	}
}

if(!empty($_GET['end'])) {
	$end  = $_GET['end'];
	if( !is_numeric($end)){
		echo json_encode(array("end timestamp should be numeric"));
		exit(0);
	}
}

if(!empty($_GET['type'])) {
	$type = $_GET['type'];
	if( !in_array($type, $types)){
		echo json_encode(array("illegal type"));
		exit(0);
	}
}
// basic checks ends

$game = $_GET['game'];


$game_cfg = load_game_config($game);

// check if the game config loaded or not .
// i.e. game exists or not
if ( empty($game_cfg) ) {

	echo json_encode("game {$game} doesn't exists in zperfmon server");
	exit(0);
}

//$game_cfg = $game_cfg[$game];
$game_cfg = $game_cfg;


function get_game_db_conf($game_cfg) {

	return array( 'db_host'=>$game_cfg['db_host'],
		      'db_name'=>$game_cfg['db_name'],
		      'db_user'=>$game_cfg['rpt_user'],
                      'db_pass'=>$game_cfg['rpt_pass']
		     );
}

class API extends PDOAdapter {

	public function __construct($game_cfg) {
		
		$game_db_conf = get_game_db_conf($game_cfg);
		$db_host = $game_db_conf['db_host'];
		$db_name = $game_db_conf['db_name'];
		$db_user = $game_db_conf['db_user'];
		$db_pass = $game_db_conf['db_pass'];
		parent::__construct($db_host, $db_user, $db_pass, $db_name);
	}

	public function get_events($start=null, $end=null, $type) {

		$ts = $this->check_timestamps($start, $end);

		if ( empty($type) ) {

			$type = 'release';
		}

		if(isset($ts['error'])) {
			return $ts;
		}

		if (empty($ts)) {
			$where = " type!=''";
		} else {
			$where = " unix_timestamp(start) <= {$ts['end']} and unix_timestamp(start) >= {$ts['start']} and type != ''";
		}

		if( $type !== 'all' ) {
			$where .= " and type='$type' ";
		}

		$query = "select unix_timestamp(start) as time,type,text from events where $where ";

		error_log("get_events_query: ".$query);
		$stmt = $this->prepare($query);

		$rows = $this->fetchAll($stmt, array());

		return $rows;
	}

	private function check_timestamps($start, $end) {

		$ret = array();

		if( !empty($start) ) {
			
			if( !empty($end) ) {
				if($start < $end) {  // both timestamps are given
					$ret['start'] = $start;
					$ret['end'] = $end;
				} else {
					$ret['error'] = "Wrong timestamps are given";
				}
			} else { 
				$end = (int) time();
				if( $start < $end ) {
					$ret['start'] = $start;
					$ret['end'] = $end;
				} else {
					$ret['error'] = "start timestamp is greater that end timestamp";
				}
			}
		} else {

			if( !empty($end) ) {
				$ret['error'] = "Please specify start timestamp";
			} else {
#				$end = (int) time();
#				$start = $end - 1800;
#				$ret['end'] = $end;
#				$ret['start'] = $start;
			}
		}

		return $ret;
	}
}

$api = new API($game_cfg);
$result = $api->get_events($start, $end, $type);

echo json_encode($result);

?>
