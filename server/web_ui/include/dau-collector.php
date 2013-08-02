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

define('__id_of_game__', 0);

function get_dau_cfg()
{
	/* ugly hack */
	include "/etc/zperfmon/conf.d/game.cfg";

	return array(	
		"db_host" => $game_cfg["__game_name__"]["db_host"],
		"db_user" => $game_cfg["__game_name__"]["db_user"],
		"db_pass" => $game_cfg["__game_name__"]["db_pass"],
		"db_name" => "zperfmon_dau");
}

include_once "PDOAdapter.php";

class DAUAdapter extends PDOAdapter
{
	public function __construct()
	{
		$cfg = get_dau_cfg();
		$db_server = $cfg["db_host"];
		$db_user = $cfg["db_user"];
		$db_pass = $cfg["db_pass"];
		$db_name = $cfg["db_name"];
		parent::__construct($db_server, $db_user, $db_pass, $db_name);
	}
	
	public function get_dau($gid, $start_time, $end_time, $snid = null, $cid = null, $UTC = false)
	{

		if ( $gid !== null && $start_time != null && $end_time != null ) $where = " AND gid = :gid ";
		else return null;
		
                if(isset($snid) && count($snid) > 0) {
                    
                    if(is_array($snid)){
                        $sns = "(" . implode("," , $snid) . ")";
                        $where .= " AND snid IN $sns ";
                    }
                    else{
                        $where .= " AND snid = $snid ";
                    }
                    
                }

                if(isset($cid)&& count($cid) > 0) {
                    
                    if(is_array($cid)){
                        $cs = "(" . implode("," , $cid) . ")";
                        $where .= " AND cid IN $cs ";
                    }
                    else{
                        $where .= " AND cid = $cid ";
                    }
                    
                }
        if ( $UTC == false) {
			$query = "SELECT unix_timestamp(time) as timestamp, SUM(dau) as DAU, gid
			from dau_5min WHERE time >= from_unixtime(:start_time) AND time < from_unixtime(:end_time) $where GROUP BY time,gid";
		}
		else {
			$query = "SELECT unix_timestamp(time) as timestamp, SUM(dau) as DAU, gid
			from dau_5min WHERE time >= CONVERT_TZ(from_unixtime(:start_time),'SYSTEM','+00:00') 
			AND time < CONVERT_TZ(from_unixtime(:end_time),'SYSTEM','+00:00') $where GROUP BY time,gid order by time desc";
		}
		$parameters = array(
			"start_time" => array($start_time, PDO::PARAM_INT),
			"end_time" => array($end_time, PDO::PARAM_INT)
		);

		$parameters["gid"] = array($gid, PDO::PARAM_INT);

		$stmt = $this->prepare($query);

		if($stmt)
		{
			return $this->fetchAll($stmt, $parameters);
		}

		return null;
	}

        public function get_dau_day_boundry($gid, $snid = null, $cid = null) {

                if ($gid == null ) return null;
            
                // timestamp(date(utc_date()))
                $today = mktime(0,0,0,date("m"),date("d"),date("Y"));
                $start_time = $today - 86400;
                $end_time = $today + 60;
                $ret = $this->get_dau($gid, $start_time, $end_time, $snid, $cid, true);

                
                $retval = array();
                foreach ($ret[0] as $key => $value) {
                    if($key == 'DAU') $retval['dau'] = $value;
                    if($key == 'timestamp') $retval['time'] = date('Y-m-d H:i:s', $value);
                    if($key == 'gid') $retval['gid'] = $value; 
                }
                
                return array($retval);
                
	}
        
        public function get_timed_dau($timestamp, $gid, $snid = null, $cid = null) {
                
                if ($gid == null || $timestamp == null) return null;
                
                $ret = $this->get_dau($gid,  $timestamp -  300 , $timestamp, $snid, $cid);
                return $ret[0]['DAU'];
                
	}
        
	public function store_dau($rows)
	{
		$query = "REPLACE INTO dau_5min (time,snid,gid,cid,dau) VALUES (from_unixtime(:time),:snid,:gid,:cid,:dau);";

		$stmt = $this->prepare($query);
		list($time, $snid, $gid, $cid, $dau) = array(0,0,0,0,0);
		$ref_parameters = array(
		"time" => array(&$time, PDO::PARAM_INT),
		"snid" => array(&$snid, PDO::PARAM_INT),
		"gid" => array(&$gid, PDO::PARAM_INT),
		"cid" => array(&$cid, PDO::PARAM_INT),
		"dau" => array(&$dau, PDO::PARAM_INT));

		foreach($rows as $row) {
			list($time, $snid, $gid, $cid, $dau) = $row;
			print_r($row);
			if(!$this->store($stmt, $ref_parameters)) {
				error_log("dau-collector: Could not insert DAU for $gid");
			}
		}
	}
}

