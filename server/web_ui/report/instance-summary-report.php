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

$game_list = $server_cfg['instance_report_games'];

$ec_games = array();
$zc_games = array();

/*
$zc_games = array( //temporarily hardcoded
	"pets_zcloud", 
	"adventure_prod", 
	"frontier", 
	"treasure",
	"crime_prod",
	"castle_prod",
	"fishyahoo",
	"kingdoms_prod"
);
*/

foreach($game_list as $game) {
	$game_cfg = load_game_config($game);
	if($game_cfg['cloud_name'] == "zcloud") {
		$zc_games[] = $game;
	} else {
		$ec_games[] = $game;
	}
}
define("DAU_PI_GOOD",  $server_cfg['dau_pi_good']);
define("DAU_PI_BAD", $server_cfg['dau_pi_bad']);
define("DAU_PI_GOOD_ZC", $server_cfg['dau_pi_good_zc']);
define("DAU_PI_BAD_ZC", $server_cfg['dau_pi_bad_zc']);

function sort_by_dau_cmp($a, $b) {
    if($a["dau"] == $b["dau"]) {
        return 0;
    } else {
        return ($a["dau"] > $b["dau"]) ? -1 : 1;
    }
}

function sort_by_dau($data) {
	uasort($data, "sort_by_dau_cmp");
	return $data;
}

function format_rps($rps, $zc=0) {
	if($zc) {
		$good = 100;
		$avg = 60;
	} else {
		$good = 60;
		$avg = 40;
	}

	$ret = $rps;
	if($rps >= $good) {
		$ret = "<font color=GREEN><b>$rps</b></font>";
	} else if($rps >= $avg) {
		$ret = "<font color=ORANGE><b>$rps</b></font>";
	} else if ($rps > 0) {
		$ret = "<font color=RED><b>$rps</b></font>";
	}
	return $ret;
}

function get_game_status($dau, $zc=0) {
	if($zc) {
		$good = DAU_PI_GOOD_ZC;
		$bad = DAU_PI_BAD_ZC;
	} else {
		$good = DAU_PI_GOOD;
		$bad = DAU_PI_BAD;
	}

    if($dau >= $good) {
        $status = "GREEN";
    } else if($dau >= $bad) {
        $status = "ORANGE";
    } else {
        $status = "RED";
    }
    $markup .= "<span style='background-color:$status;'>&nbsp;&nbsp;&nbsp;&nbsp;</span>";
    return $markup;
}


/* function to calculate minimum cost among all the clouds */
function minimum_cost($ec_array, $zc_array) {
	$cost_per_user_min = -1;
	/*
	foreach  ( $ec_array as $ec => $ec_data ) {
		if ( $cost_per_user_min == -1){ 
			$cost_per_user_min = $ec_data['cost_per_user'];
		}
		$cost_per_user_min = min($cost_per_user_min, $ec_data['cost_per_user']);
	}
	*/
	foreach  ( $zc_array as $zc => $zc_data ) {
		if ( $cost_per_user_min == -1){
			$cost_per_user_min = $zc_data['cost_per_user'];
		}
		$cost_per_user_min = min($cost_per_user_min, $zc_data['cost_per_user']);
	}
	return $cost_per_user_min;
}

