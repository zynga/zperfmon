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

include_once "run_in_background.php";

function del_dir_create($dir){
	if(is_dir($dir)){
		exec("sudo rm -rf $dir");//rmdir($dir);
	}
	mkdir($dir);
}	


function get_pdt_per_page($pool_ip,$instance_count,$prev_pdt = NULL){

	$dir = "/tmp/pdts/";
	// Delete the previous pdts
	del_dir_create($dir);
	$relative_path = realpath(dirname(__FILE__));
	$pdt_count = -1;
	$loop = 0;
	$parallel =20;
	if($instance_count < 20)
		$parallel = $instance_count;
	$instance_count_parallel = $instance_count/$parallel;
	$required_pdt = (int)(0.66*$instance_count); // 2/3 rd of instance_count
	
	$cmd = "php $relative_path/get_pdt.php -p ".$pool_ip." -c ".$instance_count_parallel;
	$pid_array = array();
	//Maximum number of hits = 2*instance_count
	while($pdt_count<$required_pdt && $loop<3){
		for($i=0; $i<$parallel; $i++){
			$pid_array[] = run_in_background($cmd);
		}
		// Wait for the process to complete
		foreach($pid_array as $pid){
			while(is_process_running($pid)){
				sleep(1);
			}
		}
		$pdt_count = count(glob($dir."*"));
		$loop++;
	}
	if($pdt_count < $required_pdt)
		die("ERROR: Required number ( $required_pdt ) of pdt.json not fetched. Fetched $pdt_count pdt.json from 3*instance_count fetches.\n");

	exec("/usr/local/zperfmon/bin/pdt.py ". $dir."*", $pdt_aggr);
	$file_count = array_shift($pdt_aggr);
	$file_count = explode(" ",$file_count);
	$file_count = intval($file_count[1]);
	
	$page_pdt = array();

        foreach($pdt_aggr as $pdt_list){
                $pdt_list_array = explode(",", $pdt_list);
		$page_name = $pdt_list_array[0];
		$pdt = "";
		$count_per_file = intval($pdt_list_array[1]/$file_count);
		$time_per_file = intval($pdt_list_array[2]/$file_count);

		if($prev_pdt != NULL){
			$prev_count = $prev_pdt[$page_name][0];
			$prev_time = $prev_pdt[$page_name][1];
			if($prev_count < $count_per_file){
				$count_diff = $count_per_file - $prev_count;
				$time_diff = $time_per_file - $prev_time;
				$pdt = intval($time_diff / $count_diff);
	        	}
			else{
				$pdt = $pdt_list_array[3];
			}
		}
		else{
			$pdt = $pdt_list_array[3];
		}

                $page_pdt[$page_name] = array($count_per_file,$time_per_file,$pdt);
        }
	return $page_pdt;
}
?>
