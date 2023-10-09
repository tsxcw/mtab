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
 * This sample demonstrates how to list versions under specified bucket
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
	 * Enable bucket versioning
	 */
	$obsClient -> setBucketVersioningConfiguration(['Bucket' => $bucketName, 'Status' => 'Enabled']);
	
	/*
	 * First prepare folders and sub folders
	 */
	$keys = [];
	$promise = null;
	$keyPrefix = 'MyObjectKey';
	$folderPrefix = 'src';
	$subFolderPrefix = 'test';
	
	for($i = 0; $i<5; $i++){
		$key = $folderPrefix . $i . '/';
		$obsClient -> putObject(['Bucket'=>$bucketName, 'Key' => $key]);
		$keys[] = ['Key' => $key];
		for($j = 0; $j < 3; $j++){
			$subKey = $key . $subFolderPrefix . $j . '/';
			$obsClient -> putObject(['Bucket'=>$bucketName, 'Key' => $subKey]);
			$keys[] = ['Key' => $subKey];
		}
	}
	
	/*
	 * Insert 2 objects in each folder
	 */
	$resp = $obsClient -> listObjects(['Bucket' => $bucketName]);
	foreach ($resp ['Contents'] as $content ) {
		for($k =0; $k < 2; $k++){
			$objectKey = $content['Key'] . $keyPrefix . $k; 
			$obsClient -> putObject(['Bucket'=>$bucketName, 'Key' => $objectKey, 'Body' => 'Hello OBS']);
			$keys[] = ['Key' => $objectKey];
		}
	}
	
	/*
	 * Insert 2 objects in root path
	 */
	$obsClient -> putObject(['Bucket'=>$bucketName, 'Key' => $keyPrefix . '0', 'Body' =>  'Hello OBS']);
	$obsClient -> putObject(['Bucket'=>$bucketName, 'Key' => $keyPrefix . '1', 'Body' =>  'Hello OBS']);
	
	printf("Put %d objects completed.\n\n", count($keys));
	
	
	$keys = [];
	
	/*
	 * List versions using default parameters, will return up to 1000 objects
	 */
	$resp = $obsClient -> listVersions (['Bucket' => $bucketName ]);
	printf("\tVersions:\n");
	foreach ( $resp ['Versions'] as $version ) {
		printf("\t%s etag[%s] versionid[%s]\n", $version['Key'], $version['ETag'],$version['VersionId']);
		$keys[] = ['Key' => $version['Key'], 'VersionId' => $version['VersionId']];
	}
	printf("\n");
	
	printf("\tDeleteMarkers:\n");
	foreach ( $resp ['DeleteMarkers'] as $deleteMarker ) {
		printf("\t%s versionid[%s]\n", $deleteMarker['Key'], $deleteMarker['VersionId']);
		$keys[] = ['Key' => $deleteMarker['Key'], 'VersionId' => $deleteMarker['VersionId']];
	}
	printf("\n");
	
	
	/*
	 * List all the versions in way of pagination
	 */
	printf("List all the versions in way of pagination:\n");
	$nextMarker = null;
	$index = 1;
	do{
		$resp = $obsClient -> listVersions(['Bucket' => $bucketName, 'MaxKeys' => 10, 'KeyMarker' => $nextMarker]);
		$nextMarker = $resp['NextKeyMarker'];
		printf("Page:%d\n", $index++);
		
		printf("\tVersions:\n");
		foreach ( $resp ['Versions'] as $version ) {
			printf("\t%s etag[%s] versionid[%s]\n", $version['Key'], $version['ETag'],$version['VersionId']);
		}
		
		printf("\n");
		printf("\tDeleteMarkers:\n");
		foreach ( $resp ['DeleteMarkers'] as $deleteMarker ) {
			printf("\t%s versionid[%s]\n", $deleteMarker['Key'], $deleteMarker['VersionId']);
		}
	}while($resp['IsTruncated']);
	printf("\n");
	
	/*
	 * List all versions group by folder
	 */
	printf("List all versions group by folder \n");
	$resp = $obsClient -> listVersions(['Bucket' => $bucketName, 'Delimiter' => '/']);
	
	printf("Root path:\n");
	printf("\tVersions:\n");
	foreach ( $resp ['Versions'] as $version ) {
		printf("\t%s etag[%s] versionid[%s]\n", $version['Key'], $version['ETag'],$version['VersionId']);
	}
	
	printf("\n");
	printf("\tDeleteMarkers:\n");
	foreach ( $resp ['DeleteMarkers'] as $deleteMarker ) {
		printf("\t%s versionid[%s]\n", $deleteMarker['Key'], $deleteMarker['VersionId']);
	}
	
	listVersionsByPrefix($resp);
	
	
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

function listVersionsByPrefix($resp){
	global $obsClient;
	global $bucketName;
	while(!empty($resp ['CommonPrefixes'])){
		foreach ($resp ['CommonPrefixes'] as $commonPrefix){
			$commonPrefix = $commonPrefix['Prefix'];
			printf("Folder %s:\n", $commonPrefix);
			$resp = $obsClient -> listVersions(['Bucket' => $bucketName, 'Delimiter' => '/', 'Prefix' => $commonPrefix]);
			printf("\tVersions:\n");
			foreach ( $resp ['Versions'] as $version ) {
				printf("\t%s etag[%s] versionid[%s]\n", $version['Key'], $version['ETag'],$version['VersionId']);
			}
			printf("\n");
			printf("\tDeleteMarkers:\n");
			foreach ( $resp ['DeleteMarkers'] as $deleteMarker ) {
				printf("\t%s versionid[%s]\n", $deleteMarker['Key'], $deleteMarker['VersionId']);
			}
			listVersionsByPrefix($resp);
		}
	}
	
}