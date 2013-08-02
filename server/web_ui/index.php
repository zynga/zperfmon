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

header("Cache-Control: no-cache");
if (!isset($debug)) {
	$user = $_SERVER['PHP_AUTH_USER'];
} else {
	$user = "admin";
}

$user = "admin";

$game_config_path = "/etc/zperfmon/";
$per_game_config_path = "/etc/zperfmon/conf.d/";
$core_include = "/var/www/html/zperfmon/include/";

set_include_path(get_include_path() . ":$game_config_path:$per_game_config_path:$core_include");

include_once 'server.cfg';
include_once 'game_config.php';

function ifsetor($a, $k, $d) 
{
	if(isset($a[$k])) return $a[$k];
	return $d;
}
$game_name = null;
$game_name = $_GET['game'];
$nochrome  = ifsetor($_GET,'nochrome', 'false');
//echo $game_name;
//echo $nochrome;
if(preg_match('/^[ ]*$/',$game_name,$matches)){
	if($matches[0]){
		//echo "<br>set game name to null</br>";
		$game_name=null;
	}
}

if(preg_match('/(true)|(false)/',$nochrome,$matches)){
	if(!$matches[0]){
		$nochrome=null;
	}
}

function is_hidden()
{
	global $game_name, $nochrome;
	if($nochrome == "true" && $game_name != null){
		echo "none";
	}else{
		echo "block";
	}
}

function is_exit()
{
	global $game_name, $nochrome;
	if($nochrome == "true" && $game_name == null){
		//echo "</br> set game name also with nochrome=true</br>";
		return true;
	}else{
		return false;
	}
}

if(is_exit()){
	header("HTTP/1.1 400 Bad Request");
	echo "<p><h4> nochrome=true requires a game name as parameter</h4></p></br>";
	exit(1);
}

if(!isset($game_name)) {
	if(isset($_GET['gameid'])){
		include_once('gameidMap.php');
		define('TTL', 30 * 60); //cache expiry time for apc is 30min
		$key = "gameid";
		$gameidArray = apc_fetch($key, $success);
		if($success) {
		} else {
			$gameidArray = getGameMapping($server_cfg);
			apc_add($key, $gameidArray, TTL);
		}
		$game_name = $gameidArray[$_GET['gameid']];
		if(!isset($game_name))
		{
			$game_name  = $server_cfg['game_list'][0];
		}
	}
	else {
		foreach($server_cfg['game_list'] as $game){
			$game_name = $game;
			break;
		}
	}
}

$game_cfg = load_game_config($game_name);

function get_array_map($server_cfg, $game_cfg) {
	include_once('/usr/local/zperfmon/bin/rightscale.php');
	$rs = new RightScale($server_cfg, $game_cfg);
	$array_map = $rs->get_array_to_arrayid_mapping();
	$ret = array("all" => "all");
	$ret = array_merge($ret, $array_map);
	return $ret;
}

function populateGameList() {
    global $server_cfg;
	global $game_name;

    $arr = array();
    $dev = array();
    $stg = array();
    foreach($server_cfg['game_list'] as $game) {
        $tmp = split('_', $game);
        if($tmp[count($tmp)-1] == "dev") {
            $dev[] = $game;
        } else if($tmp[count($tmp)-1] == "stage" || $tmp[count($tmp)-1] == "staging" ) {
            $stg[] = $game;
        } else {
            $arr[] = $game;
        }
    }

    sort($arr);
    sort($dev);
    sort($stg);

    $arr[] = "-";
    $arr = array_merge($arr, $stg);
    $arr[] = "-";
    $arr = array_merge($arr, $dev);

    $ret = "";
    foreach($arr as $game) {
        if(isset($game_name) && $game_name == $game) {
            $ret .= "<option name=$game value=$game selected>$game</option>\n";
        } else if($game == "-") {
            $ret .= "<option value='' disabled>----------</option>\n";
        } else {
            $ret .= "<option name=$game value=$game>$game</option>\n";
        }
    }

    return $ret;
}

$rev="1.0.2-dev";

echo <<<EOF
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>

<!-- Fonts -->
<link href="http://fonts.googleapis.com/css?family=Ubuntu" rel="stylesheet" type="text/css">
<link href="http://fonts.googleapis.com/css?family=Michroma" rel="stylesheet" type="text/css">

<link type="text/css" href="/zperfmon/css/style.css" rel="stylesheet"/>

<!-- Don't change the order of inclusion of following two css files. We want twocolumn.css contents to over-ride jquery-ui definitions -->
<link type="text/css" href="/zperfmon/css/smoothness/jquery-ui-1.8.5.custom.css" rel="stylesheet"/>
<link type="text/css" href="/zperfmon/css/twocolumn.css?rev=$rev" rel="stylesheet"/>
<link type="text/css" href="/zperfmon/css/gauges.css?rev=$rev" rel="stylesheet"/>
<link type="text/css" href="/zperfmon/css/main.css?rev=$rev" rel="stylesheet" />
<link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/combo?2.9.0/build/paginator/assets/skins/sam/paginator.css&2.9.0/build/datatable/assets/skins/sam/datatable.css"> 

