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

include_once 'eu-collector.php';
include_once 'server.cfg';

function shadow_upload($target_file)
{
	if(defined('SHADOW_UPDATE_URL') && !isset($_GET['shadow'])) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_URL, SHADOW_UPDATE_URL."?shadow&v=".$_GET["v"]);
		curl_setopt($ch, CURLOPT_POST, true);
		$post = array("EU"=>"@$target_file");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		if($response === false) {
			error_log("Shadow upload of EU to ".SHADOW_UPDATE_URL." failed");
		}
		curl_close($ch);
	}
}

function store_eu($server_cfg)
{
	$rows = array();
	$tmp_file = fopen($_FILES['EU']['tmp_name'], "r");

	while($row = fgetcsv($tmp_file, 0, ",")) 
	{
		$rows[] = $row;
	}

	$eustore = new EUAdapter($server_cfg);

	$eustore->store_eu($rows);
	
	shadow_upload($_FILES['EU']['tmp_name']);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method == "POST") store_eu($server_cfg);

?>
