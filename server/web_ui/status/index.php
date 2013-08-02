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

include_once 'setup_page.php';

$game_name = $game_cfg["name"];

$xhprof_upload_dir = "/var/opt/zperfmon/$game_name/xhprof/";
$count = 0;
$result_array = array();
foreach(glob($xhprof_upload_dir."*") as $timeslot_dir){
	if(preg_match("%".$xhprof_upload_dir."(\d+)$%",$timeslot_dir,$matches_timeslot)){
		$timestamp = (int)$matches_timeslot[1] * 1800;
		$result_array[$matches_timeslot[1]] = array() ;
		$result_array[$matches_timeslot[1]]['ips'] = null;
		$result_array[$matches_timeslot[1]]['no_of_profiles'] = 0;
		foreach(glob($timeslot_dir."/[0-9]*", GLOB_ONLYDIR) as $ip){
			$result_array[$matches_timeslot[1]]['ips'] .= basename($ip).",";
		}
		$no_of_profiles = count(glob("$timeslot_dir/*/*:xhprof"));
		$result_array[$matches_timeslot[1]]['no_of_profiles'] = $no_of_profiles;
		$result_array[$matches_timeslot[1]]['ips'] = rtrim($result_array[$matches_timeslot[1]]['ips'], ",");
	}
	if(48 == $count){
		break;
	}else{
		$count++;
	}
}

function get_data($result_array){
	$str= "[\n";
	foreach($result_array as $timeslot=>$cols){
		$rightscale_link = "<a href=\"https://my.rightscale.com/clouds/1234/ec2_instances;active?".
				"filter_sort_listing=true&order_by=Nickname&filter_type=Private%20IP&".
				"filter_value=__ip_here__&commit=Apply\" target=\"_blank\">__ip_here__</a>";
		$ip_array = explode(",",$cols['ips']);
		for($i = 0; $i < count($ip_array); $i++){
			$ip_array[$i] = str_replace("__ip_here__", $ip_array[$i], $rightscale_link);
		}
		$ips = implode(",",$ip_array);
		$str = $str. "[new Date(".($timeslot*1000*1800)."),'".$ips."',".$cols['no_of_profiles'].",".
			count(explode(",",$ips))."],\n";
	}
	$str = $str. "]";
	return $str;
}

$data = get_data($result_array);

?>

<html>
<head>
<title>Zperfmon-status</title>
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type='text/javascript'>
google.load('visualization', '1.1', {packages: ['table']});
function drawVisualization() {
	var dataTable = new google.visualization.DataTable();
		dataTable.addColumn("date","time");
		dataTable.addColumn("string","ips");
		dataTable.addColumn("number","no of profiles", 'n_profiles');
		dataTable.addColumn("number","no of ips");
		dataTable.addRows(<?php echo $data; ?>);
		
		var formatter_date = new google.visualization.DateFormat({pattern: 'MMM-dd HH:mm'});
		formatter_date.format(dataTable, 0);
		
		var formatter = new google.visualization.ArrowFormat({base: 300});
		formatter.format(dataTable, 2);	
		google.visualization.drawChart({
				"containerId": "visualization_div",
				"dataTable": dataTable,
				"refreshInterval": 5,
				"chartType": "Table",
				"options": {
				"alternatingRowStyle": true,
				"showRowNumber" : false,
				"allowHtml" : true
				}
				});
}
google.setOnLoadCallback(drawVisualization);
</script>
</head>	
<body>
<div id="visualization_div">
Loading status data !!!!
</div>
</body>
</html
