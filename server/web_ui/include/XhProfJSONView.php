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


include_once 'XhProfView_Interface.php';

/**
 * Description of XhProfView
 *
 * @author user
 */
class XhProfJSONView extends XhProfView_Interface
{
    /*
     * Render the model data in form of following form
     *
     *  cols: [col1, col2, col3]
     *  rows: [{val11, val12, val13}, {val21, val22, val23}, {val31, val32, val33}]
     */
  public function Render($array)
    {
        if (!isset ($array))
        {
            return;
        }

        if (count($array) == 0)
        {
            return;
        }

        echo "rows:" . json_encode(array_keys($array[0]));
        echo "\n";

        $list_begin = "[";
        $list_end = "]";
        $row_begin = "{";
        $row_end = "}";

        $var = "";
        $var = $var . $list_begin;
        foreach ($array as $key => $value)
        {
            $var = $var . ($row_begin . implode(",", array_values($value)) . $row_end);
            $var = $var . ",";
        }
        $var = rtrim($var, ",") . $list_end;

        echo "cols:" . $var;
    }


	/*
	 * Render the model data in form of following form
	 * [
	 *  cols: [col1, col2, col3]
	 *  rows: [{val11, val12, val13}, {val21, val22, val23}, {val31, val32, val33}]
	 *  agrgt_cols: [col1, col2, col3]
	 *  agrgt: [{val11, val12, val13}, {val21, val22, val23}, {val31, val32, val33}]
	 * ]
	 */
	public function RenderCombination($chart_cols, $chart_result,
			       $tables_cols = null, $table_result = null)
	{
		if (!isset ($chart_cols) || !isset($chart_result)) {
		  return;
		}

		$json_container = array();

		$json_container["cols"] = $chart_cols;
		$json_container["rows"] = $chart_result;

		if (isset($tables_cols) && isset($table_result)) {
			$json_container["agrgt_cols"] = $tables_cols;
			$json_container["agrgt"] = $table_result;
		}

		return json_encode($json_container);
	}

}
?>
