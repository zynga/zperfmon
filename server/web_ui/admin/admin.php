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


require_once 'setup_page.php';
include_once 'XhProfModel.php';
include_once 'spyc.php';
include_once 'yml_conf.inc.php';
include_once 'game_config.php';
include_once 'server.cfg';

$xhProfModelObject = new XhProfModel($server_cfg, $game_cfg);	

$result = $xhProfModelObject->generic_execute_get_query_detail('get_last_event', null);

$type = (string)$result['rows'][0][0];
$text = (string)$result['rows'][0][1];

# $event = "$type:$text";
$event = "$text"; 
$event = str_replace('"','',$event);

if(!isset($arrayid)){
	$arrayid = 'all';
}

?>

<html>
<head>
<title> Administrator </title>
<link rel="stylesheet" href="/zperfmon/css/jquery.autocomplete.css" />
<script src="/zperfmon/js/jquery-1.5.2.min.js"></script>
<script src="/zperfmon/js/jquery.lightbox_me.js"></script>
</head>
<body>

    <form id="tag_form">
	<input type="hidden" name="cmd" value="TAG"/>
	<input type="hidden" name="game" value=<?php echo "\"$game\"";?>/>
	<input type="hidden" name="array" value=<?php echo "\"$arrayid\"";?>/>
	<div>
		<table border=0>
			<tr>
				<td>Add Tag</td>
				<td><input id="tag" type="text" name="content" value="<?php echo $event;?>"/></td>
		
				<td><input type="submit" name="submit" value="Submit" id="tag_submit"/></td>
			</tr>
		</table>
	</div>
    </form>
<?php
	include_once "slack_api.php";

	function get_instance_count_slack_offset($game_name){
		$slack_api = json_decode(calculate_slack($game_name,false),true);
		return $slack_api;
	}		
	$metric_array_map = array("common_web_eu"=>"web","common_mc_eu"=>"mc","common_mb_eu"=>"mb","common_db_eu"=>"db","common_proxy_eu"=>"proxy",
					"common_msched_eu"=>"msched","common_mqueue_eu"=>"mqueue","common_nagios_eu"=>"nagios","common_consumer_eu"=>"consumer","common_gib_web"=>"gib"	
				);
	$hostgroupConfigObj = new HostgroupConfig($server_cfg, $game);
	$yaml_array = $hostgroupConfigObj->load_hostgroup_config();		
	$final_yaml_array = array();
	$common_config_array = array();
	foreach($yaml_array as $key=>$yaml_str){
		if(!isset($yaml_str["class"]) and $metric_array_map[$key]!= NULL){
			$common_config_array[$metric_array_map[$key]] = $yaml_str;
			continue;
		}
		$final_yaml_array[$key]=$yaml_str;
		if($final_yaml_array[$key]['hostgroup'] == '')
			$final_yaml_array[$key]['hostgroup'] = str_replace(".*",'',$key);
	}
	
	$json_final_array = json_encode($final_yaml_array);			
	$json_common_config_array = json_encode($common_config_array);
	$yaml_str = Spyc::YAMLDump($final_yaml_array);
	/*
	//		$val = $hostgroupConfigObj->get_config_column_names();
	foreach($common_config_array as $key=>$yaml_str){
		var_dump($key);
		echo "<br>";
		foreach($yaml_str as $key => $val)
			var_dump($yaml_str);	
		echo "<br><br>";
	}

	*/
	//		var_dump($final_yaml_array);
	
	echo "<div id='welcome'> Welcome <b>".$_SERVER['REMOTE_USER']."</b></div>";

	$class_list = array("","web","mc","mb","db","proxy","msched","mqueue","nagios","consumer","gib");

	$i = 1;
	
//var_dump($final_yaml_array);	
	echo "<div id='hosts'>
              <textarea id='host_count' readonly></textarea>
              </div>";
	
	$summary_data = get_instance_count_slack_offset($game);
