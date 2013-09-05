#! /usr/bin/php
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
Script to get random IPs will the following limits for games
to use with zperfmon

Usage: [-g game]
*/

## GLOBALS ##
$MIN = 5;
$MAX = 15;
$PRCNT = 10;
$LIMIT = 55;
#############

include 'server.cfg';

$options = getopt ("g:");

function giveIPs(&$srvs) {
	global $MIN;
	global $MAX;
	global $PRCNT;
	global $LIMIT;

	$resultIPs="";
	$totalCount = 0;

	foreach ( (array_keys($srvs)) as $id) {
		$count = count($srvs[$id]);
		$prcnt = $count * $PRCNT / 100;
		if ($prcnt > $MAX) {
			$prcnt = $MAX;
		} elseif ($prcnt < $MIN) {
			$prcnt = $MIN;
		}
		$totalCount += $prcnt;
		$ipArray = $srvs[$id];
		shuffle ($ipArray);
		$srvs[$id] = array_slice($ipArray,0,$prcnt) ;
	}
	if ($totalCount > $LIMIT) stripIPs ($srvs,$totalCount,$LIMIT);
}


function stripIPs(&$srvs,$total,$hLimit) {
	/* purpose: limit the total count of IPs within $LIMIT
	Logic: Get the average and pick candidates which are having count > avg
	Get the contribution to the difference weighted by the number they already have
	Remove that many items from each of them to make the total count within $LIMIT
	*/

	$count = 0;
	$avg = $total / count($srvs);
	
	$countAboveAvg = 0;
	foreach ($srvs as $id => $ips) {
		if (count($ips) >= $avg) $countAboveAvg += count($ips);
	}
	
	$diff = $total - $hLimit;
	foreach ($srvs as $id => $ips) {
		if (count($ips) >= $avg) {
			$numToRemove = round(count($ips)/$countAboveAvg * $diff);
			$srvs[$id] = array_slice($ips,0,-$numToRemove);
		}
	}
}
	
// main()
	
if (isset($options['g']) && $options['g'] !== '') {
	$game_names = explode(",",$options['g']);
} else {
	$game_names = $server_cfg['game_list'];
}

foreach ($game_names as $game) {
	unset ($servers, $json, $totalCount);
	$iplistFile = "/db/zperfmon/$game/iplist.json";

	if (! file_exists($iplistFile) ) {
	echo "ERROR: Skipped $game - iplist file not found: " . $iplistFile . "\n";
		continue;
	}

	$file = "/db/zperfmon/$game/iplist.json"; 
	$json = file_get_contents($file);

	if ($json === false || empty($json)) {
	echo "ERROR: Skipped $game - Cannot read iplist file\n";
		continue;
	}

	$t = json_decode($json,TRUE);

	if ($t === NULL || $t === FALSE || empty($t)) {
	echo "ERROR: Skipped $game - Cannot decode json file: $file\n";
		continue;
	}

	foreach ($t as $ip => $val) {
		$id = $val['arrayId'];
		$servers[$id][] = $ip;
	}

	giveIPs($servers);

	$totalCount = 0;
	$ipArray = array();
	foreach ($servers as $id => $ips) {
		$totalCount += count($ips);
	//	echo "Id: $id. Servers: ". implode(",",$ips)."\n";
		$ipArray = array_merge($ipArray,$ips);
	}

	foreach ($ipArray as $ip) {
		echo "$ip\n";
	}
	echo "\nGame: $game, total count - $totalCount\n";
//	echo "Game: $game\n";
//	print_r($ipArray);
//	exec("php zrt_update.php -g $game -n dev 

}
