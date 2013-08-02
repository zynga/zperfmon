
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
 * 'log_selector_ids' is the radio button group which gives the log-name to select rows with
 * 
 * 'cols' gives description of tables columns - [ ['type1', 'title1'], ['type2', 'title2'], .... ]
 *
 * 'data' contains an array of rows to populate the table-widget with.
 * 
 */

var filteredlogtable = {"init" : function(accordion_id, target_div, cols, data) {
	var tableDrawOption = {
		page: 'enable',
		pageSize: 24,
		pagingButtonsConfiguration: 'auto',
		allowHtml: true,
		pagingSymbols: {prev: 'prev', next: 'next'}
	};

	var color = new Array();
	color["INFO"] = "palegreen";
	color["WARNING"] = "lightblue";
	color["ERR"] = "lightcoral";
	color["CRIT"] = "red";

	function FilteredTable(cols, data, colors) {

		var dataTable = new google.visualization.DataTable();

		$.each(cols, function(index, entry) {
			       dataTable.addColumn(entry[0], entry[1]);
		       });
		
		dataTable.addRows(data);
		var date_format = new google.visualization.DateFormat({pattern: "dd-MMM-yyyy HH:mm"});
		date_format.format(dataTable, 0);
		this.dataTable = dataTable;
		numCols = this.dataTable.getNumberOfColumns();
		numRows = this.dataTable.getNumberOfRows();
		//alert("rows: "+numRows+" Cols: "+numCols);
		for(i = 0; i < numRows; i++)
		{			
			for(j = 0; j < numCols; j++)
			{
				var color = colors[this.dataTable.getValue(i,1)];
				this.dataTable.setProperty(i,j,"style","background-color: " + color + ";");
			}
		}
		this.dataView = new google.visualization.DataView(this.dataTable);
	}

	FilteredTable.prototype.update = function() {
		
		var active_accordion = $(accordion_id).accordion('option', 'active');
		
		var selected_level = $("input[name='log_level_selector']:checked").val();
		var selected_module =  $("input[name='log_module_selector']:checked").val();	
		this.dataView.setRows(0, this.dataTable.getNumberOfRows() - 1);
		if ( (selected_level != "all_levels") && (selected_module != "all_modules") ) {
			rows = this.dataView.getFilteredRows([{column: 1, value: selected_level},
							      {column: 2, value: selected_module}]);
			this.dataView.setRows(rows);
		}
		if ( (selected_level == "all_levels") && (selected_module != "all_modules") ) {
			rows = this.dataView.getFilteredRows([{column: 2, value: selected_module}]);
			this.dataView.setRows(rows);
		}
		if ( (selected_level != "all_levels") && (selected_module == "all_modules") ) {
			rows = this.dataView.getFilteredRows([{column: 1, value: selected_level}]);
			this.dataView.setRows(rows);
		}

		this.table.draw(this.dataView, tableDrawOption);
	}

	var filteredtable = new FilteredTable(cols, data, color);
	filteredtable.table = new google.visualization.Table(document.getElementById(target_div));
	filteredtable.table.draw(filteredtable.dataView, tableDrawOption);

	/* Hook to log level and log module radio group change event */
	$("input[name='log_level_selector']").change( function() {
		filteredtable.update();
	});

	$("input[name='log_module_selector']").change( function() {
		filteredtable.update();
	});

	//$(accordion_id).accordion({ autoHeight: false });
	$(accordion_id).accordion({
		change: function() {
			filteredtable.update();
		}
	});
}};
