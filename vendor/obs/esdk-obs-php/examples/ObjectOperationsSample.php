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
 * This sample demonstrates how to do object-related operations
 * (such as create/delete/get/copy object, do object ACL/OPTIONS)
 * on OBS using the OBS SDK for PHP.
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
	printf("Create a new bucket for demo\n\n");
	$obsClient -> createBucket(['Bucket' => $bucketName]);
	
	/*
	 * Create object
	 */
	$content = 'Hello OBS';
	$obsClient -> putObject(['Bucket' => $bucketName, 'Key' => $objectKey, 'Body' => $content]);
	printf("Create object: %s successfully!\n\n", $objectKey);
	
	
	/*
	 * Get object metadata
	 */
	printf("Getting object metadata\n");
	$resp = $obsClient->getObjectMetadata([
			'Bucket'=>$bucketName,
			'Key'=>$objectKey,
	]);
	printf("\tMetadata:%s\n\n", json_encode($resp));
	
	/*
	 * Get object
	 */
	printf("Getting object content\n");
	$resp = $obsClient -> getObject(['Bucket' => $bucketName, 'Key' => $objectKey]);
	printf("\t%s\n\n", $resp['Body']);
	
	/*
	 * Copy object
	 */
	$sourceBucketName = $bucketName;
	$destBucketName = $bucketName;
	$sourceObjectKey = $objectKey;
	$destObjectKey = $objectKey . '-back';
	printf("Copying object\n\n");
	$obsClient -> copyObject([				
			'Bucket'=> $destBucketName,
			'Key'=> $destObjectKey,
			'CopySource'=>$sourceBucketName . '/' . $sourceObjectKey,
			'MetadataDirective' => ObsClient::CopyMetadata
	]);
	
	/*
	 * Options object
	 */
	doObjectOptions();
	
	/*
	 * Put/Get object acl operations
	 */
	doObjectAclOperations();
	
	/*
	 * Delete object
	 */
	printf("Deleting objects\n\n");
	$obsClient -> deleteObject(['Bucket' => $bucketName, 'Key' => $objectKey]);
	$obsClient -> deleteObject(['Bucket' => $bucketName, 'Key' => $destObjectKey]);
	
} catch ( ObsException $e ) {
	echo 'Response Code:' . $e->getStatusCode () . PHP_EOL;
	echo 'Error Message:' . $e->getExceptionMessage () . PHP_EOL;
	echo 'Error Code:' . $e->getExceptionCode () . PHP_EOL;
	echo 'Request ID:' . $e->getRequestId () . PHP_EOL;
	echo 'Exception Type:' . $e->getExceptionType () . PHP_EOL;
} finally{
	$obsClient->close ();
}

function doObjectOptions()
{
	
	global $obsClient;
	global $bucketName;
	global $objectKey;
	
	$obsClient->setBucketCors ( [
			'Bucket' => $bucketName,
			'CorsRule' => [
					[
							'AllowedMethod' => ['HEAD', 'GET', 'PUT'],
							'AllowedOrigin' => ['http://www.a.com', 'http://www.b.com'],
							'AllowedHeader'=> ['Authorization'],
							'ExposeHeaders' => ['x-obs-test1', 'x-obs-test2'],
							'MaxAgeSeconds' => 100
					]
			]
	] );
	
	$resp = $obsClient->optionsObject([
			'Bucket'=>$bucketName,
			'Key' => $objectKey,
			'Origin'=>'http://www.a.com',
			'AccessControlRequestMethods' => ['PUT'],
			'AccessControlRequestHeaders'=> ['Authorization']
	]);
	printf ("Options bucket: %s\n\n", json_encode($resp -> toArray()));

}

function doObjectAclOperations()
{
	global $obsClient;
	global $bucketName;
	global $objectKey;
	
	printf("Setting object ACL to " . ObsClient::AclPublicRead . "\n\n");
	
	$obsClient ->setObjectAcl([
			'Bucket' => $bucketName,
			'Key' => $objectKey,
			'ACL' => ObsClient::AclPublicRead
	]);
	
	printf("Getting object ACL\n");
	$resp = $obsClient -> getObjectAcl([
			'Bucket' => $bucketName,
			'Key' => $objectKey
	]);
	printf("\tOwner:%s\n", json_encode($resp['Owner']));
	printf("\tGrants:%s\n\n", json_encode($resp['Grants']));
}