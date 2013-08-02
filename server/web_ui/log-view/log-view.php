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

/*
 @Author: uprakash
*/

ini_set('memory_limit', '48M');

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

function get_log_level_list($xhprofModelObject, $game_cfg){

	$result = $xhprofModelObject->generic_execute_get_query_detail('get_log_level_list',
			array("log_table" => $game_cfg["log_table"]),false,true);
	return $result;
	
}

function get_log_module_list($xhprofModelObject, $game_cfg){

	$result = $xhprofModelObject->generic_execute_get_query_detail('get_log_module_list',
			array("log_table" => $game_cfg["log_table"]),false,true);
	return $result;
}

function construct_log_level_list($log_level_list)
{

	# font colors for levels

	$colors = array('INFO'=> 'green', 'WARNING' => 'blue', 'ERR' => 'orange', 'CRIT' => 'red');

	# Create radion buttons for each log level

	$side_pane_text = array('<p><input type="radio" class="select_columns0" id="all_levels" 
				name="log_level_selector" value="all_levels" checked="true" />
				<label for="all_levels">ALL</label><br/></p>');


	foreach($log_level_list['rows'] as $row) {
		
		$log_level = $row[0];
		$color = strtoupper($log_level);
		$side_pane_text[] = "<p><input type=\"radio\" class=\"select_columns0\" id=\"{$log_level}\" 
					name=\"log_level_selector\" value=\"{$log_level}\">
					<font color=\"$colors[$color]\">${log_level}</font></input></br></p>";
	}
	return implode("\n", $side_pane_text);
}

function construct_log_module_list($log_module_list)
{

	# Create radion buttons for each log level

	$side_pane_text = array('<p><input type="radio" class="select_columns1" id="all_modules" 
				name="log_module_selector" value="all_modules" checked="true" />
				<label for="all_modules">ALL</label></br></p>');

	foreach($log_module_list['rows'] as $row) {
		
		$log_module = $row[0];
		$side_pane_text[] = "<p><input type=\"radio\" class=\"select_columns1\" id=\"{$log_module}\"
				name=\"log_module_selector\" value=\"{$log_module}\" />
				<label for=\"{$log_module}\">${log_module}</label></input></br></p>";
	}
	return implode("\n", $side_pane_text);
}


function get_log_data($xhprofModelObject, $game_cfg){
	$result = $xhprofModelObject->generic_execute_get_query_detail('get_log_data',
                                      array("log_table" => $game_cfg["log_table"]),false,true);
	$cols = array_flip($result["cols"]);
	$str_array = array("[");
	foreach($result["rows"] as $row){
		
		$timestamp = $row[$cols["timestamp"]];	
		$module_name = $row[$cols["module_name"]];
		$log_level = $row[$cols["log_level"]];
		$message = json_encode($row[$cols["message"]]);
		$str_array[] = "[new Date({$timestamp}*1000),'{$log_level}','{$module_name}',{$message}],";
	}
	$str_array[] = "]";
	$result = implode("\n", $str_array);
	return $result;
}

function construct_log_table($log_data)
{

	$table  = "[\n";
	$table .= "['date', 'Date Time'],\n";
	$table .= "['string', 'Log Level'],\n";
	$table .= "['string', 'Module Name'],\n";
	$table .= "['string', 'Message']\n";

	$table .= "],\n";

	$table .= "\n\n".$log_data;
	return $table;
}

$log_data = get_log_data($xhprofModelObject, $game_cfg);
$log_module_list = get_log_module_list($xhprofModelObject, $game_cfg);
$log_level_list = get_log_level_list($xhprofModelObject, $game_cfg);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>L o g s</title>
	<script type='text/javascript' src='/zperfmon/js/log-view.js'></script>
</head>

<body>
	<div id="SidePane">
		<!-- <div id="log_accordion"> -->
		<fieldset>
			<legend><h3 id="log_levels"><label for='accord0'>Log Levels</label></h3></legend>
			<div id="logLevels">	
				<?php echo construct_log_level_list($log_level_list); ?>
			</div>
		</fieldset>
		</br>
		<fieldset>
			<legend><h3 id="log_modules">Log Modules</h3></legend>
			<div id="logModules">
				<?php echo construct_log_module_list($log_module_list); ?>
			</div>
		</fieldset>
		<!-- </div> -->
	</div>
	<div id="MainPane">
		<div id="log_table">
			<img src="/zperfmon/images/spinner.gif"/>
		</div>
	</div>
	<div class="spacer"> </div>
		<script type="text/javascript">
			filteredlogtable.init("#log_accordion","log_table",
					<?php echo construct_log_table($log_data); ?>);
		</script>
</body>
</html>
