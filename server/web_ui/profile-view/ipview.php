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

//
// For current timeslot or passed timeslot, for the given IP, list
// available profiles with a link to open it in XHPROF view.
//
function get_path($server_cfg)
{
	$ip = "127.0.0.1";
	$ts = round(time()/1800 - 1);
	$game = "zperfmon";

	foreach (array("ip", "ts", "game") as $param) {
		if (isset($_GET[$param])) {
			$$param = $_GET[$param];
		}
	}
	$path = explode("%s", $server_cfg['root_upload_directory']);
	$base_path = $path[0];
	$path_to_prof = $base_path."$game/timeslots/$ts/xhprof/$ip/";

	return $path_to_prof;
}

function get_slot_list(&$current_slot, $server_cfg, $action="ips")
{
	$ts_now = round(time()/1800 - 1);

	if (isset($_GET["ts"])) {
		$ts = $_GET["ts"];
	} else {
		$ts = $ts_now;
	}

	$metrics = "";
	if (isset($_GET["metrics"])) {
		$metrics = "&metrics={$_GET["metrics"]}";
	}
	$current_slot = $ts;
	if ($action == "top5" && !isset($_GET["ts"])) {
		$current_slot--;
	}
	$game = $_GET["game"];

	$slot_list = array();
	for ($i = $ts - 3; $i < $ts + 3 && $i < $ts_now; $i++) {
		if ($i == $ts) {
			$ir = "<b>$i</b>";
		} else {
			$ir = "$i";
		}
		$slot_list[] = "<td><a href=http://".$server_cfg['hostname']."/zperfmon/$game/$action?ts=$i$metrics>$ir</a></td>";
	}

	return "<table><tr><td><b>Now: $ts_now Timeslots</b></td>" . implode("\n", $slot_list) . "</tr></table>";
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
	$prof_path = get_path($server_cfg);
	
	echo "<html><head><title>$prof_path</title></head>\n";
	echo "<body>\n<table border=1>\n";
	
	$profiles = glob($prof_path . "*:xhprof");
	
	echo "<tr><td>Profile</td><td>Wall Time</td><td>CPU Time</td><td>Mem Util</td><td>Peak Mem</td></tr>\n";
	foreach ($profiles as $prof) {
		$prof_parts = explode(":", basename($prof));
		$page = $prof_parts[3];
		$tod = date("Y/m/d G:i:s", $prof_parts[1]);

		echo "<tr><td><a href=\"" . get_prof_link($prof, $server_cfg) . "\">$tod, $page</a></td>";

		$smry = get_summary($prof);
		foreach(array("wt", "cpu", "pmu", "mu") as $metric) {
			$$metric = $smry[$metric];
			echo "<td align=\"right\">{$smry[$metric]}</td>";
		}
		echo "</tr>\n";
	}

	echo "</table>\n</body>\n";
	echo "</html>";
}

function print_chooser($metrics) {
	?>
<?php
	echo "<form><table><tr>\n";
	foreach ($metrics as $m) {
		echo "<td><input class=\"metradio\" type=\"radio\" value=\"$m\" name=\"chooser\" onclick=\"chooseMetric()\">$m</input></td>\n";
	}
	echo "</tr></table></form>\n";
}


function print_table($table, $server_cfg, $game, $ts) {
	$prof_path_prefix = sprintf($server_cfg["root_upload_directory"] . "%s/xhprof",
				    $game, $ts);				    

	foreach ($table as $prof => $row) {
		echo "<tr class=\"gradeA\">\n";
	 	$page_parts = explode(":", basename($prof));
		$page = $page_parts[3];
		$tod = date("Y/m/d G:i:s", $page_parts[1]);

		$prof_path = sprintf("%s/%s/%s", $prof_path_prefix, $page_parts[2], $prof);

		echo "<td><a href=\"" . get_prof_link($prof_path, $server_cfg) . "\" target=\"$page\">";
		// echo "$tod, $page</a></td>\n";
		echo "$page</a></td>\n";
		for ($i = 1; $i <= 5; $i++) {
		  echo sprintf("<td>%s</td><td>%d</td>", $row["n$i"], $row["v$i"]);
		}
		echo "\n</tr>\n";
	}
}

