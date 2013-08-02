//
// Copyright 2013 Zynga Inc.
// 
// Licensed under the Apache License, Version 2.0 (the "License");
//    you may not use this file except in compliance with the License.
//    You may obtain a copy of the License at
// 
//    http://www.apache.org/licenses/LICENSE-2.0
// 
//    Unless required by applicable law or agreed to in writing, software
//      distributed under the License is distributed on an "AS IS" BASIS,
//      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//    See the License for the specific language governing permissions and
//    limitations under the License.
// 

$(document).ready(function() {
	$( "#compare_button").button();
	$( "#hide_button").button();
	$( "#show_button").button();
	$("#previousday").button();
	$("#previousweek").button();
	$("#compare").button();
	changeArray();
	$('#GameTab').css('height','1500px');

$("#ProfileDateWidget").datepicker({
     altField: "#UTC_seconds",
     altFormat: "@",
     onSelect: function(dateText, inst) {
		fetchHourSlots();
	},
})
//.datepicker('setDate', new Date());

// Check if timestamp has been passed in URL, set default to current date if no timestamp is passed
if(  timestamp == '')
{
	$("#ProfileDateWidget").datepicker('setDate', new Date());
}
else
{
	var date = convertToDate(timestamp);
	$("#ProfileDateWidget").datepicker('setDate', date);
}
// if dtae is changed then fetch the new profile
$('#hour-time-slots').change(
	function() {
		fetchXhProf();
	}
);

fetchHourSlots();
fetchRelease();

 // Dialog box for the compare button
$dialog = $('#compareOverlay')
	.dialog({
		autoOpen: false,
		title: 'Compare With',
		width: 500,
		height: 400
	});
// open the compare dialog box 
$('#opener').click(function() {
	$dialog.dialog('open');
});

$('#compare_button').click(function() {
	$('#datepicker').datepicker( "hide" );
	$('#hour-time-slots-compare').focus();
});

$('#showSidePanel').click(function() {
	var children = document.getElementById('ui-tabs-4').childNodes;
	document.getElementById('showSidePanel').style.display ="none";
	document.getElementById('xhprof_view').width = $(window).width();
	document.getElementById('opener').style.display ="block";
	for (var i = 0; i < children.length; i++)
	{
		if(children[i].id=="SidePane")
		{
			children[i].style.display = "block";
		}
		if(children[i].id=="MainPaneTable")
		{
			children[i].style.position = "relative";
			children[i].style.left = "0px";
		}
	}
});

$('#hideSidePanel').click(function() {
	var children = document.getElementById('ui-tabs-4').childNodes;
	document.getElementById('showSidePanel').style.display ="block";
	document.getElementById('xhprof_view').width = $(window).width();
	document.getElementById('opener').style.display ="none";
	for (var i = 0; i < children.length; i++)
	{
		if(children[i].id=="SidePane")
		{
			children[i].style.display = "none";
		}
		if(children[i].id=="MainPaneTable")
		{
			children[i].style.position = "absolute";
			children[i].style.left ="50px";
		}
	}
});

// Dtae picker for compare overlay
$( "#datepicker" ).datepicker({
	altField: "#UTC_seconds_compare",
	altFormat: "@",

	onSelect: function(dateText, inst) {
		fetchHourSlotsCompare();
	}
});
// change the game array list when game name changes in compare overlay
$('#GameListPopup').change(function(){
	changeArray();
});

// compare two profiles when compare button is clicked
$('#compare').click(function() {
	$dialog.dialog('close');
	var timeStamp1 = $('#hour-time-slots :selected').val();
	var timeStamp2 = $('#hour-time-slots-compare :selected').val();
	var gameName1;
	var gameName2;
	if ( array_id != 'all')
		gameName1 = game_name+'_'+array_id;
	else
		gameName1 = game_name;
	if( $('#ArrayListPopup option:selected').val() != 'all')
		gameName2 = $('#GameListPopup option:selected').val()+'_'+$('#ArrayListPopup option:selected').val();
	else
		gameName2 = $('#GameListPopup option:selected').val();
	$("#hour-time-slots-compare").focus();
	if ( ( timeStamp1 == undefined || timeStamp2 == undefined  ) && ( $('#ReleaseId :selected').val() == 'Previous Release' ) )
	{
		alert("invalid timestamp selected");
		return;
	}
	if ( timeStamp1 == undefined || timeStamp2 == undefined  ) 
	{
		compareRelease();	
	}
	var selected_page = $("input:radio[name=profile_page]:checked").val();
	var page = selected_page.substring(0,selected_page.indexOf(" "));
	var path1 = "%2Fvar%2Fwww%2Fhtml%2Fzperfmon%2Fblobs%2F"+gameName1+"%2F_blobdir_"+timeStamp1+"%2F"+timeStamp1+"."+page+".xhprof";
	var path2 = "%2Fvar%2Fwww%2Fhtml%2Fzperfmon%2Fblobs%2F"+gameName2+"%2F_blobdir_"+timeStamp2+"%2F"+timeStamp2+"."+page+".xhprof";
	var loc = document.location.toString();
	while ( loc[loc.length-1] != '/' )
	{
		loc = loc.slice(0,loc.length-1); 
	}
	var url = loc+"xhprof_html/index.php?file1="+path1+"&file2="+path2;
	var new_win_name = gameName1+timeStamp1+gameName2+timeStamp2;
    new_win  = window.open("",new_win_name);
	//new_win  = window.open("","new_win");
	$.getJSON("profile-view/ExtractProfileFilename.php?array="+ $('#ArrayListPopup option:selected').val(),
	  { game: $('#GameListPopup option:selected').val(), timestamp: timeStamp2 },
	  function(json){
		new_win.location.href =url;
		}
	);
});
});