<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript" src="/zperfmon/js/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="/zperfmon/js/jquery-ui-1.8.5.custom.min.js"></script>
<script type="text/javascript" src="/zperfmon/js/jquery.sparkline.min-1.6.js"></script>
<script type="text/javascript" src="/zperfmon/js/jquery.flot-0.7.js"></script>
<script type="text/javascript" src="/zperfmon/js/jquery.flot.stack-0.7.js"></script>
<script type="text/javascript" src="/zperfmon/js/jquery.flot.axislabels-0.1.js"></script>
<script type="text/javascript" src="/zperfmon/js/charts.js?rev=$rev"></script>
<script type="text/javascript" src="/zperfmon/js/main.js?rev=$rev"></script>
<script type='text/javascript' src='/zperfmon/js/chartcontrol.js?rev=$rev'></script>
<script type='text/javascript' src='/zperfmon/js/autoheight.js?rev=$rev'></script>
<script type='text/javascript' src='/zperfmon/js/filteredtable.js?rev=$rev'></script>
<script type='text/javascript' src='/zperfmon/js/top5table.js?rev=$rev'></script>
<script type="text/javascript" src="http://yui.yahooapis.com/combo?2.9.0/build/yahoo-dom-event/yahoo-dom-event.js&2.9.0/build/datasource/datasource-min.js&2.9.0/build/element/element-min.js&2.9.0/build/paginator/paginator-min.js&2.9.0/build/datatable/datatable-min.js"></script> 

<!-- <script type="text/javascript" src="https://www.google.com/jsapi?autoload=%7B%22modules%22%3A%5B%7B%22name%22%3A%22visualization%22%2C%22version%22%3A%221%22%2C%22packages%22%3A%5B%22annotatedtimeline%22%2C%22table%22%5D%7D%5D%7D"></script> -->
EOF;
?>

<title>zPerfmon Main Page</title>

