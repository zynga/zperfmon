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


//
// Helper script to retrieve server configuration entries. 
// 
// Argument to the script is a comma-separated list of configuration
// variables. If called with one variable, it will print the value of
// that variable directly. If there are more variables, the output is
// a json formatted associative array of "var" => value.
//

include_once 'server.cfg';
include_once 'zpm_util.inc.php';
zpm_preamble("");

function getValue($value,$quote,$space_seperated){

	if (!is_string($value)) {
		if($space_seperated == true){
			$value = implode(' ', $value); 
		}
		else{
        		$value = json_encode($value);
		}
        } elseif($quote) {

                $value = "\"$value\"";
        }
	return $value;
}

function main($server_cfg, $argv) {

	$space_seperated = false;

	array_shift($argv);
	
        if ($argv[0] == "-s") {
		$space_seperated = true;
                array_shift($argv);
        }

	$var_string = implode("", $argv);
	
	if (empty($var_string)) {
		return;
	}
	
	$conf_vars = explode(",", $var_string);
		
   	if (count($conf_vars) == 0) {
		// ignore
	} else if (count($conf_vars) == 1) {
		if (isset($server_cfg[$conf_vars[0]])) {
			$value = $server_cfg[$conf_vars[0]];
			$value = getValue($value,false,$space_seperated);
			echo $value;
		}
	} else {

		$need_comma = False;

		echo "{";

		foreach($conf_vars as $var) {

			$value = $server_cfg[$var];
			$value = getValue($value,true,$space_seperated);

			if ($need_comma) {
				echo ",";
			} else {
				$need_comma = True;
			}

			echo "\"$var\":$value";
		}

		echo "}";
	}
}

main($server_cfg, $argv);
zpm_postamble("");
