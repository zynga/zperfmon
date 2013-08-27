#!/usr/bin/php
<?php

$eu_conf_file = "/etc/zperfmon/eu.ini";

$eu_conf = parse_ini_file('/etc/zperfmon/eu.ini', true);

$db = $eu_conf['DB']['database'];
$user = $eu_conf['DB']['user'];
$pass = $eu_conf['DB']['password'];
$host = $eu_conf['DB']['host'];

$eu_schema_file = "/usr/local/zperfmon/etc/schemas/eu_schema.sql";


$cmd = "cat $eu_schema_file | mysql -u $user -p$pass -h $host";

$ret = exec($cmd, $output, $ret_status);

if($ret_status != 0) {

	echo "eu database is not created\n";
}

