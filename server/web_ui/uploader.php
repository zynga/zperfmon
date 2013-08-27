<?php

include_once 'setup_page.php';
include_once 'XhProfModel.php';
include_once 'logger.inc.php';

$target_file = null;

function getRealIpAddr()
{
	// check ip from share internet
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { // to check ip is pass from proxy
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		if (isset($_SERVER) and key_exists('REMOTE_ADDR', $_SERVER)) {
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = "127.0.0.1";
		}
	}

	return $ip;
}

function process_target($target_dir, $file_name, $logger, $content=false)
{
	if($content === false) {
		$tmp_file = $_FILES['uploadedfile']['tmp_name'];
		$uploaded_file_name = basename($_FILES['uploadedfile']['name']);
	}

	$timestamp = get_timestamp($uploaded_file_name, $content !== false);

	$target_dir = create_directory($target_dir, $timestamp);

	if ($target_dir === null) {
		$logger->log("uploader","Directory is not created",Logger::CRIT);
		header("HTTP/1.1 500 Server error");
		echo "Could not create directory $target_dir\n";
		return;
	}

	$target_file = $target_dir."/".$file_name;

	if ( (isset($tmp_file) && move_uploaded_file($tmp_file, $target_file)) || 
	      file_put_contents($target_file, $content) ) {

		$logger->log("uploader",$target_file." has been uploaded",Logger::INFO);
		echo "SUCCESS: $target_file has been uploaded<br></br>\n";
		touch($target_dir."/.".basename($target_file)); 
	} else {
		$logger->log("uploader","could not move ". $tmp_file . 
				" to " . $target_file,Logger::ERR);

		header("HTTP/1.1 500 Server error");
		echo "FAILED: could not move $tmp_file to  $target_file<br></br>\n";
	}
	return $target_file;
}

function backfill_target($server_cfg, $game_cfg, $target_path, $command, $logger, $content = false)
{
	$command = strtolower($command);
	$xhProfModelObject = new XhProfModel($server_cfg, $game_cfg, false);	

	$tmp_file = $_FILES['uploadedfile']['tmp_name'];
	$uploaded_file_name = basename($_FILES['uploadedfile']['name']);
	if (isset($tmp_file)) {
		$content = file_get_contents($tmp_file);
	}
	
	$timestamp = get_timestamp($uploaded_file_name, $content !== false);
	$result = insert_event($xhProfModelObject, $server_cfg, $game_cfg, $timestamp, $command, $content);
	
	if ($result) {
		$logger->log("uploader",$content." has been inserted",Logger::INFO);
		return "\"$content\" as a \"$command\" event at $timestamp";
	}

	$target_file = "$target_path/$timestamp.$command";

	if ((isset($tmp_file) && move_uploaded_file($tmp_file, $target_file)) || 
             file_put_contents($target_file, $content)) {
	     
		$logger->log("uploader",$target_file." has been uploaded",Logger::INFO);
		echo "SUCCESS: $target_file has been uploaded<br></br>\n";
		touch("$target_path/.$command"); 
	} else {
		$logger->log("uploader","could not move ". $tmp_file . 
				" to " . $target_file,Logger::ERR);
		$target_file = null;
		header("HTTP/1.1 500 Server error");
		echo "FAILED: could not move $tmp_file to  $target_file<br></br>\n";
	}
	return $target_file;
}

function insert_event($xhProfModelObject, $server_cfg = null, $game_cfg = null, $timestamp, $type, $text)
{
	if (!$xhProfModelObject) {
		return false;
	}
	$query_name = "event_insert";
	$result = null;
	$result = $xhProfModelObject->generic_execute_get_query($query_name,
								array('start'=>$timestamp,
								'type'=>$type,
								'text'=>$text
								), false, false);

	//if its a array, just return, else if its a game ,fill the array dbs as well
	if( isset($game_cfg['parent']) ){
		//its an array, so our work here is done, return
		return $result;
	}

	$result_array = null;
	$arrays = get_array_id_map($server_cfg, $game_cfg);
	
	//for loop to put events in each array db
	foreach($arrays as $array=>$array_id){
		$game_array_cfg = load_game_config($game_cfg['name'], $array_id);
	        
		$xhProfModelObject = new XhProfModel($server_cfg, $game_array_cfg, false);
		$result_array = $xhProfModelObject->generic_execute_get_query($query_name,
        	                                                        array('start'=>$timestamp,
                	                                                'type'=>$type,
                        	                                        'text'=>$text
                                	                                ), false, false);
	
	}	
	
	return $result;
}

