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


/**
* zPerfmon Email Report
* @author Saurabh Odhyan (sodhyan@zynga.com)
*/

include_once 'server.cfg';
include_once 'game_config.php';
include_once "report-adapter.php";
include_once "report-markup.php";
include_once "report-css.php";

$game_list = $server_cfg['game_list'];
if(isset($_GET["game"])) {
	$game = $_GET["game"];
} else {
	$game = $game_list[0];
}
$game_cfg = load_game_config($game);
$game_cfg = $game_cfg[$game];

$dataObj = new Report($server_cfg, $game_cfg);

$reportData = array(
	"pdt_profiles" => $dataObj->get_pdt_and_profiles(),
	"agg_top5_func" => $dataObj->get_agg_top5_functions(),
	"tracked_func" => $dataObj->get_tracked_functions_wt(),
	"web_eu" => $dataObj->get_web_eu(),
	"mc_eu" => $dataObj->get_mc_eu(),
	"db_eu" => $dataObj->get_db_eu(),
	"instances" => $dataObj->get_instance(),
	"dau_per_instance" => $dataObj->get_dau_per_instance()
);

function get_tracked_func_data() {
    global $reportData;
    global $dataObj;
    $pages = get_popular_pages($reportData["pdt_profiles"]["tday"]["pdt"]);
    $ret = $dataObj->get_tracked_functions_wt($pages);
    return $ret;
}

$reportData["tracked_func_all"] = get_tracked_func_data();

function round_up($v) {
	return number_format(round($v));
}

function get_table_class($class) {
	if($class == "row1") {
		return "row2";
	} else {
		return "row1";
	}
}

function get_summary_msg($dod, $wow) {
	$dodv = "<b>".abs($dod)."%</b>";
    $wowv = "<b>".abs($wow)."%</b>";
	$dec = "<font color=#458B00>dropped</font>";
	$inc = "<font color=#EE2C2C>increased</font>";

	$msg = "";
	if($dod == 0 && $wow == 0) {
		$msg .= "remained same DoD and WoW";
	} elseif($dod == 0 && $wow > 0) {
		$msg .= "$inc by $wowv WoW";
	} elseif($dod == 0 && $wow < 0) {
		$msg .= "$dec by $wowv WoW";
	} elseif($dod > 0 && $wow == 0) {
		$msg .= "$inc by $dodv DoD";
	} elseif($dod < 0 && $wow == 0) {
		$msg .= "$dec by $dodv DoD";
	}elseif($dod > 0 && $wow > 0) {
		$msg .= "$inc by $dodv DoD and $wowv WoW";
	} elseif($dod < 0 && $wow < 0) {
		$msg .= "$dec by $dodv DoD and $wowv WoW";
	} elseif($dod > 0 && $wow < 0) {
		$msg .= "$inc by $dodv DoD and $dec by $wowv WoW";
	} else {
		$msg .= "$dec by $dodv DoD and $inc by $wowv WoW";
	}
	return $msg;
}

function get_popular_pages($pdt) {	
	$ret = array();
	foreach($pdt as $page=>$info) {
		$ret[] = $page;
	}
	return $ret;
}

function pdt_summary() {
	$tbl_struct = array(
		array(
			"label" => "Page",
			"bold" => 1
		),
		array(
			"label" => "Delivery time (ms)",
			"align" => "right",
		),
		array(
			"label" => "Change",
		),
	);

	$tbl_data = array();

	global $reportData;
	$pages = get_popular_pages($reportData["pdt_profiles"]["tday"]["pdt"]);
	$i = 0;
	foreach($pages as $page) {
		$pdt_tday = $reportData["pdt_profiles"]["tday"]["pdt"][$page]["avg"];
        $pdt_yday = $reportData["pdt_profiles"]["yday"]["pdt"][$page]["avg"];
        $pdt_wday = $reportData["pdt_profiles"]["wday"]["pdt"][$page]["avg"];

		$time = round_up($pdt_tday);
        $dod = round((($pdt_tday - $pdt_yday)/$pdt_yday) * 100);
        $wow = round((($pdt_tday - $pdt_wday)/$pdt_wday) * 100);

        $msg = get_summary_msg($dod, $wow);

		$tbl_data[$i][] = $page;
		$tbl_data[$i][] = $time;
		$tbl_data[$i][] = $msg;

		$i++;
	}

	$markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
	echo $markup;
}

