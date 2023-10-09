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
 * This sample demonstrates how to list objects under specified bucket
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
	printf("Create a new bucket for demo\n\n");
	$obsClient -> createBucket(['Bucket' => $bucketName]);
	
	
	/*
	 * First insert 100 objects for demo
	 */
	$promise = null;
	$keyPrefix = 'MyObjectKey';
	for($i = 0;$i < 100;$i++){
		$key = $keyPrefix . strval($i);
		$p = $obsClient -> putObjectAsync(['Bucket' => $bucketName, 'Key' => $key, 'Body' => 'Hello OBS'],function(){});
		if($promise === null){
			$promise = $p;
		}
		$keys[] = ['Key' => $key];
	}
	$promise -> wait();
	
	printf("Put %d objects completed.\n\n", count($keys));
	
	/*
	 * List objects using default parameters, will return up to 1000 objects
	 */
	printf("List objects using default parameters:\n");
	
	$resp = $obsClient -> listObjects(['Bucket' => $bucketName]);
	foreach ( $resp ['Contents'] as $content ) {
		printf("\t%s etag[%s]\n", $content ['Key'], $content ['ETag']);
	}
	printf("\n");
	
	/*
	 * List the first 10 objects
	 */
	printf("List the first 10 objects:\n");
	
	$resp = $obsClient -> listObjects(['Bucket' => $bucketName, 'MaxKeys' => 10]);
	foreach ( $resp ['Contents'] as $content ) {
		printf("\t%s etag[%s]\n", $content ['Key'], $content ['ETag']);
	}
	printf("\n");
	
	$theSecond10ObjectsMarker = $resp['NextMarker'];
	/*
	 * List the second 10 objects using marker
	 */
	printf("List the second 10 objects using marker:\n");
	$resp = $obsClient -> listObjects(['Bucket' => $bucketName, 'MaxKeys' => 10, 'Marker' => $theSecond10ObjectsMarker]);
	foreach ( $resp ['Contents'] as $content ) {
		printf("\t%s etag[%s]\n", $content ['Key'], $content ['ETag']);
	}
	printf("\n");
	
	/*
	 * List objects with prefix and max keys
	 */
	printf("List objects with prefix and max keys:\n");
	$resp = $obsClient -> listObjects(['Bucket' => $bucketName, 'MaxKeys' => 5, 'Prefix' => $keyPrefix . '2']);
	foreach ( $resp ['Contents'] as $content ) {
		printf("\t%s etag[%s]\n", $content ['Key'], $content ['ETag']);
	}
	printf("\n");
	
	/*
	 * List all the objects in way of pagination
	 */
	printf("List all the objects in way of pagination:\n");
	$nextMarker = null;
	$index = 1;
	do{
		
		$resp = $obsClient -> listObjects(['Bucket' => $bucketName, 'MaxKeys' => 10, 'Marker' => $nextMarker]);
		$nextMarker = $resp['NextMarker'];
		printf("Page:%d\n", $index++);
		foreach ( $resp ['Contents'] as $content ) {
			printf("\t%s etag[%s]\n", $content ['Key'], $content ['ETag']);
		}
		
	}while($resp['IsTruncated']);
	printf("\n");
	/*
	 * Delete all the objects created
	 */
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