function process_xhprof_target($xhprof_target_dir, $logger)
{
	
	$tmp_file = $_FILES['uploadedfile']['tmp_name'];
	
	$source_ip = getRealIpAddr();
	$uploaded_file_name = basename($_FILES['uploadedfile']['name']);

	if (isset($_GET['shadow'])) {
		// rename <timestamp>.tar.bz__<ip> to <timestamp>.tar.bz__
		$uploaded_file_name = str_replace("__${source_ip}","",$uploaded_file_name);
	}
	
	$timestamp = get_timestamp($uploaded_file_name, false);
	$target_dir = create_directory($xhprof_target_dir, $timestamp);
	if ($target_dir === null) {
		$logger->log("uploader","Directory is not created",Logger::CRIT);
		header("HTTP/1.1 500 Server error");
		echo "Could not create directory $xhprof_target_dir\n";
		return;
	}	

	$target_file = "$target_dir/{$uploaded_file_name}__{$source_ip}";

	if (move_uploaded_file($tmp_file, $target_file)) {
		$logger->log("uploader",$target_file." has been uploaded",Logger::INFO);
		echo "SUCCESS: $target_file has been uploaded<br></br>\n";

		// 
		// Write the uploaded file name to .profiles for reference to what all files has been uploaded
		// This will be used while processing.
		//
		file_put_contents("$target_dir/.profiles", "{$uploaded_file_name}__{$source_ip},", FILE_APPEND | LOCK_EX); // functions file tag is inserted in-cron
		touch("$target_dir/.slowpages");
		touch("$target_dir/.apache_stats");
	} else {
		$logger->log("uploader","could not move ". $tmp_file . 
				" to " . $target_file,Logger::ERR);
		$target_file = null;
		header("HTTP/1.1 500 Server error");
		echo "FAILED: could not move $tmp_file to  $target_file<br></br>\n";
	}
	return $target_file;
}

function create_directory($dir_name,$timestamp)
{
	if (!$timestamp) {
		return null;
	}

	//
	// Make sure any minute inside given half hour slot goes into that
	// timeslot. If we round(), till 15th minute will go into the previous
	// timeslot and 15-30 will go into the next timeslot. To avoid that,
	// we cast to int and get the mantissa.
	//
	$time_slot = (int)($timestamp / (30 * 60));
	$dir_name = sprintf($dir_name, (string)$time_slot);
	$oldmask = umask(0); // to set the chmod of directory as 777

	if (!is_dir($dir_name)  && !mkdir($dir_name, 0777, true)) {
		return null;
	}

	umask($oldmask);
	return $dir_name;
}

function get_timestamp($uploaded_file, $is_content=false)
{
	$timestamp = time();
	if ($is_content) {
		// a content has uploaded just return current timestamp
		return $timestamp;
	}
	
	if (!$uploaded_file) {
		header("HTTP/1.1 500 Server error");
		echo "FAILED: No file name specified for upload<br></br>\n";
		return null;
	}

	$matches = array();

	if (preg_match("/^([0-9]+)\..*/", $uploaded_file, $matches)) {
		/*uploaded file has timestamp*/
		$timestamp = (int)$matches[1];
	}
	return $timestamp;
}

function cmd_dispatch($server_cfg, $game_cfg, $command)
{
	$cmd_to_file = array("XHPROF" => "profile_upload_directory",
			     "DAU" => "dau_file",
			     "TAG" => "tag_dir",
			     "RELEASE" => "release_dir",
			     "VERTICA" => "zmonitor_upload_file");

	$target_dir = sprintf($server_cfg['root_upload_directory'],$game_cfg["name"]);
	
	$content = false;
	if (array_key_exists("content", $_POST)) {
		$content = $_POST["content"];
	}

	$logger = new Logger($server_cfg,$game_cfg);
	switch ($command) {
	case "XHPROF":
		$target_dir = $target_dir."/%s/".$server_cfg[$cmd_to_file[$command]];
		$target_file = process_xhprof_target($target_dir, $logger);
		break;
	case "TAG":
	case "RELEASE":
		$target_dir = sprintf($server_cfg['event_directory'], $game_cfg['name']);
		$target_file = backfill_target($server_cfg, $game_cfg, $target_dir, $command, $logger, $content);
		break;
	case "DAU":
	case "VERTICA":
		$target_dir = $target_dir."/%s/";
		$target_file = process_target($target_dir, $server_cfg[$cmd_to_file[$command]], $logger, $content);
		break;
	default:
		$logger->log("uploader","Your wish is beyond my repertoire, ". 
				"unknown command \".$command.\"",Logger::ERR);

		header("HTTP/1.1 500 Server error");
		echo "Your wish is beyond my repertoire, unknown command \"$command\"";
		break;
	}
	
	return $target_file;
}

$target_file = cmd_dispatch($server_cfg, $game_cfg, $_POST["cmd"]);

//define('SHADOW_UPDATE_URL', "http://xxxx.xxxx.xxx/zperfmon/uploader.php");
if(defined('SHADOW_UPDATE_URL') && !isset($_GET['shadow'])) {
	$uploaded_file = basename($_FILES['uploadedfile']['name']);
	$headers = array(
		"X-Forwarded-For: ".getRealIpAddr(),
		"X-Via: ".$_SERVER['SERVER_ADDR']
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_URL, SHADOW_UPDATE_URL."?shadow");
	curl_setopt($ch, CURLOPT_POST, true);
	$post = array(
			"uploadedfile"=>"@$target_file",
			"cmd" => $_POST["cmd"],
			"game" => $game
		     );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 
	$response = curl_exec($ch);
	curl_close($ch);
}

if ($target_file != null) {
	header("HTTP/1.1 200 Success"); // changed return code to 200 from 201
	echo "SUCCESS: processed $target_file<br/>\n";
}

?>
