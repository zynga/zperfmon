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


ini_set("memory_limit", -1);

include_once 'server.cfg';
include_once 'logger.inc.php';
include_once 'game_config.php';
include_once 'XhProfModel.php';
include_once 'yml_conf.inc.php';
include_once 'rightscale.php';

function create_query_add_column($table, $columns)
{
	if (empty($columns)) {

		return;
	}

	$query = "ALTER TABLE $table ADD COLUMN ( ";

	$cols = array();

	foreach ( $columns as $col=>$col_property ) {
		$type = $col_property['type'];
		$default = $col_property['default'];
		$cols[] = "`$col` $type DEFAULT '$default'";
	}

	$query .= implode(",", $cols) . ")";
	return $query;
}


function get_table_columns($table, $xhprofModelObj)
{
	$table_columns = array();
	$query_name = "get_columns";
	$return = $xhprofModelObj->generic_execute_get_query($query_name, 
							     array("table"=>$table), 
							     false, false 
							    );
		
	if ( !$return ) {
		echo "error in getting table columns of $table: ". mysql_error();
		return ;
	}

	while ($row = mysql_fetch_row($return)) {
		$table_columns[] =  $row[0];
	}

	return $table_columns;
}

function get_missing_columns($config_columns, $table, $xhprofModelObj)
{


	$table_columns = get_table_columns($table, $xhprofModelObj);
	if ( empty($table_columns) ) {
		
		echo "table columns are empty!!";
	}
	
	$config_missing_columns = array_diff(array_keys($config_columns), $table_columns);
	$missing_columns = array();

	foreach ( $config_missing_columns as $col ) {

		$missing_columns[$col] = $config_columns[$col];
	}

	return $missing_columns;
}

function add_missing_columns($config_col_names, $table, $xhprofModelObj)
{
	
	$missing_columns = get_missing_columns($config_col_names, $table, $xhprofModelObj);
	
	$add_column_query = create_query_add_column($table, $missing_columns);
	
	if ( empty($add_column_query) ) {
		echo "cannot create query as missing column entries are empty exiting\n";
	}

	echo $add_column_query."\n";
	$query_name = "add_missing_columns";

	$return = $xhprofModelObj->generic_execute_get_query($query_name, 
							     array('query'=>$add_column_query), 
							     false, false 
							    );
	if ( !$return ) {
		echo "columns are not added mysql_error : ".mysql_error();
		return 1;
	} else {
		return 0;
	}
}

function main($server_cfg)
{
	$options = getopt("g:");
	$game = $options['g'];

	$game_cfg = load_game_config($game);
	$hostConfigObj = new HostgroupConfig($server_cfg, $game);

	$rsObj = new RightScale($server_cfg, $game_cfg);
	
	$array_id_name = array_values($rsObj->get_array_to_arrayid_mapping());
	$config_col_names = $hostConfigObj->get_config_column_names();
	$xhprofModelObj = new XhprofModel($server_cfg, $game_cfg, false);

	// add columns for both the tables
	$tables = array("vertica_stats_30min", "vertica_stats_daily");

	echo "adding columns for $game:\n";
	foreach ( $tables as $table ) { 
		$result = add_missing_columns($config_col_names, $table, $xhprofModelObj);
	}
	foreach($array_id_name as $array_id) {
		
		echo "adding columns for $game:$array_id:\n";
		$game_cfg = load_game_config($game, $array_id);
		$xhprofModelObj = new XhprofModel($server_cfg, $game_cfg, false);
		foreach ( $tables as $table ) {
			$result = add_missing_columns($config_col_names, $table, $xhprofModelObj);
		}
	}
}

main($server_cfg);

?>
