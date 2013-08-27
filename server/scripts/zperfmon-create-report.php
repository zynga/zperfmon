#!/usr/bin/php
<?php

#
# This script is to create database and the tables 
# required for report trending.
#
# author: gkumar@zynga.com (Gaurav Kumar)
# modified: uprakash@zynga.com (Ujwalendu Prakash)
#

include_once 'server.cfg';

$report_conf_file = $server_cfg['report_conf_file'];

$report_conf = parse_ini_file($report_conf_file, true);

$db = $report_conf['DB']['database'];
$user = $report_conf['DB']['user'];
$pass = $report_conf['DB']['password'];
$host = $report_conf['DB']['host'];

$report_schema_file = "/usr/local/zperfmon/etc/schemas/report.sql";


$cmd = "cat $report_schema_file | mysql -u $user -p$pass -h $host";

$ret = exec($cmd, $output, $ret_status);

if($ret_status != 0) {

	echo "report database is not created\n";
}

