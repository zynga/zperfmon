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

$game_config_path = "/etc/zperfmon/";
$per_game_config_path = "/etc/zperfmon/conf.d/";
$core_include = "/var/www/html/zperfmon/include/";

set_include_path(get_include_path() . ":$game_config_path:$per_game_config_path:$core_include");

include_once "rightscale.php";
include_once "game_config.php";
include_once 'server.cfg';
function get_array_map($server_cfg, $game_cfg) {
	include_once('/usr/local/zperfmon/bin/rightscale.php');
	$rs = new RightScale($server_cfg, $game_cfg);
	$array_map = $rs->get_array_to_arrayid_mapping();
	$ret = array("all" => "all");
	$ret = array_merge($ret, $array_map);
	return $ret;
}					
$game_array_id_name = array();
$key = "gameidmapping";
define('TTL', 12 * 60 * 60); //cache expiry time for apc is 12 hrs
$game_array_id_name = apc_fetch($key, $success);
if ( !$success){	
	foreach($server_cfg['game_list'] as $game) {
		$game_cfg = load_game_config($game);
		$array_list = get_array_map($server_cfg, $game_cfg);
		$game_array_id_name[$game] = $array_list;
	}
	apc_add($key, $game_array_id_name, TTL);
}
$game_array_id_json = json_encode($game_array_id_name);
$game = "";
if(isset($_REQUEST["game"])) {
	$game = $_REQUEST["game"];
}
if (isset($_REQUEST["array"]) && $_REQUEST['array'] !== 'all' ) 
{
	$array = $_REQUEST['array'];
} 
else 
{
	$array = "all";
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Developer View - Profile Dissection</title>
    <script type='text/javascript' >
         // game name to be passed in different Json fetch
         var game_name = "<?php   echo $game; ?>";
         var array_id = "<?php   echo $array; ?>";
         var game_array_id_json = JSON.parse(<?php echo json_encode($game_array_id_json);?>);
	 var timestamp = "<?php echo $_REQUEST['timestamp'] ?>";

   </script>

    <script type="text/javascript" src="/zperfmon/js/sprintf-0.6.js"></script>
    <script type='text/javascript' src='/zperfmon/js/autoheight.js'></script>
    <script type='text/javascript' src='/zperfmon/js/jquery.cookie.js'></script>
    <script type='text/javascript' src='/zperfmon/js/jquery.autocomplete.js'></script>
    <script type='text/javascript' src='/zperfmon/js/jquery.tooltip.js'></script>
    <script type='text/javascript' src='profile-view/dev-view.js?rev=0.0.1'></script>
    <link rel="stylesheet" href="/zperfmon/css/jquery.autocomplete.css"></link>
 <link rel="stylesheet" href="profile-view/dev-view.css?rev=0.0.1"></link>
  </head> 

 <body >
      <div id="Separator">Profiles by page aggregated every 30 minutes. "all" is an aggregation of all pages in a 30 min time-slot.</div>

      <div id="SidePane">
<span id='hideSidePanel' style='float: right; top: 250px;left:2px; position: relative;'><span style='z-index:0;' id='hide_button'><</span></span>
	<div id="SidePanelOuter">
        <span class="ui-datepicker-month"><b>Select Date</b>
        <input id="ProfileDateWidget" />
          <input type="hidden" id="UTC_seconds"/>
        </span>

        <div id="ProfileDateList"> </div>
			<span class="ui-datepicker-month"><b>Select Time Slot</b>
					<select id="hour-time-slots"> </select>
			</span>
			<span class="ui-datepicker-month" style="display:block" id="selectedDateTime"></span>
			<span class="ui-datepicker-month" style="display:block;font-size:14px;" size="-1" id="profiledsFileList"></span>
     </div>
</div><!--
<span  id="opener"style="position:absolute;left:20px;top:145px;z-index:101"><span id="compare_button" >Compare</span></span>-->
<span id="showSidePanel"style="display:none;position:absolute;left:-1px;top:314px;"><span id="show_button">></span></span>
<div id='compareOverlay' >
Other Profiles : 
<br />
<table>
	<tr>
		<td>
			<label for="GameListPopup">Game:&nbsp;</label>
		</td>
		<td>
			<select name=GameSelectorPopup id="GameListPopup" style="width:175px;">>
                        <?php
                            foreach($server_cfg['game_list'] as $game_compare) {
                                    if(isset($game_name) && $game_name == $game_compare) {
                                        echo "<option name=$game_compare value=$game_compare selected>$game_compare</option>\n";
                                     } else {
                                        echo "<option name=$game_compare value=$game_compare>$game_compare</option>\n";
                                     }
                             }
                        ?>
                      </select>
		</td>
	</tr>
	<tr>
		<td>
			<label for="ArrayListPopup">Array:&nbsp;</label>
		</td>
		<td>
                      <select name=ArraySelectorPopup id="ArrayListPopup" style="width: 175px;">
                      </select>
		</td>
	</tr>
	<tr>
		<td>
			<label for="datepicker">Date:&nbsp;&nbsp;</label>
		</td>
		<td>
			<input id="datepicker" style="width:175px;">
			<input type="hidden" id="UTC_seconds_compare">
		</td>
	<tr>
	</tr>
		<td>
			<label for="hour-time-slots-compare">Time:&nbsp;&nbsp;</label>
		</td>
		<td>
			<select id="hour-time-slots-compare" style="width:175px;">
		</td>
	</tr>
</table>
<div>
	<hr />
	<div style="text-align:center;">
		OR
	</div>
	<hr/>
	Previous Releases:
	<br />
        <table>
		<tr>
		<td>
		  Releases:
		</td>
		<td>
                <select name=ReleaseId id=ReleaseId >
                </select>
		</td>
		</tr>
        </table>

<div style=''>
	<button id = "previousday" style="width:150px;top:-225px;left:300px" type="button" onClick= changeDate() >Previous Day</button>
	<button id = "previousweek" style="top:-185px;width:150px;left:145px" type="button" onClick= changeWeek()>Previous Week</button>
	<button align= "center" id = "compare"style="left:-100px;width:100px;top:10px;" type="button">Compare</button>
</div>
</div>
</div>

<div id="MainPaneTable">
    <iframe  name="xhprof_page" id="xhprof_view" width="1429px" height="1600px" frameborder="0"></iframe>
</div>
<div class="spacer"> </div>
<script type="text/javascript">
function set_page_size(width,height)
{
	$('#xhprof_view').height(height+50);
	if ( $('#SidePane').height() > height)
	{
		$('#GameTab').height($('#SidePane').height());
	}
	else
	{
		 $('#GameTab').height(height+75);
	}
	if ( (width+350) > $('#body_top').width())
	{
		$('#GameTab').width(width+350);	
		$('#xhprof_view').width(width+50);
	}
	else
	{
		$('#GameTab').width($('#body_top').width());
		$('#xhprof_view').width($('#body_top').width()-$('#SidePane').width());
	}
}
</script>
  </body>
</html>


