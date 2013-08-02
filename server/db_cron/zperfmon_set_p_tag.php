<?php

error_reporting(E_STRICT|E_ALL);
date_default_timezone_set('UTC');

function log_msg($str, $log_file)
{
        $t = date('Y-m-d H:i:s');
        error_log("[$t] $str\n", 3, $log_file);
}

function mysql_query_p($query, $con, $log_file)
{
        $res = mysql_query($query, $con);
        if (! $res) {
                log_msg("[zperfmon_partition_dynamics.php] query failed:
                        $query [".mysql_error($con)."]", $log_file);
		if(preg_match("/Error in list of partitions to DROP/",mysql_error($con)))
			return;
                die();
        }
        return $res;
}

function add_p_tag($db,$con,$log_file){
	$query="select timestamp from $db.xhprof_blob_30min where p_tag=0";
	$res_a = mysql_query_p($query, $con,$log_file);
	while($row_a = mysql_fetch_assoc($res_a)) {
		$timeslot = $row_a['timestamp'];
		list($mon_p,$hour_p,$min_p,$sec_p) = split("-", date('m-H-i-s',strtotime($timeslot)));
		$p_tag = $mon_p;
		
		if (!((($hour_p == 0) || ($hour_p == 2) || ($hour_p == 4) || ($hour_p == 6) || ($hour_p == 8) ||($hour_p == 10) || ($hour_p == 12) || ($hour_p == 14) || ($hour_p == 16) || ($hour_p == 18) || ($hour_p == 20) || ($hour_p == 22) ) && ($min_p == 0) && ($sec_p == 0))) {
       		        $p_tag = 0 - $p_tag;
 	      	}
		
		$query="update $db.xhprof_blob_30min set p_tag=$p_tag where timestamp='$timeslot'";
	        $res=mysql_query_p($query, $con,$log_file);
		
        }
	$query = "alter table $db.xhprof_blob_30min engine=innodb";
	$res = mysql_query_p($query, $con,$log_file);
	
	$query = "alter table $db.xhprof_blob_30min drop partition p000";
        $res = mysql_query_p($query, $con, $log_file);
}

function add_correct_p_tag($db_array,$con,$log_file){
	foreach ($db_array as $db) {
		add_p_tag($db,$con,$log_file);
        }	
}

function main($server_cfg)
{
        $eu_conf = parse_ini_file($server_cfg['eu_conf_file'], true);
        $db_host = $eu_conf['DB']['host'];
        $db_user = $eu_conf['DB']['user'];
        $db_pwd = $eu_conf['DB']['password'];

	$log_file = sprintf($server_cfg['log_file'],"add_correct_p_tag");
        log_msg("Starting [partition_dynamics]", $log_file);
        log_msg("db_host: $db_host", $log_file);

        $con = mysql_connect($db_host, $db_user, $db_pwd);
        $con or die("mysql_connect failed: " . mysql_error());
        $query = "select table_schema from information_schema.tables where
                        table_name = 'xhprof_blob_30min'";
        $res_a = mysql_query_p($query, $con, $log_file);
        $db_array = array();
        while ($row_a = mysql_fetch_assoc($res_a)) {
                $db_array[] = $row_a['table_schema'];
        }
        add_correct_p_tag($db_array,$con,$log_file);
        mysql_close($con);
}

include_once "/etc/zperfmon/server.cfg";
main($server_cfg);
?>
