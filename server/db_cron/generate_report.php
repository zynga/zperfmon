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


//
// For each passed in name, generate HTML report at a known location
// and send it to all recipients configured.
//

ini_set("memory_limit", -1);

include_once "server.cfg";
include_once "game_config.php";
include_once "curl_prefetch.php";
include_once "logger.inc.php";


//
// Generate a daily or weekly status report for the given game. $period
// specifies whether the report is daily or weekly, default is daily.
// $timestamp gives a day or week for which the report has to be generated,
// this defaults to null which means 'now'.
//
function build_report($server_cfg, $game_cfg, $period, $timestamp)
{
	$game = $game_cfg["name"];

	// Date from half-hour timestamp
	$date_string = gmdate("M-d-Y,H:i:s", $timestamp * 1800);

	$report_file = sprintf($server_cfg["{$period}_report_file"],
			       $game, $date_string);

	$report_url = sprintf($server_cfg["report_url"], $game);

	$ch = curl_init($report_url);

	$fp = fopen($report_file, "w");
	if (!$ch || !$fp) {
		error_log("--\nReport generation failed: $report_file or $report url\n--\n",
			  3, sprintf($server_cfg['log_file'],$game_cfg['name']));
		return  null;
	}

	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	curl_exec($ch);
	curl_close($ch);
	fclose($fp);

	return $report_file;
}


function send_mail($server_cfg, $game_cfg, $report)
{
	$to = "";

	$bcc = $server_cfg["mail_recipients"];
	$bcc .= "," . $game_cfg["mail_recipients"];

	$subject = "Performance report for {$game_cfg['name']}";
	$headers = 'From: noreply@xxxx.xxx' . "\r\n" .
		'Bcc: ' . $bcc . "\r\n" .
		'Content-Type: text/HTML' . "\r\n" .
		'X-Mailer: PHP/' . phpversion();

	$message = file_get_contents($report);
	
	$tidy = new tidy;
    $tidy->parseString($message);
    $tidy->cleanRepair();
    mail($to, $subject, $tidy, $headers);
}


function generate_report($server_cfg, $game_cfg, $timestamp, $period)
{
	$report = build_report($server_cfg, $game_cfg, $period, $timestamp);
	if ($report === null) {
		return null;
	}

	send_mail($server_cfg, $game_cfg, $report);
}

?>
