
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

var filteredtable = {"init" : function(target_div, page_selector_id,
				       cols, data, slow_page_dir) {

	var tableDrawOption = {
		page: 'enable',
		pageSize: 10,
		pagingButtonsConfiguration: 'auto',
		pagingSymbols: {prev: 'prev', next: 'next'}
	};

	function FilteredTable(page_selector_id, cols, data, slow_page_dir) {
		var dataTable = new google.visualization.DataTable();

		this.page_selector = "input[name='"+page_selector_id+"']";

		$.each(cols, function(index, entry) {
			       dataTable.addColumn(entry[0], entry[1]);
		       });
		
		dataTable.addRows(data);
		var date_format = new google.visualization.DateFormat({pattern: "dd-MMM-yyyy HH:mm"});
		date_format.format(dataTable, 0);
		this.dataTable = dataTable;
		this.dataView = new google.visualization.DataView(this.dataTable);
		this.slow_page_dir = slow_page_dir
	}

	FilteredTable.prototype.update = function() {

		var selected_page = $(this.page_selector + ":checked").val();

		this.dataView.setRows(0, this.dataTable.getNumberOfRows() - 1);
		
		if (selected_page != "all_pages") {
			rows = this.dataView.getFilteredRows([{column: 0, value: selected_page}]);
			this.dataView.setRows(rows);
		}

		this.table.draw(this.dataView);
	}

	var filteredtable = new FilteredTable(page_selector_id, cols, data, slow_page_dir);

	filteredtable.table = new google.visualization.Table(document.getElementById(target_div));
	filteredtable.table.draw(filteredtable.dataView);

	/* Hook to page name radio group change event */
	$(filteredtable.page_selector).change( function() {
		filteredtable.update();
		if ($("#table_or_profile").val() == "Show Table") {
			showProfile();
		}
	});

	function showProfile()
	{
		$(this).val("Show Table");
		$("#slow_page_table").hide();

		if (filteredtable.table.getSelection().length != 0) {
			sel = filteredtable.table.getSelection();
			srow = sel[0];
			srow = sel[0].row;
			selected_row = filteredtable.table.getSelection()[0].row;
		} else {
			selected_row = 0;
		}
		
		file_name = filteredtable.dataView.getValue(selected_row, 13) + "." + 
			filteredtable.dataView.getValue(selected_row, 0);

		$("#slow_page_iframe").attr('src',
			"/zperfmon/xhprof_html/index.php?file=" +
				filteredtable.slow_page_dir + "/" + file_name + ".xhprof");
		$("#slow_page_profile").show();
		doIframe();
	}
	
	function showTable()
	{
		$(this).val("Show Profile");
		$("#slow_page_profile").hide();
		$("#slow_page_table").show();
	}


	$("#table_or_profile").button().toggle( showProfile, showTable);
}};
