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


error_reporting(E_STRICT|E_ALL);
date_default_timezone_set('UTC');

include_once "server.cfg";
include_once "game_config.php";
include_once "rightscale.php";

function get_options()
{
        $options = getopt("g:t:");

        return $options;
}

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
		die();
	}
	return $res;
}

function drop_partition($partition_drop,$partition_add_elements,$db,$con,$log_file)
{
	$batch_size = 200;
        log_msg("Dropping + adding partition $partition_drop", $log_file);
        $query = "alter table $db.xhprof_blob_30min drop partition $partition_drop";
        $res = mysql_query_p($query, $con, $log_file);
        $partition_add = $partition_drop;
        $a = $partition_add_elements[0];
        $b = $partition_add_elements[1];
        $c = $partition_add_elements[2];
        $d = $partition_add_elements[3];
        $query = "alter table $db.xhprof_blob_30min add partition
                        (partition $partition_add values in ($a,$b,$c,$d)) ";
        $res = mysql_query_p($query, $con, $log_file);

}

function drop_partition_data($partition_drop, $partition_drop_array, $array_ids, $db, $con, $log_file)
{
	$partition_add_elements_array = array
                ("p001" => array(1,7,-1,-7),"p002" => array(2,8,-2,-8),"p003" => array(3,9,-3,-9),"p004" => array(4,10,-4,-10),
                "p005" => array(5,11,-5,-11),"p006" => array(6,12,-6,-12));

	$partition_add_elements = $partition_add_elements_array[$partition_drop];
	$partition_add_elements_array = $partition_add_elements_array[$partition_drop_array];

	drop_partition($partition_drop,$partition_add_elements,$db, $con, $log_file);
	foreach($array_ids as $id){
		$array_db = $db."_".$id;	
		drop_partition($partition_drop_array,$partition_add_elements_array,$array_db, $con, $log_file);	
	}
}

function delete_rows($cursor_delete, $db, $con, $log_file)
{
	log_msg("Deleting specific rows with p_tag=$cursor_delete", $log_file);
	$query = "select count(*) as count from $db.xhprof_blob_30min where p_tag = $cursor_delete";
	$res = mysql_query_p($query, $con, $log_file);
	$row = mysql_fetch_assoc($res);
	$c = $row['count'];
	log_msg("$c rows to delete", $log_file);
	$query = "delete from $db.xhprof_blob_30min where p_tag = $cursor_delete";
	$res = mysql_query_p($query, $con, $log_file);
}

function crunch_space($db, $con, $log_file)
{
	log_msg("crunching space ", $log_file);
	$query = "alter table $db.xhprof_blob_30min engine=innodb";
	$res = mysql_query_p($query, $con, $log_file);
}

function report_partitions_state($db, $con, $log_file)
{
	$query = "select partition_name, table_rows from information_schema.partitions 
		where table_name = 'xhprof_blob_30min' and table_schema = '$db'";
	$res = mysql_query_p($query, $con, $log_file);
	$msg = "<partition_name, no_of_rows>";
	while ($row = mysql_fetch_assoc($res)) {
		$p_name = $row['partition_name'];
		$table_rows = $row['table_rows'];
		$msg = $msg . ", <$p_name, $table_rows>";
	}
	log_msg($msg, $log_file);
}

function partition_dynamics($game_names, $server_cfg, $con, $log_file) 
{
	//Month to Partition map
	$partition_drop_map = array('p001','p002','p003','p004',
                'p005','p006','p001','p002','p003','p004','p005','p006');
	
	//Current month to retention month p_tag map.
        $cursor_retain_months = array(11,12,1,2,3,4,5,6,7,8,9,10);

	$month_index = date('m') - 1;
	
	$array_drop_index = $month_index - 2;
	
	//If array drop index is negative go to the end of array
	if($array_drop_index < 0)
		$array_drop_index = 12 + $array_drop_index; 

	$partition_drop_array_games = $partition_drop_map[$array_drop_index];

	foreach ($game_names as $game) {
		$db = "zprf_".$game;
		report_partitions_state($db, $con, $log_file);

	        $cursor_retain = $cursor_retain_months[$month_index];
       		$cursor_delete = (0 - $cursor_retain);

		//Deleting rows in the ratio 1:4 
		delete_rows($cursor_delete, $db, $con, $log_file);		
		
		//Doing an alter table
		crunch_space($db, $con, $log_file); 

		//Fetching the retention time from game_cfg file
		$game_cfg = load_game_config($game);

		$retention_time = $game_cfg["xhprof_retention_time"];
		//Maximum retention time = 4 months
		//If retention time not specified setting the default retention time as 4 months
		if($retention_time == NULL or $retention_time > 4)
			$retention_time = 4; 
		
		$partition_drop_index = $month_index - $retention_time - 1;

		if($partition_drop_index < 0)
			$partition_drop_index = 12 + $partition_drop_index; 
	
		$partition_drop = $partition_drop_map[$partition_drop_index];

		$rs = new RightScale($server_cfg, load_game_config($game));
	        $array_ids = $rs->get_arrays_to_serve();
		drop_partition_data($partition_drop, $partition_drop_array_games, $array_ids, $db, $con, $log_file);
	}
}

function main($server_cfg)
{
	$options = get_options();
	$game_names=null;
        if (isset($options['g']) && $options['g'] !== '') {
                $game_names = explode(",",$options['g']);
        } else {
                $game_names = $server_cfg['game_list'];
        }
	
	$eu_conf = parse_ini_file($server_cfg['eu_conf_file'], true);
	$db_host = $eu_conf['DB']['host'];
	$db_user = $eu_conf['DB']['user'];
	$db_pwd = $eu_conf['DB']['password'];

	$log_file = sprintf($server_cfg['log_file'],"partition_cron");
	log_msg("Starting [partition_dynamics]", $log_file);
	log_msg("db_host: $db_host", $log_file);
	$con = mysql_connect($db_host, $db_user, $db_pwd);
	$con or die("mysql_connect failed: " . mysql_error());

	partition_dynamics($game_names, $server_cfg, $con, $log_file);

	log_msg("Done [partition_dynamics] for non array games", $log_file);

	mysql_close($con);
}

main($server_cfg);
?>

