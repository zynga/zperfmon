
/*
 *   M O D I F I E D   as   follows
 *
 * Copyright (c) 2013 Zynga
 *
 * Added utility functions, YUI support and igbinary+bzip support.
 */

/*  Copyright (c) 2009 Facebook
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

/**
 * Helper javascript functions for XHProf report tooltips.
 *
 * @author Kannan Muthukkaruppan
 */

// Take a string which is actually a number in comma separated format
// and return a string representing the absolute value of the number.
function dateToString (date) {
    var min = date.getMinutes();
    if ( min > 55 ) {
        diff = 60-min ;
        var new_timestamp = date.getTime() + diff * 60 * 1000;
        date = new Date( new_timestamp);
        min = date.getMinutes();
    }
    if ( min > 25 && min < 35) {
        diff = 30-min;
        var new_timestamp = date.getTime() + diff * 1000;
        date = new Date( new_timestamp);
        min = date.getMinutes();
    }
    var month = date.getMonth() +1;
    var day = date.getDate();
    var year = date.getFullYear();
    var hour = date.getHours();
    var nxt_hour = date.getHours();
    var nxt_min = date.getMinutes();
    if ( min > 0 ) {
        nxt_hour += 1;
        nxt_min = "00";
    }
    else {
        min = "0"+min;
        nxt_min = "30";
    }
    return ( month + "/" + day +"/" + year +"    "+hour+":"+min+" - "+nxt_hour+":"+nxt_min);
}
function convertToDate(timeslot) {
	var timeslot = timeslot * 1000;
	var d = new Date();
	var g = -d.getTimezoneOffset();
	g = g * 60;
	timeslot = timeslot+g;
	var date = new Date(timeslot);
	return date;
}
function getArrayIdName() {
	/*
	 $.getJSON("?array="+ $('#ArrayListPopup option:selected').val(),
          { game: $('#GameListPopup option:selected').val(), timestamp: timeStamp2 },
          function(json){
                new_win.location.href =url;
                }
        );
	*/
}
function newUrlFunction(new_url) {
	var  loc = document.location.toString();
	var new_loc ="";
        if ( loc.search('index.php') >= 0) {
			new_loc = loc.replace('index.php',new_url);
        }
        else {
			new_loc = loc.replace('?',new_url+'?');
        }
	return new_loc;
}
function changeUrlfn(new_url) {
	var  loc = document.location.toString();
	if ( loc.search('index.php') >= 0) {
		new_loc = loc.replace('index.php',new_url);
		new_loc =new_loc.replace('&symbol=',"&func=");
		document.location.href = new_loc ;
	}
	else {
		new_loc = loc.replace('?',new_url+'?');
		new_loc =new_loc.replace('&symbol=',"&func=");
		document.location.href = new_loc ;
	}
}

function changeUrl(new_url) {
	var  loc = document.location.toString();
	if ( loc.search('index.php') >= 0) {
		new_loc = loc.replace('index.php',new_url);
		document.location.href = new_loc ;
	}
	else {
		new_loc = loc.replace('?',new_url+'?');
		document.location.href = new_loc ;
	}
}
function myCustomInitialSort(a,b){
	var url = document.location.toString();
	var param = (url.substring(url.indexOf("?")+1));
	var row = "wt";
	var type = "desc";
	if ( param.indexOf("sort_row") > 0 ) {
		row = param.substring(param.indexOf("sort_row")+9);
		if ( row.indexOf("&") > 0 )
			row = row.substring(0,row.indexOf("&"));
	}
	if ( param.indexOf("sort_type") > 0 ) {
		type = param.substring(param.indexOf("sort_type") + 10);
		if ( type.indexOf("&")>0 )
			type = type.substring(0,type.indexOf("&"));
	}
	if ( type == "desc" ) {
		if ( a[row] > b[row] )
			return -1;
		else
			return 1;
	}
	else if ( type == "asc" ) {
		if ( a[row] > b[row] )
			return 1;
		else
			return -1;
	}
};

var host= document.location.host;
function stringAbs(x) {
	return x.replace("-", "");
}
// TODO replace it
function searchfield_focus() {
	if ( $('#fn-input').val() == 'Function Search')
		$('#fn-input').val('');
}

	// Takes a number in comma-separated string format, and
	// returns a boolean to indicate if the number is negative
	// or not.
function isNegative(x) {
	return (x.indexOf("-") == 0);
}

function addCommas(nStr)
{
	nStr += '';
	x = nStr.split('.');
	x1 = x[0];
	x2 = x.length > 1 ? '.' + x[1] : '';
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(x1)) {
		x1 = x1.replace(rgx, '$1' + ',' + '$2');
	}
	return x1 + x2;
}

// Mouseover tips for parent rows in parent/child report..
function ParentRowToolTip(cell, metric)
{
	var metric_val;
	var parent_metric_val;
	var parent_metric_pct_val;
	var col_index;
	var diff_text;
	row = cell.parentNode;
	tds = row.getElementsByTagName("td");

	parent_func    = tds[0].innerHTML;  // name

	if (diff_mode) {
		diff_text = " diff ";
	} else {
		diff_text = "";
	}

	s = '<center>';

	if (metric == "ct") {
		parent_ct      = tds[1].innerHTML;  // calls
		parent_ct_pct  = tds[2].innerHTML;

		func_ct = addCommas(func_ct);

		if (diff_mode) {
		  s += 'There are ' + stringAbs(parent_ct) +
			(isNegative(parent_ct) ? ' fewer ' : ' more ') +
			' calls to ' + func_name + ' from ' + parent_func + '<br>';

		  text = " of diff in calls ";
		}  else {
		  text = " of calls ";
		}

		s += parent_ct_pct + text + '(' + parent_ct + '/' + func_ct + ') to '
		  + func_name + ' are from ' + parent_func + '<br>';
	}
	else {

		// help for other metrics such as wall time, user cpu time, memory usage
		col_index = metrics_col[metric];
		parent_metric_val     = tds[col_index].innerHTML;
		parent_metric_pct_val = tds[col_index+1].innerHTML;

		metric_val = addCommas(func_metrics[metric]);

		s += parent_metric_pct_val + '(' + parent_metric_val + '/' + metric_val
		  + ') of ' + metrics_desc[metric] +
		  (diff_mode ? ((isNegative(parent_metric_val) ?
						" decrease" : " increase")) : "") +
		  ' in ' + func_name + ' is due to calls from ' + parent_func + '<br>';
	  }

	s += '</center>';
	return s;
}