<script type="text/javascript">
(function() {
	/* Variables for holding configured games name and enabled metrics for each of the configured game */
	var games_metric = new Array();
	var games = new Array();
	var user = "<?php echo $user?>";

	/* Function to get the configured games from server.cfg in /etc/zperfmon/server.cfg */
	function getGamesName()
	{
		<?php
			if(isset($game_name) && in_array($game_name,$server_cfg['game_list'])){
				echo "games=\"$game_name\"";
			}else{
				$games = implode(",",$server_cfg['game_list']);
				echo "games= \"$games\".split(\",\");";
			}
		?>
	}

	/* Function to get the enabled metrics for each of the configured game from game config file in /etc/zperfmon/conf.d/ */
	function getGamesMetrics()
	{
		<?php
			foreach($server_cfg['game_list'] as $game) 
			{
				$enabled_metrics = implode(",",$game_cfg["enabled_metrics"]);
				echo "games_metric[\"$game\"] =  \"$enabled_metrics\".split(\",\");\n\t";
			}
		?>
	}	

	getGamesName();
	getGamesMetrics();
	google.load('visualization', '1', {'packages':['annotatedtimeline']});

	function generateTabs(active_game, active_array) {
		var tabList =new Array( 
			{'url':'bd-view/bd-view.html','title':'Business Dashboard','data':'rightscale_data', 'id':'bd'},
			{'url':'cto-view/cto-view.php','title':'CTO Dashboard','data':'apache_stats', 'id':'cto'},
			{'url':'eu-view/eu-view.php','title':'EU Dashboard','data':'zmonitor_data', 'id':'eu'},
			{'url':'profile-view/dev-view.php','title':'Profile Dashboard','data':'xhprof_blob', 'id':'profile'},
			{'url':'top5-view/top5-view.php', 'title' : "Top5 functions", "data" : "function_analytics", 'id':'top5'},
			{'url':'slow-page/slow-page.php','title':'Slow Page','data':'slow_page', 'id':'slowpage'},
			{'url':'log-view/log-view.php','title': 'Logs','data':'', 'id':'log'},
			//{'url':'zmon.php','title': 'zMonitor','data':'zmon_data', 'id':'zmon'},
			//{'url':'splunkd.php','title': 'splunk','data':'splunkd_data', 'id':'splunkd'},
			{'url':'admin/admin.php','title': 'Administrator','data':'admin_data', 'id':'admin'}
		);

		var idx2Hash = {};
		var hash2Idx = {};

		function getTabsLinkMarkup() {
			var ret = "";
			for(var i = 0; i < tabList.length; i++) {
				var url = tabList[i].url + "?game=" + active_game + "&array=" + active_array + "&timestamp=" + "<?php echo $_REQUEST['timestamp'] ?>";
				ret += "<li class='ui-state-default ui-corner-top'><a href=" + url + "><span>" + tabList[i].title + "</span></a></li>";
				idx2Hash[i] = "tab-" + tabList[i].id;
				hash2Idx[idx2Hash[i]] = i;
			}

			return ret;
		}

		function addTabs() {		
			var markup = getTabsLinkMarkup();
			$("#GameTab ul").html(markup);

			$('.ui-tabs-nav').append('<span id="refresh-tabs"></span>');
			$('#refresh-tabs').button({ icons: {primary:'ui-icon-refresh'}, 'label' : '<span class="smallfont">Refresh</span>' });
			$('#refresh-tabs').click(function() {
				var tabs = $('#GameTab').tabs();
				var selected = tabs.tabs('option', 'selected');
				tabs.tabs('load', selected);
			});

			$("#GameTab").tabs({
				ajaxOptions:{},
				select: function(e, ui) {
					window.location.hash = idx2Hash[ui.index];
				}
			});
			
			var hash = window.location.hash;
			if(hash == "") {
				$("#GameTab").tabs('select', 0);
			} else {
				hash = hash.substr(1);
				var idx = hash2Idx[hash];
				$("#GameTab").tabs('select', idx);
			}
		}

		function initWebHelp() {
			$('.ui-tabs-nav').append('<span id="web-help"></span>');
			$('#web-help').button({ icons: {primary:'ui-icon-help'}, 'label' : '<span class="smallfont">Help</span>' });
			$('#web-help').click(function() {
				var tabs = $('#GameTab').tabs();
				var selected = tabs.tabs('option', 'selected');
				var tabArr = $('.ui-tabs-nav li span');
				var label = $(tabArr[selected]).text();

				var url = "";
				switch(label) {
					case "Business Dashboard":
						url = "bd_help.pdf";
						break;
					case "CTO Dashboard":
						url = "cto_help.pdf";
						break;
					case "EU Dashboard":
						url = "eu_help.pdf";
						break;
					case "Profile Dashboard":
						url = "profile_help.pdf";
						break;
					case "Top5 functions":
						url = "top5_help.pdf";
						break;
					case "Slow Page":
						url = "slow_page_help.pdf";
						break;
				};

				if(url !== "") {
					url = "help/" + url;
					window.open(url);
				}
			});
		}

		addTabs();
		initWebHelp();
	}

	/* This will be the callback function for change event of games selector drop down menu */
	function populateTabs()
	{ 
		var active_game = $('#GameList option:selected').val();
		var active_array = $('#ArrayList option:selected').val();
		if(active_game==""){
			$("#GameTable").replaceWith($("#NoGame"));
			if(user =="admin"){
				$("#GameTab").show();
				generateTabs(active_game);
			}else{
				$("#NoGame").show();
				$("#GameTab").hide();
			}
		}
		else{
			$("#GameTable").show();
			$("#NoGame").hide();
			$("#GameTab").show();
			generateTabs(active_game, active_array);
		}
	}

	$(document).ready( function () { 
		$("#GameTab").tabs({'cache': true});
		populateTabs();

		$("#GameList").change(function() {
			var newgame = $('#GameList').val();
                	document.location.href = "index.php?game=" + newgame + document.location.hash;
		});

		$("#ArrayList").change(function() {
			var game = $('#GameList').val();
			var newarray = $("#ArrayList").val();
			document.location.href = "index.php?game=" + game + "&array=" + newarray + document.location.hash;
		});
	});
})();
</script>
</head>

<body id="body_top">
<div style="display:<?php echo is_hidden(); ?>" class="hd">
	<div class="title left hdblock">zPerfmon</div>
    <div id="game-info" class="right">
        <div id="select-game-div" class="left">
            <div class="left">
                <div class="label">Game</div>
                <div class="options">
                    <select name=GameSelector id="GameList">
                        <?php
							echo populateGameList();
                        ?>
                    </select>
                </div>
            </div>
            <div class="left">
                <div class="label">Array</div>
                <div class="options">
                    <select name=ArraySelector id="ArrayList">
                        <?php
                            $array_list = get_array_map($server_cfg, $game_cfg);
                            foreach($array_list as $array_name => $array_id) {
                                if(isset($_GET['array']) && $_GET['array'] == $array_id) {
                                   echo "<option name=$array_name value=$array_id selected>$array_name</option>\n";
                                } else {
                                    echo "<option name=$array_name value=$array_id>$array_name</option>\n";
                                }
                            }
                        ?>
                    </select>
                </div>
            </div>
            <div class="clear"></div>
        </div>
		<div class="right">
            <div id="game-name"><?php echo strtoupper($game_cfg['sn_game_name']); ?></div>
            <div id="dashboard-view-link">
                <a href="overview/index.php<?php if(isset($game_name)) {echo '?game='.$game_name;} ?>" style="font-size:15px">Go to Overview Dashboard</a>
            </div>
        </div>
    </div>
    <div class="clear"></div>
</div>

<!-- This div will be  replaced with the div with id 'GameTable' when no game is configured -->
<div id="NoGame">
    <p>
        No Game is configured
    </p>
</div>

<div id="GameTab">
    <ul> <!-- Needed to populate jQuery Tabs -->
    </ul>
</div>

</body>
</html>
