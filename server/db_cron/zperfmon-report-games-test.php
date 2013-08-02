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
 @ Author : nalam@zynga.com
*/
//html parser
function get_game_name_in_report($html){
 // Find the table
        preg_match_all("/<table.*?>.*?<\/[\s]*table>/s", $html, $table_html);
        //find tr
        foreach($table_html as $values){
                foreach($values as $value){
                        preg_match_all("/<tr.*?>(.*?)<\/[\s]*tr>/i",$value,$tr_match);
                        array_pop($tr_match);
                        $tr_matches[] = $tr_match;
                }
        }
        //remove the headers
	for($i = 0; $i<count($tr_matches); $i++){
	        array_shift($tr_matches[$i][0]);
	}
        //find td
        foreach($tr_matches as $values){
                for($i=0;$i<count($values);$i++){
                        for($j=0;$j<count($values[$i]);$j++){
                                preg_match_all("/<td.*?>(.*?)<\/[\s]*td>/", $values[$i][$j], $td_match);
                                array_pop($td_match);
                                $td_matches[] = $td_match;
                        }
                }
        }
        //parse the values
        $return_table_values = array();
        for($i=0;$i<count($td_matches);$i++)
        for($j=0;$j<count($td_matches[$i]);$j++)
                for($k = 0;$k<count($td_matches[$i][$j]);$k++){
                        $a = $td_matches[$i][$j][$k];
                        if(preg_match('/<td.*(left|right)>.*.html.*\'>/',$a)){
                                $a =  preg_replace('/<td.*(left|right)>.*.html.*\'>/','',$a);
                                $a = preg_replace('/<.*/','',$a);

                        }
                        else if(preg_match('/<td.*(left|right)>.*background-color:/',$a)){
                                $a = preg_replace('/<td.*(left|right)>.*background-color:/','',$a);
                                $a = preg_replace('/;.*/','',$a);
                        }
                        else if(preg_match('/<td.*color=(\w+).*<b>/',$a)){
                                $a = preg_replace('/<td.*color=(\w+).*<b>/','',$a);
                                $a = preg_replace('/<.*/','',$a);
                        }
                        else if(preg_match('/<td.*(left|right)>/',$a)){
                                $a = preg_replace('/<td.*(left|right)>/','',$a);
                                $a = preg_replace('/<.*/','',$a);

                        }
                        $td_matches[$i][$j][$k] = trim($a);
                        $return_table_values[$i] = $td_matches[$i][$j];
                }
	foreach($return_table_values as $values)
		$games_in_report[] = $values[0];
        return $games_in_report;


}
function get_duplicated_games($array){
	$duplicates = array();
        $unique = array_unique($array);
        if(count($array) > count($unique))
                for($i = 0; $i < count($array); $i++)
                        if(!array_key_exists($i, $unique))
                                $duplicates[] = $array[$i];
        $duplicate_games = array_unique($duplicates);
        return $duplicate_games;

}
function main($server_cfg){
	$summary_report_url = $server_cfg["instance_summary_report_url"];
	$html = file_get_contents($summary_report_url);
	$games_in_report = get_game_name_in_report($html);	
	$report_games_in_server_cfg = $server_cfg["instance_report_games"];
	$games_duplicated_in_report = get_duplicated_games($games_in_report);
	$games_duplicated_in_server_cfg = get_duplicated_games($report_games_in_server_cfg);
	$games_missing_in_report = array_unique(array_diff($report_games_in_server_cfg,$games_in_report));
	$games_missing_in_server_cfg = array_unique(array_diff($games_in_report,$report_games_in_server_cfg));
	$body = "";
	$status = 0;
	if($games_duplicated_in_server_cfg && $games_duplicated_in_report){
		$status = 1;
		$subject = "[WARNING] Games duplicated in report and server_cfg";
		$body .= "\n"."[WARNING] Games duplicated in report:".implode(",",$games_duplicated_in_report)."\n"."[WARNING] games duplicated in server_cfg:".implode(",",$games_duplicated_in_server_cfg);
	}else if($games_duplicated_in_server_cfg){
		$status = 1;
		$subject = "[WARNING] Games duplicated in server_cfg";
		$body = "[WARNING] Games duplicated in server_cfg :".implode(", ",$games_duplicated_in_server_cfg);
	}else if($games_duplicated_in_report){
		$status = 1;
		$subject = "[WARNING] games duplicated in report";
		$body = "[WARNING] Games duplicated in report :".implode(", ",$games_duplicated_in_report);
	}
	if($games_missing_in_report){
		$status = 2;
		$subject = "[ERROR] Games Missing in Report";
		$body .= "\n"."[ERROR] Games missing in Report :".implode(",",$games_missing_in_report);
	}
	else if($games_missing_in_server_cfg){
		$status = 2;
		$subject = "[ERROR] Games missing in server_cfg";
		$body .= "\n"."[ERROR] Games missing in server_cfg:".implode(",",$games_missing_in_server_cfg);	
	}
	if($status > 0){
		$to = "xxx@xxxx.xxx";
		mail($to,$subject,$body);

	}
	else return;

		
}
include_once("/etc/zperfmon/server.cfg");
main($server_cfg);

?>
