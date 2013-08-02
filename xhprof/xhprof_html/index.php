<?php

/*
 *   M O D I F I E D   as   follows
 *
 * Copyright (c) 2010 Zynga
 *
 * Added utility functions, YUI support and igbinary+bzip support.
 */

//  Copyright (c) 2009 Facebook
//
//  Licensed under the Apache License, Version 2.0 (the "License");
//  you may not use this file except in compliance with the License.
//  You may obtain a copy of the License at
//
//      http://www.apache.org/licenses/LICENSE-2.0
//
//  Unless required by applicable law or agreed to in writing, software
//  distributed under the License is distributed on an "AS IS" BASIS,
//  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//  See the License for the specific language governing permissions and
//  limitations under the License.
//

//
// XHProf: A Hierarchical Profiler for PHP
//
// XHProf has two components:
//
//  * This module is the UI/reporting component, used
//    for viewing results of XHProf runs from a browser.
//
//  * Data collection component: This is implemented
//    as a PHP extension (XHProf).
//
//
//
// @author(s)  Kannan Muthukkaruppan
//             Changhao Jiang
//

// by default assume that xhprof_html & xhprof_lib directories
// are at the same level.

$GLOBALS['XHPROF_LIB_ROOT'] = dirname(__FILE__) . '/../xhprof_lib';

include_once $GLOBALS['XHPROF_LIB_ROOT'].'/display/xhprof.php';
//require_once '/var/www/html/zperfmon_new/include/profilepie.inc.php';
// param name, its type, and default value
$params = array('run'        => array(XHPROF_STRING_PARAM, ''),
                'wts'        => array(XHPROF_STRING_PARAM, ''),
                'symbol'     => array(XHPROF_STRING_PARAM, ''),
                'sort'       => array(XHPROF_STRING_PARAM, 'wt'), // wall time
                'run1'       => array(XHPROF_STRING_PARAM, ''),
                'run2'       => array(XHPROF_STRING_PARAM, ''),
                'source'     => array(XHPROF_STRING_PARAM, 'xhprof'),
                'all'        => array(XHPROF_UINT_PARAM, 0),
		'file'	     => array(XHPROF_STRING_PARAM, ''),
		'file1'	     => array(XHPROF_STRING_PARAM, ''),
		'file2'	     => array(XHPROF_STRING_PARAM, ''),
                );

// pull values of these params, and create named globals for each param
xhprof_param_init($params);

/* reset params to be a array of variable names to values
   by the end of this page, param should only contain values that need
   to be preserved for the next page. unset all unwanted keys in $params.
 */
foreach ($params as $k => $v) {
  $params[$k] = $$k;

  // unset key from params that are using default values. So URLs aren't
  // ridiculously long.
  if ($params[$k] == $v[1]) {
    unset($params[$k]);
  }
}

echo "<html>";

echo "<head><title>XHProf: Hierarchical Profiler Report</title>";
xhprof_include_js_css();



echo "</head>";

echo "<body class='yui-skin-sam' style='margin:0px;font-size:12px'>";
echo "<div id='loading_image' style='position:absolute;top:500px;left:300px;z-index:1001'> <img src='../images/spinner.gif'></div>";
//echo "<iframe style='position:absolute;left:375px' id='frame_pie1' width='33%' height='800px' src='pie1.php?run=1310581800.all&sort=excl_cpu&file=%2Fvar%2Fwww%2Fhtml%2Fzperfmon_new%2Fblobs%2Fcity%2F_blobdir_1309318200%2F1309318200.all.xhprof' width=10% height=10%></iframe>";
//echo "<iframe style='position:absolute;left:1000px' id='frame_pie2' width='33%' height='375px' src='pie2.php?run=1310581800.all&sort=excl_cpu&file=%2Fvar%2Fwww%2Fhtml%2Fzperfmon_new%2Fblobs%2Fcity%2F_blobdir_1309318200%2F1309318200.all.xhprof' width=10% height=10%></iframe>";
//echo "<div id='basic'></div>";
$vbar  = ' class="vbar"';
$vwbar = ' class="vwbar"';
$vwlbar = ' class="vwlbar"';
$vbbar = ' class="vbbar"';
$vrbar = ' class="vrbar"';
$vgbar = ' class="vgbar"';

$xhprof_runs_impl = new XHProfRuns_Default(dirname($file));
displayXHProfReport($xhprof_runs_impl, $params, $source, $run, $wts,
                    $symbol, $sort, $run1, $run2, $file, $file1, $file2);
?>

<?php echo "</body>";
echo "</html>";