function tracked_func_summary() {
	$tbl_struct = array(
        array(
            "label" => "Function",
            "bold" => 1
        ),
        array(
            "label" => "Wall time (&#xb5;s)",
            "align" => "right",
        ),
        array(
            "label" => "Change",
        ),
    );

    $tbl_data = array();

    global $reportData;
	$tday = $reportData["tracked_func"]["tday"];
    $yday = $reportData["tracked_func"]["yday"];
    $wday = $reportData["tracked_func"]["wday"];
	$i = 0;
	foreach($tday as $fun=>$t) {
        $t = $t["avg"];
        $y = $yday[$fun]["avg"];
        $w = $wday[$fun]["avg"];

        $dod = round((($t - $y)/$y) * 100);
        $wow = round((($t - $w)/$w) * 100);
        $dodv = abs($dod);
        $wowv = abs($wow);
        $time = round_up($t);

        $msg = get_summary_msg($dod, $wow);

		$tbl_data[$i][] = $fun;
        $tbl_data[$i][] = $time;
        $tbl_data[$i][] = $msg;

        $i++;
    }

    $markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

function top5func_summary() {
	$tbl_struct = array(
        array(
            "label" => "Today",
        ),
        array(
            "label" => "Yesterday",
        ),
        array(
            "label" => "Last week",
        ),
    );

    $tbl_data = array();

    global $reportData;
	$tday = $reportData["agg_top5_func"]["tday"];
    $yday = $reportData["agg_top5_func"]["yday"];
    $wday = $reportData["agg_top5_func"]["wday"];

	for($i = 0; $i < sizeof($tday); $i++) {
		$tbl_data[$i][] = $tday[$i]["function"];
        $tbl_data[$i][] = $yday[$i]["function"];
        $tbl_data[$i][] = $wday[$i]["function"];
	}

    $markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

function get_pdt_markup($page) {
    $markup = "<table class=label>";
    $markup .= "<tr><td>Page Delivery Time (ms)</td></tr>";
    $markup .= "</table>";

	$tbl_struct = array(
        array(
            "label" => "Today",
			"minmax" => 1,
        ),
        array(
            "label" => "Yesterday",
			"minmax" => 1,
        ),
        array(
            "label" => "Last week",
			"minmax" => 1,
        ),
    );

	$tbl_data = array();

	global $reportData;

	//avg
	$i = 0;
	foreach($reportData["pdt_profiles"] as $day=>$data) {
		$tbl_data[0][$i]["avg"] = round_up($data["pdt"][$page]["avg"]);
		$i++;
	}

	//min-max
	$i = 0;
	foreach($reportData["pdt_profiles"] as $day=>$data) {
		$tbl_data[0][$i]["min"] = round_up($data["pdt"][$page]["min"]);
		$tbl_data[0][$i]["max"] = round_up($data["pdt"][$page]["max"]);
		$i++;
	}

    $markup .= ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);

	return $markup;
}

function get_top5_func_markup($key, $page, $label) {
	$markup = "<table class=label>";
    $markup .= "<tr><td>$label</td></tr>";
    $markup .= "</table>";

	$tbl_struct = array(
		array(
			"label" => "Function",
		),
        array(
            "label" => "Today",
			"align" => "right",
        ),
        array(
            "label" => "Yesterday",
			"align" => "right",
        ),
        array(
            "label" => "Last week",
			"align" => "right",
        ),
    );

    $tbl_data = array();

    global $reportData;

	for($i = 0; $i < 5; $i++) {
		$tbl_data[$i][] = $reportData["pdt_profiles"]["tday"]["profiles"][$page][$key][$i][0];
		$tbl_data[$i][] = round_up($reportData["pdt_profiles"]["tday"]["profiles"][$page][$key][$i][1]);
		$tbl_data[$i][] = round_up($reportData["pdt_profiles"]["yday"]["profiles"][$page][$key][$i][1]);
		$tbl_data[$i][] = round_up($reportData["pdt_profiles"]["wday"]["profiles"][$page][$key][$i][1]);
	}

    $markup .= ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);	
	return $markup;
}
function get_tracked_markup($page) {
	$markup = "<table class=label>";
    $markup .= "<tr><td>Inclusive Wall Time of Tracked Functions (&#xb5;s)</td></tr>";
    $markup .= "</table>";

	$tbl_struct = array(
        array(
            "label" => "Function",
        ),
        array(
            "label" => "Today",
            "align" => "right",
        ),
        array(
            "label" => "Yesterday",
            "align" => "right",
        ),
        array(
            "label" => "Last week",
            "align" => "right",
        ),
    );

    $tbl_data = array();

    global $reportData;
	$data = $reportData["tracked_func_all"];
    $funArr = $data["tday"][$page]; //tracked functions
	$i = 0;
	foreach($funArr as $fun => $v) {
		$tbl_data[$i][] = $fun;
		$tbl_data[$i][] = round_up($data["tday"][$page][$fun]);
        $tbl_data[$i][] = round_up($data["yday"][$page][$fun]);
        $tbl_data[$i][] = round_up($data["wday"][$page][$fun]);
		$i++;
	}

    $markup .= ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    return $markup;
}

