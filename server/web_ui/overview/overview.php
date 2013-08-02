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

$core_include = "/var/www/html/zperfmon/include/";
set_include_path(get_include_path() . ":$core_include");

require_once 'setup_page.php';
require_once 'XhProfModel.php';
require_once 'dau-collector.php';
header("Cache-Control: public, max-age=400");

$PageList = null;

function getXhProfModel()
{
    global $game_cfg, $server_cfg;
    if(isset($_SESSION['XhProfModel']))
    {
        return $_SESSION['XhProfModel'];
    }
    else
    {
        $_SESSION['XhProfModel'] = new XhProfModel($server_cfg, $game_cfg);
        return $_SESSION['XhProfModel'];
    }
    
}

function get_column_vector($array, $index)
{
	$ret = array();
	foreach($array as $row)
	{
		$ret[] = $row[$index];
	}
	return $ret;
}
function get_time_series($array, $ts, $index)
{
	$ret = array();
	foreach($array as $row)
	{
		$ret[] = array($row[$ts],$row[$index]);
	}
	return $ret;
}

function print_page_times()
{
	$xhprofModelObject = getXhProfModel();
	$end_time = time();
	$start_time = $end_time - (24 * 3600);
	$pages = $xhprofModelObject->generic_execute_get_query_detail(
				"cto_get_top_pages_by_delivery_time",
				array('end_time' => $end_time,
				      'start_time' => $start_time) # 24 hours
		);
	$pages = get_column_vector($pages["rows"], 0);
	$pages = array_splice($pages, 0, 5);

	$columns = "`".implode("`,`",$pages)."`";
	$chart_result = $xhprofModelObject->generic_execute_get_query("cto_get_top_pages_avg_load_time",
				array('table' => "apache_stats_flip_avg",
				'end_time' => $end_time,
				'start_time' => $start_time,
				'columns'=> $columns));

	foreach($pages as $page) 
	{
		print_pdt($page, get_column_vector($chart_result, $page));
	}	
}

function get_pct_kind($pct)
{
	if($pct > 10) {
		$kind = "good";
	}
	else if($pct >= 0) {
		$kind = "okay";
	}
	else if($pct < -10) {
		$kind = "ugly";
	}
	else {
		$kind = "bad";
	}
	return $kind;
}

function get_arrow($kind)
{
	switch($kind) {
		case "good":
		{
			return  "&uarr;";
		}
		break;
		case "okay":
		{
			return "~"; 
		}
		break;
		case "bad":
		{
			return "&darr;";
		}
		break;
		case "ugly":
		{
			return "&darr;";
		}
		break;
	}	
	return "?";
}

function get_dau()
{
    global $game_cfg, $server_cfg;
	$gid = $game_cfg["gameid"];
        $snid = $this->game_cfg["snid"] ? $this->game_cfg["snid"] : null;
	$cid = $this->game_cfg["cid"] ? $this->game_cfg["cid"] : null;
	$end_time = time();
	$start_time = $end_time - (1800);
	$daustore = new DAUAdapter();
	$dau = $daustore->get_dau($gid, $start_time, $end_time, $snid, $cid);
	$last2 = array_splice($dau, -2, 2);
	if(count($last2) != 2) {
		return array(-1,-1);
	}
	return get_column_vector($last2, "DAU");
}

function print_dau()
{
	list($old_dau,$dau) = get_dau();
	echo number_format($dau);

	$pct = round((($dau - $old_dau) / $old_dau)*100,2);


	if($pct > 0) {
		$pct = "+".abs($pct);
		$kind = "good";
		$arrow = "&uarr;";
	} else if($pct < 0) {
		$kind = "bad";
		$arrow = "&darr;";
	}
	else {
		$pct = "0.00";
		$kind="okay";
		$arrow = "~";
	}
echo "<!-- ";
	echo "<div class='$kind' style='display:inline; font-size: 112pt; margin-top: -24pt'>${arrow}</div>";

	echo "<span class='$kind'>($pct%)</span>";
echo "-->";
}

