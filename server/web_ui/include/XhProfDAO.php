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


/*
 * Data Access Object for Zperfmon
 */
ini_set("memory_limit", -1);
include_once 'ZPerfmonDAO_Interface.php';

#
# DAO specific replacement for strval cast. Puts single quotes around the
# string to escape unintentional interpreation of contents by javascript.
#
function add_quote($text)
{
	return "'" . $text . "'";
}

/**
 * Description of XhProfDAO
 *
 * @author user
 */
class XhProfDAO extends ZPerfmon_Interface {

    /*
     *  Host name for the database
     */
    private $db_host;

    /*
     * Database name to connect with
     */
    private $db_name;

    /*
     * Database user who is granted access to the necessary tables
     */
    private $db_user;

    /*
     * Database password
     */
    private $db_password;

    /**
     * Connection holds MySQL resource
     */
    private $connection;
    
    /**
     * MySQLi connection object
     */
    private $mysqli_conn;
    
    /**
     * Map MySQL datatypes to PHP function which can cast to that type.
     */
    static private $db_to_php_type = array( 'int' => 'intval',
					    'real' => 'doubleval',
					    'string' => 'strval',
					    'timestamp' => 'strval'
	    );
	/**
	 * Dispatch table for functions that map mysqli field types to php
	 * data types.
	 */
    static private $mysqli_to_php_type = array(
	    0 => 'intval', # "DECIMAL";
	    1 => 'intval', #  "TINYINT";
	    2 => 'intval', #  "SMALLINT";
	    3 => 'intval', #  "INTEGER";
	    4 => 'doubleval', #  "FLOAT";
	    5 => 'doubleval', #  "DOUBLE";
	    7 => 'strval', # TIMESTAMP
	    8 => 'intval', #  "BIGINT";
	    9 => 'intval', #  "MEDIUMINT";
	    # 10 =>  "DATE";
	    # 11 =>  "TIME";
	    # 12 =>  "DATETIME";
	    # 13 =>  "YEAR";
	    # 14 =>  "DATE";
	    # 16 =>  "BIT";
	    # 246 =>  "DECIMAL";
	    # 247 =>  "ENUM";
	    # 248 =>  "SET";
	    # 249 =>  "TINYBLOB";
	    250 =>  "strval",
	    # 251 =>  "LONGBLOB";
	    # 252 =>  "BLOB";
	    253 => 'add_quote', # "VARCHAR";
	    254 => 'add_quote', # "CHAR";
	    # 255 =>  "GEOMETRY";
	    1024 => 'dummyval');



    function __construct($db_host, $db_user, $db_password, $db_name)
    {
        $this->db_host = $db_host;
        $this->db_name = $db_name;
        $this->db_user = $db_user;
        $this->db_password = $db_password;
    }

    private function send_db_conn_error($err_msg) 
    {
	header("HTTP/1.1 500  Server error");
	die("$err_msg:" . mysql_error() . "\n");
    }

    /**
     * Create new connection to database
     */
    public function connect()
    {
        /*
         * Connect to the specified database and select the database
         */
        if (!isset ($this->connection) || !mysql_ping($this->connection))
        {
            $this->connection = mysql_pconnect($this->db_host, $this->db_user, $this->db_password);
            $this->connection || $this->send_db_conn_error("sql connect failed");
        }
	
	if (!mysql_select_db($this->db_name, $this->connection)) {
		$this->send_db_conn_error("unable to select db:");
	}

	if (!$this->mysqli_conn) {
		$this->mysqli_conn = new mysqli($this->db_host,
						$this->db_user,
						$this->db_password,
						$this->db_name);
		# check connection
		if (mysqli_connect_errno()) {
			$this->send_db_conn_error("mysqli connect failed with " .
						  mysqli_connect_error());
		}
	}
    }

    /**
     * Break connection to database
     */
    public function disconnect()
    {
        //clean up connection!
	if(null !== $this->connection){
        	$ret = mysql_close($this->connection);
	        $this->connection = null;
        	return $ret;
	}
	if(null !== $this->mysqli_conn){
	        $this->mysqli_conn->close();
	}
    }

    /**
     * Sanitize data to be used in a query
     *
     * @param $data
     */
    public function escape($data)
    {
        return mysql_real_escape_string($data, $this->connection);
    }


