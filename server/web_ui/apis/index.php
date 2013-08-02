
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
<?php 

header("Cache-Control: no-cache");
/*
 * The RestRequest class is meant for handling the incoming API
 * requests and wrapping the output.
 * 
 * @Author Gaurav (gkumar@zynga.com)
 */
require_once(dirname(__FILE__)."/zPerfmonAPI.class.php");

class RestRequest{

    /* API revision*/
    const API_REVISION = '1.0';
	
    /* actions for redirecting API */
    protected $action = '';
	
    /* parameters with default values provided in URL for API */
    protected $action_param = '';
	
    /* parameters with corresponding values provided in URL for API */
    protected $params = array();
    
    protected $lastError = '';
	
    /* zPerfmon custom status code to text mapping */
    protected $statusMap = array(
		0 => 'Game added Successfully',
		1 => 'Something is wrong with the input provided.',
		2 => 'The API doesnt support calls other than GET and POST. Please use accordingly',
		3 => 'Game already exists.',
		4 => 'Something went wrong in zPerfmon. Please try again later.'
    );

    protected $statusMapGet = array(
                0 => 'Request serverd Successfully',
    		1 => 'Something is wrong with the input provided.',
                2 => 'The API doesnt support calls other than GET and POST. Please use accordingly',
                3 => 'Some Confilct.',
                4 => 'Something went wrong in zPerfmon. Please try again later.'
    );
    
    /* zPerfmon custom status code to HTTP Header mapping */
    protected $httpResponseMap = array(
		0 => 'HTTP/1/1 200 Success',
		1 => 'HTTP/1.1 400 Bad Request',
		2 => 'HTTP/1.1 403 Forbidden',
		3 => 'HTTP/1.1 409 Conflict',
		4 => 'HTTP/1.1 500 Internal Server Error'
    );

    /* Generic function to create bundle the output of the api call */
    public function createReturn($status, $output = '', $apiversion = self::API_REVISION, $format = 'json'){
		$output = ( $output != '' ) ? $output : $this->statusMap[$status];
		if ( is_string($output) ){ 
			error_log( $output );
		}
		header( $this->httpResponseMap[$status] );
		$result = array('status' => $status,
			'apiversion' => $apiversion, 
			'format' => $format,
			'output' => $output);            
		return $result;
    }
    public function createGetReturn($status, $output = '',$time = 0, $apiversion = self::API_REVISION, $format = 'json'){
                $output = ( $output != '' ) ? $output : $this->statusMapGet[$status];
		$status_message = $this->statusMapGet[$status];
                if ( is_string($output) ){
                        error_log( $output );
                }
                header( $this->httpResponseMap[$status] );
                $result = array('status' => $status,
				'status_message' => $status_message,
				'time_spent_on_server' => $time,
                        'apiversion' => $apiversion,
                        'format' => $format,
			'output' => $output);
					
                return $result;
    }    

 
    /*
     * The function resposible to handle and redirect the incoming request to the
     * proper function.
     */
    public function ProcessRequest(){
		$request_method = strtolower($_SERVER['REQUEST_METHOD']);
		
		switch ($request_method)  
		{  
			case 'get':  
				$result = $this->ProcessGetRequest();
				break;  
			case 'post':  
				$result = $this->ProcessPostRequest();
				break;  
			default:
				$this->status = 2;
				$result = $this->createReturn($this->status);
				break;
		}	
		echo json_encode($result)."\n";
		$this->shadow_upload();
    }

    public function shadow_upload(){
        if(defined('SHADOW_UPDATE_URL') && !isset($_GET['shadow'])) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_VERBOSE, 0);
                curl_setopt($ch, CURLOPT_URL, SHADOW_UPDATE_URL.$this->action);
                curl_setopt($ch, CURLOPT_POST, true);
                $post = $_POST;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                if($response === false) {
                        error_log("Shadow forward of add/delete game api to ".SHADOW_UPDATE_URL."$this->action failed");
			return;
                }
		echo "\n\nSHADOW $this->action STATUS :\n$response\n";
                curl_close($ch);
        }
    }
    
    /* 
     * 	Function to get the Rule to be applied, and also extra parameters given in URL
     *  i.e job need to be done 
    */
    protected function processRule(){
		
		$options = $_GET;
		try {
			$variables = array();
			if( isset($options['action']) ){
				$variables = explode('/', $options['action']);
				
				while ( !empty($variables) ){
					$this->params[] = array_shift($variables);
				}
				$this->action = array_pop($this->params);
				return true;
			}
			else {
				$this->status = 1;
				return false;
			}
		}
		catch(Exception $e) {
			$this->status = 1;
			return false;
		}
	}
	
    /*
     * Processes the Post request
     */
    protected function ProcessPostRequest(){
        	/* process rules */
		$this -> processRule();
		$data = $_POST;
		$zPerfmonapi = new zPerfmonAPI();                
		switch ($this->action) {
			case 'add':
				$zPerfmonapi->addGame($data);	
				break;
			case 'delete':
				$zPerfmonapi->deleteGame($data);
				break;	
			case 'slack':
				$zPerfmonapi->computeSlack($data);
				break;
			default:
				$this->status = 1;
				return $this->createReturn($this->status);
		}

		return $this->createReturn($zPerfmonapi->status, $zPerfmonapi->lastError);
    }
	
	
    /*
     * Processes the Get request
     */
    protected function ProcessGetRequest(){
		$start_time = time();
        	/* process rules */
		$this -> processRule();
		$data = $_GET;
		// To be extended in Near Future
                $zPerfmonapi = new zPerfmonAPI();
		switch ($this->action) {
			case 'cpu':
                                $zPerfmonapi->getEU($data,$this -> params[0],"web_cpu_idle_util");
                                break;
			case 'memory':
                                $zPerfmonapi->getEU($data,$this -> params[0],"web_mem_used_util");
                                break;
			case 'rps':
                                $zPerfmonapi->getEU($data,$this -> params[0],"web_rps");
                                break;
			case 'instances':
				$zPerfmonapi->getInstances($data,$this -> params[0]);
				break;
			case 'pagetime':
				$zPerfmonapi->getPagetime($data,$this -> params[0]);
				break;
			case 'tracked_functions':
                                $zPerfmonapi->getTrackedFunction($data,$this -> params[0]);
                                break;
			default:
                                $this->status = 1;
				return $this->createGetReturn($this->status);
				break;

		}
		$end_time = time();
		$time_in_server = $end_time - $start_time;
		$result = $this->createGetReturn($zPerfmonapi->status, $zPerfmonapi->lastError,$time_in_server);
		return $result;
    }
}

function main(){
    $zPerfmonAPI = new RestRequest();
    $zPerfmonAPI->ProcessRequest();
}

main();
