#!/usr/bin/php
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

	$dir = "/tmp/pdts/";
	$options = getopt("p:c:");
	$pool_ip = $options['p'];
	$instance_count_parallel = $options['c'];
	$cmd = "curl -s 3.141592653:2.718281823@$pool_ip/zplog/pdt.json";

	// Fetching the pdt.json file instance_count_parallel times serially
	for($i=0;$i<$instance_count_parallel;$i++){
		$out = exec($cmd);
		if(json_decode($out) == null){
			continue;
         	}
		$md5 = exec("echo -n $out | md5sum | awk '{print $1 }'");
	
		file_put_contents($dir.$md5, $out);
	}
?>