    /** Prepare query to execute. Do any kind of data-binding if necessary
     *
     * @param <type> $query
     * @param <type> $detail
     * @param <type> $select
     * @return <type>
     */
    public function prepare_and_query($query,
                                      $detail = false,
                                      $select = true)
    {
        //Handle boundary cases
        if (empty($query))
        {
            return null;
        }

        // echo $query, "\n";
        //execute prepared query and store in result variable
	if(php_sapi_name() != "cli") header("Query :".json_encode($query));
        $result = null;
        try
        {
            $result = mysql_query($query, $this->connection);
            if ($result)
            {
                if ($select)
                {
                    //$rows = mysql_fetch_array($result, MYSQL_ASSOC);
                    $rows = $this->mysql_fetch_full_result_array($result, $detail);
                    mysql_free_result($result);
                    return $rows;
                }
                else
                {
                    return $result;
                }
            }
        }
        catch(Exception $ex)
        {
            //Log::
	  header("HTTP/1.1 500  Server error");
        }

        if ($result && $select)
        {
            mysql_free_result($result);
        }

        return null;
    }

    /** put the full result in one array
     *
     * @param record_set $result
     * @return <type>
     */
    private function mysql_fetch_full_result_array($result, $detail = false)
    {
        /*
         * Resultant multi-D array
         */
        $table_result = array();
        $row_index = 0;

        /*
         * Total number of columns returned
         */
        $field_count = mysql_num_fields($result);

	if ($detail) {
	    $columns = array();
	    for ( $col_index = 0; $col_index < $field_count; $col_index++) {
		$columns[] = mysql_field_name($result, $col_index);
	    }
	}


        /*
         * Iterate over the rows
         */
        while($row = mysql_fetch_assoc($result))
        {
            /*
             * Nested array
             */
            $arr_row = array();

            /*
             * Initialize field index at beginning of a new row
             */
            $field_index = 0;

            /*
             * Iterate over the columns
             */
            while ($field_index < $field_count)
            {
                $col = mysql_fetch_field($result, $field_index);


                /*
                 * Bug:
                 *
                 * If the 2D array being generated, is pushed to json_encode
                 * even non-string values are gettingwrapped by double quotes
                 * (" .. ")
                 * 
                 * To circumvent, explicitly checking value type -
                 */

		$cast_function = self::$db_to_php_type[$col->type];

		$field_value = $cast_function($row[$col->name]);

		if (!$detail) {
			$arr_row[$col->name] = $field_value;
		} else {
			$arr_row[$field_index] = $field_value;
		}

                $field_index++;
            }
            
            $table_result[$row_index] = $arr_row;
            $row_index++;
        }
	
	if (!$detail) {
	    return $table_result;
	} else {
	    return array("rows" => $table_result,
			 "cols" => $columns);
	}
    }


    #
    # Batch execute given list of queries and return results in an
    # array indexed by query order
    #
    public function prepare_and_query_multi($query)
    {
        //Handle boundary cases
        if (empty($query))
        {
            return null;
        }

	#
	# run the passed query and accumulate each component queries
	# result into one array. We leave the task of associating query
	# to its result to the caller.
	#
        $result_array = array();
        try
        {
		$result = $this->mysqli_conn->multi_query($query);
		if (!$result) {
			echo "Error:", $this->mysqli_conn->error, "\n";
			throw new Exception("Query failed");
		}

		# Fetch result for each component query and massage
		# it into a form UI (view) components understand.
		do {
			$result_obj = $this->mysqli_conn->store_result();

			# Even if this query didn't return results
			# maybe the next did, skip instead of aborting.
			if (!$result_obj) {
				$result_array[] = NULL;
				continue;
			}
		    
			$result_array[] =
				$this->mysql_fetch_multi_result($result_obj);

			$result_obj->free();
		} while ($this->mysqli_conn->next_result());
        }
        catch(Exception $ex)
        {
		//Log::
		header("HTTP/1.1 500  Server error");
		echo "Exception:", $this->mysqli_conn->error, "\n";
		return null;
        }

        return $result_array;
    }

    #
    # Fetch results from all components queries in the last
    # multi-query. Each result-set will appended to the array
    # being returned
    #
    public function mysql_fetch_multi_result($result)
    {
	    $rows = array();

	    $columns = $result->fetch_fields();
			    
	    /*
	     * Iterate over the rows
	     */
	    while($row = $result->fetch_row()) {

		    $row_holder = array();
			
		    #
		    # Iterate over the columns and handle each col
		    # type separately
		    for ($index = 0; $index < count($columns); $index++) {

			    $col = $columns[$index];
			    $cast_function = self::$mysqli_to_php_type[$col->type];
			    $row_holder[] = $cast_function($row[$index]);
		    }

		    $rows[] = $row_holder;
	    }

	    $cols = array();

	    foreach($columns as $col) {
		    $cols[] = $col->name;
	    }

	    return array(
		    "rows" => $rows,
		    "cols" => $cols);
    }
}
?>