/* function to print data of a given cloud */
function print_games_summary($dataArr, $zc = 0 ) {
	$tbl_struct = array(
        array(
            "label" => "Game",
            "bold" => 1
        ),
        array(
            "label" => "Total Instances",
            "align" => "right",
        ),
		/*
        array(
            "label" => "Total Cost ($)*",
			"align" => "right",
        ),
		*/
		array(
			"label" => "DAU",
			"align" => "right",
		),
		array(
			"label" => "DAU per Instance",
			"align" => "right",
		),
		array(
			"label" => "RPS<sup>1</sup>",
			"align" => "right",
		),
		array(
			"label" => "Cost per User<sup>2</sup>",
			"align" => "right",
		),
		array(
			"label" => "Optimal Instance <br/> count<sup>3</sup>", 
			"align" => "right",
		),
		array(
			"label" => "% Slack",
			"align" => "right",
		),
		array(
			"label" => "Status<sup>4</sup>",
		),
    );
	
	global $server_cfg;
	if(isset($server_cfg["hostname"])) {
		$hostname = $server_cfg["hostname"];
	} else {
		$hostname = "xxxx.xxxx.xxx";
	}
	$tbl_data = array();
	$i = 0;
	foreach($dataArr as $game => $data) {
		if(isset($_GET['static'])) {
			$date = date('dm');
			$game_detail_link = "<a href='http://$hostname/zperfmon/report/static/$date/$game.html'>$game</a>";
		} else {
			$game_detail_link = "<a href='instance-detail-report.php?game=$game'>$game</a>";
		}

		$status = get_game_status($data["dau_per_instance"], $zc);

		$tbl_data[$i][] = $game_detail_link; //game name
		$tbl_data[$i][] = number_format($data["count"]); //instances
		$tbl_data[$i][] = number_format($data["dau"]); //dau
		$tbl_data[$i][] = number_format($data["dau_per_instance"]);
		$tbl_data[$i][] = format_rps($data["rps"], $zc);
		$tbl_data[$i][] = $data["cost_per_user"];
//		$tbl_data[$i][] = number_format($data["optimal_instance_count"]); //recommended instance count
		$tbl_data[$i][] = "-";
		$tbl_data[$i][] = number_format($data["slack"], 2)."%"; //% slack
		$tbl_data[$i][] = $status;
		$i++;
	}
	
	
	$markup = ReportTableMarkup::get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

/* function to fetch data for a given cloud */
function get_games_summary_info($games, $zc=0) {
	$reportDataCollector = new ReportCollector($GLOBALS['server_cfg']);
	$dataArr = array();
	$cost_per_user_min = 1000;
	foreach($games as $game) {


		$instance_util_obj = new InstanceUtilAdapter($game);
		$data = $instance_util_obj->get_game_summary();

		if($data["rps"]== 0 or $data["dau"] === NULL) { //dont show games whose data is unavailable
			continue;
		}
			
		$cost_per_user = round($data["cost"]/$data["dau"], 4);
		if($cost_per_user > 0) {
			$cost_per_user_min = min($cost_per_user, $cost_per_user_min);
		}
		$slack = round((($data["count"] - $data["optimal_instance_count"]) * 100)/$data["count"], 2);
	
		$dataArr[$game] = array(
			"count" => $data["count"],
			"rps" => round($data["rps"]),
			"cost" => $data["cost"],
			"dau" => $data["dau"],
			"dau_per_instance" => round($data["dau"]/$data["count"]),
			"cost_per_user" => $cost_per_user,
			"optimal_instance_count" => $data["optimal_instance_count"],
			"slack" => $slack,
		);
		
		
		
		
		/*
		 * Review Comment :- create a function for this 
		 * not creating a function as the no of parameters are too many 
         * and creating a fn would not be helpful
		 */

		if($_GET['store_report'] == 'true') {
			// inserting data in the report instance_utilization table
			$data_utilization['game'] = $game ;
			$data_utilization['total_instance'] = $data["count"];
			$data_utilization['DAU'] = $data["dau"];
			$data_utilization['DAU_per_instance'] = round($data["dau"]/$data["count"]);
			$data_utilization['optimal_instance_count'] = $data["optimal_instance_count"];
			$data_utilization['slack_per'] = $slack;
			$data_utilization['cloud_id'] = $zc;
			$reportDataCollector->insertInstanceUtilization($data_utilization);
		}		
		
		
	}

	$dataArr = sort_by_dau($dataArr);

	global $server_cfg;
	if(isset($server_cfg["hostname"])) {
		$hostname = $server_cfg["hostname"];
	} else {
		$hostname = "xxxx@xxxx.xxx";
	}


	return $dataArr;
}

/* function to format cost per user when minimum cost is calculated */
function format_cost_per_user($array , $min_cost) {
	 foreach  ( $array as $key => $value ) {
		$array[$key]['cost_per_user'] = round((($array[$key]['cost_per_user'])/$min_cost),1);
		if ( $array[$key]['cost_per_user'] == 1) {
			$array[$key]['cost_per_user'] = 'x';
		}
		else {
			$array[$key]['cost_per_user'] = $array[$key]['cost_per_user'].'x';
		}
        }
	return $array;
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

<body>
<h1>zPerfmon: Instance Utilization Summary (<?php echo getReportStatus(); ?>)</h1>
<h4><?php print_date(); ?></h4>
<?php 
	/* get data for both clouds ,
		find minimum cost ,
		then format cost according to min cost 
	*/
//	$ec_game_data = get_games_summary_info($ec_games);
	$ec_game_data = array();
	$zc_game_data = get_games_summary_info($zc_games, 1);
	$min_cost = minimum_cost($ec_game_data, $zc_game_data);
	$ec_game_data = format_cost_per_user($ec_game_data, $min_cost);
	$zc_game_data = format_cost_per_user($zc_game_data, $min_cost);

?>
<!--<h2>EC2 Games</h2>-->
<?php //print_games_summary($ec_game_data); ?>
<h2>zCloud Games</h2>
<?php print_games_summary($zc_game_data,1); ?>

<div class="footer">
<!--<div class="note">*Amazon’s EC2 Pricing</div>-->
<div class="note">Utilization data is the peak over the last 24 hours</div>
<div class="note"><sup>1</sup>Aggregated RPS (EC2): <b><font color=GREEN>60+</font> | <font color=ORANGE>40-59</font> | <font color=RED>1-39</font></b></div>
<div class="note"><sup>1</sup>Aggregated RPS (zCloud): <b><font color=GREEN>100+</font> | <font color=ORANGE>60-99</font> | <font color=RED>1-59</font></b></div> 
<div class="note"><sup>2</sup>x represents a unit of cost</div>
<!--<div class="note"><sup>3</sup>Instances needed, considering optimal resource utilization (Optimal utilization is assumed for arrays where zmonitor-client is not installed)</div>-->
<div class="note"><sup>3</sup>Pending revised logic.</div>
<div class="note"><sup>4</sup>DAU per Instance (EC2): <b><font color=GREEN><?php echo DAU_PI_GOOD."+" ?></font> | <font color=ORANGE><?php echo DAU_PI_BAD."-".(DAU_PI_GOOD-1) ?></font> | <font color=RED><?php echo "0-".(DAU_PI_BAD-1) ?></font></b></div>
<div class="note"><sup>4</sup>DAU per Instance (zCloud): <b><font color=GREEN><?php echo DAU_PI_GOOD_ZC."+" ?></font> | <font color=ORANGE><?php echo DAU_PI_BAD_ZC."-".(DAU_PI_GOOD_ZC-1) ?></font> | <font color=RED><?php echo "0-".(DAU_PI_BAD_ZC-1) ?></font></b></div>
<div class="note">DAU may vary from official figures. Go to <a href="http://xxxx.xxxx.xxx">Stats</a> for official DAU.</div>
<div class="note">Please get in touch with the zPerfmon team at <a href="mailto:zperfmon-dev@xxxx.xxx">zperfmon-dev@xxxx.xxx</a> for further assistance</div>
</div>

</body>
</html>
