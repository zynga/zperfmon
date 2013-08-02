<?php

/*
 * Pie-chart visualization for xhprof data.
 *
 * Copyright (c) 2010 Zynga
 *
 */

include_once "xhprof_lib.php";
include_once "xhprof_runs.php";
include_once "profilepie.inc.php";

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Developer View - Profile Dissection</title>
	<link type='text/css' href='/zperfmon/css/smoothness/jquery-ui-1.8.15.custom.css' rel='stylesheet'/ type='text/css'>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
    	<script src="/zperfmon/js/jquery-1.5.2.min.js"></script>
	<script src="/zperfmon/js/jquery-ui-1.8.5.custom.min.js"></script>

    <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
    </script>

  </head>

  <body>
	 <div id='back'> Back </div>
	 <div id='both'>
	<span id="pie-chart1" style="float:left;margin-top:50px;"></span>
	<span id="pie-chart2" style="float:left;margin-top:50px;"></span>
	</div>
<script>
/**
* Pie chart hack
*/
var pieData = <?php echo json_encode(get_direct_pie($_GET['file']));
?>;

$(document).ready(function() {
	$('#back').button();
	$('#back').click(function() {
		window.history.back();
	});
var COLORS = ['#4572A7', '#AA4643', '#89A54E', '#80699B', '#3D96AE', '#DB843D', '#458B00', '#8B7355', '#EEB422', '#DC143C'];
function getPieChartsData() {
        var o = pieData;
        var dataArr = [];

        for(fn in o) {
            if(o.hasOwnProperty(fn)) {
                var arr = o[fn];
                var total = 0;
                var data = [];

                for(var i = 0; i < arr.length; i++) {
                    total += parseFloat(arr[i][1]);
                }

                for(var i = 0; i < arr.length; i++) {
                    data.push({
                        name: arr[i][0],
                        y: Math.round((parseFloat(arr[i][1]) / total) * 100),
                        color: COLORS[i]
                    });
                }
            }
            dataArr.push({
                title:fn,
                data:data
            });
        }
        return dataArr;
}

function createPieChart(id, title, data) {
	var dt = [];

	for (var ind = 0; ind < data.length; ind++) {
		dt.push([data[ind]["name"], data[ind]["y"]]);
	}

	var data = google.visualization.arrayToDataTable(dt);

        var options = {title: title,
		chartArea: {left:0, top:20, width:"90%",height:"90%"} };

	
	$("#" + id).width(($(window).width() - 40)/2);
        var chart = new google.visualization.PieChart(document.getElementById(id));
        chart.draw(data, options);;
}

function printPieCharts() {
        var dataArr = getPieChartsData();
        for(var i = 0; i < dataArr.length; i++) {
            createPieChart('pie-chart' + (i+1), dataArr[i].title, dataArr[i].data);
        }
    }

printPieCharts();
});
</script>

  </body>
</html>

