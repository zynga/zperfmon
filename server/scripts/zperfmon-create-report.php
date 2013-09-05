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