function popular_pages_details() {
	global $reportData;
    $pages = get_popular_pages($reportData["pdt_profiles"]["tday"]["pdt"]);
	$markup = "";
    
	foreach($pages as $page) {
		$markup .= "<h2>$page</h2>";

		$markup .= "<table class=noborder>";

		//PDT
		$markup .= "<tr><td class='nopadding noborder'>".get_pdt_markup($page)."</td></tr>";
		
		//Top 5 functions
		$markup .= "<tr><td class='nomargin nopadding noborder'>";
		$markup .= "<table class='nomargin nopadding'>";
		$markup .= "<tr>";
		$markup .= "<td class='nopadding noborder'>".get_top5_func_markup("Exclusive Wall time", $page, "Exclusive Wall time (&#xb5;s)")."</td>";
		$markup .= "<td class='nopadding noborder' style='padding-left:20px'>".get_top5_func_markup("Exclusive CPU time", $page, "Exclusive CPU time (&#xb5;s)")."</td>";
		$markup .= "</tr>";
		$markup .= "</table>";
		$markup .= "</td></tr>";

		//Tracked functions
		$markup .= "<tr><td class='nomargin nopadding noborder'>".get_tracked_markup($page)."</td></tr>";
		
		$markup .= "</table>";
	}
	echo $markup;
}

$keyMap = array(
	"web_eu" => array(
		array(
			"key" => "web_cpu",
			"label" => "CPU"
		),
		array(
			"key" => "web_mem",
			"label" => "Memory"
		),
		array(
			"key" => "web_nw",
			"label" => "Network"
		)
	),
	"mc_eu" => array(
		array(
			"key" => "mc_gets",
			"label" => "gets"
		),
		array(
			"key" => "mc_sets",
			"label" => "sets"
		),
		array(
			"key" => "mc_hits",
			"label" => "hits"
		),
		array(
			"key" => "mc_misses",
			"label" => "misses"
		)
	),
	"db_eu" => array(
		array(
			"key" => "db_md0_disk_ops",
			"label" => "md0_disk_ops"
		),
		array(
			"key" => "db_select",
			"label" => "select"
		),
		array(
			"key" => "db_insert",
			"label" => "insert"
		)
	),
	"instances" => array(
		array(
			"key" => "DAU",
			"label" => "DAU"
		),
		array(
			"key" => "web_count",
			"label" => "Web"
		),
		array(
			"key" => "db_count",
			"label" => "DB"
		),
		array(
			"key" => "mc_count",
			"label" => "Memcache"
		)
	),
	"dau_per_instance" => array(
		array(
			"key" => "web_count",
			"label" => "Web"
		),
		array(
			"key" => "db_count",
			"label" => "DB",
		),
		array(
			"key" => "mc_count",
			"label" => "Memcache"
		)
	)
);