//	foreach($hostgroups_array as $hostgroups){
	foreach($final_yaml_array as $regex=>$metric){
		$hostgroups = $metric["hostgroup"];
		$flag = FALSE;
		echo "<div id='hostgroups$i' class='hosts'>
				<label onclick=processclick($i,'$hostgroups') class='heading'>$hostgroups</label>
				<img id='delete_$id' src='/zperfmon/admin/images/delete.jpg' alt='Delete Hostgroup' onclick=processDelete('hostgroups$i','$hostgroups')>";
				if(isset($summary_data[$hostgroups]))
					echo "<div id='summary_$i' class='summary'>Instance Count : ".$summary_data[$hostgroups]["count"]."<br>Slack : ".$summary_data[$hostgroups]["slack"]."</div>";				
		        echo "<div id='class_name$i' class='hidden'>
				<label>Class: </label>
				
			<select name='class' id='class_list$i' onchange=processChange($i,'$hostgroups')>";
		foreach($class_list as $class)
		{
			if($class == $metric["class"]){
				echo "<option name=$class value=$class selected>$class</option>";
				$flag = TRUE;
			}
			else{ 
				echo "<option name=$class value=$class>$class</option>";
			}
		}
		if(!$flag){
			foreach($class_list as $class)
				echo "<option name=$class value=$class>$class</option>";
			echo "<option name=$class value='' selected></option>";
		}
				
		echo "</select>
			<div id='metric_$i'>";
		foreach($common_config_array as $class=>$metrics){
			echo "<div id='metric_$i"._."$class' class='hidden'>";
			$j  = 1;
			foreach($metrics as $metric=>$simpler){
				echo "<div id='metric_$i"._."$class"._."$j'>
					<label onclick=processMetricClick($i,'$class','$metric')>$metric : </label>
					
					<div id='simpler_metric_$i"."_"."$class"."_"."$metric' class='hidden'>";
					$k=1;
					foreach($simpler as $key=>$value){
						echo "<div id=simpler_metric_$i"."_".$key."_".$j."_".$k.">
							<label>$key : </label>";
						if(isset($final_yaml_array[$regex][$metric][$key]))
							$value = $final_yaml_array[$regex][$metric][$key];
						$id = "simpler_metric_val_$i"."_".$class."_".$key."_".$j."_".$k;
						if(is_bool($value))$value=$value ? "true":"false";
						if($key == "complement"){
							echo "<select id=$id name='bool' onchange=inputChanged('$id','$hostgroups','$metric','$key')>";
								if($value == "true")
									echo "<option value=true selected>True</option><option value=false>False</option>";
								else
									echo "<option value=true>True</option><option value=false selected>False</option>";
							echo "</select></div>";
						}else{
							if (is_numeric($value))
								echo "<input type='text' value='$value' size='30' onchange=inputChanged('$id','$hostgroups','$metric','$key') id=$id></div>";
							else 
								echo "<input type='text' value='$value' size='30' onchange=inputChanged('$id','$hostgroups','$metric','$key') id=$id disabled></div>";
						}
						$k++;
					}			
					echo "</div></div>";
				$j++;											
		
			}
			echo "</div>";
		}
		$id = "hostgroup_regex_$i";
		echo "</div></div>
			<div id=regex_$i class='hidden'>
			<label>Hostgroup Regex: </label>
			<input id=$id type='text' value=$regex size='30' onfocusout=regexInputChanged('$id','$hostgroups') class='regex'></div>
			<div id=hostgroup_disabled_$i class='hidden'>
			<label>Hostgroup: </label>
			<input type='text' value=$hostgroups size='30' disabled></div></div>";
		$i++;
	}
?>
<div id="create" class="hidden">
      <h3 id="see_id" class="sprited">Hostgroup creation form </h3>
      <span>Please fill the form and click create to add a new hostgroup:</span>
      <div id="sign_up_form">
          <label><strong>Hostgroup:</strong> <input id='new_hostgroup' value=''></label><br>
          <label><strong>Class:</strong>
		<select name='class' id='new_class'>
	<?php 	foreach($class_list as $class)
                     echo "<option name=$class value=$class>$class</option>";
                
	?>
	</label></select><br><br>
          <div id="actions">
              <a class="form_button close" id="create_click" href="#tab-admin"><font size="3">Create</font></a>
          </div>
      </div>
</div>

<form id="submit_form1" action="/zperfmon/admin/dump_file.php" method="post">
	<div><input id='json_final' name='json_final' value=<?php echo $json_final_array;?> type='hidden'>
               <input id='json_common' name='json_common' value=<?php echo $json_common_config_array;?> type='hidden'>
		<input id='game_name' name='game' value=<?php echo $game;?> type='hidden'>
	<button id="submit" name="submit" type="submit">Submit</button>
	<button id="create_hostgroup" type="button">Create Hostgroup</button>
	<button id="dry_run" type="button">Compute Slack with current Config</button>
