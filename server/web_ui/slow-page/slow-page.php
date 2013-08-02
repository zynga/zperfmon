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


ini_set('memory_limit', '48M');

$core_include = "/var/www/html/zperfmon/include/";

set_include_path(get_include_path() . ":$core_include");
include_once 'setup_page.php';

$game_name = $game_cfg["name"];

include_once('XhProfModel.php');


// We go global for multiple queries from same object and due to
// laziness
$xhprofModelObject = new XhProfModel($server_cfg, $game_cfg);

function throw_error($error_message)
{
	header("HTTP/1.1 500 Server error");
	die($error_message);
}


function get_slow_page_list()
{
	global $xhprofModelObject;
	global $game_cfg;
	
	$result = $xhprofModelObject->generic_execute_get_query_detail('get_slow_page_list',
				array("slow_page_table" => $game_cfg["slow_page_table"]));

	if (!$result) {
		throw_error("Internal plumping error, page list is no more");
	}

	# We want list of pages, that would be the only row in the result.
	return $result["rows"];
}


function construct_slow_page_list()
{
	$page_list = get_slow_page_list();

	# Create radion buttons for each page and add an 'aggregate' button extra

	$side_pane_text = array('<input type="radio" name="slow_page_selector" value="all_pages" checked=\"true\">All slow pages<br/><br/>');


	foreach($page_list as $row) {
		
		$page = $row[0];
		$side_pane_text[] = "<input type=\"radio\" name=\"slow_page_selector\" value=\"{$page}\">$page</input><br></br>";
	}
	
	return implode("\n", $side_pane_text);
}


function get_slow_page_data()
{
	global $xhprofModelObject;
	global $game_cfg;

	$result = $xhprofModelObject->generic_execute_get_query_detail('get_slow_page_data',
					     array("slow_page_table" => $game_cfg["slow_page_table"]));

	if (!$result) {
		throw_error("Internal plumping error, slow db could not be owned (mined)");
	}

	return $result;
}


function construct_slow_page_table()
{
	$slow_page_data = get_slow_page_data();

	$table  = "[\n";
	$table .= "['string', 'Page Name'],\n";
	$table .= "['datetime', 'Time Of Day'],\n";
	$table .= "['number', 'Time to deliver'],\n";
	$table .= "['string', 'Top1-fn'],\n";
	$table .= "['number', 'Top1-eWT'],\n";
	$table .= "['number', 'Top1-CT'],\n";
	$table .= "['string', 'Top2-fn'],\n";
	$table .= "['number', 'Top2-eWT'],\n";
	$table .= "['number', 'Top2-CT'],\n";
	$table .= "['string', 'Top3-fn'],\n";
	$table .= "['number', 'Top3-eWT'],\n";
	$table .= "['number', 'Top3-CT'],\n";
	$table .= "['string', 'IP'],\n";
	$table .= "['number', 'id']\n";

	$table .= "],\n";

	$table .= "\n\n[\n";

	#
	# Populate the per-page entries which are of the form
	# 
	# page_name , page_time , 'top1_fn,eWT,CT,iWT' , 'top2_fn,eWT,CT,iWT' , ...
	#
	foreach($slow_page_data["rows"] as $row) {
		$page_name = $row[0];
		$time_of_day = "new Date({$row[6]})";
		$page_time = round($row[1]); # we want ms accuracy to match function times
		$table .= "[\"$page_name\", $time_of_day, $page_time, ";

		foreach(array_slice($row, 2,3) as $entry) {

			$fn_details = array_slice(explode(",", $entry), 0, 3);
			
			$fn_name = $fn_details[0];
			if (strlen($fn_name) > 18) {
				$fn_name = substr($fn_name, 0, 18) . "...";
			}

			$fn_ewt = round($fn_details[1] / 1000); # again, we want only milliseconds
			$fn_ct = $fn_details[2];

			$table .= "\"$fn_name\", $fn_ewt, $fn_ct,";
		}
		
		$table .= "\"{$row[5]}\",";
		$table .= "{$row[7]}";

		$table .= "],\n";
	}

	$table .= "]\n\n";
	return $table;
}

function slow_page_dir($server_cfg, $game_cfg)
{
	return sprintf($server_cfg["slow_page_dir"], 
				$game_cfg["name"]);
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title></title>

  </head>

  <body>
	<div id="Separator">Pages which took more time than the configured threshold to execute.</div>

	<div id="SidePane">
	<span id="SidePaneHeader"></span>
		<?php echo construct_slow_page_list(); ?>
		<input type="button" id="table_or_profile" value="Show Profile"/>
	</div>
	
	<div id="MainPane">
		
		<div id="slow_page_table">
		<img src="/zperfmon/images/spinner.gif"/>
		</div>

		<div class="hidden" id="slow_page_profile">
		<iframe name="slow_page_view" id="slow_page_iframe" class="autoHeight" frameborder="0"></iframe>
		</div>
	</div>
    <div class="spacer"> </div>
	<script type="text/javascript">
		filteredtable.init("slow_page_table", "slow_page_selector",
			   <?php echo construct_slow_page_table();
				   echo ",".json_encode(slow_page_dir($server_cfg, $game_cfg)); ?>);
		// To add support for resizing of page 
		function set_page_size(width,height)
		{
				$('#slow_page_iframe').height(height+50);
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
					$('#slow_page_iframe').width(width+50);
				}
				else
				{
					$('#GameTab').width($('#body_top').width());
					$('#slow_page_iframe').width($('#body_top').width()-$('#SidePane').width());
				}
		}
  	</script>
	<style>
        #MainPane{
            float: left;
        }
    </style>

  </body>
</html>
