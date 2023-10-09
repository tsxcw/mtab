<?php

/**
 * Copyright 2019 Huawei Technologies Co.,Ltd.
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use
 * this file except in compliance with the License.  You may obtain a copy of the
 * License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed
 * under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
 * CONDITIONS OF ANY KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations under the License.
 *
 */

namespace Obs;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Obs\Log\ObsLog;

class ObsException extends \RuntimeException
{
	const CLIENT = 'client';
	
	const SERVER = 'server';
	
    private $response;

    private $request;

    private $requestId;

    private $exceptionType;
        
    private $exceptionCode;
    
    private $exceptionMessage;
    
    private $hostId;
	
    public function __construct ($message = null, $code = null, $previous = null) 
    {
    	parent::__construct($message, $code, $previous);
    }
    
    public function setExceptionCode($exceptionCode)
    {
        $this->exceptionCode = $exceptionCode;
    }

    public function getExceptionCode()
    {
        return $this->exceptionCode;
    }
    
    public function setExceptionMessage($exceptionMessage)
    {
        $this->exceptionMessage = $exceptionMessage;
    }
    
    public function getExceptionMessage()
    {
    	return $this->exceptionMessage ? $this->exceptionMessage : $this->message;
    }

    public function setExceptionType($exceptionType)
    {
        $this->exceptionType = $exceptionType;
    }

    public function getExceptionType()
    {
        return $this->exceptionType;
    }

    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    public function getRequestId()
    {
        return $this->requestId;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getStatusCode()
    {
        return $this->response ? $this->response->getStatusCode() : -1;
    }
    
    public function setHostId($hostId){
    	$this->hostId = $hostId;
    }
    
    public function getHostId(){
    	return $this->hostId;
    }

    public function __toString()
    {
        $message = get_class($this) . ': '
            . 'OBS Error Code: ' . $this->getExceptionCode() . ', '
            . 'Status Code: ' . $this->getStatusCode() . ', '
            . 'OBS Error Type: ' . $this->getExceptionType() . ', '
            . 'OBS Error Message: ' . ($this->getExceptionMessage() ? $this->getExceptionMessage():$this->getMessage());

        // Add the User-Agent if available
        if ($this->request) {
            $message .= ', ' . 'User-Agent: ' . $this->request->getHeaderLine('User-Agent');
        }
        $message .= "\n";
        
        ObsLog::commonLog(INFO, "http request:status:%d, %s",$this->getStatusCode(),"code:".$this->getExceptionCode().", message:".$this->getMessage());
        return $message;
    }
    
}