function fetchRelease(){
        $.getJSON("profile-view/getReleaseList.php?array=" + array_id,
                { game: game_name},
                function (d){
                        $('#ReleaseId').empty();
                        $('#ReleaseId').append("<option value='Previous Release'>Select A Release</option>");
                        $.each(d, function(i,v){
                                $('#ReleaseId').append("<option value='"+v.timestamp+"'>"+v.text+"</option>");
                        });
        });
}


function compareWindow(timeStamp2){
        var timeStamp1 = $('#hour-time-slots :selected').val();
        var gameName1;
        if ( array_id != 'all')
                gameName1 = game_name+'_'+array_id;
        else
                gameName1 = game_name;

        var selected_page = $("input:radio[name=profile_page]:checked").val();
        var page = selected_page.substring(0,selected_page.indexOf(" "));
        var path1 = "%2Fvar%2Fwww%2Fhtml%2Fzperfmon%2Fblobs%2F"+gameName1+"%2F_blobdir_"+timeStamp1+"%2F"+timeStamp1+"."+page+".xhprof";
        var path2 = "%2Fvar%2Fwww%2Fhtml%2Fzperfmon%2Fblobs%2F"+gameName1+"%2F_blobdir_"+timeStamp2+"%2F"+timeStamp2+"."+page+".xhprof";
        var loc = document.location.toString();
        while ( loc[loc.length-1] != '/' )
        {
                loc = loc.slice(0,loc.length-1);
        }
        var url = loc+"xhprof_html/index.php?file1="+path1+"&file2="+path2;
        var new_win_name = gameName1+timeStamp1+gameName1+timeStamp2;
        new_win  = window.open("",new_win_name);
        $.getJSON("profile-view/ExtractProfileFilename.php?array="+ $('#ArrayListPopup option:selected').val(),
          { game: $('#GameListPopup option:selected').val(), timestamp: timeStamp2 },
          function(json){
                new_win.location.href =url;
                }
        );
}

