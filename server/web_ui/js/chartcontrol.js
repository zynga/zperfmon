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

var chartcontrol = {"init" : function(prefix, dataurls){

	var accordion_id = "#"+prefix+"_accordion";
	var chart_id = "#"+prefix+"_chart";
	var table_id = "#"+prefix+"_table";

	var tableDrawOption = {
		page: 'enable',
		pageSize: 48,
		pagingButtonsConfiguration: 'auto',
		pagingSymbols: {prev: 'prev', next: 'next'}
	};
	function ChartController(urls) {
		this.chart = null;
		this.table = null;
		this.urls = urls;
		this.data = [];
		this.model = null;
	}


	ChartController.prototype.update = function() {
		
		if(!this.chart) return;
		
		var idx = $(accordion_id).accordion("option", "active");

		if(!this.data[idx]) return;

		var data = this.data[idx].data;
		var cols = this.data[idx].columns;

		var activecols = [0].concat($.map( $('.select_columns'+idx, $(accordion_id)), function(c) { 
			if($(c).is(':checked'))
			{
				var id = $(c).attr('id');
				return [cols[id]];
			}
			return null; 
		}));

		if(cols["tag"]) activecols.push(cols["tag"]);
		
		var dt = new google.visualization.DataView(data);

		dt.setColumns(activecols);

		var zoom = this.chart.getVisibleChartRange();
		var zoom_st = zoom ? zoom.start : null;
		var zoom_end = zoom ? zoom.end : null;

	       	this.chart.draw(dt,  {displayAnnotations: true,
                       scaleType: 'allmaximize', scaleColumns: [0, 3], 
		       zoomEndTime: zoom_end, zoomStartTime: zoom_st, 
                       annotationsWidth: 10, displayZoomButtons: false, 
                       thickness: 1, fill: 3});

//	       	this.chart.draw(dt,  {displayAnnotations: true, legendPosition: 'newRow', 
//		       zoomEndTime: zoom_end, zoomStartTime: zoom_st, 
//                       annotationsWidth: 10, displayZoomButtons: false, 
//                       thickness: 1, fill: 3});
		
  		this.table.draw(dt, tableDrawOption);
		this.model = dt;
	}

	ChartController.prototype.zoomTable = function(start, end) {
		if(!this.model) return;

		var z = new google.visualization.DataView(this.model);
		var viewport = z.getFilteredRows([{'column': 0, 'minValue': start, 'maxValue': end}]);
		z.setRows(viewport);
  		this.table.draw(z, tableDrawOption);
	}

	function TagMaker(tags) {
		this.tags = tags;
		this.tags.sort(function(t1, t2) {
			return (t1["start"] - t2["start"]);
		});
		this.getTag = function(ts) {
			var tag = null;
			for(var i = 0; i < this.tags.length; i++) {
				if(this.tags[i]["start"] > ts) break; /* in the future */
				tag = this.tags[i]["text"];
			}
			return tag;
		};
	}

	function makeDataTable(idx, data) {
		var tags = data["tags"] ? new TagMaker(data["tags"]) : null;

		var cols = $.map( $('.select_columns'+idx, $(accordion_id)), function(c) { 
			var id = $(c).attr('id');
			return [[id, $("label[for='"+id+"']").text(), data.cols.indexOf(id)]];
		});

		var dt = new google.visualization.DataTable();
	        dt.addColumn('datetime', 'Date', 'timestamp');
		
		if(tags) dt.addColumn('string', 'Tag/Release', 'tag');

		$.each(cols, function(i, v) {
			dt.addColumn('number', v[1], v[0]);
		});

		var oldtag = null;
		var timestamp_index = data.cols.indexOf("timestamp");

		var rows = $.map(data.rows, function(v) { 
			var row = [new Date(v[timestamp_index] * 1000)];

			if(tags)
			{
				var tag = tags.getTag(v[timestamp_index]);
				if(tag != oldtag) 
				{
					oldtag = tag;
					row.push(tag);
				}
				else row.push(null);
			}

			$.each(cols, function(i, c) {
				var index = c[2];
				row.push(v[index]);
			});
			// I hate how map does a concat()
			return [row];
		});

		var columnids = {"timestamp" : 0};

		for(var i = 0; i < dt.getNumberOfColumns(); i++) {
			var id = dt.getColumnId(i);
			columnids[id] = i;
		}
		
		dt.addRows(rows);

		var date_format = new google.visualization.DateFormat({pattern: "dd-MMM-yyyy HH:mm"});
		date_format.format(dt, 0);

		return {"columns" : columnids, "data": dt};
	}


	ChartController.prototype.fetch = function(idx) {
		var EXT = this;
		$.getJSON(this.urls[idx], function(data) { 
			EXT.data[idx] = makeDataTable(idx, data);
			EXT.update(); 
		});
	}

	var chartcontroller = new ChartController(dataurls);
	$(accordion_id).accordion({
		change: function(e, ui) {
			chartcontroller.update();
		}}
	);				

	$('input[type="checkbox"]', $(accordion_id)).change(function() { 
		chartcontroller.update();
	});

	
	$.each(dataurls, function(i, v) { chartcontroller.fetch(i); });

	var min_width = Math.max($(chart_id).parent().innerWidth(), 400);
	var min_height = Math.max($(chart_id).parent().innerHeight(), 300);

	$(chart_id).css('width', min_width);
	$(chart_id).css('height', min_height);


	var min_width = Math.max($(table_id).parent().innerWidth(), 400);
	var min_height = Math.max($(table_id).parent().innerHeight(), 300);

	$(table_id).css('width', min_width);
	$(table_id).css('height', min_height);


	chartcontroller.chart = new google.visualization.AnnotatedTimeLine(document.getElementById(chart_id.replace('#','')));
	chartcontroller.table = new google.visualization.Table(document.getElementById(table_id.replace('#','')));

	google.visualization.events.addListener(chartcontroller.chart, 'rangechange', function(ev) {
		chartcontroller.zoomTable(ev.start, ev.end);
	});  


	//chartcontroller.update(); 

}};