function print_gauge($id, $name, $v)
{

$big = floor(round($v, 2));
$small = abs(round($v - $big, 2)*100); 
$pct = floor($v);

if($pct <= 40) $kind = "good";
else if($pct <= 49) $kind = "okay";
else if($pct <= 59) $kind = "bad";
else $kind = "ugly";

$h = $pct*1.4; # 140px fixed size in css
echo <<<EOF
<div class="gHolder">
		<div class="gLabel">$name</div>
  		<div class="gGlass g_$kind"></div>
			<div class="gBigNumber">$big</div>
			<div class="gSmallNumber">.$small</div>

			<div id="gFill_$id" class="gFill g_$kind" style="height: 2px"></div>
  		<div class="gBackground g_$kind"></div>
</div>
<script type="text/javascript">
$('#gFill_$id').animate({height: $h}, 2000, "swing");
</script>
EOF;
}

function get_eu($q, $kind)
{
	global $game_cfg;
	$end_time = time();
	$start_time = ($end_time - 7*24*3600);
	$xhprofModelObject = getXhProfModel();
	$chart_result = $xhprofModelObject->generic_execute_get_query($q,
			array('table' => "vertica_stats_30min",
			      'end_time' => $end_time,
			      'start_time' => $start_time,
			      'cpu_threshold' =>$game_cfg['cpu_threshold'],
			      'mem_threshold' =>$game_cfg['mem_threshold'],
			      'pkts_threshold' =>$game_cfg['pkts_threshold']));

	$columns = get_column_vector($chart_result, $kind);
	$last = array_splice($columns, -1 , 1);
	return $last[0]*100;
}

function print_headroom()
{
	print_gauge("web_cpu", "Web CPU", get_eu("eu_web_chart_range", "web_cpu")); 
	print_gauge("web_mem", "Web Memory", get_eu("eu_web_chart_range", "web_mem")); 
}

function print_pdt($page, $times)
{
	$sparkline = implode(",", $times);
	$last = array_pop($times);
	$avg = array_sum($times)/count($times);
	$pct = round((($last - $avg)/$avg) * 100);
	$kind = get_pct_kind(-1*$pct);

	if($pct >= 0) {
		$pct = "+".abs($pct);
	}

	$arrow = get_arrow(get_pct_kind(-1*$pct));
	echo <<<EOF
	<div>
	<span style="font-size: 24pt; width: 24pt; float: left; clear:left; display: block;" class="$kind">$arrow</span>
<span class="sparkline">$sparkline</span> 
$page <span class="$kind">$last</span> ms 
(<span class="$kind">$pct%</span>)
</div>
EOF;

}

function print_eu()
{
	global $game_cfg;
	$xhprofModelObject = getXhProfModel();
	$end_time = time();
	$start_time = $end_time - (2 * 3600);

	$chart_result = $xhprofModelObject->generic_execute_get_query('bd_chart_range_per_dau',
					array('table' => $game_cfg["db_stats_table"],
						'end_time' => $end_time,
						'start_time' => $start_time,
						'extra_params' => ""));

	$last2 = array_splice($chart_result, -2, 2);
	list($new, $old) =  get_column_vector($last2, "dau_all_count");
	$pct = 100*($new - $old)/($old);
	$diff = abs($new - $old);

	if($diff >= 0) {
		$kind = "good";
		$type = "more";
	} else {
		$kind = "bad";
		$type = "less";
	}
	$arrow = get_arrow($kind);

	$pct = round(abs($pct),2);

	echo <<<EOF
	Serving <span class="$kind">$new</span> users per server <span class="$kind"> $diff $type $arrow ($pct%)</span>
EOF;

}

function print_tracked_functions() {
	global $game_cfg;

	$tracked_functions = array( "MC::set", "MC::get", 
			"ApcManager::get", "ApcManager::set",
			"serialize", "unserialize",
			);
	if(isset($game_cfg["tracked_functions"])) $tracked_functions = $game_cfg["tracked_functions"];

	$columns = "`".implode("`,`",$tracked_functions)."`";


	$end_time = time();
	$start_time = $end_time - (4* 3600);
	$xhprofModelObject = getXhProfModel();
	$chart_result = $xhprofModelObject->generic_execute_get_query("cto_get_tracked_functions_by_column",
			array('table' => "tracked_functions_flip_incl_time",
				'end_time' => $end_time,
				'start_time' => $start_time,
				'page' => "all",
				'columns'=>$columns));
	print "$.plot($('#tier-chart'),[";
	foreach($tracked_functions as $func) 
	{
		$data=array("label" => $func, "data" => get_time_series($chart_result, "timestamp", $func));
		echo "convert_timestamps(".json_encode($data)."),";
	}
	echo <<<EOF
	]
	,{
		series: {stack: true,  lines: {show: true, fill: true}},
		legend: {show: true, position: "sw"},
		xaxis: { mode: "time",  minTickSize: [1, "hour"] },
		yaxis: { label: "ms" },
	});
