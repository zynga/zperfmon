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

include_once 'server.cfg';

function calculate_slack($game,$slack_now=true){
        global $server_cfg;
        $api = sprintf($server_cfg["compute_slack_api"],$server_cfg["hostname"]);
	
	if($slack_now)
	        $post_values = "game_name=".$game."&slack_now=true";
	else
		$post_values = "game_name=".$game."&slack_now=false";
        $options = array (CURLOPT_RETURNTRANSFER => true, // return web page
                                        CURLOPT_HEADER => false, // don't return headers
                                        CURLOPT_FOLLOWLOCATION => true, // follow redirects
                                        CURLOPT_ENCODING => "", // handle compressed
                                        CURLOPT_USERAGENT => "zperfmon", // who am i
                                        CURLOPT_AUTOREFERER => true, // set referer on redirect
                                        CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
                                        CURLOPT_TIMEOUT => 60, // timeout on response
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_POST => 2,
                                        CURLOPT_POSTFIELDS => $post_values);

        $ch = curl_init ( $api );
        curl_setopt_array ( $ch, $options );
        $content = curl_exec ( $ch );
        $err = curl_errno ( $ch );
        $errmsg = curl_error ( $ch );
        $httpCode = curl_getinfo ( $ch, CURLINFO_HTTP_CODE );
        curl_close ( $ch );
        if($httpCode!=200){
                die("HTTP Error Code : ".$httpCode."\nCURL Error : $errmsg\n");
        }

        $content = trim($content);
        $content = explode("\n",$content);
        return $content[0];

}

?>
