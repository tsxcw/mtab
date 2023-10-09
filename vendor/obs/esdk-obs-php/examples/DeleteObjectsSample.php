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
 * This sample demonstrates how to delete objects under specified bucket
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

$bucketName = 'my-obs-bucket-demo';


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
	 * Batch put objects into the bucket
	 */
	$content = 'Thank you for using Object Storage Service';
	$keyPrefix = 'MyObjectKey';
	$keys = [];
	
	$start = microtime(true);
	
// 	doUploadSync($keys, $keyPrefix, $content);
	
	doUploadAsync($keys, $keyPrefix, $content);
	
	printf("Cost " . round(microtime(true) - $start, 3) * 1000 . " ms to upload 100 objects\n\n");
	
	/*
	 * Delete all objects uploaded recently under the bucket
	 */
	
	printf("Deleting all objects\n\n");
	
	$resp = $obsClient->deleteObjects([
			'Bucket'=>$bucketName,
			'Objects'=>$keys,
			'Quiet'=> false,
	]);
	
	printf("Delete results:\n\n");
	$i = 0;
	foreach ($resp['Deleteds'] as $delete)
	{
		printf("\tDeleteds[$i][Key]:%s,Deleted[$i][VersionId]:%s，Deleted[$i][DeleteMarker]:%s，Deleted[$i][DeleteMarkerVersionId]:%s\n",
				$delete['Key'],$delete['VersionId'],$delete['DeleteMarker'],$delete['DeleteMarkerVersionId']);
		$i++;
	}
	printf("\n");
	printf("Error results:\n\n");
	$i = 0;
	foreach ($resp['Errors'] as $error)
	{
		printf("\tErrors[$i][Key]:%s,Errors[$i][VersionId]:%s，Errors[$i][Code]:%s，Errors[$i][Message]:%s\n",
				$error['Key'],$error['VersionId'],$error['Code'],$error['Message']);
		$i++;
	}
	
} catch ( ObsException $e ) {
	echo 'Response Code:' . $e->getStatusCode () . PHP_EOL;
	echo 'Error Message:' . $e->getExceptionMessage () . PHP_EOL;
	echo 'Error Code:' . $e->getExceptionCode () . PHP_EOL;
	echo 'Request ID:' . $e->getRequestId () . PHP_EOL;
	echo 'Exception Type:' . $e->getExceptionType () . PHP_EOL;
} finally{
	$obsClient->close ();
}

function doUploadSync(&$keys, $keyPrefix, $content)
{
	global $obsClient;
	global $bucketName;
	for($i = 0;$i < 100;$i++){
		$key = $keyPrefix . strval($i);
		$obsClient -> putObject(['Bucket' => $bucketName, 'Key' => $key, 'Body' => $content]);
		printf("Succeed to put object %s\n\n", $key);
		$keys[] = ['Key' => $key];
	}
}

function doUploadAsync(&$keys, $keyPrefix, $content)
{
	global $obsClient;
	global $bucketName;
	$promise = null;
	for($i = 0;$i < 100;$i++){
		$key = $keyPrefix . strval($i);
		$p = $obsClient -> putObjectAsync(['Bucket' => $bucketName, 'Key' => $key, 'Body' => $content],
				function($exception, $resp) use ($key){
					printf("Succeed to put object %s\n\n", $key);
				});
		if($promise === null){
			$promise = $p;
		}
		$keys[] = ['Key' => $key];
	}
	$promise -> wait();
}
