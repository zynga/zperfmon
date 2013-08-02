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


/**
* zPerfmon Email Report Table Markup Generator
* @author Saurabh Odhyan (sodhyan@zynga.com)
*/

class ReportTableMarkup {

	private static function get_table_class($class) {
		if($class == "row1") {
			return "row2";
		} else {
			return "row1";
		}
	}

	/**
	* Get markup for the table header
	* @method get_head_markup
	* @private
	*/
	private static function get_head_markup($tblStruct, $tblData) {
		$markup = "<tr>";
		for($i = 0; $i < sizeof($tblStruct); $i++) {
			$label = $tblStruct[$i]["label"];
			$markup .= "<td class=hd>$label</td>";
		}
		$markup .= "</tr>";
		return $markup;
	}


	/**
	* Get markup for the table body
	* @method get_body_markup
	* @private
	*/
	private static function get_body_markup($tblStruct, $tblData) {
		$markup = "";
		$class = "row2";
		for($r = 0; $r < sizeof($tblData); $r++) {
			$row = $tblData[$r];
			$class = self::get_table_class($class);
            $markup .= "<tr class=$class>";
			for($c = 0; $c < sizeof($row); $c++) {
				$col = $row[$c];

				if(isset($tblStruct[$c]["minmax"])) {
					$minmax = $tblStruct[$c]["minmax"];
				} else {
					$minmax = 0;
				}

				if($minmax != 1) {
					if(isset($tblStruct[$c]["align"])) {
						$align = $tblStruct[$c]["align"];
					} else {
						$align = "left";
					}
					$markup .= "<td class=$align>";

					if(isset($tblStruct[$c]["bold"]) && $tblStruct[$c]["bold"] == 1) {
						$markup .= "<b>$col</b>";
					} else {
						$markup .= $col;
					}

					$markup .= "</td>";
				} else {
					$avg = $col["avg"];
					$min = $col["min"];
					$max = $col["max"];
					$markup .= "<td>";
                    $markup .= "<table class='minmax'>";
                    $markup .= "<tr>";
                    $markup .= "<td><b>Avg: $avg</b></td>";
                    $markup .= "</tr>";
                    $markup .= "<tr>";
                    $markup .= "<td>Min: $min | Max: $max</td>";
                    $markup .= "</tr>";
                    $markup .= "</table>";
                    $markup .= "</td>";
				}
			}
			$markup .= "</tr>";
		}
		return $markup;
	}


	/**
	* Get markup for the table
	* @method get_table_markup
	* @public
	*
	* @param $tblStruct Structure of the table being constructed
    *
    * Format:
    *   {
    *       [0] => {
    *           "label" => "page",
    *           "align" => "left/right",
    *           "bold" => 0/1,
    *           "minmax" => 0/1
    *       },
    *       [1] => {
    *           ...
    *       },
    *       ...
    *   }
	*
	* @param $tblData Data for the table being constructed
    *
    * 2D Array where element at each index represent the data 
    * to be displayed in the table at that index
    *
    * Header labels are taken care of by $tblStruct
    *
    * Example:
    *
    * DAU, x1, x2, x3
    * Web, y1, y2, y3
    * DB, z1, z2, z3

	*/
	public static function get_table_markup($tblStruct, $tblData) {
		$markup = "<table cellspacing=0 cellpadding=0>";

		$markup .= self::get_head_markup($tblStruct, $tblData);
		$markup .= self::get_body_markup($tblStruct, $tblData);

		$markup .= "</table>";

		return $markup;
	}
}