function compareRelease() {
        pres=$('#ReleaseId :selected').index();
        var prev =pres-1 ;
        var start_time;
        var end_time;
        if ( pres > 1 )
        {
                end_time = $('#ReleaseId option').eq(prev).val();
        }
        else
        {
                var date = new Date();
                end_time = date.getTime().toString();
                end_time = end_time/1000;
        }
        start = $('#ReleaseId :selected').val();
        start_time = (parseInt(start)+parseInt(end_time))/2;
        $.getJSON("profile-view/getProfileList.php?array=" + array_id,
                {game: game_name, start_time: start_time, end_time: end_time},
                function(d) {
                        var break_loop = 0;
                        $.each(d, function(i,v){
                                if ( break_loop == 0 )
                                {
                                        compareWindow(v.timestamp);
                                }
                                break_loop++;
                        });
                }
        );
}



function sort_unique(arr) {
	arr = arr.sort(function (a, b) { return b*1 - a*1; });
	var ret = [arr[0]];
	for (var i = 1; i < arr.length; i++) { // start loop at 1 as element 0 can never be a duplicate
		if (arr[i-1] !== arr[i]) {
			ret.push(arr[i]);
		}
	}
	return ret;
}

// Convert to CLIENT side time (not to UTC time)
function convertToDate(timeslot){
	var timeslot= timeslot*1000;
	var d = new Date();
	var g = -d.getTimezoneOffset();
	g = g*60;
	timeslot = timeslot+g;
	var date = new Date(timeslot);
	return date;
	
}

function arr_unique(arr) {
	arr = arr.sort();//function (a, b) { return b - a; });
	var ret = [arr[0]];
	for (var i = 1; i < arr.length; i++) { // start loop at 1 as element 0 can never be a duplicate
		if (arr[i-1] !== arr[i]) {
			ret.push(arr[i]);
		}
	}
	return ret;
}

function sorted_key(json){
	var num=[]
	for( key in json)
	{
		var new_key = key.substring(key.indexOf(' ('));
		var numb = new_key.match(/\d/g);
		numb = numb.join("");
		num.push(numb)
	}
	num = sort_unique(num);
	new_array =[]
	for ( var sorted_num in num)
	{
		for( key in json)
		{
			var new_key = key.substring(key.indexOf(' ('));
			var present_num = new_key.match(/\d/g);
			present_num = present_num.join("");
			if(present_num == num[sorted_num])
			{
				new_array.push(key);
			}
		}
	}
	return new_array;
}

function changeArray(){        
	var popup_game = $('#GameListPopup option:selected').val();
	$('#ArrayListPopup').empty();
	$.each(game_array_id_json[popup_game] , function(index,value){
		$('#ArrayListPopup').append("<option value='"+value+"'>"+index+"</option");
	});
}

function fetchLastProfileTime()
{
	$.getJSON("profile-view/getLastProfile.php?array=" + array_id,
			{game: game_name},
				function(d) {
						lastDate(d[0].timestamp);
				}
			);
}
function lastDate(timestamp)
{
	var prevDate = new Date(timestamp*1000);
	$("#ProfileDateWidget").datepicker('setDate',prevDate);
	fetchHourSlots();
}

