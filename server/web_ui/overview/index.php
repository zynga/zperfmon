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
* zPerfmon Dashboard
* @author Saurabh Odhyan (sodhyan@zynga.com)
*/ 

$core_include = "/var/www/html/zperfmon/include/";
set_include_path(get_include_path() . ":$core_include");
include_once 'server.cfg';
include_once "../report/instance-util-adapter.php";

//get the current page url
function curPageURL() {
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}
$pageURL = curPageURL();

header("Refresh: 300; URL=$pageURL"); //refresh the page every 5 min

function get_game_list() {
	global $server_cfg;
	$games = $server_cfg["overview_games"];
	sort($games);
	return $games;
}
$game_list = get_game_list();

$warnMsgArr = array();

function get_instance_util_data($game) {
	define('TTL', 30 * 60); //cache expiry time for apc is 30min

	$key = "instance_util_data_$game";
	$instances_obj = apc_fetch($key, $success);

	if($success) {
		return $instances_obj;
	} else {
		$instance_util_obj = new InstanceUtilAdapter($game);
		$instances_obj = array(
			"instances" => $instance_util_obj->get_instances_detail_data(),
			"total" => $instance_util_obj->get_game_summary(),
			"dau" => $instance_util_obj->get_dau()
		);
		apc_add($key, $instances_obj, TTL);
		return $instances_obj;
	}
}

if(!isset($_GET["demo"])) {
	include_once 'game_config.php';
	include_once 'overview-adapter.php';
	if(!isset($_GET['game'])) {
		if(isset($_GET['gameid'])){
	        include_once('gameidMap.php');
			define('TTL', 30 * 60); //cache expiry time for apc is 30min
			$key = "gameid";
			$gameidArray = apc_fetch($key, $success);
			if($success) {
			}
			else {
				$gameidArray = getGameMapping($server_cfg);
				apc_add($key, $gameidArray, TTL);
			}
			$game = $gameidArray[$_GET['gameid']];
			if(!isset($game)) {
				$game  = $server_cfg['game_list'][0];
			}
        }
		else {
			$game  = $server_cfg['game_list'][0];
		}
	}
	 else {
		$game = $_GET['game'];
	}

    $instances_obj = get_instance_util_data($game);

	$game_cfg = load_game_config($game);
	$dataObj = new OverviewAdapter($game_cfg);
} else {
	include_once 'overview-data.php';
	$dataObj = new OverviewData(null);
}

if(isset($_GET["cycle"])) { //cycle through games
	$idx = array_search($game, $game_list);
	$len = sizeof($game_list);
	$idx++; 
	if($idx == $len) {
		$idx = 0;
	}
	$pageURL = "index.php?game=$game_list[$idx]&cycle";
	header("Refresh: 60; URL=$pageURL"); //cycle every 1 min
}

$overviewData = array(
	"game_info" => $dataObj->get_game_info(),
	"dau" => $dataObj->get_dau(),
	"web_eu" => $dataObj->get_web_eu(),
	"web_rps" => $dataObj->get_web_rps(),
	"proxy_eu" => $dataObj->get_proxy_eu(),
	"deployment_eu" => $dataObj->get_deployment_eu(),
	"instances" => $dataObj->get_instances(),
	"splunk" => $dataObj->get_splunk(),
	"page_times" => $dataObj->get_page_times(),
	"profile_data" => $dataObj->get_profile_data()
);

function print_gauge($id, $name, $v, $min, $max, $good, $bad, $highest)
{
	$big = floor(round($v, 2));
        if ( $big < 0 ) {
                $big = 0;
        }
	$small = abs(round($v - $big, 2)*100);
	$pct = floor($v);

	if($v <= $good) $kind = "good";
	else if($v >= $good && $v <= $bad) $kind = "bad";
	else $kind = "ugly";

	//normalize the values to the scale of [0,1]
	if($min < 0) {
		$max -= $min;
		$v -= $min; 
		$highest -= $min;
		$min = 0;
	}
	$range = $max - $min;
	$nv = $v/$range;
	$nhighest = $highest/$range;

	//find the height upto which the gauge needs to be filled
	$h = $nv * 140; //140px fixed size in css
	$h_max = ($nhighest * 140)."px";

	echo <<<EOF
	<div class="gHolder left">
		<div class="gLabel">$name</div>
		<div class="gGlass g_$kind"></div>
		<div class="gBigNumber">$big</div>
		<div class="gSmallNumber">%</div>
		<div class="left">
			<div class="gFill g_max" style="height: $h_max"></div>
		</div>
		<div class="left">
			<div id="gFill_$id" class="gFill g_$kind" style="height: 2px"></div>
		</div>
		<div class="gBackground g_$kind"></div>
	</div>
	<script type="text/javascript">
	$('#gFill_$id').animate({height: $h}, 2000, "swing");
	</script>
EOF;
}

