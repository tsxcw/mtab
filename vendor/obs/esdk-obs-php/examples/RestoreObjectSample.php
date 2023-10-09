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
 * This sample demonstrates how to download an cold object
 * from OBS using the OBS SDK for PHP.
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

$bucketName = 'my-obs-cold-bucket-demo';

$objectKey = 'my-obs-cold-object-key-demo';


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
	 * Create a cold bucket
	 */
	printf("Create a new cold bucket for demo\n\n");
	$obsClient -> createBucket(['Bucket' => $bucketName, 'StorageClass' => ObsClient::StorageClassCold]);
	
	/*
	 * Create a cold object
	 */
	printf("Create a new cold object for demo\n\n");
	$content = 'Hello OBS';
	$obsClient -> putObject(['Bucket' => $bucketName, 'Key' => $objectKey, 'Body' => $content]);
	
	/*
	 * Restore the cold object
	 */
	printf("Restore the cold object\n\n");
	$obsClient -> restoreObject([
			'Bucket' => $bucketName,
			'Key' => $objectKey,
			'Days' => 1,
	    'Tier' => ObsClient::RestoreTierExpedited
	]);
	
	/*
	 * Wait 6 minute to get the object
	 */
	sleep(60 * 6);
	
	/*
	 * Get the cold object
	 */
	printf("Get the cold object\n");
	$resp = $obsClient -> getObject(['Bucket' => $bucketName, 'Key' => $objectKey]);
	printf("\t%s\n\n", $resp['Body']);
	
	/*
	 * Delete the cold object
	 */
	$obsClient -> deleteObject(['Bucket' => $bucketName, 'Key' => $objectKey]);
	
} catch ( ObsException $e ) {
	echo 'Response Code:' . $e->getStatusCode () . PHP_EOL;
	echo 'Error Message:' . $e->getExceptionMessage () . PHP_EOL;
	echo 'Error Code:' . $e->getExceptionCode () . PHP_EOL;
	echo 'Request ID:' . $e->getRequestId () . PHP_EOL;
	echo 'Exception Type:' . $e->getExceptionType () . PHP_EOL;
} finally{
	$obsClient->close ();
}