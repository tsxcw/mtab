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

/**
 * This sample demonstrates how to create an empty folder under
 * specified bucket to OBS using the OBS SDK for PHP.
 */
if (file_exists ( 'vendor/autoload.php' )) {
	require 'vendor/autoload.php';
} else {
	require '../vendor/autoload.php'; // sample env
}

if (file_exists ( 'obs-autoloader.php' )) {
	require 'obs-autoloader.php';
} else {
	require '../obs-autoloader.php'; // sample env
}

use Obs\ObsClient;
use Obs\ObsException;

$ak = '*** Provide your Access Key ***';

$sk = '*** Provide your Secret Key ***';

$endpoint = 'https://your-endpoint:443';

$bucketName = 'my-obs-bucket-demo';

$objectKey = 'my-obs-object-key-demo';


/*
 * Constructs a obs client instance with your account for accessing OBS
 */
$obsClient = ObsClient::factory ( [
		'key' => $ak,
		'secret' => $sk,
		'endpoint' => $endpoint,
		'socket_timeout' => 30,
		'connect_timeout' => 10
] );

try
{
	/*
	 * Create bucket
	 */
	echo "Create a new bucket for demo\n\n";
	$obsClient -> createBucket(['Bucket' => $bucketName]);
	
	
	/*
	 * Create an empty folder without request body, note that the key must be
	 * suffixed with a slash
	 */
	$keySuffixWithSlash = "MyObjectKey1/";
	$obsClient -> putObject(['Bucket' => $bucketName, 'Key' => $keySuffixWithSlash]);
	echo "Creating an empty folder " . $keySuffixWithSlash . "\n\n";
	
	/*
	 * Verify whether the size of the empty folder is zero
	 */
	$resp = $obsClient -> getObject(['Bucket' => $bucketName, 'Key' => $keySuffixWithSlash]);
	
	echo "Size of the empty folder '" . $keySuffixWithSlash. "' is " . $resp['ContentLength'] .  "\n\n";
	if($resp['Body']){
		$resp['Body'] -> close();
	}
	
	/*
	 * Create an object under the folder just created
	 */
	$obsClient -> putObject(['Bucket' => $bucketName, 'Key' => $keySuffixWithSlash . $objectKey, 'Body' => 'Hello OBS']);

} catch ( ObsException $e ) {
	echo 'Response Code:' . $e->getStatusCode () . PHP_EOL;
	echo 'Error Message:' . $e->getExceptionMessage () . PHP_EOL;
	echo 'Error Code:' . $e->getExceptionCode () . PHP_EOL;
	echo 'Request ID:' . $e->getRequestId () . PHP_EOL;
	echo 'Exception Type:' . $e->getExceptionType () . PHP_EOL;
} finally{
	$obsClient->close ();
}