</form>    

</body>
<script type="text/javascript">
	$('#dry_run').click( function(e){
		$.ajax({'type':"POST", 'url':"/zperfmon/admin/dump_file.php", 'data':$("#tag_form").serialize()+ '&' + $("#submit_form1").serialize() + '&dry_run=true',
                     success: function(d) {
			console.log(d);		
		     }
		});
		e.preventDefault();
	});

	$(function() {
		$('.regex').each(function() {
	           var elem = $(this);
        	   elem.bind("propertychange keyup input paste", function(event){
			checkHostCount(elem.val(),"");
		   });
		 });	
		
		$('#create_hostgroup').click(function(e) {
			$('#create').lightbox_me({
				centered: true, 
				onLoad: function() { 
					$('#create').find('input:first').focus()
				}
        		});
			e.preventDefault();
		});
	});
	var arr;
	function checkHostCount(changed_val,host){
        	$.ajax({'type':"GET", 'url':"/zperfmon/admin/fetch_hosts.php", 'data':$("#tag_form").serialize()+ '&regex=' +changed_val,
                     success: function(d) {
			arr = $.parseJSON(d);
			var str="Hosts Information : \n\n";
			if(host!= "")
				str = str + "Hostgroup : " + host + "\n";
			str = str + "Hostgroup Regex : " + changed_val + "\n";
			str = str + 'Count: ' + arr["count"];
			if(arr["count"] != 0){
				str = str + '\n\n' + 'Hostnames :\n';
				for( host in arr["hosts"])
					str = str + arr["hosts"][host] + '\n';	
			}
			$('#host_count').val(str);
                     }

                });
	}
	$('#create_click').click( function(e){
		var new_hostgroup = $('#new_hostgroup').val();
		var new_class = $('#new_class').val();
		addNewHostgroup(new_hostgroup,new_class);
	});
	function addNewHostgroup(new_hostgroup,new_class){
                var json_final = JSON.parse($('#json_final').val());
                var json_common = JSON.parse($('#json_common').val());
		var regex = new_hostgroup + '.*';
		if(new_class != ""){
                        if(!json_final.hasOwnProperty(regex)){
                                json_final[regex] = "";
                        }console.log("DD1" + json_common + ' ====' + new_class);
                        json_common[new_class]["class"] = new_class;
			console.log("DD2");
                        if(json_final[regex] != "")
                                json_common[new_class]['hostgroup'] = json_final[regex]['hostgroup'];
                        else
                                json_common[new_class]['hostgroup'] = new_hostgroup;
                        json_final[regex] = json_common[new_class];
                }else{
                        json_final[regex] = "";
                        json_final[regex]["class"] = "";
                        json_final[regex]["hostgroup"] = new_hostgroup;
                }

		$('#json_final').val(JSON.stringify(json_final));
                $('#submit').click();
			
	}

	$("#tag_submit").click( function(e){
			console.log(e.keyCode);
			$.ajax({type:"POST", url:"/zperfmon/uploader.php", data:$("#tag_form").serialize(), 
				success: function() {
					alert("Successfully submitted");
				}
			});
			e.preventDefault();
		}
	);


        function inputChanged( id,host,metric,key ){
		var regex = getRegexParent(host);
                var json_final = JSON.parse($('#json_final').val());
                console.log("changed" + id + '--' + host + '--' + metric + '--' + key + '--' + json_final[regex][metric][key]);
                var changed_val = $('#' + id).val();
                json_final[regex][metric][key]=changed_val;
                $('#json_final').val(JSON.stringify(json_final));
                console.log("changed" + id + '--' + host + '--' + metric + '--' + key + '--' + json_final[regex][metric][key]);

        }

	function processclick(index,host){
		$('#class_name' + index).toggle(300);
		$('#regex_' + index).toggle(300);
		$('#summary_' + index).toggle();
		$('#hostgroup_disabled_' + index).toggle();
		processClickVisibility(index,host);
		var regex_val = $('#hostgroup_regex_' + index).val();
		if($('#hostgroup_disabled_' + index).is(':visible'))
			checkHostCount(regex_val,host);
	}

	function confirmDelete(){
                var agree=confirm("Are you sure you want to delete it?");
		
                if (agree)
                     return true;
                else
                     return false;
        }
	function regexInputChanged(id,host){
		var changed_val = $('#' + id).val();	
		if(changed_val == ""){
			alert("Please enter a valid hostgroup regular expression ending with .*");
			$('#' + id).focus();
			return;
		}
		var json_final = JSON.parse($('#json_final').val());
		var regex = getRegexParent(host);
		if(json_final.hasOwnProperty(regex)){
			json_final[changed_val] = json_final[regex];
			delete json_final[regex];
			$('#json_final').val(JSON.stringify(json_final));
		}
		
		checkHostCount(changed_val,host);

	}
	function processDelete(id,host){
		if(confirmDelete()){
			deleteRow(id,host);				
		}
        }

	function deleteRow(id,host){
		var regex = getRegexParent(host);
		var json_final = JSON.parse($('#json_final').val());
		if(json_final.hasOwnProperty(regex)){
			$('#' + id).hide();
			
			delete json_final[regex];
			$('#json_final').val(JSON.stringify(json_final));
		}else{
			alert("No hostgroup " + host + " in the yaml file.Hostgroup already deleted!!!");
		}
		
	}
	
	function getRegexParent(host){
		var json_final = JSON.parse($('#json_final').val());
		for (var regex in json_final){
                        if(json_final[regex]['hostgroup'] == host)
                                return regex;
                }
		return 0;
	}

	function processChange(index,host){
		var clas = $('#class_list' + index).val();
		var json_final = JSON.parse($('#json_final').val());
		var json_common = JSON.parse($('#json_common').val());
		var regex = getRegexParent(host);
		if(regex == 0)
			regex = host + '.*';
		if(clas != ""){	
			if(!json_final.hasOwnProperty(regex)){
				json_final[regex] = "";	
			}
			json_common[clas]['class'] = clas;
			if(json_final[regex] != "")
				json_common[clas]['hostgroup'] = json_final[regex]['hostgroup'];		
			else
				json_common[clas]['hostgroup'] = host;	
			json_final[regex] = json_common[clas];
		}else{
			json_final[regex] = "";
			json_final[regex]["class"] = "";
			json_final[regex]["hostgroup"] = host;
		}
		$('#json_final').val(JSON.stringify(json_final));	
		processClickVisibility(index,host);	
	}

	function processClickVisibility(index,host){
		var clas = $('#class_list' + index).val();
		$("*[id^=metric_" + index +"]").hide();
                $('#metric_' + index).show();
                if(clas != "")
                        $("*[id^=metric_" + index + '_' + clas +"]").show();
	}

	function processMetricClick(index1,clas,metric){
		$('#simpler_metric_' + index1 + '_' + clas + '_' + metric).toggle(300);
	}

