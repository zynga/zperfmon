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


include "dau-collector.php";

function print_dau()
{
	header("Content-Type: text/plain");
	$end_time = time();
	$start_time = $end_time - (24*3600);

	$daustore = new DAUAdapter();
	$data = $daustore->get_dau(null, $start_time, $end_time);
	print_r($data);
}

function shadow_upload($target_file)
{
	if(defined('SHADOW_UPDATE_URL') && !isset($_GET['shadow'])) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_VERBOSE, 0);
		curl_setopt($ch, CURLOPT_URL, SHADOW_UPDATE_URL."?shadow&v=".$_GET["v"]);
		curl_setopt($ch, CURLOPT_POST, true);
		$post = array("DAU"=>"@$target_file");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		if($response === false) {
			error_log("Shadow upload of DAU to ".SHADOW_UPDATE_URL." failed");
		}
		curl_close($ch);
	}
}

function store_dau()
{
	$rows = array();
	$tmp_file = fopen($_FILES['DAU']['tmp_name'], "r");

	while($row = fgetcsv($tmp_file, 0, "\t")) 
	{
		$rows[] = $row;
	}

	$daustore = new DAUAdapter();

	$daustore->store_dau($rows);
	shadow_upload($_FILES['DAU']['tmp_name']);
}

$method = $_SERVER['REQUEST_METHOD'];

if($method == "GET") print_dau();
else if ($method == "POST") store_dau();
?>