function print_gauge2($id, $name, $v, $min, $max, $highest)
{
    $big = floor(round($v, 2));
    if ( $big < 0 ) {
           $big = 0;
    }

    $small = abs(round($v - $big, 2)*100);
	$kind = "good";

    //normalize the values to the scale of [0,1]
    if($min < 0) {
        $max -= $min;
        $v -= $min;
        $highest -= $min;
        $min = 0;
    }
    $range = $max - $min;
    $nv = $v/$range;
    $nhighest = $highest/$range;

    //find the height upto which the gauge needs to be filled
    $h = $nv * 140; //140px fixed size in css
    if($nhighest > 1) {
       $nhighest = 1;
    }

    $h_max = ($nhighest * 140)."px";

    echo <<<EOF
    <div class="gHolder left">
        <div class="gLabel">$name</div>
        <div class="gGlass g_$kind"></div>
        <div class="gBigNumber">$big</div>
        <div class="left">
            <div class="gFill g_max" style="height: $h_max"></div>
        </div>
        <div class="left">
            <div id="gFill_$id" class="gFill g_$kind" style="height: 2px"></div>
        </div>
        <div class="gBackground g_$kind"></div>
    </div>
    <script type="text/javascript">
    $('#gFill_$id').animate({height: $h}, 2000, "swing");
    </script>
EOF;
}

function get_arrow($type)
{
    switch($type) {
        case "more":
        {
            return  "&uarr;";
        }
        break;
        case "less":
        {
            return "&darr;";
        }
        break;
        case "same":
        {
            return "";
        }
        break;
    }
    return "?";
}

function print_arrow($diff, $rev = 0) {
	$diff = round($diff, 2);
	if($diff == 0) return;
	if($diff > 0) {
		$type = 'more';
		$diff = '+'.$diff;
		if(!$rev) {
			$col = 'red';
		} else {
			$col = 'green';
		}
	} else if($diff < 0) {
		$type = 'less';
		if(!$rev) {
			$col = 'green';
		} else {
			$col = 'red';
		}
	} else {
		$type = 'same';
		$col = 'black';
	}
	$arrow = get_arrow($type);
	echo <<<EOF
	<div class="arrow-wrapper $col left">
		<div class="arrow">$arrow</div>
		<div class="diff">($diff%)</div>
	</div>
EOF;
}

function print_gauges() {
	global $overviewData;
	$web_eu = $overviewData['web_eu'];

	$dataArr = array(
        "web_mem" => $web_eu['memory'],
        "web_cpu" => $web_eu['cpu'],
        "web_rps" => $overviewData['web_rps'],
    );

	$threshold = array(
		"web_mem" => 80,
		"web_cpu" => 90
	);

	foreach($dataArr as $key => $data) {
		$curr = $data['current'];
		$prev = $data['previous'];
		$diff = $curr - $prev;
		$label = $data['label'];
		$highest = $data['max'];

		echo '<div class="gauge-wrapper left">';
		if($key == "web_rps") { //no percentage, no color coding
			print_gauge2($key, $label, $curr, 0, 500, $highest);
		} else {
			print_gauge($key, $label, $curr, 0, 100, 40, $threshold[$key], $highest);
    		print_arrow($curr - $prev);
		}
		echo '</div>';

		if($key != "web_rps" && $curr > $threshold[$key]) {
			global $warnMsgArr;
			$warnMsgArr[] = "Web ".$label." has exceeded its warning threshold of ".$threshold[$key]."%.";
		}
	}
}

function print_meter($val) {
	$val = round($val);
    if($val <= 30) {
        $col = "green";
    } else if($val <= 60) {
        $col = "orange";
    } else {
        $col = "red";
    }
    echo <<<EOF
    <div class="bar">
        <div class="meter $col">
            <span title="$val%" style="width: $val%"></span>
        </div>
        <div class="value">$val%</div>
    </div>
EOF;
}