function fetchXhProf() {
	var ts = $('#hour-time-slots :selected').val();
	if(!ts) {
		$('#profiledsFileList').html("<hr />No data for this time slot<hr /><br /><div id='lastProf' >Last Profile</div>");
		$('#xhprof_view').attr('src','about:blank');
		$('#opener').addClass('hide');
		$('#hideSidePanel').addClass('hide');
		$('#lastProf').button();
		$('#lastProf').click(function() {
			fetchLastProfileTime();
		});
		return;
	}
	else {
		$('#opener').removeClass('hide');
		$('#hideSidePanel').removeClass('hide');
	}
	$.getJSON("profile-view/ExtractProfileFilename.php?array=" + array_id,
		{ game: game_name, timestamp: ts },
		function(json){
			var resultString = '';
			var sorted_array =sorted_key(json);
			for ( php_file_index in sorted_array)
			{
				php_file=sorted_array[php_file_index];
				var display_php_text;
				var memory_profile_pres;
				if ( php_file.indexOf('[') > 0 )
				{
				       display_php_text = php_file.substring(0,php_file.indexOf('['));
				       memory_profile_pres = php_file.substring(php_file.indexOf('[')+1,php_file.indexOf(']'));
				}
				else	
				{
					memory_profile_pres = "MemDisabled";
				}
				if ( memory_profile_pres == "MemEnabled")
				{
					text_color = "blue";
					title_text = "*Memory Profile Present";
				}
				else
				{
					text_color = "black";
					title_text = "Memory Profile Absent";
				}
				if ( display_php_text.indexOf('^') == 0 ) //Index should always be zero for a profile coming from HIPHOP node
                                {
                                        text_color="darkred";
                                        var text = display_php_text;
                                        var class_val = 'hiphop';
					display_php_text=text.substring(0,display_php_text.indexOf('^'))+text.substring(display_php_text.indexOf('^')+1)+" (H)";
                                }
                                else {
                                        var class_val='non-hiphop';
                                }
	
					
				var target = "xhprof_html/index.php?sort=excl_cpu&file="+json[php_file] + "&run=" + basename(json[php_file],".xhprof");
				
				if(php_file.indexOf("all") == 0) {
					/* hack to prevent reading other file due to error in manifest files */
					
					var pos = json[php_file].indexOf('.');
					var new_path = json[php_file].substring(0,pos)+".all.xhprof";
					json[php_file] = new_path;
					target = "xhprof_html/index.php?sort=excl_cpu&file="+json[php_file] + "&run=" + basename(json[php_file],".xhprof");
					/* end of hack */

					resultString += "<div style='color:"+text_color+"'><input type='radio' checked='true' name='profile_page' value='"+php_file+"' onClick='changeIframeTarget(\""+target+"\")'>";
					$('#xhprof_view').attr("src", "./xhprof_html/index.php?sort=excl_cpu&file="+json[php_file] + "&run=" + basename(json[php_file],".xhprof"));
					doIframe();
				}
				else {
					resultString += "<div class="+class_val+" style='color:"+text_color+"'><input type='radio' name='profile_page' value='"+php_file+"' onClick='changeIframeTarget("+"\"xhprof_html/index.php?sort=excl_cpu&file="+json[php_file]+"&run="+basename(json[php_file],".xhprof")+"\")'>";
				}
				resultString +="<span class='"+memory_profile_pres+"' title='"+title_text+"' >"+ display_php_text + "</span><br /><br /></div>";
			}
			$('.MemEnabledMemEnabled').tooltip();
			var dateTimeSelected = "<hr />" + "<b> </b>" + "<span id='opener' ><span id='compare_button' >Compare</span></span>" ;
			var searchProfile = "<input id='prof-search' style='width:200px;height:25px;color:grey;' value='Filter Profile' onblur='profileFilterBlur()' onfocus='profileFilterFocus()'><br /><br />";
			$('#hiphop_checkbox').click(function() {
                                var thisCheck = $(this);
                                if (thisCheck.is (':checked')){
                                        $('.hiphop').show();
                                }
                                else{
                                        $('.hiphop').hide();
                                }
                        });
			var legend = '<br /> <div id="legend"> <div style="float:left;width:0.8em;height:0.8em;background-color:blue;margin-top: 0.4em;"> </div> <div style="font-size:0.8em;">&nbsp;*Memory Profile present </div> <div style="float:left;width:0.8em;height:0.8em;background-color:#6B8E23;margin-top: 0.4em;">  </div> <div style="font-size:0.8em;"> Partial Memory Profile </div> <div style="float:left;width:0.8em;height:0.8em;background-color:darkred;margin-top: 0.4em;"> </div> <div style="font-size:0.8em;"> HipHop Profiles </div> </div><hr /><input type="checkbox" id="hiphop_checkbox" name="filter_checkbox" value="Hiphop" checked="yes"/><label for="hiphop_checkbox"> Hiphop Profiles</label><input type="checkbox" id="non_hiphop_checkbox" name="filter_checkbox" value="Others" checked="yes"/><label for="non_hiphop_checkbox"> Others profiles</label>';
			$('#profiledsFileList').html(dateTimeSelected + legend + "<hr />"+"<br />" + "" + searchProfile + resultString);
			$('#hiphop_checkbox').click(function() {
                                var thisCheck = $(this);
                                if (thisCheck.is (':checked')){
                                        $('.hiphop').show();
                                }
                                else{
                                        $('.hiphop').hide();
                                }
                        });
                        $('#non_hiphop_checkbox').click(function() {
                                var thisCheck = $(this);
                                if (thisCheck.is (':checked')){
                                        $('.non-hiphop').show();
                                }
                                else{
                                        $('.non-hiphop').hide();
                                }
                        });
			$( "#hide_button").button();
			$('#compare_button').button();
			$('#opener').click(function() {
				$dialog.dialog('open');
			});
			var profSearchAutoText = [];
			$('#prof-search').val($.cookie('prof-search'));
			var searchText = $('#prof-search').val();
			$("input:radio[name=profile_page]").each(function(index, value) {
				if ( $(this).val().toString().indexOf("@") > 0 )
					profSearchAutoText.push($(this).val().toString().substring(0,$(this).val().toString().indexOf("@")));
				if ( $(this).val().toString().indexOf("^") > 0 )
					profSearchAutoText.push($(this).val().toString().substring(0,$(this).val().toString().indexOf("^")));
			});
			if(searchText != '' && searchText != 'Filter Profile') 			{  
				$("input:radio[name=profile_page]").each(function(index, value) {
					if($(this).val().toString().search($('#prof-search').val()) == -1 &&  $(this).val().toString().indexOf("all") != 0)
					{
						$(this).parent().addClass('hide');
					}
				});
			}
			profSearchAutoText = arr_unique(profSearchAutoText);
			$("#prof-search").autocomplete(profSearchAutoText);
			$("#prof-search").keyup( function() {
				var searchText = $("#prof-search").val();
				if(searchText != '' || searchText != 'Filter Profile')
				{
					$("input:radio[name=profile_page]").each(function(index, value) {
						if($(this).val().toString().search($('#prof-search').val()) > -1)
						{
							$(this).parent().removeClass('hide');
						}
						else
						{
							$(this).parent().addClass('hide')
						}
					});
				}
				else
				{
					$("input:radio[name=profile_page]").each(function(index, value) {
						$(this).parent().removeClass('hide');
					});
				}
				$.cookie('prof-search',$('#prof-search').val());
			});
			$('#hideSidePanel').click(function() {
				var children = document.getElementById('ui-tabs-4').childNodes;
				document.getElementById('showSidePanel').style.display ="block";
				document.getElementById('xhprof_view').width = $(window).width();
				document.getElementById('opener').style.display ="none";
				for (var i = 0; i < children.length; i++)
				{
					if(children[i].id=="SidePane")
					{
						children[i].style.display = "none";
					}
					if(children[i].id=="MainPaneTable")
					{
						children[i].style.position = "absolute";
						children[i].style.left ="50px";
					}
				} 
			});
			$('.iframe_load_xhprof').click(function() {
				doIframe();
			});
		}
	);
 }
