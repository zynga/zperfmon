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


/*
* This script compresses the profiles uploaded to zperfmon-server
* using bzip2 compression  and deletes the uploaded tar files
*/

function bzip2_compress($src_file, $dest_file=null) {

	if($dest_file == null) {
		$dest_file = $src_file;
	}

	$content = file_get_contents($src_file);
	if(empty($content)) {
		return 1;
	}

	$bz = bzopen($dest_file, "w");
	if(!$bz) {
		return 2;
	}

	$ret = bzwrite($bz, $content);
	if( !$ret ) {
		bzclose($bz);
		return 3;
	}

	bzclose($bz);
	return 0;
}

function remove_uploaded_profiles( $files = array() ) {

	$undeleted_files = array();
	foreach( $files as $file ) {
		if ( ! unlink($file) ) {
			$undeleted_files[] = $file;
		}
	}
	return $undeleted_files;
}

function compress_unziped_profiles($server_cfg, $game_cfg, $time_slots) {
	
	$game = $game_cfg['name'];
        $log_file = sprintf($server_cfg['log_file'], $game);
        $root_upload_directory = sprintf($server_cfg["root_upload_directory"],
                                                    $game_cfg["name"]);

        // what all directories have been processed
        $compress_marker = glob("$root_upload_directory/$time_slots/" .
                                      $server_cfg['profile_upload_directory'] . "/.compress", GLOB_BRACE);

	$ret_values = array(0 => "bzipped and wriiten successfully",
		     	    1 => "src file is empty",
		     	    2 => "error while opening the bzip2 file",
		     	    3 => "error in writing the bzip2 file",
		     	   );

	foreach ( $compress_marker as $marker ) {
		$profiles = array();
		$xhprof_dir = dirname($marker);
		// First collect aggregated .xhprof profiles which are in _blob_/ directory
		$agg_profiles = glob("$xhprof_dir/_blobdir_/[1-9]*.xhprof"); 
		$profiles = array_merge($profiles, $agg_profiles);

		if ( !isset($game_cfg['id']) )  {
			// Its a parent game, collect all the profiles in IP directories
			// Profiles listed in IP directory
			$profiles = glob("$xhprof_dir/[1-9]*/*:xhprof");
			$profiles = array_merge($profiles, $agg_profiles);
			// uploaded tar files to be deleted
			$tar_files = glob("$xhprof_dir/*.tar.bz__[1-9]*");
		}

		foreach ( $profiles as $profile) { 
			$ret = bzip2_compress($profile);
			if ( $ret != 0 ) {
				error_log( "bzip2 compress failed for $profile: {$ret_values[$ret]}\n", 3, $log_file );
			}
		}

		// Delete the uploaded tar files
		if ( !empty($tar_files) ) {
			$undeleted_files = remove_uploaded_profiles($tar_files);
			error_log( "un deleted tar files are: " . print_r($undeleted_files, true), 3, $log_file);
		}
		// Delete the marker file
		unlink($marker);
	}
}
