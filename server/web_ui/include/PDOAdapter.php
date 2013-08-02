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


class PDOAdapter
{
	private $pdo = null;

	protected function __construct($db_host, $db_user, $db_pass, $db_name, $db_type='mysql')
	{
		$this->pdo = new PDO( "{$db_type}:host={$db_host};dbname={$db_name}",$db_user, $db_pass);
	}


	/* prepare a statement to be later executed with execute/fetchAll */
	protected function prepare($query) 
	{
		if($this->pdo)
		{
			$db = $this->pdo;
			$stmt = $db->prepare($query);
			return $stmt;
		}

		return null;
	}

	private function bind($stmt, $parameters)
	{
		foreach($parameters as $name => $value)
		{
			$type = null;
			if(is_array($value))
			{
				list($value, $type) = $value;
			}
			else
			{
				/* different from default for bindValue, which is STRING */
				$type = PDO::PARAM_INT;
			}

			$stmt->bindValue(":$name", $value, $type);
		}
	}

	protected function execute($stmt, $parameters)
	{
		$this->bind($stmt, $parameters);
		return ($stmt->execute());
	}

	protected function store($stmt, $parameters)
	{
		return $this->execute($stmt, $parameters);
	}

	protected function fetchAll($stmt, $parameters)
	{
		if ($this->execute($stmt, $parameters))
		{
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		return null;
	}
}