function print_deployment_eu() {
	global $overviewData;
	$dep_eu = $overviewData['deployment_eu'];
	foreach($dep_eu as $key => $data) {
		$curr = $data['current'];
        $prev = $data['previous'];
        $diff = $curr - $prev;
        $label = $data['label'];

		echo '<div class="meter-wrapper">
				<div class="label">' . $label . '</div>';
		print_meter($curr);
        echo '</div>';
	}
}


function print_dau() {
	global $overviewData;
	$dau = $overviewData['dau'];
	$dau_current = number_format($dau['current']);
	if ( $dau_current < 0 ) {
                $dau_current = 0;
        }
	echo <<<EOF
	<span class="left" title="Active users from 24 hours ago to now">
		<span class="key">Rolling DAU<span class="superscript hide">DOD</span></span>
		<span class="val">$dau_current<sup>*</sup></span>
		<span style="font-size:16px;float:right;">*Rolling DAU is not an official metric</span>
	</span>
EOF;
	$diff = $dau['current'] - $dau['previous'];
	$per = round(($diff * 100)/$dau['previous'], 2);
	//print_arrow($per, 1);	

	if($diff < 0) {
		global $warnMsgArr;
		//$warnMsgArr[] = "DAU dropped by ".number_format(-$diff)." day over day.";
	}
}

function get_table_arrow($diff) {
	if($diff > 0) {
		return '<span class="arrow2 red">&uarr;</span>';
	} else if($diff < 0) {
		return '<span class="arrow2 green">&darr;</span>';
	} else {
		return "";
	}
}

function print_splunk() {
	global $overviewData;
	$splunk = $overviewData['splunk'];
	$splunk_prev_fatal = $splunk['previous']['fatal'];
	$splunk_prev_warning = $splunk['previous']['warning'];
	$splunk_prev_info = $splunk['previous']['info'];
	$splunk_curr_fatal = $splunk['current']['fatal'];
	$splunk_curr_warning = $splunk['current']['warning'];
	$splunk_curr_info = $splunk['current']['info'];
	$arrow_fatal = get_table_arrow($splunk_curr_fatal - $splunk_prev_fatal);
	$arrow_warning = get_table_arrow($splunk_curr_warning - $splunk_prev_warning);
	$arrow_info = get_table_arrow($splunk_curr_info - $splunk_prev_info);

	echo <<<EOF
	<table class="table1">
		<thead>
			<tr>
				<th class="title">SPLUNK</th>
				<th scope="col" abbr="fatal">Fatal</th>
				<th scope="col" abbr="warning">Warning</th>
				<th scope="col" abbr="info">Info</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th scope="row">Today</th>
				<td>$splunk_curr_fatal $arrow_fatal</td>
				<td>$splunk_curr_warning $arrow_warning</td>
				<td>$splunk_curr_info $arrow_info</td>
			</tr>
			<tr>
				<th scope="row">Yesterday</th>
				<td>$splunk_prev_fatal</td>
				<td>$splunk_prev_warning</td>
				<td>$splunk_prev_info</td>
			</tr>
		</tbody>
	</table>
EOF;

	$diff_fatal = $splunk_curr_fatal - $splunk_prev_fatal;
	if($diff_fatal > 0) {
		global $warnMsgArr;
		$warnMsgArr[] = "Splunk fatal errors have increased by ".$diff_fatal." since yesterday.";
	}
}	

define("NA", "<i>-- NA --</i>");
define("DAU_PI_GOOD", 3000);
define("DAU_PI_BAD", 2000);
define("SLACK_GOOD", 15);
define("SLACK_BAD", 30);

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

function get_table_markup($tbl_struct, $tbl_data) {
	$markup = "";
	$markup .= "<table class='table1'>";
	$markup .= "<thead><tr>";
	//$markup .= "<th class=title>INSTANCES</th>";
	for($i = 0; $i < sizeof($tbl_struct); $i++) {
		$v = $tbl_struct[$i]["label"];
		$markup .= "<th scope=row>$v</th>";
	}
	$markup .= "</tr></thead>";

	$markup .= "<tbody>";
	for($i = 0; $i < sizeof($tbl_data); $i++) {
		$markup .= "<tr>";
		$row = $tbl_data[$i];
		for($j = 0; $j < sizeof($row); $j++) {
			$v = $row[$j];
			$align = $tbl_struct[$j]["align"];
			$markup .= "<td style='text-align:$align'>$v</td>";
		}
	}
	$markup .= "</tbody>";
	$markup .= "</table>";
	return $markup;
}

