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

include_once 'spyc.php';
include_once "server.cfg";

function load_config($str_yaml) {

        return Spyc::YAMLLoad($str_yaml);
}

function get_conf($server_cfg, $game_name){

        $common_config = $server_cfg["common_config_file"];
        $hostgroup_config = sprintf($server_cfg["hostgroups_config"], $game_name);

                #print $common_config." ".$hostgroup_config;
        //        $f_common_config = fopen($common_config,"r");
          //      $f_hostgroup_config = fopen($hostgroup_config,"r");

            //    $content_common = fread($f_common_config,filesize($common_config));
              //  $content_hostgroup = fread($f_hostgroup_config,filesize($hostgroup_config));

                //$content_config = $content_common.$content_hostgroup;
                //$cfg = load_config($content_config);

        $f_common_config = file_get_contents($common_config);
        $f_hostgroup_config = file_get_contents($hostgroup_config);

        $content_config = $f_hostgroup_config.$f_hostgroup_config;
        $cfg = load_config($content_config);

	return $cfg;
}

?>
