
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

/**
* zPerfmon Charts and Table Manager
* @author Saurabh Odhyan (sodhyan@zynga.com)
* @modified Binu Jose Philip (bphilip@zynga.com)
*/

var zPerfmon = window.zPerfmon || {};

/**
* @class Charts
* uses Google Charts Annotated Time Line
*/
zPerfmon.Charts = function(qGame, qArray, tabId) {

	var oData,
		unit = "",
		chartArr = [],
		animCharts = true,
		tabSel = "#" + tabId,
		$dialogEl = $(tabSel + "-dialog"),
		COLORS = ['#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#458B00', '#8B7355', '#458B74', '#CD950C'],
		ALPHA = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
		splitMode = 0,
		rangeSelBtn = [{
			type: 'day',
			count: 1,
			text: '1d'
		}, {
			type: 'week',
			count: 1,
			text: '1w'
		}, {
			type: 'month',
			count: 1,
			text: '1m'
		}, {
			type: 'month',
			count: 2,
			text: '2m'
		}, {
			type: 'all',
			text: 'All'
		}];

	
	function customTooltipFormatter (useHeader, point) {
		var series = point.series;
		var toolTipUnit = series.yAxis.axisTitle && series.yAxis.axisTitle.textStr;
		if(toolTipUnit === undefined || toolTipUnit == "DAU") {
			toolTipUnit = "";
		}
		return ['<span style="color:'+ series.color +'">', (point.name || series.name), '</span>: ',
		(!useHeader ? ('<b>x = '+ (point.name || point.x) + ',</b> ') : ''),
		'<b>', (!useHeader ? 'y = ' : '' ), (point.y.toString().indexOf('.') > 0  ? (point.y.toFixed(2)) : point.y), '</b>','<b>'+toolTipUnit+'</b>'].join('');
	}

	function getURL() {
		var url = "index.php";
		if(qGame != null) {
			url += "?game=" + qGame;
		}
		if(qArray != null) {
			url += "&array=" + qArray;
		}
		return url;
		//return window.location.href.replace(window.location.hash,"");
	}

	function getLinksMarkup(ts) {
		var markup = '',
			url = getURL();

		url += "&timestamp=" + ts;

		if(tabId !== "tab-bd") {
			markup += '<div><a href="' + url + '#tab-bd" id="dialog-bd-link">Business Dashboard</a></div>';
		}
		if(tabId !== "tab-cto") {
			markup += '<div><a href="' + url + '#tab-cto" id="dialog-cto-link">CTO Dashboard</a></div>';
		}
		if(tabId !== "tab-eu") {
			markup += '<div><a href="' + url + '#tab-eu" id="dialog-eu-link">EU Dashboard</a></div>';
		}
		
		markup += '<div><a href="' + url + '#tab-profile" id="dialog-profile-link">Profile Dashboard</a></div>';
		return markup;
	}

	function getDialogContent(ts, dateStr) {
		var markup = '';	
		markup += '<div style="margin-top:16px" id=' + tabId + '-dialog-links>';
		markup += getLinksMarkup(ts/1000);
		markup += '</div>';
		markup += '<div style="margin-top:16px">';
        markup += dateStr;
        markup += '</div>';
		return markup;
	}

	function getColor(k) {
		var n = COLORS.length;
		return COLORS[k%n];
	}

	function getDataSeries(item) {
		var dataRows = oData.rows,
			dataCols = oData.cols,
			itemIdx = 1,
			data = [],
			ret,
			i,
			x,
			y;
		
		for(i = 0; i < dataCols.length; i++) {
			if(dataCols[i] == item.key) {
				itemIdx = i;
				break;
			}
		}
		
		for(i = 0; i <dataRows.length; i++) {
			x = dataRows[i][0] * 1000; //converting to millisec
			y = dataRows[i][itemIdx];
			if(y > 0) { //skip data with errors during collection
				data.push([x, y]);
			}
		}
		
		ret = {
			data: data,
			color: getColor(itemIdx - 1)
		};
		return ret;
	}

	function getPrevData(o, diff) {
		var i,
			tdayTS,
			newts,
			ret = [];
		
		tdayTS = o[o.length - 1][0];
		
		for(i = 0; i < o.length; i++) {
			newts = o[i][0] + diff;
			if(newts > tdayTS) {
				break;
			}
			ret.push([newts, o[i][1]]);
		}

		return ret;
	}

	function getTagData() {
		var tags = oData.tags,
			ret= [],
			i;
		
		for(i = 0; i < tags.length; i++) {
			ret.push({
				x: tags[i].start * 1000, //seconds to milliseconds
				title: ALPHA[i % 26], //after Z, start with A again
				text: tags[i].text
			});
		}
		return ret;
	}

	function buildTagHash() {
	    var tag_hash = [];
	    var ts, name;

	    for(var i = 0; i < oData.tags.length; i++) {
		    ts = Math.round((oData.tags[i]["start"] /1800)* 1800);
		    tag_hash[ts] = oData.tags[i]["text"];
	    }

	    return tag_hash;
	}

	function createLineChart(el, data, options) {
		var g_data = new google.visualization.DataTable();

		g_data.addColumn('datetime', 'Timestamp', "ts");
		for(var j = 1; j < oData.cols.length; j++) {
			g_data.addColumn("number", oData.cols[j], oData.cols[j]);
		}
		g_data.addColumn("string", "Tag", "tag");

		var tag_hash = buildTagHash();
		var ev_ts, row;

		for (var j = 0; j < oData.rows.length; j++) {

			row = oData.rows[j];
			ev_ts = row[0];
			row[0] = new Date(ev_ts * 1000);

			if (ev_ts in tag_hash) {
				row.push(tag_hash[ev_ts]);
			} else {
				row.push(null);
			}

			g_data.addRow(row);
		}

		el.style.height = Math.min($(window).height(), 420);
		el.style.width = $(".chart-wrapper left").width() - 200;
		var g_chart = new google.visualization.AnnotatedTimeLine(el);
		g_chart.draw(g_data, {
					displayZoomButtons: false,
					displayAnnotations: true,
				     scaleColumns: [1, 2],
				     scaleType: "allfixed"}
				);
		return g_chart;
	}

	function setChartLimits(start, end) {
		var extremes,
			chart,
			i;
		
		for(i = 0; i < chartArr.length; i++) {
			chart = chartArr[i];
			extremes = chart.xAxis[0].getExtremes();
			start = Math.max(start, extremes.dataMin);
			end = Math.min(end, extremes.dataMax);
			chart.xAxis[0].setExtremes(start, end);
		}
	}

	function getSelItems() {
		var itemEl = $(tabSel + " .select-chart-items .selected input:checkbox:checked"),
			items = [],
			key,
			label,
			i;
		
		for(i = 0; i < itemEl.length; i++) {
			key = $(itemEl[i]).attr("id");
			label = ($(itemEl[i]).siblings()[0]).innerHTML;
			items.push({
				key: key,
				label: label
			});
		}
		return items;
	}

	function displayCombinedCharts() {
		var items = getSelItems(),
			oSeries,
			data = [],
			colors = [],
			tagData,
			options,
			chartEl,
			i,
			o;
	  	
		chartArr = []; //clear the existing charts
		options = {};

		for(i = 0; i < items.length; i++) {
			o = getDataSeries(items[i]);
			
			if(o.data.length == 0) continue; //do not show on chart if data doesn't exist #SEG-4948	
			o.data[o.data.length] = o.data[o.data.length-1]; // Hacky fix to show last data
			oSeries = {
				name:items[i].label,
				//id:'item'+i,
				data:o.data,
				marker: {
					radius:6
				}
			};

			if(items[i].label == "DAU") {
				oSeries.yAxis = 1;
				oSeries.dashStyle = 'shortdot';
				options.dau = items[i].label;
			}

			data.push(oSeries);
			colors.push(o.color);
		}

		tagData = getTagData();
		data.push({
			name:'Release Tags',
			type:'flags',
			data:tagData,
			//onSeries:'item'+i,
			color:'#000',
			shape:'circlepin',
			width:14,
			cursor:'pointer'
		});
	   	
		options.colors = colors;

		chartEl = $(tabSel + " .combined-charts")[0];
		chartArr.push(createLineChart(chartEl, data, options));
	}

	function getSplitViewMode() {
		var inpName = tabId + "-split-view-mode";
		return $(tabSel + ' input:radio[name=' + inpName + ']:checked').val();
	}

	function displayOverallChart(chartEl, item) {
		var data,
			tagData,
			options,
			o;
	
		o = getDataSeries(item);
		tagData = getTagData();
		data = [{
			name: item.label,
			id: item.key,
			data: o.data,
			marker: {
				radius: 6
			}
		}, {
			name:'Release Tags',
			type:'flags',
			data:tagData,
			onSeries:item.key,
			color:'#000',
			shape:'circlepin',
			width:14,
			cursor:'pointer'
		}]; 
		
		options = {
			colors: [o.color],
			title: item.label
		};

		chartArr.push(createLineChart(chartEl, data, options));
	}

	function displayDoDWoWChart(chartEl, item) {
		var o,
			data = [],
			tday = [], //today
			yday = [], //yesterday
			wday = [], //last week
			options,
			i;

		o = getDataSeries(item);
		
		tday = o.data; //today
		yday = getPrevData(tday, 86400 * 1000); //yesterday	
		wday = getPrevData(tday, 8 * 86400 * 1000); //last week

		//var tagData = getTagData();
		
		data.push({
			name:"Today",
			//id:'item'+i,
			data:tday,
			marker: {
				radius:6
			}
		},{
			name:"Yesterday",
			data:yday
		},{
			name:"Last week",
			data:wday
		/*
		},{
			type:'flags',
			data:tagData,
			//onSeries:'item'+i,
			color:'#000',
			shape:'circlepin',
			width:14,
			cursor:'pointer'
		*/
		});
		
		options = {
			title:item.label
		};
		
		chartArr.push(createLineChart(chartEl, data, options));
	}

	function displaySplitCharts() {
		var items,
			markup,
			mode,
			chartEl,
			i;

		chartArr = []; //clear the existing charts

		items = getSelItems();
		markup = getSplitChartsMarkup(items);		 
		$(tabSel + " .split-charts").html(markup);
	   	
		for(i = 0; i < items.length; i++) {
			chartEl = $(tabSel + " .split-chart-" + i)[0];
			mode = getSplitViewMode();
			if(mode == "dodwow") {
				displayDoDWoWChart(chartEl, items[i]);
			} else {
				displayOverallChart(chartEl, items[i]);
			}
		}
	}

	function getSplitChartsMarkup(items) {
		var ret = "",
			cls,
			i;

		for(i = 0; i < items.length; i++) {
			cls = "split-chart-" + i;
			ret += "<div class='chart-title'>" + items[i].label + "</div>";
			ret += "<div class='" + cls + " chart-block'></div>";
		}
		return ret;
	}

	function initSplitCharts() {
		$(tabSel + " .split-chart-btn").click(function() {
			if($(this).html() == "Split Charts") {
				$(tabSel + " .combined-charts").addClass("hide");
				$(tabSel + " .split-charts").removeClass("hide");
				$(tabSel + " .split-chart-controller").removeClass("hide");
				$(tabSel + " .split-chart-btn").html("Combine Charts");
				splitMode = 1;
				displaySplitCharts();
			} else {
				$(tabSel + " .combined-charts").removeClass("hide");
				$(tabSel + " .split-charts").addClass("hide");
				$(tabSel + " .split-chart-controller").addClass("hide");
				$(tabSel + " .split-chart-btn").html("Split Charts");
				splitMode = 0;
				displayCombinedCharts();
			}
		});

		var inpName = tabId + "-split-view-mode";
		$(tabSel + ' input:radio[name=' + inpName + ']').click(function() {
			displaySplitCharts();
		});

	}

	function initItemSelection() {
		$(tabSel + " .select-chart-items input:checkbox").click(function() {
			if(splitMode) {
				displaySplitCharts();
			} else {
				displayCombinedCharts();
			}
		});
	}

	//Add jqueryui datepicker to the charts range selector input
	function initDatePicker() {
		var $input = $('input.charts-range-selector');
		$input.datepicker();
	}

	initSplitCharts();
	initItemSelection();
	initDatePicker();

	/**
	* Public methods
	*/
	return {
		refresh: function(o, unitCharts, anim) {
			unit = unitCharts;
			oData = o;
			if(anim === undefined) {
				animCharts = true;
			} else {
				animCharts = anim;
			}
			if(splitMode) {
				displaySplitCharts();
			} else {
				displayCombinedCharts();
			}
			initDatePicker();
		},

		setChartLimits: setChartLimits,

		resetChartLimits: function() {
			setChartLimits(zPerfmon.chartLimits.min, zPerfmon.chartLimits.max);
		},

		getSelectedItems: getSelItems
	};
};


