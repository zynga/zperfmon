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
* zPerfmon: Instances Email Report
* @author Saurabh Odhyan (sodhyan@zynga.com)
*/

include_once 'server.cfg';
include_once 'game_config.php';
include_once "instance-util-adapter.php";
include_once "report-markup.php";
include_once "report-css.php";
include_once "report-collector.php";

$game_list = $server_cfg['game_list'];
if(isset($_GET["game"])) {
	$game = $_GET["game"];
} else {
	$game = $game_list[0];
}

$game_cfg = load_game_config($game);

if(isset($game_cfg["cloud_name"]) && $game_cfg["cloud_name"] == "zcloud") {
	define("DAU_PI_GOOD", $server_cfg['dau_pi_good_zc']);
	define("DAU_PI_BAD", $server_cfg['dau_pi_bad_zc']);
} else {
	define("DAU_PI_GOOD",  $server_cfg['dau_pi_good']);
	define("DAU_PI_BAD", $server_cfg['dau_pi_bad']);
}
define("NA", "<i>-- NA --</i>");
define("SLACK_GOOD", 15);
define("SLACK_BAD", 30);

$instance_util_obj = new InstanceUtilAdapter($game);


function get_game_status($dau) {
	if($dau >= DAU_PI_GOOD) {
		$status = "GREEN";
    } else if($dau >= DAU_PI_BAD) {
		$status = "ORANGE";
    } else {
		$status = "RED";
    }
    $markup = "<b>Status:<sup>1</sup> </b><span style='background-color:$status;'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp</span>";
	return $markup;
}

function format_dau_per_instance($dau) {
	$dauStr = number_format($dau);
	if($dau >= DAU_PI_GOOD) {
		return "<font color='GREEN'>$dauStr</font>";
	} else if($dau >= DAU_PI_BAD) {
		return "<font color='ORANGE'>$dauStr</font>";
	} else {
		return "<font color='RED'>$dauStr</font>";
	}
}

function print_dau_summary() {
	global $instance_util_obj;
	$dau = $instance_util_obj->get_dau();
	$instances = $instance_util_obj->get_instances_detail_data();
	$total_cost = 0;
	$total_instances = 0;
	foreach($instances as $class => $data) {
		$total_cost += $data["cost"];
		$total_instances += $data["count"];
	}
	$cost_per_user = round($total_cost/$dau, 4);
	$dau_per_instance = round($dau/$total_instances);
	$markup = get_game_status($dau_per_instance);
	$markup .= "<div><b>DAU:</b> " . number_format($dau) . "</div>";
	$markup .= "<div><b>DAU per Instance:<sup>1</sup></b> " . format_dau_per_instance($dau_per_instance) . "</div>";
	//$markup .= "<div><b>Cost per user:</b> $$cost_per_user per day</div>";
	$markup .= "<br/>";
	echo $markup;
}

function format_slack($slack) {
	if($slack === NA) {
		return $slack;
	} else if($slack < 0) {
		return "<font color='DARKRED'><b>$slack</b></font>";
	} else if($slack <= SLACK_GOOD) {
		return "<font color='GREEN'>$slack</font>";
	} else if($slack <= SLACK_BAD) {
		return "<font color='ORANGE'>$slack</font>";
	} else {
		return "<font color='RED'>$slack</font>";
	}
}

function sort_by_instance_count_cmp($a, $b) {
	if($a["count"] == $b["count"]) {
		return 0;
	} else {
		return ($a["count"] > $b["count"]) ? -1 : 1;
	}
}

function sort_by_instance_count($instances) {
	uasort($instances, "sort_by_instance_count_cmp");
	return $instances;
}