/*
* @method get_eu_markup
*/
function get_eu_markup($eu, $summary = 0) {
	$tbl_struct = array(
		array(
			"label" => "&nbsp;",
			"align" => "left",
			"bold" => 1
		),
		array(
			"label" => "Today",
			"align" => "right",
		),
		array(
			"label" => "Yesterday",
			"align" => "right",
		),
		array(
			"label" => "Last week",
			"align" => "right",
		)
	);

	global $reportData;
	global $keyMap;
	$data = array();
	$keys = $keyMap[$eu];
	for($i = 0; $i < sizeof($keys); $i++) {
    	$tbl_data[$i] = array();
        $tbl_data[$i][0] = $keys[$i]["label"];
		$key = $keys[$i]["key"];
		$j = 1;
		foreach($reportData[$eu] as $day=>$data) {
			if($summary == 1) {
				$tbl_data[$i][$j] = round_up($data["avg"][$key]);
			} else {
				$tbl_struct[$j]["minmax"] = 1;
				if($eu == "web_eu") {
					$avg = round_up($data["avg"][$key] * 100);
					$min = round_up($data["min"][$key] * 100);
					$max = round_up($data["max"][$key] * 100);
				} else {
					$avg = round_up($data["avg"][$key]);
					$min = round_up($data["min"][$key]);
					$max = round_up($data["max"][$key]);
				}
				$tbl_data[$i][$j] = array(
					"avg" => $avg,
					"min" => $min,
					"max" => $max
				);
			}
			$j++;
		}
	}	
	
	return ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data); 
}

function print_web_eu() {
	$markup = "";
    $markup .= "<h2>Web Utilization (%)</h2>";
	$markup .= get_eu_markup("web_eu");
	echo $markup;
}

function print_mc_eu() {
	$markup = "";
    $markup .= "<h2>Memcache Utilization (per second)</h2>";
    $markup .= get_eu_markup("mc_eu");
    echo $markup;
}

function print_db_eu() {
    $markup = "";
    $markup .= "<h2>DB Utilization (per second)</h2>";
    $markup .= get_eu_markup("db_eu");
    echo $markup;
}

function print_instances() {
	$markup = "";
    $markup .= "<h2>Total Instances</h2>";
	$markup .= get_eu_markup("instances");
	echo $markup;
}

function print_dau_per_instance() {
	$markup = "";
    $markup .= "<h2>DAU per Instance</h2>";
    $markup .= get_eu_markup("dau_per_instance");
	echo $markup;
}

function instances_summary() {
	echo get_eu_markup("instances", 1);
}

function print_date() {
	$date = date("l M d, Y");
	echo $date;
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
  "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<title>zPerfmon Daily Report</title>
<style type="text/css">
<?php echo get_css(); ?>
</style>
</head>

<body>
<h1>zPerfmon: Daily performance report for <?php echo ucwords($game_cfg["sn_game_name"]); ?></h1>
<h4><?php print_date(); ?></h4>

<h2>Instances</h2>
<?php instances_summary(); ?>

<h2>Top 5 popular pages based on number of hits in last 24 hours</h2>
<?php pdt_summary(); ?>

<h2>Tracked functions across all pages</h2>
<?php tracked_func_summary(); ?>

<h2>Top 5 functions by exclusive wall time across all pages</h2>
<?php top5func_summary(); ?>

<hr>
<h1 class="detail">DETAILS</h1>

<?php print_web_eu(); ?>
<?php print_mc_eu(); ?>
<?php print_db_eu(); ?>

<?php print_instances(); ?>
<?php print_dau_per_instance(); ?>

<hr class="subdiv">

<?php popular_pages_details(); ?>
</body>
</html>