function fetchHourSlotsCompare(value) {
	var selectedDate = parseInt($("#UTC_seconds_compare").val())/1000; // milliseconds to seconds
	$.getJSON("profile-view/getProfileList.php?array=" + array_id,
		{game: game_name, start_time: selectedDate, end_time: selectedDate+(3600*24)},
		function(d) {
			$('#hour-time-slots-compare').empty();
			$.each(d, function(i,v){
				$('#hour-time-slots-compare').append("<option value='"+v.timestamp+"'>"+formatTimeStamp(v.timestamp)+"</option>");
			});
			$('#hour-time-slots-compare').val(value);
		}
	);
 }
function changeIframeTarget(target){
	document.getElementById("xhprof_view").src=target;
}

// Change to previous day in compare overlay
function changeDate() {
	dateString =document.getElementById("ProfileDateWidget").value;
	var present = new Date(dateString);
	var previousDay = new Date();
	previousDay.setTime(present.getTime() - (1000*3600*24));
	document.getElementById("datepicker").value = (previousDay.getMonth()+1)+"/"+(previousDay.getDate())+"/"+previousDay.getFullYear();
	var diff= $("#UTC_seconds").val()-$('#hour-time-slots :selected').val()*1000;
	var diff_int = ((diff*-1)/1800000);
	document.getElementById("UTC_seconds_compare").value =previousDay.valueOf(); 
	var new_value = (parseInt($("#UTC_seconds_compare").val())+diff_int*1800000)/1000;
	fetchHourSlotsCompare(new_value);
}

