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
 * This sample demonstrates how to multipart upload an object concurrently
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
	 * Claim a upload id firstly
	 */
	$resp = $obsClient -> initiateMultipartUpload(['Bucket' => $bucketName, 'Key' => $objectKey]);
	
	$uploadId = $resp['UploadId'];
	printf("Claiming a new upload id %s\n\n", $uploadId);
	
	$sampleFilePath = '/temp/test.txt'; //sample large file path
	//  you can prepare a large file in you filesystem first
	createSampleFile($sampleFilePath);
	
	$partSize = 5 * 1024 * 1024;
	$fileLength = filesize($sampleFilePath);
	
	$partCount = $fileLength % $partSize === 0 ?  intval($fileLength / $partSize) : intval($fileLength / $partSize) + 1;
	
	if($partCount > 10000){
		throw new \RuntimeException('Total parts count should not exceed 10000');
	}
	
	printf("Total parts count %d\n\n", $partCount);
	$parts = [];
	$promise = null;
	/*
	 * Upload multiparts to your bucket
	 */
	printf("Begin to upload multiparts to OBS from a file\n\n");
	for($i = 0; $i < $partCount; $i++){
		$offset = $i * $partSize;
		$currPartSize = ($i + 1 === $partCount) ? $fileLength - $offset : $partSize;
		$partNumber = $i + 1;
		$p = $obsClient -> uploadPartAsync([
				'Bucket' => $bucketName, 
				'Key' => $objectKey, 
				'UploadId' => $uploadId, 
				'PartNumber' => $partNumber,
				'SourceFile' => $sampleFilePath,
				'Offset' => $offset,
				'PartSize' => $currPartSize
		], function($exception, $resp) use(&$parts, $partNumber) {
			$parts[] = ['PartNumber' => $partNumber, 'ETag' => $resp['ETag']];
			printf ( "Part#" . strval ( $partNumber ) . " done\n\n" );
		});
		
		if($promise === null){
			$promise = $p;
		}
	}
	
	/*
	 * Waiting for all parts finished
	 */
	$promise -> wait();
	
	usort($parts, function($a, $b){
		if($a['PartNumber'] === $b['PartNumber']){
			return 0;
		}
		return $a['PartNumber'] > $b['PartNumber'] ? 1 : -1;
	});
	
	/*
	 * Verify whether all parts are finished
	 */
	if(count($parts) !== $partCount){
		throw new \RuntimeException('Upload multiparts fail due to some parts are not finished yet');
	}
	
	
	printf("Succeed to complete multiparts into an object named %s\n\n", $objectKey);
	
	/*
	 * View all parts uploaded recently
	 */
	printf("Listing all parts......\n");
	$resp = $obsClient -> listParts(['Bucket' => $bucketName, 'Key' => $objectKey, 'UploadId' => $uploadId]);
	foreach ($resp['Parts'] as $part)
	{
		printf("\tPart#%d, ETag=%s\n", $part['PartNumber'], $part['ETag']);
	}
	printf("\n");
	
	
	/*
	 * Complete to upload multiparts
	 */
	$resp = $obsClient->completeMultipartUpload([
			'Bucket' => $bucketName,
			'Key' => $objectKey,
			'UploadId' => $uploadId,
			'Parts'=> $parts
	]);
	
// 	deleteTempFile($sampleFilePath);
	
	
} catch ( ObsException $e ) {
	echo 'Response Code:' . $e->getStatusCode () . PHP_EOL;
	echo 'Error Message:' . $e->getExceptionMessage () . PHP_EOL;
	echo 'Error Code:' . $e->getExceptionCode () . PHP_EOL;
	echo 'Request ID:' . $e->getRequestId () . PHP_EOL;
	echo 'Exception Type:' . $e->getExceptionType () . PHP_EOL;
} finally{
	$obsClient->close ();
}


function createSampleFile($filePath)
{
	if(file_exists($filePath)){
		return;
	}
	$filePath = iconv('UTF-8', 'GBK', $filePath);
	if(is_string($filePath) && $filePath !== '')
	{
		$fp = null;
		$dir = dirname($filePath);
		try{
			if(!is_dir($dir))
			{
				mkdir($dir,0755,true);
			}
			
			if(($fp = fopen($filePath, 'w')))
			{
				
				for($i=0;$i< 1000000;$i++){
					fwrite($fp, uniqid() . "\n");
					fwrite($fp, uniqid() . "\n");
					if($i % 100 === 0){
						fflush($fp);
					}
				}
			}
		}finally{
			if($fp){
				fclose($fp);
			}
		}
	}
}

function deleteTempFile($sampleFilePath) {
    if(file_exists($sampleFilePath)){
        unlink($sampleFilePath);
    };
}
