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


ini_set('memory_limit', '96M');

$core_include = "/var/www/html/zperfmon/include/";

set_include_path(get_include_path() . ":$core_include");
include_once 'setup_page.php';

$game_name = $game_cfg["name"];

include_once('XhProfModel.php');


#
# Globals to store transformed query result used to build the page
#
$top5_page_list = array();
$top5_function_list = array();
$top5_data = "";

#
# This class generates uniquely colored spans for each chunk of text
# you give it.
# 
class Palette {

	public $color_list = array("#FFFFFF", "#CCCCCC", "#999999", "#FFCC00", "#FF9900", "#FF6600", "#99CC00", "#CC9900", "#FFCC33", "#FFCC66", "#FF9966", "#FF6633", "#CCFF00", "#CCFF33", "#999900", "#CCCC00", "#FFFF00", "#CC9933", "#99FF00", "#CCFF66", "#99CC33", "#999933", "#CCCC33", "#FFFF33", "#FF6699", "#66FF00", "#99FF66", "#66CC33", "#999966", "#CCCC66", "#FFFF66", "#CC6666", "#FF6666", "#FF66CC", "#33FF00", "#66FF33", "#66CC00", "#99FF33", "#CCCC99", "#FFFF99", "#CC9966", "#CC9999", "#FF9999", "#FF33CC", "#33CC00", "#99CC66", "#CCFF99", "#FFFFCC", "#FFCC99", "#FF9933", "#FFCCCC", "#FF99CC", "#CC6699", "#33CC33", "#66CC66", "#00FF00", "#33FF33", "#66FF66", "#99FF99", "#CCFFCC", "#CC99CC", "#669966", "#99CC99", "#FFCCFF", "#FF99FF", "#FF66FF", "#FF33FF", "#CC66CC", "#66CC99", "#99FFCC", "#CCFFFF", "#3399FF", "#99CCFF", "#CCCCFF", "#CC99FF", "#9966CC", "#00FF33", "#33FF66", "#00CC66", "#33FF99", "#99FFFF", "#99CCCC", "#6699CC", "#9999FF", "#9999CC", "#00FF66", "#66FF99", "#33CC66", "#66FFFF", "#66CCCC", "#669999", "#CC66FF", "#00FF99", "#66FFCC", "#33CC99", "#33FFFF", "#33CCCC", "#9966FF", "#00FFCC", "#33FFCC", "#00FFFF", "#00CCCC", "#3399CC", "#00CC99", "#33CCFF", "#66CCFF", "#6699FF", "#00CCFF");

	public $elements;

	function __construct()
	{
		shuffle($this->color_list);
		$this->elements = array();
	}

	function format($text)
	{
		if (!in_array($text, $this->elements)) {
			$this->elements[] = $text;
		}

		$color_index = 
			array_search($text, $this->elements) % count($this->elements);
		
		$color = $this->color_list[$color_index];

		return $text;
	}
}


function throw_error($error_message)
{
	header("HTTP/1.1 500 Server error");
	die($error_message);
}

#
# Find all contigous chunks where "timestamp" or "page" change and
# create a row with cols out of corresponding "function"s. Also return
# the list of unique pages contributing to top5.
#
function xform_rows_to_top5($resArray, $column_vector)
{
	global $top5_page_list;
	global $top5_function_list;
	global $top5_data;

	$ts_col = array_search("timestamp", $column_vector);
	$page_col = array_search("page", $column_vector);
	$fn_col = array_search("function", $column_vector);

	if ($ts_col === FALSE || $page_col === FALSE || $fn_col === FALSE) {
		header('HTTP/1.1 500 Internal Server Error');
		print_r($column_vector);
		die("column names messed up.");
	}

	$palette = new Palette();

	$last_ts = NULL;
	$last_page = NULL;
	
	$chunk = array();

	$top5_data = "\n\n[\n";

	foreach($resArray as $row) {

		$page = $row[$page_col];
		$ts = $row[$ts_col];
		$fn = $row[$fn_col];

		# continue current chunk if ts and page are same
		if ($ts == $last_ts && $page == $last_page) {

			if (count($chunk) == 2 + 5) { # 2 is for timestamp and page
				# Already 5 entries
				continue;
			} else {
				$chunk[] = $palette->format($fn);
			}
		} else {
			#
			# either ts or page changed, insert current chunk
			# and reset trackers
			#
			# If there are no entries in current chunk
			# this is the very first entry, special case -
			# don't we hate those?
			#
			if (count($chunk) != 0) {
				while (count($chunk) < (5+2)) {
					$chunk[] = "'no_entry'";
				}
			
				$top5_data .= json_encode($chunk).",\n";
			}

			$chunk = array();

			$chunk = array($ts, $page, $palette->format($fn));
			$last_ts = $ts;
			$last_page = $page;

			$top5_page_list[$page] = TRUE;
		}
	}

	$top5_data .= "\n],\n";
	$top5_data .= json_encode($palette->elements)."\n\n";
}


function construct_top5_side_pane()
{
	global $top5_page_list;

	$page_list = array();

	foreach(array_keys($top5_page_list) as $page) {
		$page_list[] = trim($page, "'");
	}

	# Create radio buttons for each page and add an 'aggregate' button extra

	$side_pane_text = array('<input type="radio" name="top5_page_selector" value="all" checked=\"true\">All pages</input><br><br/>');

	foreach($page_list as $page) {
		
		if ($page == "all") {
			continue;
		}

		$side_pane_text[] = 
			"<input type=\"radio\" name=\"top5_page_selector\" value=\"{$page}\">$page</input><br></br>";
	}
	
	return implode("\n", $side_pane_text);
}


function construct_top5_table()
{
	global $top5_data;

	$table  = "[\n";
	$table .= "['datetime', 'Timestamp'],\n";
	$table .= "['string', 'Page'],\n";
	$table .= "['string', 'Top-1'],\n";
	$table .= "['string', 'Top-2'],\n";
	$table .= "['string', 'Top-3'],\n";
	$table .= "['string', 'Top-4'],\n";
	$table .= "['string', 'Top-5'],\n";

	$table .= "],\n";

	$table .= $top5_data;
	return $table;
}


function get_top5($server_cfg, $game_cfg, $xhprofModelObject)
{
	$end_time = time();
	$start_time = ($end_time -  1 * 7 * 24 * 60 * 60); # 1 Week ago

	$result = $xhprofModelObject->generic_execute_get_query_detail(
		'top5_functions_ewt_range',
		array('end_time' => $end_time,
		      'start_time' => $start_time));

	if (!$result) {
		throw_error("Error in entrails, top5 functions table is not feeling upto it");
	}
	
	xform_rows_to_top5($result["rows"],
			   $result["cols"]);
}

#
# We go global for multiple queries from same object due to laziness
$xhprofModelObject = new XhProfModel($server_cfg, $game_cfg);

get_top5($server_cfg, $game_cfg, $xhprofModelObject);

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title></title>
  </head>

  <body>
      <div id="Separator">Top-5 functions w.r.t Exclusive wall time (not on-CPU time) aggregated every 30 minutes.</div>

	<div id="SidePane">
	<span id="SidePaneHeader">Top-5 Pages</span>
		<br></br>
		<br></br>
		<?php echo construct_top5_side_pane(); ?>
	</div>
	
	<div id="MainPane">		
		<div id="top5_table">
		<img src="/zperfmon/images/spinner.gif"/>
		</div>
	</div>
	<div class="spacer"> </div>
	<script type="text/javascript">
		top5table.init("top5_table", "top5_page_selector",
			   <?php echo construct_top5_table(); ?>);
  	</script>


  </body>
</html>