// Change to previous week in compare overlay
function changeWeek() {
	dateString =document.getElementById("ProfileDateWidget").value;
	var present = new Date(dateString);
	var previousDay = new Date();
	previousDay.setTime(present.getTime() - (1000*3600*24*7));
	document.getElementById("datepicker").value = (previousDay.getMonth()+1)+"/"+(previousDay.getDate())+"/"+previousDay.getFullYear();
	var diff= $("#UTC_seconds").val()-$('#hour-time-slots :selected').val()*1000;
	var diff_int = ((diff*-1)/1800000);
	document.getElementById("UTC_seconds_compare").value =previousDay.valueOf(); 
	var new_value = (parseInt($("#UTC_seconds_compare").val())+diff_int*1800000)/1000;
	fetchHourSlotsCompare(new_value);
}

// print the timestamp and half hour before  in timestamp
function formatTimeStamp(ts)  {
	var d = new Date(ts*1000);
	var h = d.getHours();
	var m = d.getMinutes();
	var m2 = (m+30) % 60;
	var h2 = h + (m2? 0 : 1);
	return h + ":" + (m ? m : "00") + " - " + (h2) + ":" + (m2 ? m2 : "00") ;
 }

function basename (path, suffix) {
	var b = path.replace(/^.*[\/\\]/g, '');
	if (typeof(suffix) == 'string' && b.substr(b.length-suffix.length) == suffix) {
		b = b.substr(0, b.length-suffix.length);
	}
	return b;
}

function fetchHourSlots()  {
    var selectedDate = parseInt($("#UTC_seconds").val())/1000; // milliseconds to seconds
    $.getJSON("profile-view/getProfileList.php?array="+array_id,
		{game: game_name,  start_time: selectedDate, end_time: selectedDate+(3600*24)},
		function(d) {
			$('#hour-time-slots').empty();
			$.each(d, function(i,v){
				var idText = "";
				if ( v.flags == "MemEnabled")
				{
					idText = "*";
					bgColor = "blue";
				}
				else if ( v.flags == "MemPartial")
				{
					bgColor = "#64FE2E";
				}
				else
				{
					bgColor ="black";
				}
				$('#hour-time-slots').append("<option style='color:"+bgColor+"' value='"+v.timestamp+"'>"+idText+formatTimeStamp(v.timestamp)+"</option>");
			});
			$('#hour-time-slots').val(timestamp);
			fetchXhProf();
		}
	);
 }
function profileFilterBlur() {
	if ( $('#prof-search').val() == '')
	{
		$('#prof-search').val( 'Filter Profile');
		$('#prof-search').css('color','grey');
	}
}
function profileFilterFocus() {
	if ( $('#prof-search').val() == 'Filter Profile')
	{
		$('#prof-search').val('');
		$('#prof-search').css('color','black');
	}
}


