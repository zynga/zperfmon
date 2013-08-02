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


class Symbol {
	public $name;
	public $metric;
	

	public function __construct($name, $metric) {
		$this->name = $name;
		$this->metric = $metric;
	}

	public function getName() {
		return $this->name;
	}

	public function getValue() {
		return $this->metric;
	}

	public static function compare($ob1, $ob2) {
		$m1 = $ob1->metric;
		$m2 = $ob2->metric;
		
		$r = ($m1 > $m2) ? 1 : (($m1 < $m2) ? -1 : 0);
		return $r;
	}
}


class TopX implements ArrayAccess {
	protected $size;
	public $elements;

	public function __construct($size=5) {
		$this->size = $size;
		$low = new Symbol("_", 0);
		$this->elements = array_fill(0, $size, $low);
	}

	public function peek() {
		return $this->elements[$this->size]->metric;
	}

	public function compareMin($metric) {
		$value = $this->elements[$this->size]->metric;

		return ($value > $metric ? 1 :
			$value < $metric ? -1 : 0);
	}

	public function insert($name, $value) {
		/* 
		 * Push items smaller than item being inserted to slot below.
		 * If largest item is smaller than item being inserted, that
		 * is handled outside the loop.
		 */
		$index = $this->size - 1;
		while ($index > 0 && $value > $this->elements[$index]->metric) {
			$this->elements[$index] = $this->elements[$index-1];
			$index--;
		}

		if ($index == $this->size-1) {
			return;
		}

		if ($index != 0 || $value <= $this->elements[$index]->metric) {
			$index++;
		}
		
		$this->elements[$index] = new Symbol($name, $value);
	}

	public function offsetGet($offset) {
		return $this->elements[$offset];
	}
	public function offsetSet($offset, $value) {
		$this->elements[$offset] = $value;
	}
	public function offsetExists($offset) {
		return isset($this->elements[$offset]);
	}
	public function offsetUnset($offset) {
		return NULL;
	}
}
