
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

/*
 * 'target_div' is the div where the table should be placed
 *
 * 'page_selector' is the radio button group which gives the page-name to select rows with
 * 
 * 'cols' gives description of tables columns - [ ['type1', 'title1'], ['type2', 'title2'], .... ]
 *
 * 'data' contains an array of rows to populate the table-widget with.
 * 
 */

var top5table = {"init" : function(target_div, page_selector_id, cols, data, functions) {

	var tableDrawOption = {
		allowHtml : true,
		page: 'enable',
		pageSize: 24,
		pagingButtonsConfiguration: 'auto',
		pagingSymbols: {prev: 'prev', next: 'next'}
	};

	function Top5table(page_selector_id, cols, data, functions) {
		var dataTable = new google.visualization.DataTable();

		this.page_selector = "input[name='"+page_selector_id+"']";

		$.each(cols, function(index, entry) {
			       dataTable.addColumn(entry[0], entry[1]);
		       });

		//
		// Massage unix timestamp column into a javascript
		// date which is in UTC
		//
		// We love hard-coding, repeat ...
		var timestamp_index = 0; // cols.indexOf("timestamp");

		for (i = 0; i < data.length; i++) {
			data[i][timestamp_index] = new Date(data[i][timestamp_index] * 1000);
		}
		

		dataTable.addRows(data);
		dataTable.sort([{column: 0, desc: true}]);
		
		var Palette = function() { 
			this.colours = {};
			var rand = function(n) {
				return Math.floor(Math.random()*(n+1));
			}
			this.next = function() {
				var r = rand(128) + 127;
				var g = rand(128) + 127;
				var b = rand(64) + 127;
				var c = "#" + (r + (g + b * 256) * 256).toString(16);
				if(this.colours[c]) return this.next();
				this.colours[c] = true;
				return c;
			}
		};

		var pastels = new Palette();

		var formatter = new google.visualization.ColorFormat();
		$.each(functions, function(i, fn) {
			// so we look for fn to something else, which has to be
			// > fn, easy fix is to append a Z
			formatter.addRange(fn, fn+"z", 'black', pastels.next());
		});
		for(i = 2; i < 7; i++) // yup, we do love hard-coding 
		{
			formatter.format(dataTable, i);
		}
		var date_format = new google.visualization.DateFormat({pattern: "dd-MMM-yyyy HH:mm"});
		date_format.format(dataTable, 0);

		this.dataTable = dataTable;
		this.dataView = new google.visualization.DataView(this.dataTable);
	}


	//
	// The page selected by the user is in the radio control group
	// identified by page_selector. Filter the table view's underlying
	// table to show only rows which belong to that page. We also hide
	// the column which holds the page row.
	//
	Top5table.prototype.update = function() {

		var selected_page = $(this.page_selector + ":checked").val();

		this.dataView.setRows(0, this.dataTable.getNumberOfRows() - 1);
		this.dataView.setColumns([0,1,2,3,4,5,6]); // We adore hard-coding

		var rows = this.dataView.getFilteredRows([{column: 1, value: selected_page}]);
		this.dataView.setColumns([0,2,3,4,5,6]); // yes, we do

		this.dataView.setRows(rows);

		this.table.draw(this.dataView, tableDrawOption);
	}

	var top5table = new Top5table(page_selector_id, cols, data, functions);

	top5table.table = new google.visualization.Table(document.getElementById(target_div));
	top5table.table.draw(top5table.dataView, tableDrawOption);

	// Filter out all rows except 'all'
	top5table.update();

	/* Hook to page name radio group change event */
	$(top5table.page_selector).change( function() {
		top5table.update();
	});
}};
