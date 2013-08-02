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
include_once "/var/www/html/zperfmon/report/report-collector.php";

class InstanceReport {
	private static function build_report($server_cfg, $game_cfg, $period, $timestamp, $report_type)
	{
		$game = null;
		if($game_cfg !== null) {
			$game = $game_cfg["name"];
		}

		// Date from half-hour timestamp
		$date_string = gmdate("M-d-Y,H:i:s", $timestamp * 1800);

		$report_file = sprintf($server_cfg["{$period}_{$report_type}_file"],
					   $game, $date_string);

		$report_url = sprintf($server_cfg["{$report_type}_url"], $game);

		$ch = curl_init($report_url);

		$fp = fopen($report_file, "w");
		if (!$ch || !$fp) {
			error_log("--\nReport generation failed: $report_file or $report_url\n--\n",
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

	private static function send_mail($server_cfg, $game_cfg, $report)
	{
		$to = "";
		$random_hash = md5(date('r', time()));
		$mime_boundary = "==Multipart_Boundary_x{$random_hash}x";
		if($game_cfg === null) {
			$subject = "Instance Utilization Summary";
			$bcc = $server_cfg["instance_report_mail_recipients"];
			$headers = 'From: '.$server_cfg["sender"]. "\r\n".
					'Bcc: ' . $bcc . "\r\n" .
					"Content-Type: multipart/mixed;".
					" boundary=\"{$mime_boundary}\"".
					'X-Mailer: PHP/' . phpversion();
			$HTMLMessage = file_get_contents($report);		
			$tidy = new tidy;
			$tidy->parseString($HTMLMessage);
			$tidy->cleanRepair();
			$message = "\n\n" .
				"--{$mime_boundary}\n" .
				"Content-Type:text/html; charset=\"iso-8859-1\"\n" .
				"Content-Transfer-Encoding: 7bit\n\n" .
				$tidy. "\n\n";
			$reportClass= new ReportCollector($server_cfg);
			$data = $reportClass->generateCSV();

			$message .=  "--{$mime_boundary}\n" .
				"Content-Type:text/csv; \n".
				" name=zPerfmonUtilTrend_".date("MjY").".csv \n".
				"Content-Transfer-Encoding: 7bit\n\n" .
				$data . "\n\n" .
				"--{$mime_boundary}--\n";
			mail($to, $subject, $message, $headers);
		} else {
			$subject = "Instance Utilization report for {$game_cfg['name']}";
			$bcc = $game_cfg["instance_report_mail_recipients"];
			$headers = 'From: '.$server_cfg["sender"] . "\r\n" .
			'Bcc: ' . $bcc . "\r\n" .
			'Content-Type: text/HTML' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

			$message = file_get_contents($report);
			
			$tidy = new tidy;
			$tidy->parseString($message);
			$tidy->cleanRepair();
			mail($to, $subject, $tidy, $headers);
		}
		
		
	}
	
	/**
    * Create a static web link for the generated html so it can accessed through browser
    */
    public static function create_static_link($server_cfg, $game_cfg, $report_file) {
        $path = $server_cfg["instance_report_web_location"];
        if(!is_dir($path)) {
            mkdir($path);
        }
		$path .= date('dm') . '/'; //directory for this day
        if(!is_dir($path)) {
            mkdir($path);
        }
		if($game_cfg) { //link for detail report per game
        	$dest = $path.$game_cfg["name"].".html";
		} else { //link for summary report
			$dest = $path."summary.html";
		}
        $ret = copy($report_file, $dest);
        if(!$ret) {
            error_log("Could not create the static html report at $dest\n", 3, sprintf($server_cfg['log_file'],$game_cfg['name']));
        }
    }

	public static function generate_summary_report($server_cfg, $timestamp, $period)
	{
		$report = self::build_report($server_cfg, null, $period, $timestamp, "instance_summary_report");
		if ($report === null) {
			return null;
		}

		self::send_mail($server_cfg, null, $report);
		self::create_static_link($server_cfg, null, $report);
	}

	public static function generate_detail_report($server_cfg, $game_cfg, $timestamp, $period)
	{
		$report = self::build_report($server_cfg, $game_cfg, $period, $timestamp, "instance_detail_report");
		if ($report === null) {
			return null;
		}

		self::send_mail($server_cfg, $game_cfg, $report);
		self::create_static_link($server_cfg, $game_cfg, $report);
	}
}

?>
