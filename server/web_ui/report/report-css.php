<?php

#
# Copyright 2013 Zynga Inc.
# 
# Licensed under the Apache License, Version 2.0 (the "License");
#    you may not use this file except in compliance with the License.
#    You may obtain a copy of the License at
# 
#    http://www.apache.org/licenses/LICENSE-2.0
# 
#    Unless required by applicable law or agreed to in writing, software
#      distributed under the License is distributed on an "AS IS" BASIS,
#      WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#    See the License for the specific language governing permissions and
#    limitations under the License.
# 


function get_css() {
$css = "h1,h2,h3,h4,span,ul,ol {
    font-family:arial;
}
span {
    font-size:14px;
}
h1 {
    font-size:24px;
    margin-bottom:0;
}
h4 {
    margin:0 0 20px 0;
    padding:0;
    font-style:italic;
    color:#777;
}
h2 {
    font-size:15px;
    margin:8px 0;
	text-decoration:underline;
}
h3 {
    font-size:15px;
    color:#777;
    margin:4px 0;
}
table {
    border:0;
    font-size:12px;
    font-family:arial;
    margin-bottom:20px;
}
table tr td:first-child {
        width:200px;
}
td {
	border-top:0;
	border-left:0;
	border-right:0;
	border-bottom:1px solid;
	padding:4px 12px;
	margin:0;
    vertical-align:top;
	border-collapse: collapse;
}
td.hd {
    background:#7CCD7C;
    border-top:1px solid;
}
table.label,
table.label td {
	border:0;
	padding:0;
	margin:0;
}
.wrapper {
	border:none;
	margin:0;
	padding:0;
}
.right {
	text-align:right !important;
}
.noborder {
	border:0;
}
.nomargin {
	margin:0;
}
.nopadding {
	padding:0;
}
table.minmax,
table.minmax td {
	margin:0;
	padding:0;
	border:0;
}
hr {
	margin:20px 0;
	color:#777;
	background-color:#777;
	height:5px;
}
hr.subdiv {
	height:1px;
}
h1.detail {
	text-align:center;
	text-decoration:underline;
}
.footer .note {
	font-size:13px;
	font-color:#777;
	font-style:italic;
}
sup {
	font-style:italic;
}";

return $css;
}

?>