EOF;
}

function print_nagios()
{
	global $game_cfg;
}

?>

<html>
<head>
<title> Overview </title>
</head>
<body>
<style type="text/css">
#overview_wrapper 
{
	min-height: 960px;
	padding-top: 12pt;
	margin-left: 12pt;
}
#overview_header 
{
	float:right;
	clear:right;
}
#overview_body
{
	float: left; 
	clear: left;
}

#page_speeds 
{
	padding-top: 24pt;
	float: left;
	padding-right: 32pt;
	min-height: 240px; 
}

#page_speeds, #DAU, #EU
{
	z-index: 2;
	position: relative;
	background: #ffffff; /* to cover up the splunk headers */
}
#page_speeds span
{
	font-size: 14pt;
	line-height: 24pt; /* match arrow */
}
#DAU {
	font-size: 96pt;
	font-family: Verdana, Arial, sans-serif;
	font-variant: small-caps;
	word-spacing: -0.2em;
}
#DAU span {
	font-size: 32pt;
}
#EU {
	font-size: 24pt;
	font-family: Verdana, Arial, sans-serif;
}
#eu span {
	font-size: 24pt;
}
#insights {
	margin-top: 24pt;
	float: right;
	line-height: 1.2em;
	max-width: 60%;
	margin-right: 24pt;
	clear: right;
}
#tier-chart {
	height: 240px;
	width: 600px;
}

#overview_footer
{
	position: fixed;
	bottom: 8px;
	right: 8px;
	left: 8px;
	padding-left: 24pt;
	padding-right: 24pt;
	z-index: 1;
}

#nagios, #splunk {
	font-size: 1.2em;
}

.good {
	color: green;
}
.bad {
	color: orange;
}
.ugly {
	color: red;
}
.okay {
	color: black;
}
.gHolder {
	float: right;
}
</style>
<div id="overview_wrapper">
<div id="overview_header">
<?php
print_headroom();
?>
</div>
<div id="DAU">
<span style="vertical-align: middle">DAU</span>
<?php print_dau(); ?> 
</div>
	<div id="EU">
		<?php print_eu(); ?>
	</div>

	<div id="page_speeds">
		<?php
			print_page_times();
		?>
	</div>
	<div id="insights">
		<div id="tier-chart">
		</div>
	</div>
</div>

<div id="overview_footer">
	<span id="splunk"></span>
	<span id="nagios"></span>
<!--
		Splunk: 
		<span class="bad" style="font-size: 4em;">238&darr;</span>
		<span class="bad" id="splunkbars">
		489,564,322,5600,1378,7899,15000,63000,76000,238
		</span>-->
</div>
<script type="text/javascript">
	$('.sparkline').sparkline();
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
	<?php print_tracked_functions(); ?>
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
		if(isset($game_cfg['game_mon'])) 
		{
			echo json_encode($game_cfg['game_mon']);
		}
		else
		{
			echo json_encode($game_cfg['name']."-mon");
		}
	?>;
		var series = get_nagios_series(data, game_mon);
		if(series)
		{
			var criticals = series["critical"];
			var history = series["critical_history"].slice(-10);
			var l = "";
			for(var i = 0; i < history.length; i++)
			{
				var k = history[i][1];
				l += ((i==0) ? "" : ",") + k;
			}

			report = "Nagios: ";

			if(criticals > 0)
			{
				report += '<span class="ugly" style="font-size: 4em;">'+criticals+'</span>';
			}
			else
			{
				report += '<span class="good" style="font-size: 4em;">'+criticals+'</span>';
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
			$('#nagios').html(report);
			$('#nagiosbars').sparkline('html', {type:"bar", barColor: $('#nagiosbars').css('color'), height: "3em"});
		}
	}
	var _s = document.createElement("script");
	_s.setAttribute("src", "http://xxxx.xxx/graph_source" + (new Date()));
	document.body.appendChild(_s);
</script>

</body>
</html>
