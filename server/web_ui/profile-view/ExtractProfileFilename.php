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


header("Content-Type:text/json");

//$_REQUEST['timestamp'] = 1284865200;
//$_GET["game"] = "fish";

ini_set('memory_limit', '48M');

$doc_root = "/var/www/html";

$core_include = $doc_root . "/zperfmon/include/";

set_include_path(get_include_path() . ":$core_include");
include_once 'setup_page.php';

$game_name = $game_cfg["name"];
include_once('XhProfModel.php');
include_once('XhProfJSONView.php');

// We will pick this up from the game config
$blob_tgt_dir = $doc_root . "/zperfmon/blobs/" . $game_cfg["name"];

$timestamp = '';
if(isset ($_REQUEST['timestamp']))
	$timestamp = $_REQUEST['timestamp'];

// Align to nearest 30 minute timestamp
$start = ($timestamp/(30 * 60)) * 30 * 60;

// end is start of next 30min slot
$end = $start + 30 * 60;

//echo $timestamp;

// xhprof files array
$xhprofiled_files_list = array();

function extract_from_db($game_cfg, $start, $end)
{
	// Database connection variables
	$dbServer = $game_cfg["db_host"];
	$dbDatabase = $game_cfg["db_name"];
	$dbUser = $game_cfg["db_user"];
	$dbPass = $game_cfg["db_pass"];
	$table = $game_cfg["xhprof_blob_table"];

	$sConn = mysql_connect($dbServer, $dbUser, $dbPass)
		or die("Couldn't connect to database server");

	$dConn = mysql_select_db($dbDatabase, $sConn)
		or die("Couldn't connect to database $dbDatabase");


	$dbQuery = "SELECT unix_timestamp(timestamp) as timestamp, xhprof_blob FROM $table WHERE timestamp >= from_unixtime($start) AND timestamp < from_unixtime($end)";

	// echo $dbQuery;
	$result = mysql_query($dbQuery) or die("Couldn't get file list");


	// We expect only one row per timestlot.
	if (mysql_num_rows($result) == 1) {
		$fileContent = @mysql_result($result, 0, "xhprof_blob");

		//$timestamp = @mysql_result($result, 0, "timestamp");
		$fileContent = @mysql_result($result, 0, "xhprof_blob");
		return $fileContent;
	}

	echo "Record doesn't exist.";
	return null;
}

function encode_send_filelist($blobdir) {
	$xhprofiled_files_list = array();
	global $doc_root;

	if(file_exists("$blobdir/manifest.json")) {
		$manifest = json_decode(file_get_contents("$blobdir/manifest.json"));
		foreach($manifest as $page => $data) {
			list($xhprof,$samples) = $data;
			if(is_array($samples)) $samples = count($samples);
			if( isset($data[2])) $memory_prof =$data[2]; else $memory_prof = "MemDisabled";
			$xhprofiled_files_list[$page." (".($samples).")[$memory_prof]"] = "$blobdir/$xhprof";
		
		}
	}
	else foreach (glob($blobdir . "/*") as $filename) {

		preg_match("/[0-9]{10}\.(.*).xhprof$/", $filename, $match);

		if (!$match) {
			continue;
		}
		
		$xhprofiled_files_list[$match[1]] = $filename; 
	}

	echo json_encode($xhprofiled_files_list);
}

if(!file_exists($blob_tgt_dir)) {
	if(!mkdir($blob_tgt_dir,0777,true)) {
		echo "Not enough permissions to create dir $blob_tgt_dir\n";
		return;
	}	
}

if (!chdir($blob_tgt_dir)) {
	echo "Could not change directory to $blob_tgt_dir!\n";
	return;
}

$blobtgz = "blob.$timestamp.tbz";
$blobdir = "_blobdir_$timestamp";

// By default pull from DB
$operation = 1;
$dirtime = 0;
$filetime = 0;

if (is_dir($blobdir)) {
	$dirtime = filemtime($blobdir);
}

if (is_file($blobtgz)) {
	$filetime = filemtime($blobtgz);
}

if ($filetime + $dirtime == 0) {
	$operation = 3;
} else if ($filetime > $dirtime) {
	$operation = 2;
}

switch($operation)
{
	case 3:
	{
		$fileContent = extract_from_db($game_cfg, $start, $end);
		if($fileContent === null) 
		{
			exit(0);
		}
		file_put_contents($blobtgz, $fileContent);
	}
	/* fall through */
	case 2:
	{
		$command = "rm -rf $blobdir; mkdir $blobdir; tar -C $blobdir --strip-components 1 -jxf $blobtgz; rm $blobtgz";
		exec($command, $output, $retval);
		if ($retval != 0) {
			echo "Bad tbz file or mis-behaving zperfmon server. Spank it!";
			exit(0);
		}
	}
	/* fall through */
	case 1:
	{
		encode_send_filelist($blob_tgt_dir."/".$blobdir);
	}
	break;
	default:
	{
		echo "We have a known Unknown error";
	}
}

?>