</script>


<style type="text/css">
.hidden{
	display: none;

}

.heading {
	font-weight: bold;
	font-size: 16;
}

.summary{
	padding-left: 0px;	
}
.hosts {
	padding-bottom: 10px;
}

.sprited {
//	background: url(http://buckwilson.me/lightboxme/download_sprite.png) no-repeat;
//	line-height: 550px;
	overflow: hidden;
//	display: block;
}

#create{
background: #eef2f7;
-webkit-border-radius: 6px;
border: 1px solid #536376;
-webkit-box-shadow: rgba(0,0,0,.6) 0px 2px 12px;
padding: 14px 22px;
width: 400px;
font: 13px/20px "Helvetica Neue", Helvetica, Arial, sans-serif;
font-family: Courier New,sans-serif !important;
}

div[id^='class_name'] {
	color: red;  
	padding-left :1em;  
}
div[id^='regex_']{
	color: red;
        padding-left :1em;
}
div[id^='metric_'] {
        color: blue;
        padding-left :2em;
}
div[id^='hostgroup_disabled_']{
	color: red;
        padding-left :1em	color: red;
        padding-left :1em;;
}
div[id^='simpler_metric_'] {
        color:green;
        padding-left :3em;
}
textarea {
	resize : none;
	float : right;
	width: 450px;
	height: 560px;
/*	border: 3px solid #cccccc;*/
	border: none;
	padding: 5px;
	font-family: sans-serif !important;
}
div[id='welcome']{
	color:green;
	text-align :right;
}

</style>

</html>