function print_instances_detail($game) {
	$reportDataCollector = new ReportCollector($GLOBALS['server_cfg']);
	$tbl_struct = array(
        array(
            "label" => "Instance Class",
            "bold" => 1
        ),
		array(
			"label" => "Total Instances",
			"align" => "right",
		),
		array(
			"label" => "DAU per Instance",
			"align" => "right",
		),
		/*
        array(
            "label" => "Cost per day ($)*",
			"align" => "right",
        ),
		*/
		array(
			"label" => "Optimal Instance <br/> count<sup>2</sup>", 
			"align" => "right",
		),
		array(
            "label" => "% Slack<sup>3</sup>",
            "align" => "right",
        ),
    );

	$tbl_data = array();

	global $instance_util_obj;
	$instances = $instance_util_obj->get_instances_detail_data();
	$total = $instance_util_obj->get_game_summary();
	$dau = $instance_util_obj->get_dau();

	$instances = sort_by_instance_count($instances);
	
	$instances["TOTAL"] = $total; //add total data to the bottom

	
	$i = 0;
	foreach($instances as $class => $data) {
		if($data["count"] == null || $data["count"] == 0) { //if instance count is 0, don't show in report
            continue;
        }
		$dau_per_instance = round($dau/$data["count"]);
		if($data["optimal_instance_count"] == 0) {
			$slack = NA;
			$optimal_count = NA;
		} else {
			$slack = number_format((($data["count"] - $data["optimal_instance_count"]) * 100)/$data["count"], 2)."%";
			$optimal_count = number_format($data["optimal_instance_count"]);
		}
		
		
		/*
		 * Review Comment :- create a function for this 
		 * not creating a function as the no of parameters are too many 
         * and creating a fn would not be helpful
		 */
		if(isset($_GET['store_report']) && $_GET['store_report'] == 'true') {
		
			$report_data['class_name'] = $class;
			$report_data['game'] =  $game;
			$report_data['total_instance'] = $data["count"];
			$report_data['DAU_per_instance'] = $dau_per_instance;
			$report_data['optimal_instance_count'] = $data["optimal_instance_count"];
			if ( $data["count"] > 0 ){
				$report_data['slack_per'] = (($data["count"] - $data["optimal_instance_count"])*100)/$data["count"];
			}
			else {
				$report_data['slack_per'] = 0;
			}
			$reportDataCollector->insertInstanceClassSummary($report_data);

		}
		
		
		
		$tbl_data[$i][] = $class; //instance class
		$tbl_data[$i][] = number_format($data["count"]); //instances
		$tbl_data[$i][] = number_format($dau_per_instance);
		//$tbl_data[$i][] = number_format($data["cost"]); //total cost
		$tbl_data[$i][] = $optimal_count; //recommended instance count
		$tbl_data[$i][] = format_slack($slack);
		$i++;
	}
	
	$markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

function get_util($utilArr) {
	$ret = NA;
	if($utilArr !== null) {
		$utilKey = $utilArr["name"];
		$utilVal = min(100, round($utilArr["utilization"]));
		$ret = "$utilKey: $utilVal%";
	}
	return $ret;
}

function get_headroom($util) {
	return 100 - round($util);
}

function print_instances_breakup() {
	$tbl_struct	= array(
		array(
			"label" => "Instance Class",
			"bold" => 1,
		),
		array(
			"label" => "Array/Pool name",
		),
		array(
			"label" => "Instance type",
		),
		array(
            "label" => "Total Instances",
            "align" => "right",
        ),
		array(
			"label" => "DAU per Instance",
			"align" => "right",
		),
		/*
		array(
			"label" => "Cost per day*",
			"align" => "right",
		),
		array(
            "label" => "Projected cost <br/> per year ($)",
            "align" => "right",
        ),
		*/
		array(
			"label" => "% Utilization",
		),
		array(
			"label" => "Optimal instance <br/> count<sup>2</sup>",
			"align" => "right",
		),
		/*
		array(
			"label" => "Optimal cost <br/> per day",
			"align" => "right",
		),
		array(
			"label" => "Optimal projected <br/> cost per year ($)",
			"align" => "right",
		),
		*/
		array(
			"label" => "% Slack<sup>3</sup>",
			"align" => "right",
		),
		array(
			"label" => "Bottleneck",
		),
		array(
			"label" => "Underutilized",
		),
		array(
			"label" => "% Headroom",
			"align" => "right",
		),
	);
	
	$tbl_data = array();

	global $instance_util_obj;
    $instances = $instance_util_obj->get_instances_breakup_data();
	$dau = $instance_util_obj->get_dau();
	//print_r($instances);

    $i = 0;
    foreach($instances as $class => $class_data) {
		foreach($class_data as $pool => $data) {
			if($data["count"] == null || $data["count"] == 0) { //if instance count is 0, don't show in report
         	   continue;
        	}
			$dau_per_instance = round($dau/$data["count"]);
			$util = get_util($data["util"]);
			$bottleneck = $data["util"]["bottleneck_key"];
			$underutilized = $data["util"]["underutil_key"];
			if($bottleneck == "") {
				$bottleneck = "-";
			}
			if($underutilized == "") {
				$underutilized = "-";
			}

			if($util == NA) {
				$optimal_count = NA;
				$optimal_cost = NA;
				$optimal_cost_year = NA;
				$slack = NA;
				$headroom = NA;
			} else {
//				$optimal_count = number_format($data["optimal_instance_count"]);
				$optimal_count = "-";
				$optimal_cost = number_format($data["optimal_cost"]);
				$optimal_cost_year = number_format($data["optimal_cost"] * 30 * 365);
				$slack = number_format((($data["count"] - $data["optimal_instance_count"]) * 100)/$data["count"], 2)."%";
				$headroom = get_headroom($data["util"]["utilization"])."%";
			}

			$tbl_data[$i][] = $class; //instance class
			if ($pool != "ungrouped") {
				$tbl_data[$i][] = $pool; //pool name
			} else {
				$tbl_data[$i][] = '<a href="javascript:show_ungrouped()">ungrouped</a>';

			}
			$tbl_data[$i][] = $data["type"]; //instance type
			$tbl_data[$i][] = number_format($data["count"]); //number of instances
			$tbl_data[$i][] = number_format($dau_per_instance);
			//$tbl_data[$i][] = number_format($data["cost"]); //cost per day
			//$tbl_data[$i][] = number_format($data["cost"] * 30 * 365); //cost per year
			$tbl_data[$i][] = $util; //utilization
			$tbl_data[$i][] = $optimal_count; //recommended instance count
			//$tbl_data[$i][] = $optimal_cost; //Optimal cost per day
			//$tbl_data[$i][] = $optimal_cost_year; //Optimal cost per year
			$tbl_data[$i][] = format_slack($slack);
			$tbl_data[$i][] = $bottleneck;
			$tbl_data[$i][] = $underutilized;
			$tbl_data[$i][] = $headroom;
			$i++;
		}
	}

    $markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

function print_instances_type_details() {
	$tbl_struct = array(
		array(
			"label" => "Instance Type",
			"bold" => 1,
		),
		array(
			"label" => "Total Instances",
			"align" => "right",
		),
		array(
			"label" => "Optimal Instance <br/> count<sup>2</sup>",
			"align" => "right",
		),
		array(
			"label" => "% Slack<sup>3</sup>",
			"align" => "right",
		),
	);

	$tbl_data = array();

	global $instance_util_obj;
	$instances = $instance_util_obj->get_instance_type_data();
	
	$instances = sort_by_instance_count($instances);

	$i = 0;	
	foreach($instances as $type => $data) {
		if($data["count"] == null || $data["count"] == 0) { //if instance count is 0, don't show in report
            continue;
        }
		if($data["optimal_instance_count"] == 0) {
            $optimal_count = NA;
			$slack = NA;
        } else {
			$slack = number_format((($data["count"] - $data["optimal_instance_count"]) * 100)/$data["count"], 2)."%";
            $optimal_count = number_format($data["optimal_instance_count"]);
        }
		$tbl_data[$i][] = $type;
		$tbl_data[$i][] = number_format($data["count"]);
		$tbl_data[$i][] = $optimal_count;
		$tbl_data[$i][] = format_slack($slack);
		$i++;
	}

	$markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

function getReportStatus() {
    if(isset($_GET['static'])) {
        return "<i>Static</i>";
    } else {
        return "<i>Live</i>";
    }
}

function print_date() {
	$date = date("l M d H:i T");
	echo $date;
}

function print_cloud() {
    global $game_cfg;
    $cloud = "";
    if(isset($game_cfg["cloud_name"])) {
        $cloud = $game_cfg["cloud_name"];
    }
    if(strlen($cloud) > 0 && $cloud == "zcloud") {
        echo "on ZCloud";
    } else {
        echo "on EC2";
    }
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" 
  "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
<title>zPerfmon: Instance Utilization Report</title>
<style type="text/css">
<?php echo get_css(); ?>
</style>
</head>

<script language="javascript" type="text/javascript">
<!--
function show_ungrouped() {
	wndw = window.open('','name','height=500,width=250');
    
	var doc = wndw.document;

	doc.write('<html><head><title>Unclassified Instances</title>');
	doc.write('</head><body><table>');

<?php
	foreach ($instance_util_obj->get_ungrouped_instances() as $host) {
		echo "doc.write('<tr><td>$host</td></tr>');";
	} 
?>
	doc.write('</table></body></html>');
	doc.close();
}
// -->
</script>

<body>
<h1>zPerfmon: Instance Utilization Detail for <?php echo ucwords($game_cfg["sn_game_name"]); ?>  <?php print_cloud(); ?> (<?php echo getReportStatus(); ?>)</h1>
<h4><?php print_date(); ?></h4>

<?php print_dau_summary(); ?>

<table class="wrapper">
	<tr>
		<td class="wrapper">
			<table class="wrapper">
				<tr>
					<td class="wrapper"><h2>Instance Class Summary</h2></td>
				</tr>
			</table>
			<table class="wrapper">
				<tr>
					<td class="wrapper"><?php print_instances_detail($game); ?></td>
				</tr>
			</table>
		</td>
		<td style="padding-left:25px" class="wrapper">
            <table class="wrapper">
                <tr>
                    <td class="wrapper"><h2>Instance Type Summary</h2></td>
                </tr>
            </table>
            <table class="wrapper">
                <tr>
                    <td class="wrapper"><?php print_instances_type_details(); ?></td>
                </tr>
            </table>
        </td>
	</tr>
</table>

<h2>Instance Pool Breakup</h2>
<?php print_instances_breakup(); ?>

<div class="footer">
<!--<div class="note">*Amazon’s EC2 Pricing</div>-->
<div class="note">Utilization data is the peak over the last 24 hours</div>
<div class="note">-- NA --: Data not available because zmonitor-client is not installed</div>
<div class="note"><sup>1</sup>DAU per Instance: <b><font color=GREEN><?php echo DAU_PI_GOOD."+" ?></font> | <font color=ORANGE><?php echo DAU_PI_BAD."-".(DAU_PI_GOOD-1) ?></font> | <font color=RED><?php echo "0-".(DAU_PI_BAD-1) ?></font></b></div>
<!--<div class="note"><sup>2</sup>Instances needed, considering optimal resource utilization (Optimal utilization is assumed for arrays where zmonitor-client is not installed)</div>-->
<div class="note"><sup>2</sup> Pending revised logic.</div>
<div class="note"><sup>3</sup>Slack: <b><font color=GREEN><?php echo "0-".SLACK_GOOD ?></font> | <font color=ORANGE><?php echo (SLACK_GOOD+1)."-".SLACK_BAD ?></font> | <font color=RED><?php echo (SLACK_BAD+1)."+" ?></font></b></div>
<div class="note">DAU may vary from official figures. Go to <a href="http://en.wikipedia.org/wiki/Civil_registry">Stats</a> for official DAU.</div>
<div class="note">Please get in touch with the zPerfmon team at <a href="mailto:@xxxx.xxx">zperfmon-dev@xxxx.xxx</a> for further assistance</div>
</div>
</body>
</html>