function print_instances() {
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
        array(
            "label" => "Optimal Instance <br/> count",
            "align" => "right",
        ),
        array(
            "label" => "% Slack",
            "align" => "right",
        ),
    );

	$tbl_data = array();

    global $instances_obj;
    $dau = $instances_obj["dau"];
	$total = $instances_obj["total"];

    $instances = sort_by_instance_count($instances_obj["instances"]);
	$instances["TOTAL"] = $total; //add total data to the bottom

    $i = 0;
    foreach($instances as $class => $data) {
		if($data["count"] == null || $data["count"] == 0) {
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
        $tbl_data[$i][] = $class; //instance class
        $tbl_data[$i][] = number_format($data["count"]); //instances
        $tbl_data[$i][] = number_format($dau_per_instance);
        //$tbl_data[$i][] = number_format($data["cost"]); //total cost
        $tbl_data[$i][] = $optimal_count; //recommended instance count
        $tbl_data[$i][] = format_slack($slack);
        $i++;
    }

    $markup = get_table_markup($tbl_struct, $tbl_data);
    echo $markup;
}

function print_game_info() {
	global $overviewData;
	$game_info = $overviewData['game_info'];
	$name = strtoupper($game_info['name']);
	$version = trim($game_info['release_version'], "\"");
	if($game_info['release_timestamp'] == 0) {
		$age = "unknown";
	} else {
		$age = round((time() - $game_info['release_timestamp'])/(60 * 60)); //release age in hours
	}

	if($age != "unknown") {
		if($age <= 6) {
			$col = "red";
		} else if($age <= 18) {
			$col = "orange";
		} else {
			$col = "green";
		}
		$age .= " hours";
	}


	echo <<<EOF
	<div>
		<span id="game-name">$name</span>
	</div>
	<div>
		<span id="game-version" class="game-release">Release: <span class="val">$version</span></span>
		<span id="game-age" class="game-release">Age: <span class="val $col">$age</span></span>
	</div>
EOF;
}

function print_pdt_charts() {
	global $overviewData;
	$alertMsg = "Alert! Current page time is too high compared to yesterday.";
	$n = sizeof($overviewData['page_times']);

	echo "<ol>";
	for($i = 1; $i <= $n; $i++) {
		echo <<<EOF
		<li>
		<div class="page-times-chart-wrapper">
        	<div id="page-times-warn$i" class="warn-msg hide">$alertMsg</div>
        	<div id="page-times-chart$i"></div>
    	</div>
		</li>
EOF;
	}
	echo "</ol>";
}

function print_page_time($page_time, $id) {
	$page = $page_time['page'];
	$data = $page_time['current'];
	
	$dataStr = "";
	$len = sizeof($data);
	for($i = 0; $i < $len; $i++) {
		$dataStr .= $data[$i][1];
		if($i < $len - 1) {
			$dataStr .= ",";
		}
	}

	$curr = $data[$len - 1][1];
	$prev = $data[$len - 2][1];
	$diff = $curr - $prev;
	if($diff > 0) {
		$col = "orange";
	} else if ($diff < 0) {
		$col = "green";
	}
	$per = round(($diff*100)/$prev, 2);

	echo <<<EOF
	<li>
		<span class="sparkline">$dataStr</span>
        <a id="pdtpager-$id">$page <span class="$col">$curr ms ($per%)</span></a>
    </li>
EOF;
}

function print_page_times() {
	global $overviewData;
	$page_times = $overviewData['page_times'];
	echo "<ul>";
	for($i = 0; $i < sizeof($page_times); $i++) {
		print_page_time($page_times[$i], $i);
	}
	echo "</ul>
	<script>
	$('.sparkline').sparkline();
	</script>";
}

function print_game_list() {
	global $game_list;
	global $game;

    $len = sizeof($game_list);
	$sel_game = $game;

    for($i = 0; $i < $len; $i++) {
        echo $i.$game_list[$i];
        if($sel_game == $game_list[$i]) {
            echo '<option selected value="'.$game_list[$i].'">'.$game_list[$i].'</option>';
        } else {
            echo '<option value="'.$game_list[$i].'">'.$game_list[$i].'</option>';
        }
    }
}

