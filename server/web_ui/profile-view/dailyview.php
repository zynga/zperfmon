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


include_once('prof_summary.php');
include_once('server.cfg');


// /zperfmon/<game>/daily[dd.mm.yy]

//
// For current timeslot or passed timeslot, for the given IP, list
// available profiles with a link to open it in XHPROF view.
//
function get_path($server_cfg)
{
	$game = "zperfmon";
	$ts = null;

	foreach (array("game", "ts") as $param) {
		if (isset($_GET[$param])) {
			$$param = $_GET[$param];
		}
	}

	if ($ts !== null) {
		$path = sprintf($server_cfg['daily_upload_directory'] . "%s/_blobdir_",
				$game, $ts);
	} else {
		$path = sprintf($server_cfg['daily_profile'], $game);
		$comps = explode("/", readlink($path));
		array_pop($comps);
		$ts = array_pop($comps);
		
	}

	return array($path, $game, $ts);
}

function get_ts_int($path)
{
	$comps = explode("/", $path);
	return intval(array_pop($comps));
}
	

function get_day_list($server_cfg, $game, $current_ts)
{
	$daily_path = sprintf($server_cfg['daily_upload_directory'], $game);
	$day_list = array();

	$daily_slots = array_map("get_ts_int", glob($daily_path . "*"));
	sort($daily_slots);
	$daily_slots = array_slice($daily_slots, -7);

	$host = $server_cfg['hostname'];
	foreach($daily_slots as $ts) {
		$entry = "<td><a href=http://$host/zperfmon/$game/daily?ts=$ts>";
		if ($ts != $current_ts) {
			$entry .= date("Y/m/d", $ts * 1800);
		} else {
			$entry .= "<b><i> " . date("Y/m/d", $ts * 1800) . " </i></b>";
		}
		$entry .= "</a></td>";
		$day_list[] = $entry;
	}
	$now = date("Y/m/d", $current_ts * 1800);
	return "<table><tr><td><b>Daily profiles for: $now | Timeslots</b></td>" . 
		implode("\n", $day_list) . "</tr></table><p></p>\n";
}

function get_raw_link($profile, $server_cfg)
{
	$game = $_GET["game"];
	return "http://".$server_cfg['hostname']."/zperfmon/xhprof_html/index_json.php?game=$game&file=$profile";
}


function get_prof_link($profile, $server_cfg)
{
	$game = $_GET["game"];
	$prefix = "http://".$server_cfg['hostname']."/zperfmon/xhprof_html/index.php?sort=excl_wt&game=$game&file=";

	return $prefix . $profile;
}

function get_ip_link($ip, $game, $server_cfg)
{
	$url = "http://".$server_cfg['hostname']."/zperfmon/$game/$ip";

	return $url;
}

function htmlize_ip_dir($server_cfg)
{
	list($prof_path, $game, $ts) = get_path($server_cfg);
	
	echo "<html><head><title>$prof_path</title></head>\n";
	echo "<body>\n";
	echo get_day_list($server_cfg, $game, $ts);
	echo "<table border=1>\n";
	
	$profiles = glob($prof_path . "/*.xhprof");

	echo "<tr><td>Profile</td><td></td><td>Wall Time</td><td>CPU Time</td><td>Mem Util</td><td>Peak Mem</td></tr>\n";

	foreach ($profiles as $prof) {
		$prof_parts = explode(".", basename($prof));
		unset($prof_parts[-1]);

		$tod = date("Y/m/d G:i:s", $prof_parts[0] * 1800);
		unset($prof_parts[0]);
		$page = implode(".", $prof_parts);

		echo "<tr><td><a href=\"" . get_prof_link($prof, $server_cfg) . "\">$tod, $page</a></td>";
		echo "<td><a href=\"" . get_raw_link($prof, $server_cfg) . "\">raw</a></td>";
		$smry = get_summary($prof);

		foreach(array("wt", "cpu", "pmu", "mu") as $metric) {
			$v = $$metric = (int)$smry[$metric];
			echo "<td align=\"right\">{$v}</td>";
		}
		echo "</tr>\n";
	}

	echo "</table>\n</body>\n";
	echo "</html>";
}

htmlize_ip_dir($server_cfg);