/**
* @class DataTable
* uses YUI Datatable
*/
zPerfmon.Charts.DataTable = function(tabId) {
	var oDataTable;

	function getTagMap(o) {
		var ret = {},
			i;

		for(i = 0; i < o.length; i++) {
			ret[o[i].start] = o[i].text;
		}

		return ret;
	}

	function getFormattedData(oData) {
		var ret = [],
			o,
			i,
			j;

		for(i = 0; i < oData.rows.length; i++) {
			o = {};
			for(j = 0; j < oData.cols.length; j++) {
				o[oData.cols[j]] = oData.rows[i][j];
			}
			ret.push(o);
		}

		return ret;
	}


	function formatDate(el, oRecord, oColumn, data) {
		var dt = new Date(data * 1000);
		el.innerHTML = dt.toGMTString();
	}

	function formatNumber(el, oRecord, oColumn, data) {
		var s;
		
		if(tabId == "tab-bd") {
			s = YAHOO.util.Number.format(data, {thousandsSeparator:','});
		} else {
			s = YAHOO.util.Number.format(data, {decimalPlaces:2});
		}

		el.innerHTML = s;
	}

	function getColDefs(colArr) {
		var oColDefs,
			i;

		oColDefs = [
            {key:"timestamp", label:"Date", sortable:true, formatter:formatDate, width:100}
        ];
        
        for(i = 0; i < colArr.length; i++) {
			oColDefs[i + 1] = colArr[i];
			oColDefs[i + 1].sortable = true;
			oColDefs[i + 1].formatter = formatNumber;
        }

		return oColDefs;
	}

	function createTable(oData, oColDefs) {
		var oDataSource,
			oConfigs,
			i;

		oDataSource = new YAHOO.util.DataSource(getFormattedData(oData));
		oDataSource.responseType = YAHOO.util.DataSource.TYPE_JSARRAY;

		oConfigs = {
			paginator: new YAHOO.widget.Paginator({
				rowsPerPage: 15,
				template: '{FirstPageLink} {PreviousPageLink} {NextPageLink} {LastPageLink}'
			}),
			sortedBy: {
				key: "timestamp",
				dir: YAHOO.widget.DataTable.CLASS_ASC
			}
		};
		
		oDataTable = new YAHOO.widget.DataTable(tabId + "-table", oColDefs, oDataSource, oConfigs);
	}

	/**
    * Add a convenience method to the DataTable prototype to update the columns
    * @method setColumns
    * @param {Object} colDef Column definition of datatable
    */
    YAHOO.widget.DataTable.prototype.setColumns = function (colDef) {
        if(colDef !== undefined && colDef !== null) {
            this._initColumnSet(colDef); // Update ColumnSet
            this._initTheadEl(); // Update the thead
        }
    };

	return {
		init: function(oData, colArr) {
			var oColDefs = getColDefs(colArr);
			createTable(oData, oColDefs);
			oDataTable.sortColumn(oDataTable._oColumnSet.keys[0]); //initially sort the first column in descending order
		},

		refresh: function(colArr, unitCharts, oData) {
			unit = unitCharts;
			var oColDefs = getColDefs(colArr);
			oDataTable.setColumns(oColDefs); //reset the datatable columns
			
			oDataTable.getDataSource().liveData = getFormattedData(oData); //update the datasource
			oDataTable.getDataSource().sendRequest(null, {
				success: oDataTable.onDataReturnInitializeTable,
				argument: oDataTable.getState()
			}, oDataTable); //update the datatable with new datasource
			
			oDataTable.sortColumn(oDataTable._oColumnSet.keys[0]); //sort the first column in descending order
			oDataTable.render(); //redraw the datatable
		}
	};
}
