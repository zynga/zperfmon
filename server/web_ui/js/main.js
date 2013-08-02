/**
* zPerfmon Main Manager
* uses Charts Manager Module
* @author Saurabh Odhyan (sodhyan@zynga.com)
*/

var zPerfmon = window.zPerfmon || {};

zPerfmon.Main = function(qGame, qArray, tabId, apiUrlArr, unitArr) {

	var selItem = 0, //currently selected item
		oData = {}, //data fetched from web api will be cached here for further requests
		oCharts = new zPerfmon.Charts(qGame, qArray, tabId),
		oTable = new zPerfmon.Charts.DataTable(tabId),
		tabSel = "#" + tabId;
	
	function formatDate(ts) {
		var month = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
		var o = new Date(ts);
		var d = o.getDate();
		var m = o.getMonth();
		var y = o.getFullYear();
		return month[m] + " " + d + ", " + y;
	}

	function getTableCols() {
		return oCharts.getSelectedItems();
	}

	function getTableWidth() {
		var tw = $(tabSel + "-table table").width(); //table width
		var vw = $(window).width(); //viewport width
		return Math.min(tw, vw - 100);
	}

	function initTable(o) {
		var colArr,
			$tableEl;
		
		colArr = getTableCols();
        oTable.init(o, colArr);
		$tableEl = $(tabSel + "-table");
		$tableEl.dialog({
			dialogClass:'ui-dialog-black',
			autoOpen:false,
        	modal:true,
			resizable:true,
			width:getTableWidth()
		});

		$(tabSel + " .show-table-btn").click(function() {
			colArr = getTableCols();
			oTable.refresh(colArr,"", oData[selItem]);
			$tableEl.dialog('open');
			$tableEl.dialog('option', 'width', getTableWidth());
			$tableEl.dialog('option', 'position', 'center');
		});
	}
	
	function showTags(tagData) {
		var ALPHA = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
			$containerEl,
			markup = '',
			ts,
			id,
			i;

		$containerEl = $(tabSel + " .tags-wrapper");
		markup += '<div class="title">Tag/Release</div>';
		markup += '<div class="tags-list">';
		for(i = tagData.length - 1; i >= 0; i--) { //show recent tags on top
			id = "tag-" + tagData[i].start;
			markup += '<a href="#" id=' + id + '>';
			markup += '<div class="tag">';
			markup += '<div class="label left">' + ALPHA[i%26] + '</div>';
			markup += '<div class="left">';
			markup += '<div>' + tagData[i].text + '</div>';
			markup += '<div>' + formatDate(tagData[i].start * 1000) + '</div>';
			markup += '</div>';
			markup += '<div class=clear></div>';
			markup += '</div>';
			markup += '</a>';
		}
		markup += '</div>';
		$containerEl.html(markup);
		$(tabSel + " .tags-list").css({
			height: function() {
				var ret = $(window).height() - 220;
				return ret;
			}
		});

		$containerEl.delegate("a", "click", function(e) {
			var ts = parseInt(e.currentTarget.id.split('-')[1], 10);
			var start = (ts - 86400) * 1000;
			var end = (ts + 86400) * 1000;
			oCharts.setChartLimits(start, end);
			return false;
		});
	}

	function noData() {
		$("#" + tabId + " .chart-block").html("Data not found");
	}

	function sortData(o) {

		function cmp(a, b) { //compare timestamps to sort
			return (a[0] - b[0]);
		}

		o.rows.sort(cmp);
	}

	function makeAPIRequest() {
		$.ajax({
			url: apiUrlArr[selItem],
			context: document.body,
			success: function(o) {
				if(o.cols === undefined || o.rows === undefined) {
					noData();
				} else {
					sortData(o); //data should be sorted according to timestamp
					oData[selItem] = o;
					var unitVal = "";
					if(unitArr !== undefined) {
						unitVal = unitArr[selItem];
					}
 					oCharts.refresh(o, unitVal);
					showTags(o.tags);
					initTable(o);
				}
			}
		});
		$("#" + tabId + " .chart-block").html('<img src="/zperfmon/images/loader.gif" style="margin:40px;"/>'); //show a loading image till data is fetched
	}

	function initMenu() {
		$(tabSel + " .menu a").click(function(e) {
			var cls = $(e.target).attr("class");
			cls = cls.split("-");
			selItem = cls[cls.length - 1];
			
			for(var i = 0; ;i++) {
				var el = $(tabSel + " .menu-item-" + i);
				if(el.length === 0) {
					break;
				}
				if(i == selItem) { //newly selected item
					$(tabSel + " .menu-item-" + i).addClass("current");
					$(tabSel + " .select-items-" + i).addClass("selected");
					$(tabSel + " .select-items-" + i).removeClass("hide");
				} else {
					$(tabSel + " .menu-item-" + i).removeClass("current");
					$(tabSel + " .select-items-" + i).removeClass("selected");
					$(tabSel + " .select-items-" + i).addClass("hide");
				}
			}

			if(oData[selItem] !== undefined) {
				var unitVal = "";
				if(unitArr !== undefined) {
					unitVal = unitArr[selItem];
				}
				oCharts.refresh(oData[selItem],unitVal, false); //(data, animation)
			} else {
				makeAPIRequest();
			}

			return false;
		});
	}

	//Redraw the charts every time a tab is selected to take care of changes in chart interval limits
	function handleChartLimits() {
		$(window).bind('hashchange', function() {
			if(window.location.hash == "#" + tabId) {
				for(var i = 0; ;i++) {
					if(oData[i] === undefined) {
						break;
					}
					//oCharts.refresh(oData[i], false); //(data, animation)
					oCharts.resetChartLimits();
				}
			}
		});
	}

	function initChartDialog() {
		$dialogEl = $(tabSel + "-dialog");
		$dialogEl.dialog({
			dialogClass:'ui-dialog-black',
			autoOpen:false,
			modal:true,
			title:'Go to'
		});
	}

	makeAPIRequest(); //make initial request and generate charts
    initMenu();
    handleChartLimits();
    initChartDialog();
}