function get_warn_msg() {
	global $warnMsgArr;
	return implode(' | ', $warnMsgArr);
}

?>

<!DOCTYPE HTML>
<html>
<head>
<title>zPerfmon Dashboard</title>

<script>
function getGameNames() {
	var games = "<?php echo implode(',', $game_list); ?>";
    return games.split(',');
}
</script>
<script>
    var zOverviewData = {
		game_info: <?php echo json_encode($overviewData['game_info']); ?>,
		dau: <?php echo json_encode($overviewData['dau']); ?>,
        web_eu: <?php echo json_encode($overviewData['web_eu']); ?>,
        instances: <?php echo json_encode($overviewData['instances']); ?>,
        page_times: <?php echo json_encode($overviewData['page_times']); ?>,
        agg_func: <?php echo json_encode($overviewData['profile_data']); ?>,
		splunk: <?php echo json_encode($overviewData['splunk']); ?>,
		instances: <?php echo json_encode($overviewData['instances']); ?>
    };
</script>

<?php
$rev="0.9.11-dev";
echo <<<EOF
<link href='http://fonts.googleapis.com/css?family=Ubuntu' rel='stylesheet' type='text/css'>
<link href='http://fonts.googleapis.com/css?family=Michroma' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.9.0/build/carousel/assets/skins/sam/carousel.css">
<link type="text/css" href="../css/overview.css?rev=$rev" rel="stylesheet"/>
<link type="text/css" href="../css/gauges.css?rev=$rev" rel="stylesheet"/>
<link type="text/css" href="../css/meters.css?rev=$rev" rel="stylesheet"/>
<link type="text/css" href="../css/bx_styles-3.0.css" rel="stylesheet"/>

<script src="../js/jquery-1.5.2.min.js"></script>
<script src="http://yui.yahooapis.com/combo?2.9.0/build/yahoo-dom-event/yahoo-dom-event.js&2.9.0/build/animation/animation-min.js&2.9.0/build/event-mouseenter/event-mouseenter-min.js&2.9.0/build/selector/selector-min.js&2.9.0/build/event-delegate/event-delegate-min.js&2.9.0/build/element/element-min.js&2.9.0/build/carousel/carousel-min.js"></script>
<script src="../js/jquery.bxSlider-3.0.min.js"></script>
<script src="../js/jquery.sparkline.min-1.6.js"></script>
<script src="../js/overview.main.js?rev=$rev"></script>
<script src="../js/overview.charts.js?rev=$rev"></script>
<script src="../js/sprintf-0.6.js"></script>
EOF;
?>

<body>
	<div class="hd">
		<div class="title left hdblock">zPerfmon</div>
		<div id="zoom-control" class="left hide">
			<a id="zoom-in" title="Zoom In"></a>
			<a id="zoom-out" title="Zoom Out"></a>
		</div>
		<div id="game-info" class="right">
			<div id="select-game-div" class="left hide">
				<div class="left">
					<span>Viewing Data for</span>
					<select id="select-game"><?php print_game_list(); ?></select>
					<div>
						<a href="../index.php?game=<?php echo $game; ?>">Tabbed view</a>
						|
						<?php 
							if(isset($_GET["cycle"])) {
								echo "<span><u>Cycle through games</u></span>";
							} else {
								echo "<a href=index.php?game=$game&cycle>Cycle through games</a>";
							}
						?>
					</div>
				</div>
			</div>
            <div class="left">
				<?php print_game_info(); ?>
			</div>
        </div>
		<div class="clear"></div>
	</div>

	<div class="hr"></div>
	
	<div class="bd">
		<div class="row">
			<div class="info-block">
				<?php print_dau(); ?>
			</div>

			<div class="gauges">
				<div><?php print_gauges(); ?></div>
				<div class="clear"></div>
				<div id="gauge-legend">
                    <span class="highmark-color"></span>
                    <span>The max level reached in last week</span>
                </div>
			</div>

			<div id="nagios" class="right"></div>
			
			<div class="clear"></div>
		</div>

		<div class="row">
			<div class="block">
				<div>
					<div class="left">
						<div id="carousel-page-times" class="left">
							<?php print_pdt_charts(); ?>
						</div>
					</div>
					<div id="page-times-pager" class="left">
						<?php print_page_times(); ?>
					</div>
				</div>
			</div>

			<div class="block"  style="overflow:hidden">
				<div id="carousel-pie-charts" class="left" style="overflow:hidden">
					<ol>
							<!-- Delete all the child elements they would be populated 
									on run time as needed
							-->
					</ol>
				</div>
				<div id="pie-charts-pager" class="left">
                    <a id="piepager-0">1</a>
                    <a id="piepager-1">2</a>
                </div>
			</div>

			<div class="right">
				<div class="box right">
					<span class="title">SPLUNK</span>
					<div id="splunk-charts">
						<div id="fatal-splunk-chart" class="left bar-chart"></div>
						<div id="warning-splunk-chart" class="left bar-chart"></div>
						<div id="info-splunk-chart" class="left bar-chart"></div>
						<div class="clear"></div>
					</div>
				</div>

				<div class="bar-legend right">
                    <div class="yesterday">
                        <span class="color"></span>
                        <span>Yesterday</span>
                    </div>
                    <div class="today">
                        <span class="color worse"></span>
						<span class="color better"></span>
                        <span>Today</span>
                    </div>
                </div>

				<div class="clear"></div>

				<div class="box right" id="instances-data">
					<?php print_instances(); ?>
					<div class="note">
						<span>Please get in touch with the zPerfmon team at <a href="mailto:xxxx@xxxx.xxx">zperfmon-dev@xxxx.xxx</a> for further assistance</span>
					</div>
				</div>

				<div class="clear"></div>
				
			</div>

			<div class="clear"></div>
		</div>
	</div>
	
	<div class="hr"></div>

	<div class="ft">
		<span id="alert-msg" class="hide darkred"></span>
	</div>

