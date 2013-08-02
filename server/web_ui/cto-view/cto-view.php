<!DOCTYPE HTML>

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

function GetPageList($server_cfg, $game_cfg)
{
    $xhprofModelObject = getXhProfModel();
    $resArray = $xhprofModelObject->generic_execute_get_query_detail(
                "cto_get_top_pages_by_delivery_time",
                array('end_time' => time(),
                      'start_time' => (time() - 24 * 7 * 24 * 60 * 60))
        );

    global $PageList;

    $PageList = get_column_vector($resArray["rows"], 0);
    return true;
}

function GetPagesByPDT($server_cfg, $game_cfg)
{
    global $PageList;

    $check_count = 4;
	$markup = "";
    foreach($PageList as $page) {
        if ($check_count) {
            $check_string = "checked";
            $check_count--;
        } else {
            $check_string = "";
        }

		$markup .= "<div>";
        $markup .= "<input type=checkbox id=$page $check_string/>";
		$markup .= "<label for=$page>$page</label>";
		$markup .= "</div>";
    }
	echo $markup;
}

$tracked_functions = array( "MC::set", "MC::get",
                       "ApcManager::get", "ApcManager::set",
                       "serialize", "unserialize",
                       "AMFBaseSerializer::serialize",
                       "AMFBaseDeserializer::deserialize");

function GetTrackedFunctions($server_cfg, $game_cfg)
{
    global $tracked_functions;
    $check_count = 2;

    if(isset($game_cfg["tracked_functions"])) {
		$tracked_functions = $game_cfg["tracked_functions"];
	}

	$markup = "";
    foreach($tracked_functions as $fn) {
        if ($check_count) {
            $check_string = "checked";
            $check_count--;
        } else {
            $check_string = "";
        }
		
		$markup .= "<div>";
        $markup .= "<input type=checkbox id=$fn $check_string/>";
		$markup .= "<label for=$fn>$fn</label>";
		$markup .= "</div>";
    }
	echo $markup;
}

GetPageList($server_cfg, $game_cfg);
?>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>zPerfmon: CTO Dashboard</title>
</head>

<body>
	<div class="container" id="tab-cto">
		<div class="menu-wrapper">
			<ul class="menu">
				<li class="button"><a class="menu-item-0 current" href=#>Page Delivery Time</a></li>
				<li class="button"><a class="menu-item-1" href=#>Tracked Functions</a></li>
				<!--<li class="button"><a id="select-period-btn" href="#">Jul 1, 2011 - Jul 31, 2011</a></li>-->
				<!--<li class="button"><input type="text" id="select-period-btn" value="Jul 1, 2011 - Jul 31, 2011" /></li>-->
			</ul>
		</div>

		<div class="left-column">
			<div class="select-chart-items">
				<div class="select-items-0 selected">
					<!--<div class="title">Select Pages</div>-->
					<div class="list">
						<?php GetPagesByPDT($server_cfg, $game_cfg); ?>
					</div>
				</div>
				
				<div class="select-items-1 hide">
					<!--<div class="title">Select Tracked Functions</div>-->
					<div class="list">
						<?php GetTrackedFunctions($server_cfg, $game_cfg); ?>
					</div>
				</div>
			</div>

			<div>
				<button class="split-chart-btn">Split Charts</button>
				<div class="split-chart-controller hide">
					<div><input type=radio name=tab-cto-split-view-mode value=overall checked />Overall</div>
					<div><input type=radio name=tab-cto-split-view-mode value=dodwow />DoD & WoW</div>
				</div>
			</div>

			<div>
				<button class="show-table-btn">Show Table</button>
			</div>
		</div>
	
		<div class="right-column">
			<div class="chart-wrapper left">
				<div class="combined-charts chart-block"></div>
				<div class="split-charts hide"></div>
			</div>
			<div class="tags-wrapper left">
            </div>
			<div class="clear"></div>
		</div>

		<div class="clear"></div>

		<div id="tab-cto-dialog" title="Profile Info"></div>

		<div id="tab-cto-table" class="yui-skin-sam"></div>
	</div>

<script>
(function() {
	var game = $('#GameList option:selected').val();
	var arrayid = $('#ArrayList option:selected').val();
	var gameParam = "game=" + game;
	if(arrayid !== "all") { //if arrayid is given, append it to the game parameter to get array specific data
		gameParam += "&array=" + arrayid;
	}
	var pageList = '<?php echo implode($PageList, ','); ?>';
	var trackedFun = '<?php echo implode($tracked_functions, ","); ?>';

	var apiUrlArr = [
		'cto-view/cto_query.php?query=cto_get_top_pages_avg_load_time&' + gameParam + '&columns=' + pageList,
		'cto-view/cto_query.php?query=cto_get_tracked_functions_by_column&' + gameParam + '&page=all&columns=' + trackedFun
	];

	var unitArr = ['mili', 'micro'];
 	var oControl = new zPerfmon.Main(game, arrayid, 'tab-cto', apiUrlArr, unitArr);

})();
</script>

</body>
</html>