function htmlize_top5($server_cfg)
{
	$ts = NULL;
	$game = $_GET['game'];
	$slot_html = get_slot_list($ts, $server_cfg, "top5");
 
	$top5_file = sprintf($server_cfg["top5_file"], $game, $ts);
	$top5 = igbinary_unserialize(file_get_contents($top5_file));

	echo "<html><head><title>Top5 for 30 minute slot at $ts</title>\n";
?>
<style type="text/css" title="currentStyle">
	@import "/zperfmon/js/datatables.1.9.4/css/demo_page.css";
	@import "/zperfmon/js/datatables.1.9.4/css/demo_table.css";
</style>
<script type="text/javascript" language="javascript" src="/zperfmon/js/datatables.1.9.4/js/jquery.js"></script>
<script type="text/javascript" language="javascript" src="/zperfmon/js/datatables.1.9.4/js/jquery.dataTables.js"></script>
<script type="text/javascript" charset="utf-8">
$(document).ready(function() {
	$('.dataTable').dataTable({
	    "bAutoWidth": false
	      });
} );
</script>
</head>
<body>
<?php
	echo $slot_html, "\n";
	echo '<div id="tablebox">', "\n";
	
	$description = array("ct" => "count",
			     "excl_wt" => "Exclusive Wall Time in milliseconds",
			     "excl_cpu" => "Exclusive CPU time in milliseconds",
			     "excl_mu" => "Exclusive Memory allocated by function, in bytes",
			     "excl_pmu" => "Process size growth in function, in bytes");

	if (isset($_GET["metrics"])) {
		$show = explode(",", $_GET["metrics"]);
	} else {
		$show = array_keys($top5);
	}

	$hide_status = "";
	foreach ($show as $metric) {
		$data = $top5[$metric];
		$desc = $description[$metric];
		echo "<p style=\"clear:both;background-color:grey;color:white;letter-spacing:3px;padding-top:1em;\">Top-5 functions by $desc</p>\n";
		echo "<div id=\"{$metric}_div\" $hide_status>\n<table id=\"{$metric}_table\" name=\"$metric\" class=\"display dataTable\">\n";
		echo "<thead><tr><th>profile</th>\n";
		for ($i = 1; $i <= 5; $i++) {
			echo "<th>func-$i</th><th>$metric-$i</th>";
		}
		echo "</tr></thead>\n";
		print_table($data, $server_cfg, $game, $ts);
		echo "</table></div><div></div>";
		// $hide_status = "style=\"display: none;\"";
	}
	echo "</body></html>";
}

function htmlize_ip_list($server_cfg)
{
	$prof_path = dirname(get_path($server_cfg));

	echo "<html><head><title>$prof_path</title>\n";
	echo get_slot_list($ts, $server_cfg);
	echo "<table>\n";
	
	
	echo "\n";
	$profiles = glob($prof_path."/[0-9]*", GLOB_ONLYDIR);
	$uniq_ips = array();
	foreach ($profiles as $prof) {
		$ip_parts = explode("xhprof", basename($prof));
		$ip = $ip_parts[0];
		$game = $_GET['game'];

		if ( !array_key_exists($ip, $uniq_ips) ) {
			$uniq_ips[$ip] = $game;
			echo "<tr><td><a href=\"" . get_ip_link($ip, $game, $server_cfg) . "?ts=$ts\">$ip</a></td></tr>\n";
		}
	}

	echo "</table>\n</body>\n";
	echo "</html>";
}

if (isset($_GET["iplist"])) {
	htmlize_ip_list($server_cfg);
} else if (isset($_GET["top5"])) {
	htmlize_top5($server_cfg);
} else {
	htmlize_ip_dir($server_cfg);
}