<script type="text/javascript">
	$('#splunkbars').sparkline('html', {type:"bar", barColor: $('#splunkbars').css('color'), height: "3em"});
	function convert_timestamps(series)
	{
		var a = series["data"];
		for(i = 0; i < a.length; i++) 
		{
			series["data"][i][0] = new Date(a[i][0]*1000);
			series["data"][i][1] = a[i][1]/1000.0;
		}
		return series;

	}
	function get_nagios_series(data, game_mon)
	{
		for(var k in data)
		{
			if(data[k].url && data[k].url.indexOf("http://"+game_mon) == 0)
			{
				return data[k];	
			}
		}
	}
	function load_nagios(data)
	{
		var game_mon = <?php
		//$game_cfg['name'] = 'city'; //dummy data for now

		if(isset($game_cfg['nagios_monitor'])) 
		{
			echo json_encode($game_cfg['nagios_monitor']);
		}
		else
		{
			echo json_encode($game_cfg['name']."-mon");
		}
		?>;
		
		var series = get_nagios_series(data, game_mon);
		if(series)
		{
			var criticals = series["unknown"];
			var history = series["unknown_history"].slice(-25);
			var l = "";
			for(var i = 0; i < history.length; i+=5)
			{
				var k = history[i][1];
				l += ((i==0) ? "" : ",") + k;
			}

			report = '<div class="left label">';
			report += 'Nagios';
			report += '<div style="font-size:12px;line-height:20px">';
			report += '<span class="right">CRITICALS</span>';
			report += '</div></div>';
			
			report += '<div class="left val">';

			if(criticals > 0)
			{
				report += '<span class="ugly" style="font-size: 3em;">'+criticals+'</span>';
			}
			else
			{
				report += '<span class="good" style="font-size: 3em;">'+criticals+'</span>';
			}

			// [0] is timestamp
			var _old = history[i-2][1];
			var _new = history[i-1][1];
			if(_new > _old)

			{
				report += '<span class="ugly" id="nagiosbars">'+l+'</span>';
			}
			else if(criticals > 0)
			{
				report += '<span class="bad" id="nagiosbars">'+l+'</span>';
			}
			else
			{
				report += '<span class="good" id="nagiosbars">'+l+'</span>';
			}

			report += '</div>';
			$('#nagios').html(report);
			$('#nagiosbars').sparkline('html', {type:"bar", barColor: $('#nagiosbars').css('color'), height: "3em"});
		}
	}
	var _s = document.createElement("script");
	_s.setAttribute("src", "http://xxxx.xxxx.xxx/graph"+(new Date()));
	document.body.appendChild(_s);
</script>

<script>
var warnMsgArr = "<?php echo get_warn_msg(); ?>";
warnMsgArr = warnMsgArr.split(' | ');
</script>

</body>
</html>

