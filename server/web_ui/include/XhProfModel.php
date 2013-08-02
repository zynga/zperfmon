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


include_once 'XhProfDAO.php';

/*
 * View interface
 */
include_once 'XhProfView_Interface.php';

/* Global map table for queries
 *
 */
include_once 'query_cfg.php';

/**
 * Model class for data feteched by various zPerfmon components
 *
 * @author dbose
 */
class XhProfModel {

    /*
     * Server configuration passed to the model. Contains configurations like
     * "db_host", "db_user" etc.
     */
    private $server_cfg;

    /*
     * Game-specific configurations passed to the model. Contains configurations
     * like "db_name"
     */
    private $game_cfg;

    /*
     * Corresponding DAO object
     */
    private $xhProfDAO;


    function __construct($server_cfg, $game_cfg, $report = true)
    {
        $this->server_cfg = $server_cfg;
        $this->game_cfg = $game_cfg;

        $this->xhProfDAO = new XhProfDAO($this->game_cfg["db_host"],
					 $this->game_cfg[$report ? "rpt_user" : "db_user"],
					 $this->game_cfg[$report ? "rpt_pass" : "db_pass"],
					 $this->game_cfg["db_name"]);
    }

    function  __destruct()
    {
        if (isset ($this->xhProfDAO))
        {
            $this->xhProfDAO->disconnect();
        }
    }


    /**
     * Thin wrapper over generic_execute_get_query() to facilitate returning
     * column names in the query result.
     */
    public function generic_execute_get_query_detail($query_name,
						     $query_parameters)
    {
      return $this->generic_execute_get_query($query_name,
					      $query_parameters,
					      true, true);
    }


    /** Returns a generic associative array by executing the query
     *  from the 'query_cfg.php' query-map indexed by $query_name
     *
     * @param <type> $query_name
     * @param <type> $param_values
     * @return <type>
     */
    public function generic_execute_get_query($query_name,
					      $param_values,
					      $detail = false,
                                              $select = true)
    {
        global $query_map;
        
        //Defensive checks
        if (empty($query_name))
        {
            return null;
        }

        //Get the query from query_cfg
        if (!array_key_exists($query_name, $query_map))
        {
            return null;
        }

        $query = null;
        if (count($param_values) == 0)
        {
            $query = $query_map[$query_name];
        }
        else
        {
            //Inflate the parametrized query
            $query = $this->sprintfn($query_map[$query_name], $param_values);
            if ($query == false)
            {
                return null;
            }
        }
        

        //TODO:
        // echo $query;

        //Connect by DAO
        //
        //Need connection pooling?
        //
        $this->xhProfDAO->connect();

        $query_exec_result = $this->xhProfDAO->prepare_and_query($query,
                                                                $detail,
                                                                $select);

        return $query_exec_result;
    }

    /*
     * Returns an array (concatenation) of results of each component
     * query. Component query results are processed with "row" and
     * "column" split. Each result would get a hash with the "cols"
     * key holding column names in the result and "rows" key holding
     * the data or cells.
     * 
     * The query is picked up from 'query_cfg.php' query-map indexed
     * by $query_name.
     *
     * @param <type> $query_name
     * @param <type> $param_values
     * @return <type>
     */
    public function generic_execute_get_query_multi($query_name, $param_values)
    {
        global $query_map;
        
        //Defensive checks
        if (empty($query_name))
        {
            return null;
        }

        //Get the query from query_cfg
        if (!array_key_exists($query_name, $query_map))
        {
            return null;
        }

        $query = null;
        if (count($param_values) == 0)
        {
            $query = $query_map[$query_name];
        }
        else
        {
            //Inflate the parametrized query
            $query = $this->sprintfn($query_map[$query_name], $param_values);
            if ($query == false)
            {
                return null;
            }
        }
        
        $this->xhProfDAO->connect();

        $query_exec_result = $this->xhProfDAO->prepare_and_query_multi($query);

        return $query_exec_result;
    }

    /**
     * version of sprintf for cases where named arguments are desired
     *
     * Example:
     * 
     * print(sprintfn('SELECT timestamp, char_length(xhprof_blob) as blob_length
     *                  FROM stats_30min
     *                  WHERE date(timestamp)=%date$s AND char_length(xhprof_blob) > 0',
             array("date" => "'23/02/2010'")));
     *
     * @param string $format sprintf format string, with any number of named arguments
     * @param array $args array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    function sprintfn ($format, array $args = array())
    {
        try
        {
            // map of argument names to their corresponding sprintf numeric argument value
            $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

            // find the next named argument. each search starts at the end of the previous replacement.
            for ($pos = 0; preg_match('/(?<=%)([a-zA-Z_]\w*)(?=\$)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);)
            {
                $arg_pos = $match[0][1];
                $arg_len = strlen($match[0][0]);
                $arg_key = $match[1][0];

                // programmer did not supply a value for the named argument found in the format string
                if (!array_key_exists($arg_key, $arg_nums))
                {
                    user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
                    return false;
                }

                // replace the named argument with the corresponding numeric one
                $format = substr_replace($format, $replace = $arg_nums[$arg_key], $arg_pos, $arg_len);
                $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
            }

            return vsprintf($format, array_values($args));
        }
        catch(Exception $ex)
        {
            return false;
        }
    }

}
?>