// Mouseover tips for child rows in parent/child report..
function ChildRowToolTip(cell, metric)
{
  var metric_val;
  var child_metric_val;
  var child_metric_pct_val;
  var col_index;
  var diff_text;

  row = cell.parentNode;
  tds = row.getElementsByTagName("td");

  child_func   = tds[0].innerHTML;  // name

  if (diff_mode) {
	diff_text = " diff ";
  } else {
	diff_text = "";
  }

  s = '<center>';

  if (metric == "ct") {

	child_ct     = tds[1].innerHTML;  // calls
	child_ct_pct = tds[2].innerHTML;

	s += func_name + ' called ' + child_func + ' ' + stringAbs(child_ct) +
	  (diff_mode ? (isNegative(child_ct) ? " fewer" : " more") : "" )
		+ ' times.<br>';
	s += 'This accounts for ' + child_ct_pct + ' (' + child_ct
		+ '/' + total_child_ct
		+ ') of function calls made by '  + func_name + '.';

  } else {

	// help for other metrics such as wall time, user cpu time, memory usage
	col_index = metrics_col[metric];
	child_metric_val     = tds[col_index].innerHTML;
	child_metric_pct_val = tds[col_index+1].innerHTML;

	metric_val = addCommas(func_metrics[metric]);

	if (child_func.indexOf("Exclusive Metrics") != -1) {
	  s += 'The exclusive ' + metrics_desc[metric] + diff_text
		+ ' for ' + func_name
		+ ' is ' + child_metric_val + " <br>";

	  s += "which is " + child_metric_pct_val + " of the inclusive "
		+ metrics_desc[metric]
		+ diff_text + " for " + func_name + " (" + metric_val + ").";

	} else {

	  s += child_func + ' when called from ' + func_name
		+ ' takes ' + stringAbs(child_metric_val)
		+ (diff_mode ? (isNegative(child_metric_val) ? " less" : " more") : "")
		+ " of " + metrics_desc[metric] + " <br>";

	  s += "which is " + child_metric_pct_val + " of the inclusive "
		+ metrics_desc[metric]
		+ diff_text + " for " + func_name + " (" + metric_val + ").";
	}
  }

  s += '</center>';

  return s;
}

/* Edited from xhprof js file
 *
 *
 *
*/

