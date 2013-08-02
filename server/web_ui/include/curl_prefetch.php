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


class Curl_Prefetch
{
	function __construct($defaults = array())
	{
		$this->mh = curl_multi_init();
		$this->handles = array();
		$this->content = array();
		if(is_array($defaults)) 
		{
			$this->defaults = $defaults;
		}
	}

	function __destruct()
	{
		foreach($this->handles as $name => $handle) 
		{
			
			curl_multi_remove_handle($this->mh, $handle);
			curl_close($handle);
		}
		unset($this->handles);
		unset($this->content);
		curl_multi_close($this->mh);
		unset($this->mh);
	}

	function add($name, $ch)
	{
		if(isset($this->handles[$name]))
		{
			return false;
		}

		if(is_string($ch))
		{
			$url = $ch;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt_array($ch, $this->defaults);

		$this->handles[$name] = $ch;

		if(0 === curl_multi_add_handle($this->mh, $ch))
		{
			$active = null;
			do
			{
				$mrc = curl_multi_exec($this->mh, $active);
			} while($active && CURLM_CALL_MULTI_PERFORM == $mrc);
		}
	}

	function alive($secs = 0.050)
	{
		$active = 0;
		if(curl_multi_select($this->mh, $secs) != -1)
		{
			$mrc = curl_multi_exec($this->mh, $active);
		}

		return ($active != 0);
	}

	function wait($name = '', &$error_msg = null)
	{
		$active = null;
		$ch = null;
		if($name) 
		{
			if(isset($this->handles[$name]))  
			{
				$ch = $this->handles[$name];
			}
			else
			{
				$error_msg = "bad key, no such handle";
				return null;
			}
		}

		if(isset($this->content[(int)$ch])) 
		{
			/* keep this in sync to the other return as much as possible */
			list($v,$error) = $this->content[(int)$ch];
			unset($this->content[(int)$ch]);

			unset($this->handles[$name]);
			curl_multi_remove_handle($this->mh, $ch);
			curl_close($ch);

			$error_msg = $error;

			return $v;
		}
		do {
			if(curl_multi_select($this->mh) != -1) 
			{
				do {
					$mrc = curl_multi_exec($this->mh, $active);
					$info = curl_multi_info_read($this->mh);
					if (false !== $info) {
						$handle = $info['handle'];
						if($info['result'] == CURLE_OK) 
						{
							$error = null;
							$v = curl_multi_getcontent($handle);
						}
						else
						{
							$error = curl_error($handle);
							$v = null;
						}

						if($handle == $ch)
						{
							unset($this->handles[$name]);
							curl_multi_remove_handle($this->mh, $handle);
							curl_close($handle);

							$error_msg = $error;

							return $v;
						}
						else
						{
							$this->content[(int)($handle)] = array($v, $error);
						}
					}
				} while($mrc == CURLM_CALL_MULTI_PERFORM || $active);
			}
		} while($mrc == CURLM_OK && $active);
		$error_msg = "";
		return null;
	}
}

/*
define('test_curl_prefetch', 1);
if(defined('test_curl_prefetch')) {
#TODO: actually write tests
$c = new Curl_Prefetch();
$c->add('l', "http://localhost/test-again.php?l");
$c->add('x', "http://localhost/test-again.php?x");
print "Done sending request, sleeping: ";
for($i = 0; $i < 10; $i++) {
	print "$i ";
sleep(1);
}
$c->wait();

print "\n";
$t1 = microtime(true);
print $c->wait('x');
$t2 = microtime(true);

print "(x) Time taken:".($t2-$t1)."\n";

$t1 = microtime(true);
print $c->wait('l');
$t2 = microtime(true);

print "(l) Time taken:".($t2-$t1)."\n";

$t1 = microtime(true);
print $c->wait('y');
$t2 = microtime(true);

print "(y) Time taken:".($t2-$t1)."\n";
}
*/
?>
