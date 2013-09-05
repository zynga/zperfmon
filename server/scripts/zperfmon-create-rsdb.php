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

$rs_conf_file = "/etc/zperfmon/rs.ini";

$eu_conf = parse_ini_file('/etc/zperfmon/rs.ini', true);

$db = $eu_conf['DB']['database'];
$user = $eu_conf['DB']['user'];
$pass = $eu_conf['DB']['password'];
$host = $eu_conf['DB']['host'];

$eu_schema_file = "/usr/local/zperfmon/etc/schemas/rs_schema.sql";


$cmd = "cat $eu_schema_file | mysql -u $user -p$pass -h $host";

$ret = exec($cmd, $output, $ret_status);

if($ret_status != 0) {

	echo "rs database is not created\n";
}

