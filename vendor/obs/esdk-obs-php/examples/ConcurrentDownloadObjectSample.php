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
 * This sample demonstrates how to download an object concurrently
 * from OBS using the OBS SDK for PHP.
 */
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require '../vendor/autoload.php'; // sample env
}

if (file_exists('obs-autoloader.php')) {
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

$localFilePath = '/temp/' . $objectKey;

/*
 * Constructs a obs client instance with your account for accessing OBS
 */
$obsClient = ObsClient::factory([ 
        'key' => $ak,
        'secret' => $sk,
        'endpoint' => $endpoint,
        'socket_timeout' => 30,
        'connect_timeout' => 10
]);

try {
    /*
     * Create bucket
     */
    printf("Create a new bucket for demo\n\n");
    $obsClient->createBucket([ 
            'Bucket' => $bucketName
    ]);

    $sampleFilePath = '/temp/test.txt'; // sample large file path
                                        // you can prepare a large file in you filesystem first
    createSampleFile($sampleFilePath);

    /*
     * Upload an object to your bucket
     */
    printf("Uploading a new object to OBS from a file\n\n");
    $obsClient->putObject([ 
            'Bucket' => $bucketName,
            'Key' => $objectKey,
            'SourceFile' => $sampleFilePath
    ]);

    /*
     * Get size of the object and pre-create a random access file to hold object data
     */
    $resp = $obsClient->getObjectMetadata([ 
            'Bucket' => $bucketName,
            'Key' => $objectKey
    ]);

    $objectSize = $resp ['ContentLength'];

    printf("Object size from metadata:%d\n\n", $objectSize);

    $dir = dirname($localFilePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    /*
     * Calculate how many blocks to be divided
     */
    $blockSize = 5 * 1024 * 1024; // 5MB
    $blockCount = intval($objectSize / $blockSize);

    if ($objectSize % $blockSize !== 0) {
        $blockCount ++;
    }

    printf("Total blocks count:%d\n\n", $blockCount);

    /*
     * Download the object concurrently
     */
    printf("Start to download %s\n\n", $objectKey);

    $fp = fopen($localFilePath, 'w');
    $promise = null;

    for($i = 0; $i < $blockCount;) {
        $startPos = $i ++ * $blockSize;
        $endPos = ($i == $blockCount) ? $objectSize - 1 : ($i * $blockSize - 1);
        $range = sprintf('bytes=%d-%d', $startPos, $endPos);
        $p = $obsClient->getObjectAsync([ 
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'Range' => $range
        ], function ($exception, $resp) use ($startPos, $fp, $i, $range) {
            fseek($fp, $startPos, 0);
            printf("%s\n", $range);
            try {
                while ( ! $resp ['Body']->eof() ) {
                    $str = $resp ['Body']->read(65536);
                    fwrite($fp, $str);
                }
            } catch ( Exception $exception ) {
                printf($exception);
            }
            $resp ['Body']->close();
            printf("Part#" . strval($i) . " done\n\n");
        });
        if ($promise === null) {
            $promise = $p;
        }
    }

    /*
     * Waiting for all blocks finished
     */
    $promise->wait();
    fclose($fp);
    if (file_exists($sampleFilePath)) {
        unlink($sampleFilePath);
    }

    /*
     * Deleting object
     */
    printf("Deleting object %s \n\n", $objectKey);
    $obsClient->deleteObject([ 
            'Bucket' => $bucketName,
            'Key' => $objectKey
    ]);
} catch ( ObsException $e ) {
    echo 'Response Code:' . $e->getStatusCode() . PHP_EOL;
    echo 'Error Message:' . $e->getExceptionMessage() . PHP_EOL;
    echo 'Error Code:' . $e->getExceptionCode() . PHP_EOL;
    echo 'Request ID:' . $e->getRequestId() . PHP_EOL;
    echo 'Exception Type:' . $e->getExceptionType() . PHP_EOL;
} finally{
    $obsClient->close();
}

function createSampleFile($filePath) {
    if (file_exists($filePath)) {
        return;
    }
    $filePath = iconv('UTF-8', 'GBK', $filePath);
    if (is_string($filePath) && $filePath !== '') {
        $fp = null;
        $dir = dirname($filePath);
        try {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (($fp = fopen($filePath, 'w'))) {

                for($i = 0; $i < 1000000; $i ++) {
                    fwrite($fp, uniqid() . "\n");
                    fwrite($fp, uniqid() . "\n");
                    if ($i % 100 === 0) {
                        fflush($fp);
                    }
                }
            }
        } finally{
            if ($fp) {
                fclose($fp);
            }
        }
    }
}
