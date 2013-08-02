<!DOCTYPE HTML>
<!--                                                                                    -->
<!--  Copyright 2013 Zynga Inc.								-->
<!--  											-->
<!--  Licensed under the Apache License, Version 2.0 (the "License");			-->
<!--     you may not use this file except in compliance with the License.		-->
<!--     You may obtain a copy of the License at					-->
<!--  											-->
<!--     http://www.apache.org/licenses/LICENSE-2.0					-->
<!--  											-->
<!--     Unless required by applicable law or agreed to in writing, software		-->
<!--       distributed under the License is distributed on an "AS IS" BASIS,		-->
<!--       WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.	-->
<!--     See the License for the specific language governing permissions and		-->
<!--     limitations under the License.							-->
<!--                                                                                    -->   
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>zPerfmon: EU Dashboard</title>
</head>
<style>
#prev {
	width:15px;
	font-size:15px;
	color:white;
	cursor: pointer;
	top:7px;
}

#next {
	width:15px;
	cursor: pointer;
        font-size:15px;
	color:white;
	top:7px;
}
</style>
<body>
	<div class="container" id="tab-eu">
		<div class="menu-wrapper">
			<ul class="menu" style = "overflow:hidden;">
				<li class="button"><a class="menu-item-0 current" href=#>Web</a></li>
				<li class="button"><a class="menu-item-1" href=#>MySQL</a></li>
			</ul>
		</div>

		<div class="left-column">
			<div class="select-chart-items">
				<div class="select-items-0 selected">
					<!--<div class="title">Web EU</div>-->
					<div class="list">
						<div>
							<input type="checkbox" id="web_cpu" checked />
							<label for "web_cpu">CPU</label>
						</div>
						<div>
							<input type="checkbox" id="web_mem" checked/>
							<label for "web_mem">Memory</label>
						</div>
						<div>
							<input type="checkbox" id="web_rps" />
							<label for "web_rps">RPS</label>
						</div>
					</div>
				</div>
				
				<div class="select-items-1 hide">
					<!--<div class="title">MySQL EU</div>-->
					<div class="list">
						<div>
							<input type="checkbox" id="db_a_cpu" checked="true"/>
							<label for="db_a_cpu">CPU</label>
						</div>
						<div>
							<input type="checkbox" id="db_a_md0_disk_ops_read"  checked="true"/>
							<label for="db_a_md0_disk_ops_read">MD0 Disk Ops Read</label>
						</div>
						<div>
							<input type="checkbox" id="db_a_md0_disk_ops_write"  checked="true"/>
							<label for="db_a_md0_disk_ops_write">MD0 Disk Ops Write</label>
						</div>
					</div>
				</div>

			</div>

			<div>
				<button class="split-chart-btn">Split Charts</button>
				<div class="split-chart-controller hide">
					<div><input type=radio name=tab-eu-split-view-mode value=overall checked />Overall</div>
					<div><input type=radio name=tab-eu-split-view-mode value=dodwow />DoD & WoW</div>
				</div>
			</div>

			<div>
				<button class="show-table-btn">Show Table</button>
			</div>
		</div>
	
		<div class="right-column">
			<div class="chart-wrapper left">
				<div class="combined-charts chart-block"></div>
				<div class="split-charts hide"></div>
			</div>
			<div class="tags-wrapper left">
            </div>
			<div class="clear"></div>
		</div>

		<div class="clear"></div>
	</div>

	<div id="tab-eu-dialog" title="Profile Info"></div>

	<div id="tab-eu-table" class="yui-skin-sam"></div>
<?php 
	include_once 'yml_conf.inc.php';
	include_once 'server.cfg';
	$game = $_GET['game'];
	$obj = new  HostgroupConfig($server_cfg,$game);
	$row = array();
	$mb_row = $obj->get_master_hostgroups(array('mb'));
	$row = array_merge($row,$mb_row);
	$mc_row = $obj->get_master_hostgroups(array('mc'));
	$row = array_merge($row,$mc_row);
?>
<script>
(function() {
	var game = $('#GameList option:selected').val();
	var arrayid = $('#ArrayList option:selected').val();
	var gameParam = "game=" + game;
	var row = <?php echo json_encode($row);?>;
	var start = 2;
	var query_param = [];
	if(arrayid !== "all") { //if arrayid is given, append it to the game parameter to get array specific data
                gameParam += "&array=" + arrayid;
        }

        var apiUrlArr = [
                'eu-view/eu-query.php?query=eu_web_chart_range&' + gameParam,
                'eu-view/eu-query.php?query=eu_db_chart_range&' + gameParam,
        ];
	for( value in row) {
		if (row[value].substring(row[value].length-2, row[value].length) == '-b') {
			continue;
		}
		$('#tab-eu .menu').append('<li class="button"><a class="menu-item-'+start+'" href=#>'+row[value]+'</a></li>');
		
		var row_id = row[value].replace(/-/gi,'_');
		var id1 = row_id+'_sets';
		var id2 = row_id+'_gets';
		var id3 = row_id+'_nw_pkts_rx';
		var id4 = row_id+'_nw_pkts_tx';

		$('#tab-eu .select-chart-items').append('<div class="select-items-'+start+' hide"> \
					<div class="list">\
						<div>\
							<input type="checkbox" id="'+id1+'"  checked="true"/>\
							<label for="'+id1+'">Ops Sets</label>\
						</div>\
						<div>\
							<input type="checkbox" id="'+id2+'"  checked="true"/>\
							<label for="'+id2+'">Ops Gets</label>\
						</div>\
						<div>\
							<input type="checkbox" id="'+id3+'"  checked="true"/>\
							<label for="'+id3+'">N/W PKTS Received</label>\
						</div>\
						<div>\
							<input type="checkbox" id="'+id4+'"  checked="true"/>\
							<label for="'+id4+'">N/W PKTS Transfered</label>\
						</div>\
					</div>\
				</div>');
		apiUrlArr[start]='eu-view/eu-query.php?query=eu_host_chart_range&hostgroup='+row[value]+'&' + gameParam;
		start += 1;
		
	}
	var menuChildWidth = 0;
	$('#tab-eu .menu li').each(function() {
		menuChildWidth += $(this).width();
	});
	var noPage = menuChildWidth/$('#tab-eu .menu').width();
	if ( noPage > 1){
		$('#tab-eu .menu').append('<li id="next" class="button" style = "position: absolute;right: 0px;">><li>');
		$('#next').click(function() {
			var start = 0 ;
			$('#tab-eu .menu li').each(function() {
				start += $(this).width();
				if( start < $('#tab-eu .menu').width() ){
					$(this).addClass('hide');
					$(this).css('display','none');
				}
				$('#prev').css('display','block');
				$('#next').css('display','none');
			});
		});
		$('#tab-eu .menu').append('<li id="prev" class="button" style = "position: absolute;left: 0px;display:none;"><<li>');
		$('#prev').click(function() {
			$('#tab-eu .menu li').each(function() {
				$(this).css('display','block');
			});
			$('#next').css('display','block');
			$('#prev').css('display','none');
		});
	}
	var oControl = new zPerfmon.Main(game, arrayid, 'tab-eu', apiUrlArr);
})();
</script>

</body>
</html>