var zperfmon = window.zperfmon ||  {};   //namespace
var xhprof ;
zperfmon.xhprof = (function() {
	// saves the value of yahoo 2 in a temp variable to be used by datatable
	var YAHOO;	
	var sumx ={};
	var sum_main = {};
	var sum_child = {};
	var sum_parent = {};
	var symbol ;
	return {
		init: function(){
			var url = document.location.toString();
			var param = (url.substring(url.indexOf("?")+1));
			symbol = param.substring(param.indexOf("symbol")+7);
			if(symbol.indexOf("&")>0)
				symbol = symbol.substring(0,symbol.indexOf("&"))
		
			YUI().use('autocomplete','yui2-dragdrop', 'autocomplete-filters', 'autocomplete-highlighters','yui2-cookie','yui2-paginator', 'yui2-datasource', 'yui2-datatable', 'yui2-logger', function(Y)
			{
				YAHOO = Y.YUI2;
			});
		},
		
		
		/*
		 * sets yui cookie	
		*/
		set_cookie: function (value){
			YAHOO.util.Cookie.setSub("zperfmon_xhprof","rows",value); 
		},
		/*
		 * Read yui cookie
		*/
		get_cookie: function (){
			return YAHOO.util.Cookie.getSub("zperfmon_xhprof","rows");
		},
			
		custom_field_sortFunction : function(a, b, desc, field) {
			var custom_sort_field = "type";
			if(!YAHOO.lang.isValue(a)) {
				return (!YAHOO.lang.isValue(b)) ? 0 : 1;
			} else if(!YAHOO.lang.isValue(b)) {
				return -1;
			}
	 
			// First compare by Column2
			var comp = YAHOO.util.Sort.compare;
			var compState = comp(a.getData(custom_sort_field), b.getData(custom_sort_field), desc);
			if(a.getData(custom_sort_field) == 'Current Function' && b.getData(custom_sort_field) == 'Current Function'){
				if(a.getData(field) == 'Exclusive Metrics for Current function'){
					return 1;
				}
			}
			if(a.getData(custom_sort_field) == 'Current Function'){
				return -1;
			}	
			if(b.getData(custom_sort_field) == 'Current Function'){
				return 1;
			}
			if(a.getData(custom_sort_field) == 'child' && b.getData(custom_sort_field)=='parent'){
				return -1;
			}
			 
			// If values are equal, then compare by Column1
			return (compState !== 0) ? compState : comp(a.getData(field), b.getData(field), desc);
		},
		myPerSortFunction : function(a, b, desc, field) {
			// Deal with empty values
			if(!YAHOO.lang.isValue(a)) {
				return (!YAHOO.lang.isValue(b)) ? 0 : 1;
			} else if(!YAHOO.lang.isValue(b)) {
				return -1;
			}     
				 
			// First compare by Column2
			var comp = YAHOO.util.Sort.compare;
			var compState = comp(a.getData("type"), b.getData("type"), desc);
			if(a.getData("type") == 'Current Function' && b.getData("type") == 'Current Function')
			{
				if(a.getData(field) == 'Exclusive Metrics for Current function')
						return 1;
			}
			if(a.getData("type") == 'Current Function')
				return -1;
			if(b.getData("type") == 'Current Function')
				return 1;
			if(a.getData("type") == 'child' && b.getData("type")=='parent')
				return -1
			var key = field.slice(0,field.length-4);
			// If values are equal, then compare by Column1
			return (compState !== 0) ? compState : comp(a.getData(key), b.getData(key), desc);
		},
		percentage : function(elLiner, oRecord, oColumn, oData,field) {
			var key = oColumn.key.slice(0,oColumn.key.length-4);
			var value = oRecord.getData(key);
			var sum =0;
			if(oRecord.getData('type') == 'child')
			{
				sum = sum_parent[key] ;
				if(oColumn.key =='ct_per')
					 sum = sum_child[key];
			}
			else if( oRecord.getData('type') =='parent')
			{
				sum = sum_parent[key];
			}
			else if( oRecord.getData('fn') != 'Exclusive Metrics for Current function')
			{
				sum = sum_main[key];
			}
			else
			{
				sum = sum_parent[key];
			}
			var percentage = ((value / sum)*100).toFixed(1);
			 if ( isNaN(percentage) || percentage == null)
				elLiner.innerHTML = "0.0%";
			else 
				elLiner.innerHTML = percentage+"%";
			if( oRecord.getData('type') == 'Current Function')
			{
				if(oColumn.key == 'ct_per')
				{
					 elLiner.innerHTML ="";
				}	
			}
			$(elLiner).addClass('right');
		},
		double_number : function (elLiner, oRecord, oColumn, oData,field){
			if ( isNaN(oData) || oData == null)
				elLiner.innerHTML = "0";
			else 
				elLiner.innerHTML = addCommas(oData.toFixed(4));
			$(elLiner).addClass('right');
			if(oRecord.getData('fn') == 'Exclusive Metrics for Current function')
			{
				if(oColumn.key == 'ct')
				{
					elLiner.innerHTML ="";
				}
			}
		},
					
		function_formatter : function (elLiner, oRecord, oColumn, oData,field){
			if(oRecord.getData("fn"))
			{
				var tmp = "";
				var replace_str = "&symbol="+symbol;
				var new_location = document.location.toString();
				new_location = new_location.replace(replace_str,"")+'&symbol='+oData;
				elLiner.innerHTML = '<a href="'+new_location+'">'+oData+tmp+'</a>';
				if(oData == "Exclusive Metrics for Current function")
				{
					elLiner.innerHTML = oData;
				}
			}
		},
		sortPts: function(a, b){
			if(a.type == "parent")
				return 1;
			else if(b.type =="parent")
				return -1;
			else if(a.type =="child")
				return 1;
			else if(b.type =="child")
				return -1;

		},
		percentage_main : function(elLiner, oRecord, oColumn, oData) 
		{
			var key = oColumn.key.slice(0,oColumn.key.length-4);
			var value = oRecord.getData(key);
			var percentage = ((value / sumx[key])*100).toFixed(1);
			if ( isNaN(percentage) || percentage == null)
				elLiner.innerHTML = "0.0%";
			else 
				elLiner.innerHTML = percentage+"%";
			YAHOO.util.Dom.addClass(elLiner,'right');
		},
		display_paginator :function(){
			var new_oConfigs =
			{
				paginator : null
			};
			var myColumnDefs =
			[
				{label:"Function Name",resizeable:true,key:"fn",width:150, sortable:true,label:"Function Name",formatter:this.function_formatter}
			];
			fields = [];
			for (var j  in myData.posts[0])
			{
				if ( j != 'fn')
					fields.push(j);
			}
			for (var i in myData.posts[0])
			{
				if ( myData.posts[0].hasOwnProperty(i))
				{
					if(!isNaN(myData.posts[0][i]))
					{
						if(i.substring(0,4)=="excl")
						{
							sumx[i]= Math.abs(myData['totals'][i.substring(5)]);
						}
						else
						{
							sumx[i]=Math.abs(myData['totals'][i]);
						}
					}
				}
			}

			for(var i in fields)
			{
				if ( fields.hasOwnProperty(i))
				{
					$("#"+fields[i]).attr('checked','checked');
					$("#"+fields[i]+'_per').attr('checked','checked');
					var obj = {sortable:true, resizeable:true,formatter:this.double_number,sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
					obj['key'] = fields[i];
					obj['label'] = fn_header[obj['key']];
					myColumnDefs.push(obj);
					var obj_per = {sortable:true, resizeable:true,formatter:this.percentage_main,sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
					obj_per['key'] = fields[i]+'_per';
					obj_per['label'] = fn_header[obj_per['key']];
					obj_per['sortOptions']['field'] = obj['key'];
					myColumnDefs.push(obj_per);
				}
			}
			new_myDataTable = new YAHOO.widget.DataTable("basic",
		   myColumnDefs, myDataTable._oDataSource, new_oConfigs);
			if (self != top )
			{
				 parent.set_page_size($("#basic").children('table').width(),$("#basic").children('table').height()+500);
			}

		},
		select_column: function(){
			var selected_options = "";
			var checkbox_container = document.getElementById("checkbox_container");
			var children = checkbox_container.childNodes;
			var myColumnDefs = [
				{ label:"Function Name",sortable:"true",key:"fn", resizeable:true,formatter:"myCustom",width:150}
			];
			for (var i = 0; i < children.length; i++) 
			{
				if(children[i].nodeName=="DIV")
				{
					var obj = {resizeable:true,sortable:true,sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
					var obj_per = {resizeable:true,sortable:true,sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
					if(children[i].childNodes[0].checked)
					{
						obj['key']=children[i].childNodes[0].id;
						obj['label'] = fn_header[obj['key']];
						selected_options += children[i].childNodes[0].id+" ";
						if(children[i].childNodes[0].id.substring(children[i].childNodes[0].id.length-3) == "per")
						{
							obj['formatter'] = "percentage";
							obj['sortOptions']['field'] = obj['key'].slice(0,obj['key'].length-4);
						}
						else
						{
							obj['formatter'] = "double_number";
						}
						myColumnDefs.push(obj);
					}
				}
			};
			YAHOO.util.Cookie.setSub("zperfmon_xhprof","rows", selected_options);
			myDataTable.setColumns(myColumnDefs);
			if (self != top )
			{
				parent.set_page_size($("#basic").children('table').width(),$("body").height());
			}
			myDataTable.render();
		},
		back: function(){
			window.history.back();
		},
		home: function(){
			var url = document.location.toString();
			// TODO replace symbol with null
			//replace symbol with any invalid iteralal
			url = url.replace('symbol=','xnv=');
			document.location.href = url;
		},
		display_function: function(){
			var th = this;
			var client = new XMLHttpRequest();
			var url = document.location.toString();
//				url = url.replace('index.php','index_json.php');
			url = newUrlFunction('index_json.php');
			client.open("GET", url, true);
			client.onreadystatechange = function() 
			{
				if ( client.readyState == 4) 
				{
					myData = JSON.parse(client.responseText);
					var data =[];
					var data_parent =[];
					var data_child =[];
					var fields = [];//'fn','type'];
					var i;
					for( i in myData)
					{
					}
					for (var j  in myData[i])
					{
						fields.push(j);
						sum_main[j] = 0;
					}
					for ( i in myData )
					{
						obj = {};
						var fn_child = i.substring(0,i.indexOf("==>"))
						var fn_parent = i.substring(i.indexOf("==>")+3)
						if ( i == "main()")
						{
							for ( var param in myData[i])
							{
								sum_main[param]=myData[i][param];
							}
						}
						for (var j  in myData[i])
						{
							obj[j] = myData[i][j];
						}
						if ( fn_parent == symbol )
						{
							obj['fn'] = fn_child;
							obj['type'] = 'parent';
							data_parent.push(obj);
						}
						else if ( fn_child == symbol)
						{
							obj['fn'] = fn_parent;
							obj['type'] = 'child';
							data_child.push(obj);
						}						
					}
					for(i in data_child)
					{
						for(j in data_child[i])
						{	
							if(j != 'type' && j != 'fn')
							{
								if(sum_child[j] == undefined)
									sum_child[j]=data_child[i][j];
								else
									sum_child[j]+=data_child[i][j];
							}
						}
					}
					for(i in data_parent)
					{
						for(j in data_parent[i])
						{
							if(j != 'type' && j != 'fn')
							{
								if(sum_parent[j] == undefined)
								{
									sum_parent[j]=data_parent[i][j];
								}
								else
								{
									sum_parent[j]+=data_parent[i][j];
								}
							}
						}
					}
					
					data = data_parent.concat(data_child);
					var current_fn ={};
					current_fn['fn']=symbol;
					current_fn['type'] = 'Current Function';
					if ( symbol == 'main()' )
					{	
						for(var  i in fields)
						{
							current_fn[fields[i]]=sum_main[fields[i]];
							sum_parent[fields[i]]=sum_main[fields[i]];
						}
					}
					else
					{
						for(var  i in fields)
						{
							current_fn[fields[i]]=sum_parent[fields[i]];
						}
					}

					data.push(current_fn);
					var current_fn ={};
					current_fn['fn']="Exclusive Metrics for Current function";
					current_fn['type'] = 'Current Function';
					for(var  i in fields)
					{
						current_fn[fields[i]]=sum_parent[fields[i]]-sum_child[fields[i]];
					}
					data.push(current_fn);
					var  myColumnDefs = [
						{ key: "fn", formatter: th.function_formatter,width:150,label: "Function Name", sortable: true, resizeable: true, sortOptions: { sortFunction: th.custom_field_sortFunction }}//,
					];
					for( i in fields)
					{
						var obj={sortable: true, resizeable: true, sortOptions: { sortFunction: th.custom_field_sortFunction }};
						obj['key']=fields[i];
						obj['label'] = fn_header[obj['key']];
						obj['formatter'] = th.double_number;
						myColumnDefs.push(obj);
						var obj_per = {sortable: true, resizeable: true,formatter:th.percentage,sortOptions: { sortFunction: th.myPerSortFunction }};
						obj_per['key']=obj['key']+'_per';
						obj_per['label'] = fn_header[obj_per['key']];
						myColumnDefs.push(obj_per);
					}
					var fields_all = ['fn','type'];
					fields_all = fields_all.concat(fields);

					var myDataSource = new window.YAHOO.util.DataSource(data); // See data.js
					myDataSource.responseType = window.YAHOO.util.DataSource.TYPE_JSARRAY;
					myDataSource.responseSchema = {
						fields: fields_all
					};
					myDataSource.doBeforeCallback = function (oRequest, oFullResponse, oParsedResponse, oCallback) {
						oParsedResponse.results.sort(th.sortPts);
						return oParsedResponse;
					};
					var myDataTable = new window.YAHOO.widget.GroupedDataTable("function_table",
					myColumnDefs, myDataSource,
					{
						groupBy: "type"//,
					});
					if (self != top )
					{
						parent.set_page_size($("#function_table").children('table').width(),$("#function_table").children('table').height()+500);
					}
					if($("#fn-summary").length > 0)
					{
						for( i in fields)
						{
							if ( fields[i] =='ct')
							{
								$('#fn-summary tr:first').after('<tr><td>Number of Function Calls</td><td>'+sum_parent[fields[i]].toFixed(4)+"</td></tr>");
							}
							else
							{
								var excl_value = (parseInt(sum_parent[fields[i]])-parseInt(sum_child[fields[i]])).toFixed(4);
								if(isNaN(excl_value)) { 
									excl_value=0;
								}
								$('#fn-summary tr').eq(1).after('<tr><td>'+fn_header_wt_newline["excl_"+fields[i]]+'</td><td>'+excl_value+"</td></tr>");
								$('#fn-summary tr').eq(1).after('<tr><td>'+fn_header_wt_newline[fields[i]]+'</td><td>'+sum_parent[fields[i]].toFixed(4)+"</td></tr>");
							}
						}
					}
					var showTimer,hideTimer;
					var tt = new window.YAHOO.widget.Tooltip("myTooltip");
					myDataTable.on('cellMouseoverEvent', function (oArgs) {
						if (showTimer) {
							window.clearTimeout(showTimer);
							showTimer = 0;
						}
						var target = oArgs.target;
						var column = this.getColumn(target);
						if (column.key != 'fn') {
							var record = this.getRecord(target);
							var description = 'no further descriptio1n';
							if( record.getData('type') == 'child')
							{
								var key = column.key;
								if( key.indexOf("_per")>0)
									key = key.substring(0,key.indexOf("_per"));
								var per = (record.getData(key)/sum_parent[key])*100;
								per =per.toFixed(2);
								var sum = sum_parent[key].toFixed(4);
								if ( key == 'ct')
									sum = sum_child[key].toFixed(4);
								var value = record.getData(key).toFixed(4);
								if ( key == 'ct')
								{
									sum = sum_child[key].toFixed(4);
									per = (record.getData(key)/sum_child[key])*100;
									per =per.toFixed(2);
								}
								description = record.getData('fn')+" when called from "+symbol+" takes "+value+" of "+fn_header_wt_newline[key]+" <br /> which is "+per+"% of the "+fn_header_wt_newline[key]+" for "+symbol+" ("+sum+")";
								
							}
							else if (record.getData('type') == 'parent')
							{
								var key = column.key;
								if( key.indexOf("_per")>0)
										key = key.substring(0,key.indexOf("_per"));
								var per = (record.getData(key)/sum_parent[key])*100;
								per =per.toFixed(2);
								var sum = sum_parent[key].toFixed(4);
								var value = record.getData(key).toFixed(4);
								description = per + "% of "+fn_header_wt_newline[key]+" ("+value+"/"+sum+") to "+symbol+" are from "+record.getData('fn');
							}
							else if( record.getData('fn') != symbol )
							{
								var key = column.key;
								if( key.indexOf("_per")>0)
								   key = key.substring(0,key.indexOf("_per"));
								var per = (record.getData(key)/sum_parent[key])*100;
								per =per.toFixed(2);
								var sum = sum_parent[key].toFixed(4);
								var value = record.getData(key).toFixed(4);
								description = "The exclusive metric "+" for "+symbol+" is "+value+"<br />which is "+per+"% of the"+fn_header_wt_newline[key]+" for "+symbol+" ("+sum+")";
							}
							var xy = [parseInt(oArgs.event.clientX,10) + 10 ,parseInt(oArgs.event.clientY,10) + 10 ];
		 
							showTimer = window.setTimeout(function() {
								tt.setBody(description);
								tt.cfg.setProperty('xy',xy);
								tt.show();
								hideTimer = window.setTimeout(function() {
									tt.hide();
								},5000);
							},500);
						}
					});
					myDataTable.on('cellMouseoutEvent', function (oArgs) {
						if (showTimer) {
							window.clearTimeout(showTimer);
							showTimer = 0;
						}
						if (hideTimer) {
							window.clearTimeout(hideTimer);
							hideTimer = 0;
						}
						tt.hide();
					});
				}
				$("#loading_image").addClass("hide");
			}
			client.send();
		}
	}
});
	
$(document).ready(function()
{
	if($('#time').length > 0)
	{
		var date = convertToDate($('#time').text());
		$("#time").text(dateToString(date));
	}
	if($('#time1').length > 0)
	{
		var date = convertToDate($('#time1').text());
		$("#time1").text(dateToString(date));
	}
	if($('#time2').length > 0)
	{
		var date = convertToDate($('#time2').text());
		$("#time2").text(dateToString(date));
	}

	xhprof = new zperfmon.xhprof();
	xhprof.init();
	if( $("#function_table").length > 0)
	{
		$("#callgraph_button_fn").button();
	}
	if ( $('#basic').length > 0 )
	{
		$('#checkbox_container').click(function(){
			xhprof.select_column();
		});
		$('#display_all').click(function(){
			xhprof.display_paginator();
		});

		checkbox_container
		$dialog = $('#checkbox_container')//compareOverlay')
			.dialog({
				autoOpen: false,
				title: 'Select Column',
				width: 300,
				height: "auto",
				hide: "explode",
				position: 'top',
				open:function(event, ui) {
					$('.ui-dialog').css('left','630px');
					$('.ui-dialog').css('top','100px');
					$('#select_column').attr("disabled", true);
					$('#select_column').css('background-color','#AAA');
					$('#select_column').css('background-image','url()');
					$('.ui-dialog-titlebar').css('background','#D8D8DA url(http://yui.yahooapis.com/2.9.0/build/assets/skins/sam/sprite.png) repeat-x 0 0');	
				},
				close:function(event, ui) {
					$('#select_column').attr("disabled",false);	
					$('#select_column').css('background-image','url(http://hotlink.jquery.com/jqueryui/themes/base/images/ui-bg_glass_75_dadada_1x400.png)');
				}
			});
		$('#select_column').click(function() {
			$dialog.dialog('open');
		});
	}
	if ( $('#function_table').length > 0){
		$('#back_button').button();
		$('#home_button').button();
		xhprof.display_function();
	}
	$('#back_button').click(function(event){
		xhprof.back();
	});
	$('#home_button').click(function(event){
		xhprof.home();
	});

	if ( $('#fn_not_found').length > 0){
		$("#loading_image").addClass("hide");
	}
	if ( $('#diff_page').length > 0){
		$('#callgraph_button').css("display","none");
		$('#piechart').css("display","none");
		fn_header =
		{
			"ct":"Calls Diff",
			"ct_per":"Calls<br>Diff%",
			"wt":"Incl. Wall<br>Diff<br>(microsec)",
			"wt_per":"IWall<br> Diff%",
			"excl_wt":"Excl. Wall<br>Diff<br>(microsec)",
			"excl_wt_per":"EWall<br>Diff%",
			"ut":"Incl. User Diff<br>(microsec)",
			"ut_per":"IUser<br>Diff%",
			"excl_ut":"Excl. User<br>Diff<br>(microsec)",
			"excl_ut_per":"EUser<br>Diff%",
			"cpu":"Incl. CPU Diff<br>(microsec)",
			"cpu_per":"ICpu<br>Diff%",
			"excl_cpu":"Excl. CPU<br>Diff<br>(microsec)",
			"excl_cpu_per":"ECpu<br>Diff%",
			"st":"Incl. Sys Diff<br>(microsec)",
			"st_per":"ISys<br>Diff%",
			"excl_st":"Excl. Sys Diff<br>(microsec)",
			"excl_st_per":"ESys<br>Diff%",
			"mu":"Incl.<br>MemUse<br>Diff<br>(bytes)",
			"mu_per":"IMemUse<br>Diff%",
			"excl_mu":"Excl.<br>MemUse<br>Diff<br>(bytes)",
			"excl_mu_per":"EMemUse<br>Diff%",
			"pmu":"Incl.<br> PeakMemUse<br>Diff<br>(bytes)",
			"pmu_per":"IPeakMemUse<br>Diff%",
			"excl_pmu":"Excl.<br>PeakMemUse<br>Diff<br>(bytes)",
			"excl_pmu_per":"EPeakMemUse<br>Diff%",
			"samples":"Incl. Samples Diff",
			"samples_per":"ISamples Diff%",
			"excl_samples":"Excl. Samples Diff",
			"excl_samples_per":"ESamples Diff%"
		};
		fn_header_wt_newline =
		{
			"ct":"Calls Diff",
			"ct_per":"CallsDiff%",
			"wt":"Incl. WallDiff(microsec)",
			"wt_per":"IWall Diff%",
			"excl_wt":"Excl. WallDiff(microsec)",
			"excl_wt_per":"EWallDiff%",
			"ut":"Incl. User Diff(microsec)",
			"ut_per":"IUserDiff%",
			"excl_ut":"Excl. UserDiff(microsec)",
			"excl_ut_per":"EUserDiff%",
			"cpu":"Incl. CPU Diff(microsec)",
			"cpu_per":"ICpuDiff%",
			"excl_cpu":"Excl. CPUDiff(microsec)",
			"excl_cpu_per":"ECpuDiff%",
			"st":"Incl. Sys Diff(microsec)",
			"st_per":"ISysDiff%",
			"excl_st":"Excl. Sys Diff(microsec)",
			"excl_st_per":"ESysDiff%",
			"mu":"Incl.MemUseDiff(bytes)",
			"mu_per":"IMemUseDiff%",
			"excl_mu":"Excl.MemUseDiff(bytes)",
			"excl_mu_per":"EMemUseDiff%",
			"pmu":"Incl. PeakMemUseDiff(bytes)",
			"pmu_per":"IPeakMemUseDiff%",
			"excl_pmu":"Excl.PeakMemUseDiff(bytes)",
			"excl_pmu_per":"EPeakMemUseDiff%",
			"samples":"Incl. Samples Diff",
			"samples_per":"ISamples Diff%",
			"excl_samples":"Excl. Samples Diff",
			"excl_samples_per":"ESamples Diff%"
		};
	}
});

// Display the search results when enter key is pressed
function display_page(event)
{
	if(event.keyCode == 13){
		document.location = document.location+'&symbol='+$("#fn-input").val();
	}
	return false;	
}
var myDatatable;
var fields = [];
var myData;
var sumx={};
var fn_header_wt_newline =
{
"fn":"Function Name",
"ct": "Calls",
"ct_per":"Calls%",
"wt":"Incl. Wall Time (microsec)",
"wt_per":"IWall%",
"excl_wt":"Excl. Wall Time (microsec)",
"excl_wt_per":"EWall%",
"ut":"Incl. User (microsecs)",
"ut_per":"IUser%",
"excl_ut":"Excl. User (microsec)",
"excl_ut_per":"EUser%",
"st":"Incl. Sys  (microsec)",
"st_per":"ISys%",
"excl_st":"Excl. Sys  (microsec)",
"excl_st_per":"ESys%",
"cpu":"Incl. CPU (microsecs)",
"cpu_per":"ICpu%",
"excl_cpu":"Excl. CPU (microsec)",
"excl_cpu_per":"ECPU%",
"mu":"Incl. MemUse (bytes)",
"mu_per":"IMemUse%",
"excl_mu":"Excl. MemUse (bytes)",
"excl_mu_per":"EMemUse%",
"pmu":"Incl.  PeakMemUse (bytes)",
"pmu_per":"IPeakMemUse%",
"excl_pmu":"Excl. PeakMemUse (bytes)",
"excl_pmu_per":"EPeakMemUse%",
"samples":"Incl. Samples",
"samples_per":"ISamples%",
"excl_samples":"Excl. Samples",
"excl_samples_per":"ESamples%"
};

var fn_header =
{
"fn":"Function Name",
"ct": "Calls",
"ct_per":"Calls%",
"wt":"Incl. Wall Time<br>(microsec)",
"wt_per":"IWall%",
"excl_wt":"Excl. Wall Time<br>(microsec)",
"excl_wt_per":"EWall%",
"ut":"Incl. User<br>(microsecs)",
"ut_per":"IUser%",
"excl_ut":"Excl. User<br>(microsec)",
"excl_ut_per":"EUser%",
"st":"Incl. Sys <br>(microsec)",
"st_per":"ISys%",
"excl_st":"Excl. Sys <br>(microsec)",
"excl_st_per":"ESys%",
"cpu":"Incl. CPU<br>(microsecs)",
"cpu_per":"ICpu%",
"excl_cpu":"Excl. CPU<br>(microsec)",
"excl_cpu_per":"ECPU%",
"mu":"Incl.<br>MemUse<br>(bytes)",
"mu_per":"IMemUse%",
"excl_mu":"Excl.<br>MemUse<br>(bytes)",
"excl_mu_per":"EMemUse%",
"pmu":"Incl.<br> PeakMemUse<br>(bytes)",
"pmu_per":"IPeakMemUse%",
"excl_pmu":"Excl.<br>PeakMemUse<br>(bytes)",
"excl_pmu_per":"EPeakMemUse%",
"samples":"Incl. Samples",
"samples_per":"ISamples%",
"excl_samples":"Excl. Samples",
"excl_samples_per":"ESamples%"
};
//check if array has all the value of second array 
function check_subset ( array1, array2)
{
	var i ;
	var j ;
	var match = 0 ;
	for ( i in array1 )
	{
		if ( array1[i] == "" )
			match++;
	}
	for ( i in array1 )
	{
		for ( j in array2)
		{
			if( array1[i] == array2[j])
			{
				match++;
			}
		}
	}
	if ( array1.length == match )
	{
		return (true);
	}
	else
	{
		return (false);
	}
}

$(document).ready(function() 
{
	if ( $('#function_table').length >0 && self!=top )
	{
		$("body").width('80%');
	}
	if ( $('#diff_page').length <= 0 && self==top )
	{
		$("body").prepend('<div id="prof_page" class="title left hdblock"  style="">&nbsp;&nbsp;zPerfmon/Profile Page</div><br />');  
	}
	if ( $('#top_data').length > 0 )
	{
	var topDatatable;
	var fields;
	var client_top = new XMLHttpRequest();
	var url = document.location.toString();
	url = newUrlFunction('index_json.php');
	url = url+"&count=100";
	client_top.open("GET",url, true);
	client_top.onreadystatechange = function() 
	{
		if(client_top.readyState == 4) 
		{
			var topData = JSON.parse(client_top.responseText);
			var sum = [];
			var fields = [];
			var fields_all = [];
			var fields_per = [];
			for (var i in topData.posts[0])
			{
				if ( topData.posts[0].hasOwnProperty(i))
				{
					fields_all.push(i);
					if(!isNaN(topData.posts[0][i]))
					{
						var temp = 0;
						var max = 0;
						for( var j in topData.posts)
						{
							if ( topData.posts.hasOwnProperty(j))
							{
								temp+=topData.posts[j][i];
								if (  topData.posts[j][i] > max )
								{
									max = topData.posts[j][i];
								}
							}
						}
						if ( i == "ct" || i.substring(0,4)=="excl" )
						{
							sum[i] = temp;
						}
						else
						{
							sum[i] = max;
						}
						if(i.substring(0,4)=="excl")
							sumx[i]= Math.abs(topData['totals'][i.substring(5)]);
						else if ( i !='fn')
						{
							sumx[i]=Math.abs(topData['totals'][i]);
						}
						fields.push(i);
						fields_per.push(i+"_per");
					}
				}
			}
			YUI().use('yui2-dragdrop','autocomplete', 'autocomplete-filters', 'autocomplete-highlighters','yui2-cookie','yui2-paginator', 'yui2-datasource', 'yui2-datatable', 'yui2-logger', function(Y) 
			{
				var YAHOO = Y.YUI2;
				var states =[];
				for( var fn_name in topData.posts)
				{
					if ( topData.posts.hasOwnProperty(fn_name))
					{
						states.push(topData.posts[fn_name]['fn']);
					}
				}
				this.myCustomDoubleFormatter = function(elLiner, oRecord, oColumn, oData) 
				{
					elLiner.innerHTML = addCommas(oData.toFixed(4));
					YAHOO.util.Dom.addClass(elLiner,'right');
				};
				this.myCustomFormatter = function(elLiner, oRecord, oColumn, oData) 
				{
					if(oRecord.getData("fn")) 
					{
						var tmp = "";
				//		if(oData.length >20)
				//			tmp="..."; 
						elLiner.innerHTML = '<a href="'+document.location+'&symbol='+oData+'">'+oData+tmp+'</a>';
					}
				};
				this.myCustomPercentageFormatter = function(elLiner, oRecord, oColumn, oData) 
				{
					var key = oColumn.key.slice(0,oColumn.key.length-4);
					var value = oRecord.getData(key);
					var percentage = ((value / sumx[key])*100).toFixed(1);
					if ( isNaN(percentage) || percentage == null)
						elLiner.innerHTML = "0.0%";
					else 
						elLiner.innerHTML = percentage+"%";
					YAHOO.util.Dom.addClass(elLiner,'right');
				};
				YAHOO.widget.DataTable.Formatter.myCustom = this.myCustomFormatter;
				YAHOO.widget.DataTable.Formatter.percentage = this.myCustomPercentageFormatter;
				YAHOO.widget.DataTable.Formatter.double_number = this.myCustomDoubleFormatter;
				YAHOO.widget.DataTable.prototype.setPaginator = function (oConfigs)
				{
					if(oConfigs !== undefined && oConfigs !== null)
					{
						this._initConfigs(oConfigs); // Update ColumnSet
						this._initTheadEl(); // Update the thead
					}
				};

				YAHOO.widget.DataTable.prototype.setColumns = function (colDef) 
				{
					if(colDef !== undefined && colDef !== null)
					{
						this._initColumnSet(colDef); // Update ColumnSet
						this._initTheadEl(); // Update the thead
					}
				};
				YAHOO.example.Basic = function() 
				{
					var myColumnDefs = 
					[
						{label:"Function Name",key:"fn", sortable:true, resizeable:true,formatter:"myCustom",width:150}
					];
					var currentValue = YAHOO.util.Cookie.getSub("zperfmon_xhprof","rows"); 
					if( currentValue || currentValue == "")
					{
						var true_values = currentValue.split(" ");
						if ( check_subset ( true_values, fields.concat(fields_per)))
						{
							for ( var i in true_values)
							{
								if ( true_values.hasOwnProperty(i))
								{
									var obj = {resizeable:true,sortable:true,sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
									if(true_values[i])
									{
										obj['key'] = true_values[i];
										obj['label'] = fn_header[obj['key']];
										if(true_values[i].substring(true_values[i].length-3) == "per")
										{
											obj['formatter'] = "percentage";
											obj['sortOptions']['field'] = true_values[i].slice(0,true_values[i].length-4);
										}
										else
										{
											obj['formatter'] = "double_number";
										}
										myColumnDefs.push(obj);
										$("#"+true_values[i]).attr('checked','checked');
									}
								}
							}
						}
						else
						{
							for(var i in fields)
							{
								$("#"+fields[i]).attr('checked','checked');
								$("#"+fields[i]+'_per').attr('checked','checked');
								if ( fields.hasOwnProperty(i))
								{
									var obj = {sortable:true, resizeable:true,formatter:"double_number",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
									obj['key'] = fields[i];
									obj['label'] = fn_header[obj['key']];
									myColumnDefs.push(obj);
									var obj_per = {sortable:false, resizeable:true,formatter:"percentage",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
									obj_per['key'] = fields[i]+'_per';
									obj_per['label'] = fn_header[obj_per['key']];
									obj_per['sortOptions']['field'] = obj['key'];
									myColumnDefs.push(obj_per);
								}
							}
						}
					}
					else
					{
						var myColumnDefs =
						[
							{label:"Function Name",key:"fn", sortable:true,label:"Function Name", resizeable:true,formatter:"myCustom",width:150}
						];
						for(var i in fields)
						{
							if ( fields.hasOwnProperty(i)&& ( fields[i].indexOf('excl')>-1 ||  fields[i].indexOf('ct') > -1))

							{
								$("#"+fields[i]).attr('checked','checked');
								$("#"+fields[i]+'_per').attr('checked','checked');
								var obj = {sortable:true, resizeable:true,formatter:"double_number",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
								obj['key'] = fields[i];
								obj['label'] = fn_header[obj['key']];
								myColumnDefs.push(obj);
								var obj_per = {sortable:true, resizeable:true,formatter:"percentage",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
								obj_per['key'] = fields[i]+'_per';
								obj_per['label'] = fn_header[obj_per['key']];
								obj_per['sortOptions']['field'] = obj['key'];
								myColumnDefs.push(obj_per);
				
							}
						}
					}
					var topDataSource = new YAHOO.util.DataSource(topData.posts);
					topDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
					topDataSource.responseSchema = 
					{
						fields: fields_all
					};
					var oConfigs = 
					{
						paginator: new YAHOO.widget.Paginator({
							rowsPerPage: 100,
							totalRecords : 100,
							template : ""
						})//,
					};
					topDataTable = new YAHOO.widget.DataTable("basic",myColumnDefs, topDataSource, oConfigs);
						if (self != top )
						{
							parent.set_page_size($("#basic").children('table').width(),$("body").height());
						}

					$("#loading_image").addClass("hide");
					return {
						oDS: topDataSource,
						oDT: topDataTable
					};
				}();
			});
		}
	}
	client_top.send();

}

	if ( $('#basic').length > 0 )
	{

	$("#searchFunction div").click(function(event){
		event.stopPropagation();
	}); 
	
	$('#callgraph_button').button();
	$('#piechart').button();
	$('#select_column').button();
	$('#display_all').button();
	$('#select_column').click(function(event){
		event.stopPropagation();
	});
	
	
	var myDatatable;
	//var fields;
	var sumx={};
	var client = new XMLHttpRequest();
	var url = document.location.toString();
			//url = url.replace('index.php','index_json.php');
	url = newUrlFunction('index_json.php');

	client.open("GET", url, true);
	client.onreadystatechange = function() 
	{
		if(client.readyState == 4) 
		{
			myData = JSON.parse(client.responseText);
			var sum = [];
			fields = [];
			fields_all = [];
			fields_per = [];
			for (var i in myData.posts[0])
			{
				if ( myData.posts[0].hasOwnProperty(i))
				{
					fields_all.push(i);
					if(!isNaN(myData.posts[0][i]))
					{
						var temp = 0;
						var max = 0;
						for( var j in myData.posts)
						{
							if ( myData.posts.hasOwnProperty(j))
							{
								temp+=myData.posts[j][i];
								if (  myData.posts[j][i] > max )
								{
									max = myData.posts[j][i];
								}
							}
						}
						if(i.substring(0,4)=="excl")
						{
							sumx[i]= Math.abs(myData['totals'][i.substring(5)]);
						}
						else
						{
							sumx[i]=Math.abs(myData['totals'][i]);
						}
						fields.push(i);
						fields_per.push(i+"_per");
						var selectbox_html = '<div><input type="checkbox" name="fields" value="'+i+'" id="'+i+'" ><label for="'+i+'">'+fn_header_wt_newline[i]+'</label></div>';
						$('#checkbox_container').append(selectbox_html);
						var selectbox_html = '<div><input type="checkbox" name="fields" value="'+i+'_per'+'" id="'+i+'_per'+'"><label for="'+i+'_per'+'">'+fn_header_wt_newline[i+'_per']+'</label></div>';
						$('#checkbox_container').append(selectbox_html);
						$('#checkbox_loading').addClass("hide");
					}
				}
			}
			YUI().use('yui2-dragdrop','autocomplete', 'autocomplete-filters', 'autocomplete-highlighters','yui2-cookie','yui2-paginator', 'yui2-datasource', 'yui2-datatable', 'yui2-logger', function(Y) 
			{
				var YAHOO = Y.YUI2;
				var states =[];
				for( var fn_name in myData.posts)
				{
					if ( myData.posts.hasOwnProperty(fn_name))
					{
						states.push(myData.posts[fn_name]['fn']);
					}
				}
				$('#fn-input').autocomplete({
					source : states
				});
				this.myCustomDoubleFormatter = function(elLiner, oRecord, oColumn, oData) 
				{
					elLiner.innerHTML = oData.toFixed(4);
										YAHOO.util.Dom.addClass(elLiner,'right');
				};
				this.myCustomFormatter = function(elLiner, oRecord, oColumn, oData) 
				{
					if(oRecord.getData("fn")) 
					{
						var tmp = "";
//							if(oData.length >20)
//								tmp="..."; 
						elLiner.innerHTML = '<a href="'+document.location+'&symbol='+oData+'">'+oData+tmp+'</a>';
					}
				};
				this.myCustomPercentageFormatter = function(elLiner, oRecord, oColumn, oData) 
				{
					var key = oColumn.key.slice(0,oColumn.key.length-4);
					var value = oRecord.getData(key);
					var percentage = ((value / sumx[key])*100).toFixed(1);
					if ( isNaN(percentage) || percentage == null)
						elLiner.innerHTML = "0.0%";
					else 
						elLiner.innerHTML = percentage+"%";
					YAHOO.util.Dom.addClass(elLiner,'right');
				};
				YAHOO.widget.DataTable.Formatter.myCustom = this.myCustomFormatter;
				YAHOO.widget.DataTable.Formatter.percentage = this.myCustomPercentageFormatter;
				YAHOO.widget.DataTable.Formatter.initialSort = this.myCustomInitialSort;
				YAHOO.widget.DataTable.Formatter.double_number = this.myCustomDoubleFormatter;
				YAHOO.widget.DataTable.prototype.setColumns = function (colDef) 
				{
					if(colDef !== undefined && colDef !== null)
					{
						this._initColumnSet(colDef); // Update ColumnSet
						this._initTheadEl(); // Update the thead
					}
				};
				YAHOO.example.Basic = function() 
				{
					var myColumnDefs = 
					[
						{label:"Function Name",key:"fn", sortable:true, resizeable:true,formatter:"myCustom",width:150}
					];
					var currentValue = YAHOO.util.Cookie.getSub("zperfmon_xhprof","rows"); 
					if( currentValue || currentValue == "")
					{
						var true_values = currentValue.split(" ");
						if ( check_subset ( true_values, fields.concat(fields_per)))
						{
							for ( var i in true_values)
							{
								if ( true_values.hasOwnProperty(i))
								{
									var obj = {resizeable:true,sortable:true,sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
									if(true_values[i])
									{
										obj['key'] = true_values[i];
										obj['label'] = fn_header[obj['key']];
										if(true_values[i].substring(true_values[i].length-3) == "per")
										{
											obj['formatter'] = "percentage";
											obj['sortOptions']['field'] = true_values[i].slice(0,true_values[i].length-4);
										}
										else
										{
											obj['formatter'] = "double_number";
										}
										myColumnDefs.push(obj);
										$("#"+true_values[i]).attr('checked','checked');
									}
								}
							}
						}
						else
						{
							for(var i in fields)
							{
								$("#"+fields[i]).attr('checked','checked');
								$("#"+fields[i]+'_per').attr('checked','checked');
								if ( fields.hasOwnProperty(i))
								{
									var obj = {sortable:true, resizeable:true,formatter:"double_number",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
									obj['key'] = fields[i];
									obj['label'] = fn_header[obj['key']];
									myColumnDefs.push(obj);
									var obj_per = {sortable:false, resizeable:true,formatter:"percentage",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
									obj_per['key'] = fields[i]+'_per';
									obj_per['label'] = fn_header[obj_per['key']];
									obj_per['sortOptions']['field'] = obj['key'];
									myColumnDefs.push(obj_per);
								}
							}
						}
					}
					else
					{
						myColumnDefs =
						[
							{label:"Function Name",key:"fn",width:150, sortable:true,label:"Function Name", resizeable:true,formatter:"myCustom"}
						];
						for(var i in fields)
						{
							if ( fields.hasOwnProperty(i) && ( fields[i].indexOf('excl')>-1 ||  fields[i].indexOf('ct') > -1))
							{
							
								$("#"+fields[i]).attr('checked','checked');
								$("#"+fields[i]+'_per').attr('checked','checked');
								var obj = {sortable:true, resizeable:true,formatter:"double_number",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
								obj['key'] = fields[i];
								obj['label'] = fn_header[obj['key']];
								myColumnDefs.push(obj);
								var obj_per = {sortable:true, resizeable:true,formatter:"percentage",sortOptions: { defaultDir: YAHOO.widget.DataTable.CLASS_DESC }};
								obj_per['key'] = fields[i]+'_per';
								obj_per['label'] = fn_header[obj_per['key']];
								obj_per['sortOptions']['field'] = obj['key'];
								myColumnDefs.push(obj_per);
				
							}
						}
					}
					var myDataSource = new YAHOO.util.DataSource(myData.posts);
					myDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;
					myDataSource.doBeforeCallback = function (oRequest, oFullResponse, oParsedResponse, oCallback) {
						oParsedResponse.results.sort(myCustomInitialSort);
						return oParsedResponse;
					},
					myDataSource.responseSchema = 
					{
						fields: fields_all
					};
					var oConfigs = 
					{
						paginator: new YAHOO.widget.Paginator({
							rowsPerPage:100,
							totalRecords : 100,
							template : ""
						})//,
					};
					myDataTable = new YAHOO.widget.DataTable("basic",myColumnDefs, myDataSource, oConfigs);
					myDataTable.doBeforeSortColumn  = function ( oColumn , sSortDir ) {
						var url = document.location.toString();
						var new_url;
						if(url.indexOf("sort_row")>0)
						{		
							var temp = url.indexOf("sort_row")+9;
							var key = oColumn.key;
							if( key.indexOf("_per")>0)
								key = key.substring(0,key.indexOf("_per"));
							var start = url.substring(0,temp);
							var end ="";
							if(url.substring(temp).indexOf('&') > 0)
								end =url.substring(temp).substring(url.substring(temp).indexOf('&'));
							url = start + key + end ;
						}
						else
						{
							var key = oColumn.key;
							if( key.indexOf("_per")>0)
									key = key.substring(0,key.indexOf("_per"));
							url = url+"&sort_row="+key;
						}
					   if(url.indexOf("sort_type")>0)
						{
							var temp = url.indexOf("sort_type")+10;
							var start = url.substring(0,temp);
							var type = "desc";
							if(sSortDir == "yui-dt-asc")
								type = "asc";
							var end ="";
							if(url.substring(temp).indexOf('&') > 0)
								end =url.substring(temp).substring(url.substring(temp).indexOf('&'));
							url = start +type+ end ;
						}
						else
						{
							var type = "desc";
							if(sSortDir == "yui-dt-asc")
							   type = "asc";
							url = url+"&sort_type="+type;
						}
						$('#link_address_anchor').attr('href',url);	
						return true;	
					}	
					$("#loading_image").addClass("hide");
					$("#top_data").addClass("hide");
					if (self != top )
					{
						parent.set_page_size($("#basic").children('table').width(),$("body").height());
					}
					return {
						oDS: myDataSource,
						oDT: myDataTable
					};
					
				}();
			});
		}
	}
	client.send();
	}
	var cur_params = {} ;
	$.each(location.search.replace('?','').split('&'), function(i, x) 
	{
		var y = x.split('='); cur_params[y[0]] = y[1];
	});
	$('input.function_typeahead')
		.autocomplete('typeahead.php', { extraParams : cur_params })
		.result(function(event, item) {
		cur_params['symbol'] = item;
		location.search = '?' + jQuery.param(cur_params);
	});
});



