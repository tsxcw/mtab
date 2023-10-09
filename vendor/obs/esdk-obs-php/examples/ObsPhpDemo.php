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
use function GuzzleHttp\json_encode;

$ak = '*** Provide your Access Key ***';

$sk = '*** Provide your Secret Key ***';

$endpoint = 'https://your-endpoint:443';

$obsClient = ObsClient::factory(array (
    'key' => $ak,
    'secret' => $sk,
    'endpoint' => $endpoint,
));

$obsClient->initLog(array (
        'FilePath' => './logs',
        'FileName' => 'eSDK-OBS-PHP.log',
        'MaxFiles' => 10,
        'Level' => WARN
));

$bucketName = 'bucket000';
$objectKey = 'test';

// create bucket
function CreateBucket() {
    global $obsClient;
    global $bucketName;
    echo "create bucket start...\n";
    try {
        $resp = $obsClient->createBucket(array (
                'Bucket' => $bucketName,
                'ACL' => ObsClient::AclPrivate,
                'LocationConstraint' => '',
                'StorageClass' => ObsClient::StorageClassWarm
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("Location:%s\n", $resp ['Location']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// list buckets
function ListBuckets() {
    global $obsClient;
    echo "list bucket start...\n";
    try {
        $resp = $obsClient->listBuckets();
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        $i = 0;
        foreach ( $resp ['Buckets'] as $bucket ) {
            printf("Buckets[$i][Name]:%s,Buckets[$i][CreationDate]:%s\n", $bucket ['Name'], $bucket ['CreationDate']);
            $i ++;
        }
        printf("Owner[ID]:%s\n", $resp ['Owner'] ['ID']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}


// delete bucket
function DeleteBucket() {
    global $obsClient;
    global $bucketName;
    echo "delete bucket start...\n";
    try {
        $resp = $obsClient->deleteBucket(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// list objects
function ListObjects() {
    global $obsClient;
    global $bucketName;
    echo "list objects start...\n";
    try {
        $resp = $obsClient->listObjects(array (
                'Bucket' => $bucketName,
                'Delimiter' => '',
                'Marker' => '',
                'MaxKeys' => '',
                'Prefix' => ''
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("IsTruncated:%d,Marker:%s,NextMarker:%s,Name:%s\n", $resp ['IsTruncated'], $resp ['Marker'], $resp ['NextMarker'], $resp ['Name']);
        printf("Prefix:%s,Delimiter:%s,MaxKeys:%d\n", $resp ['Prefix'], $resp ['Delimiter'], $resp ['MaxKeys']);
        $i = 0;
        foreach ( $resp ['CommonPrefixes'] as $CommonPrefixe ) {
            printf("CommonPrefixes[$i][Prefix]:%s\n", $CommonPrefixe ['Prefix']);
            $i ++;
        }
        $i = 0;
        foreach ( $resp ['Contents'] as $content ) {
            printf("Contents[$i][ETag]:%s,Contents[$i][Size]:%d,Contents[$i][StorageClass]:%s\n", $content ['ETag'], $content ['Size'], $content ['StorageClass']);
            printf("Contents[$i][Key]:%s,Contents[$i][LastModified]:%s\n", $content ['Key'], $content ['LastModified']);
            printf("Contents[$i][Owner][ID]:%s\n", $content ['Owner'] ['ID']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// list versions
function ListVersions() {
    global $obsClient;
    global $bucketName;
    echo "list versions start...\n";
    try {
        $resp = $obsClient->listVersions(array (
                'Bucket' => $bucketName,
                'Delimiter' => '',
                'KeyMarker' => '',
                'MaxKeys' => '',
                'Prefix' => '',
                'VersionIdMarker' => ''
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("IsTruncated:%d,KeyMarker:%s,VersionIdMarker:%s,NextKeyMarker:%s\n", $resp ['IsTruncated'], $resp ['KeyMarker'], $resp ['VersionIdMarker'], $resp ['NextKeyMarker']);
        printf("NextVersionIdMarker:%s,Name:%s,Prefix:%s,Delimiter:%s,MaxKeys:%s\n", $resp ['NextVersionIdMarker'], $resp ['Name'], $resp ['Prefix'], $resp ['Delimiter'], $resp ['MaxKeys']);
        $i = 0;
        foreach ( $resp ['CommonPrefixes'] as $CommonPrefixe ) {
            printf("CommonPrefixes[$i][Prefix]:%s\n", $CommonPrefixe ['Prefix']);
            $i ++;
        }
        $i = 0;
        foreach ( $resp ['Versions'] as $version ) {
            printf("Versions[$i][ETag]:%s,Versions[$i][Size]:%d,Versions[$i][StorageClass]:%s\n", $version ['ETag'], $version ['Size'], $version ['StorageClass']);
            printf("Versions[$i][Key]:%s,Versions[$i][VersionId]:%s,Versions[$i][IsLatest]:%d,Versions[$i][LastModified]:%s\n", $version ['Key'], $version ['VersionId'], $version ['IsLatest'], $version ['LastModified']);
            printf("Versions[$i][Owner][ID]:%s\n", $version ['Owner'] ['ID']);
            $i ++;
        }
        $i = 0;
        foreach ( $resp ['DeleteMarkers'] as $deleteMarker ) {
            printf("DeleteMarkers[$i][Key]:%s,DeleteMarkers[$i][VersionId]:%s,DeleteMarkers[$i][IsLatest]:%d,DeleteMarkers[$i][LastModified]:%s\n", $deleteMarker ['Key'], $deleteMarker ['VersionId'], $deleteMarker ['IsLatest'], $deleteMarker ['LastModified']);
            printf("DeleteMarkers[$i][Owner][ID]:%s\n", $deleteMarker ['Owner'] ['ID']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// head bucket
function HeadBucket() {
    global $obsClient;
    global $bucketName;
    echo "head bucket start...\n";
    try {
        $resp = $obsClient->headBucket(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket metadata
function GetBucketMetadata() {
    global $obsClient;
    global $bucketName;
    echo "get bucket metatdata start...\n";
    try {
        $resp = $obsClient->getBucketMetadata(array (
                "Bucket" => $bucketName,
                "Origin" => "www.example.com",
                "RequestHeader" => "header1"
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("StorageClass:%s\n", $resp ["StorageClass"]);
        printf("AllowOrigin:%s\n", $resp ["AllowOrigin"]);
        printf("MaxAgeSeconds:%s\n", $resp ["MaxAgeSeconds"]);
        printf("ExposeHeader:%s\n", $resp ["ExposeHeader"]);
        printf("AllowHeader:%s\n", $resp ["AllowHeader"]);
        printf("AllowMethod:%s\n", $resp ["AllowMethod"]);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket location
function GetBucketLocation() {
    global $obsClient;
    global $bucketName;
    echo "get bucket location start...\n";
    try {
        $resp = $obsClient->getBucketLocation(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Location:%s\n", $resp ['Location']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket storageinfo
function GetBucketStorageInfo() {
    global $obsClient;
    global $bucketName;
    echo "get bucket storage info start...\n";
    try {
        $resp = $obsClient->getBucketStorageInfo(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Size:%d,ObjectNumber:%d\n", $resp ['Size'], $resp ['ObjectNumber']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}


// set bucket quota
function SetBucketQuota() {
    global $obsClient;
    global $bucketName;
    echo "set bucket quota start...\n";
    try {
        $resp = $obsClient->setBucketQuota(array (
                'Bucket' => $bucketName,
                'StorageQuota' => 1048576
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket quota
function GetBucketQuota() {
    global $obsClient;
    global $bucketName;
    echo "get bucket quota start...\n";
    try {
        $resp = $obsClient->getBucketQuota(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("StorageQuota:%s\n", $resp ['StorageQuota']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket storage policy
function SetBucketStoragePolicy() {
    global $obsClient;
    global $bucketName;
    echo "set bucket storage policy start...\n";
    try {
        $resp = $obsClient->setBucketStoragePolicy(array (
                'Bucket' => $bucketName,
                'StorageClass' => ObsClient::StorageClassCold
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket storage policy
function GetBucketStoragePolicy() {
    global $obsClient;
    global $bucketName;
    echo "get bucket storage policy start...\n";
    try {
        $resp = $obsClient->getBucketStoragePolicy(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("StorageClass:%s\n", $resp ['StorageClass']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}


// set bucket acl
function SetBucketAcl() {
    global $obsClient;
    global $bucketName;
    echo "set bucket ACL start...\n";
    try {
        $resp = $obsClient->setBucketAcl(array (
                'Bucket' => $bucketName,
                'ACL' => '',
                'Owner' => array (
                        'ID' => 'ownerid'
                ),
                'Grants' => array (
                        0 => array (
                                'Grantee' => array (
                                        'ID' => 'userid'
                                ),
                                'Permission' => ObsClient::PermissionRead,
                                'Delivered' => true
                        ),
                        1 => array (
                                'Grantee' => array (
                                        'URI' => ObsClient::AllUsers
                                ),
                                'Permission' => ObsClient::PermissionWrite,
                                'Delivered' => true
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket acl
function GetBucketAcl() {
    global $obsClient;
    global $bucketName;
    echo "get bucket ACL start...\n";
    try {
        $resp = $obsClient->getBucketAcl(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Owner[ID]:%s\n", $resp ['Owner'] ['ID'] );
        $i = 0;
        foreach ( $resp ['Grants'] as $grant ) {
            printf("Grants[$i][Grantee][ID]:%s,Grants[$i][Grantee][URI]:%s\n",$grant ['Grantee'] ['ID'], $grant ['Grantee'] ['URI']);
            printf("Grants[$i][Permission]:%s\n", $grant ['Permission']);
            printf("Grants[$i][Delivered]:%s\n", $grant['Delivered'] ? 'true' : 'false');
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket logging configuration
function SetBucketLogging() {
    global $obsClient;
    global $bucketName;
    echo "set bucket logging configuration start...\n";
    try {
        $resp = $obsClient->setBucketLogging(array (
                'Bucket' => $bucketName,
                'Agency' => 'your agency',
                'LoggingEnabled' => array (
                        'TargetBucket' => 'bucket003',
                        'TargetPrefix' => 'bucket.log',
                        'TargetGrants' => array (
                                0 => array (
                                        'Grantee' => array (
                                                'ID' => 'userid'
                                        ),
                                        'Permission' => ObsClient::PermissionRead
                                ),
                                1 => array (
                                        'Grantee' => array (
                                                'URI' => ObsClient::AllUsers,
                                        ),
                                        'Permission' => ObsClient::PermissionRead
                                )
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket logging configuration
function GetBucketLogging() {
    global $obsClient;
    global $bucketName;
    echo "get bucket logging configuration start...\n";
    try {
        $resp = $obsClient->getBucketLogging(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Agency:%s\n", $resp ['Agency']);
        printf("LoggingEnabled[TargetBucket]:%s,LoggingEnabled[TargetPrefix]:%s\n", $resp ['LoggingEnabled'] ['TargetBucket'], $resp ['LoggingEnabled'] ['TargetPrefix']);
        if (is_array($resp ['LoggingEnabled'] ['TargetGrants'])) {
            $i = 0;
            foreach ( $resp ['LoggingEnabled'] ['TargetGrants'] as $grant ) {
                printf("LoggingEnabled[$i][TargetGrants][Permission]:%s\n", $grant ['Permission']);
                printf("LoggingEnabled[$i][TargetGrants][Grantee][ID]:%s,LoggingEnabled[$i][TargetGrants][Grantee][URI]:%s\n", $grant ['Grantee'] ['ID'], $grant ['Grantee'] ['URI']);
                $i ++;
            }
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket policy
function SetBucketPolicy() {
    global $obsClient;
    global $bucketName;
    echo "set bucket policy start...\n";
    try {
        $resp = $obsClient->setBucketPolicy(array (
                'Bucket' => $bucketName,
                'Policy' => 'your policy'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket policy
function GetBucketPolicy() {
    global $obsClient;
    global $bucketName;
    echo "get bucket policy start...\n";
    try {
        $resp = $obsClient->getBucketPolicy(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("Policy:%s\n", $resp ['Policy']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete bucket policy
function DeleteBucketPolicy() {
    global $obsClient;
    global $bucketName;
    echo "delete bucket policy start...\n";
    try {
        $resp = $obsClient->deleteBucketPolicy(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket lifycycle configuration
function SetBucketLifecycle() {
    global $obsClient;
    global $bucketName;
    echo "set bucket lifecycle configuration start...\n";
    try {
        $resp = $obsClient->setBucketLifecycle(array (
                'Bucket' => $bucketName,
                'Rules' => array (
                        0 => array (
                                'ID' => '',
                                'Prefix' => 'ok',
                                'Status' => 'Enabled',
                                'Transitions' => array (
                                        0 => array (
                                                'StorageClass' => ObsClient::StorageClassWarm,
                                                'Date' => '2019-02-01T00:00:00Z'
                                        ),
                                        1 => array (
                                                'StorageClass' => ObsClient::StorageClassCold,
                                                'Date' => '2019-03-01T00:00:00Z'
                                        )
                                ),
                                'Expiration' => array (
                                        'Date' => '2019-04-01T00:00:00Z'
                                ),
                                'NoncurrentVersionTransitions' => array (
                                        0 => array (
                                                'StorageClass' => ObsClient::StorageClassWarm,
                                                'NoncurrentDays' => 30
                                        ),
                                        1 => array (
                                                'StorageClass' => ObsClient::StorageClassCold,
                                                'NoncurrentDays' => 60
                                        )
                                ),
                                // 'Expiration'=>array('Days'=>100),
                                'NoncurrentVersionExpiration' => array (
                                        'NoncurrentDays' => 60
                                )
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket lifycycle configuration
function GetBucketLifecycle() {
    global $obsClient;
    global $bucketName;
    echo "get bucket lifecycle configuration start...\n";
    try {
        $resp = $obsClient->getBucketLifecycle(array (
                'Bucket' => $bucketName
        ));
        $i = 0;
        foreach ( $resp ['Rules'] as $rule ) {
            foreach ( $rule ['Transitions'] as $index => $transition ) {
                printf("Rules[$i][Transitions][$index][Date]:%s,Rules[$i][Transitions][$index][StorageClass]:%s\n", $transition ['Date'], $transition ['StorageClass']);
            }
            printf("Rules[$i][Expiration][Date]:%s,Rules[$i][Expiration][Days]:%d\n", $rule ['Expiration'] ['Date'], $rule ['Expiration'] ['Days']);
            printf("Rules[$i][NoncurrentVersionExpiration][NoncurrentDays]:%s\n", $rule ['NoncurrentVersionExpiration'] ['NoncurrentDays']);
            foreach ( $rule ['NoncurrentVersionTransitions'] as $index => $noncurrentVersionTransition ) {
                printf("Rules[$i][NoncurrentVersionTransitions][$index][NoncurrentDays]:%d,Rules[$i][NoncurrentVersionTransitions][$index][StorageClass]:%s\n", $noncurrentVersionTransition ['NoncurrentDays'], $noncurrentVersionTransition ['StorageClass']);
            }
            printf("Rules[$i][ID]:%s,Rules[$i][Prefix]:%s,Rules[$i][Status]:%s\n", $rule ['ID'], $rule ['Prefix'], $rule ['Status']);
            $i ++;
        }
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete bucket lifycycle configuration
function DeleteBucketLifecycle() {
    global $obsClient;
    global $bucketName;
    echo "delete bucket lifecycle configuration start...\n";
    try {
        $resp = $obsClient->deleteBucketLifecycle(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket website configuration
function SetBucketWebsite() {
    global $obsClient;
    global $bucketName;
    echo "set bucket website configuration start...\n";
    try {
        $resp = $obsClient->setBucketWebsite(array (
                'Bucket' => $bucketName,
                // 'RedirectAllRequestsTo'=>array('HostName'=>'obs.hostname','Protocol'=>'http'),
                'IndexDocument' => array (
                        'Suffix' => 'index.html'
                ),
                'ErrorDocument' => array (
                        'Key' => 'error.html'
                ),
                'RoutingRules' => array (
                        0 => array (
                                'Condition' => array (
                                        'KeyPrefixEquals' => 'docs/',
                                        'HttpErrorCodeReturnedEquals' => 404
                                ),
                                'Redirect' => array (
                                        'ReplaceKeyPrefixWith' => 'documents/',
                                        'HostName' => 'obs.hostname',
                                        'Protocol' => 'http',
                                        'HttpRedirectCode' => 308
                                )
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket website configuration
function GetBucketWebsite() {
    global $obsClient;
    global $bucketName;
    echo "get bucket website configuration start...\n";
    try {
        $resp = $obsClient->GetBucketWebsite(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("RedirectAllRequestsTo[HostName]:%s,RedirectAllRequestsTo[Protocol]:%s\n", $resp ['RedirectAllRequestsTo'] ['HostName'], $resp ['RedirectAllRequestsTo'] ['Protocol']);
        printf("IndexDocument[Suffix]:%s\n", $resp ['IndexDocument'] ['Suffix']);
        printf("ErrorDocument[Key]:%s\n", $resp ['ErrorDocument'] ['Key']);
        $i = 0;
        foreach ( $resp ['RoutingRules'] as $rout ) {
            printf("RoutingRules[$i][Condition][HttpErrorCodeReturnedEquals]:%s,RoutingRules[$i][Condition][KeyPrefixEquals]:%s\n", $rout ['Condition'] ['HttpErrorCodeReturnedEquals'], $rout ['Condition'] ['KeyPrefixEquals']);
            printf("RoutingRules[$i][Redirect][Protocol]:%s,RoutingRules[$i][Redirect][HostName]:%s,RoutingRules[$i][Redirect][ReplaceKeyPrefixWith]:%s,RoutingRules[$i][Redirect][ReplaceKeyWith]:%s,RoutingRules[$i][Redirect][HttpRedirectCode]:%s\n", $rout ['Redirect'] ['Protocol'], $rout ['Redirect'] ['HostName'], $rout ['Redirect'] ['ReplaceKeyPrefixWith'], $rout ['Redirect'] ['ReplaceKeyWith'], $rout ['Redirect'] ['HttpRedirectCode']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete bucket website configuration
function DeleteBucketWebsite() {
    global $obsClient;
    global $bucketName;
    echo "delete bucket website configuration start...\n";
    try {
        $resp = $obsClient->deleteBucketWebsite(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket versioning configuration
function SetBucketVersioning() {
    global $obsClient;
    global $bucketName;
    echo "set bucket versioning configuration start...\n";
    try {
        $resp = $obsClient->setBucketVersioning(array (
                'Bucket' => $bucketName,
                'Status' => 'Suspended'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket versioning configuration
function GetBucketVersioning() {
    global $obsClient;
    global $bucketName;
    echo "get bucket versioning configuration start...\n";
    try {
        $resp = $obsClient->getBucketVersioning(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("Status:%s\n", $resp ['Status']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket cors
function SetBucketCors() {
    global $obsClient;
    global $bucketName;
    echo "set bucket cors start...\n";
    try {
        $resp = $obsClient->setBucketCors(array (
                'Bucket' => $bucketName,
                'CorsRules' => array (
                        0 => array (
                                'ID' => '123456',
                                'AllowedMethod' => array (
                                        0 => "PUT",
                                        1 => "POST",
                                        2 => "GET",
                                        3 => "DELETE"
                                ),
                                'AllowedOrigin' => array (
                                        0 => "obs.hostname1"
                                ),
                                'AllowedHeader' => array (
                                        0 => "header-1",
                                        1 => "header-2"
                                )
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete bucket cors
function DeleteBucketCors() {
    global $obsClient;
    global $bucketName;
    echo "delete bucket cors start...\n";
    try {
        $resp = $obsClient->deleteBucketCors(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket cors
function GetBucketCors() {
    global $obsClient;
    global $bucketName;
    echo "get bucket cors start...\n";
    try {
        $resp = $obsClient->getBucketCors(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        print_r($resp ['CorsRules']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// options bucket
function OptionsBucket() {
    global $obsClient;
    global $bucketName;
    echo "options bucket start...\n";
    try {
        $resp = $obsClient->optionsBucket(array (
                'Bucket' => $bucketName,
                'Origin' => 'obs.hostname1',
                'AccessControlRequestMethods' => array (
                        0 => "PUT",
                        1 => "POST"
                ),
                'AccessControlRequestHeaders' => array (
                        0 => "header-1",
                        1 => "header-2"
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        print_r($resp);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket tagging
function SetBucketTagging() {
    global $obsClient;
    global $bucketName;
    echo "set bucket tagging start...\n";
    try {
        $resp = $obsClient->setBucketTagging(array (
                'Bucket' => $bucketName,
                'Tags' => array (
                        0 => array (
                                'Key' => 'testKey1',
                                'Value' => 'testValue1'
                        ),
                        1 => array (
                                'Key' => 'testKey2',
                                'Value' => 'testValue2'
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ["RequestId"]);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket tagging
function GetBucketTagging() {
    global $obsClient;
    global $bucketName;
    echo "get bucket tagging start...\n";
    try {
        $resp = $obsClient->getBucketTagging(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ["RequestId"]);
        foreach ( $resp ["Tags"] as $tag ) {
            printf("Tag[%s:%s]\n", $tag ["Key"], $tag ["Value"]);
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete bucket tagging
function DeleteBucketTagging() {
    global $obsClient;
    global $bucketName;
    echo "delete bucket tagging start...\n";
    try {
        $resp = $obsClient->deleteBucketTagging(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ["RequestId"]);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set bucket notification
function SetBucketNotification() {
    global $obsClient;
    global $bucketName;
    echo "set bucket notification start...\n";
    try {
        $resp = $obsClient->setBucketNotification(array (
                'Bucket' => $bucketName,
                'TopicConfigurations' => array (
                        0 => array (
                                'ID' => '001',
                                'Topic' => 'your topic',
                                'Event' => array (
                                        0 => 'ObjectCreated:*'
                                ),
                                'Filter' => array (
                                        0 => array (
                                                'Name' => 'prefix',
                                                'Value' => 'smn/'
                                        ),
                                        1 => array (
                                                'Name' => 'suffix',
                                                'Value' => '.jpg'
                                        )
                                )
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ["RequestId"]);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get bucket notification
function GetBucketNotification() {
    global $obsClient;
    global $bucketName;
    echo "get bucket notification start...\n";
    try {
        $resp = $obsClient->getBucketNotification(array (
                'Bucket' => $bucketName
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ["RequestId"]);
        print_r($resp ['TopicConfigurations']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete object
function DeleteObject() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "delete object start...\n";
    try {
        $resp = $obsClient->deleteObject(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'VersionId' => ''
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("DeleteMarker:%s,VersionId:%s\n", $resp ['DeleteMarker'], $resp ['VersionId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// options object
function OptionsObject() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "options bucket start...\n";
    try {
        $resp = $obsClient->optionsObject(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'Origin' => 'obs.hostname1',
                'AccessControlRequestMethods' => array (
                        "PUT",
                        "GET"
                ),
                'AccessControlRequestHeaders' => array (
                        0 => "header-1",
                        1 => "header-2"
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        print_r($resp);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// delete objects
function DeleteObjects() {
    global $obsClient;
    global $bucketName;
    echo "delete objects start...\n";
    try {
        $resp = $obsClient->deleteObjects(array (
                'Bucket' => $bucketName,
                'Objects' => array (
                        0 => array (
                                'Key' => 'test'
                        ),
                        1 => array (
                                'Key' => 'file.log'
                        )
                ),
                'Quiet' => false
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        $i = 0;
        foreach ( $resp ['Errors'] as $error ) {
            printf("Errors[$i][Key]:%s,Errors[$i][VersionId]:%s锛孍rrors[$i][Code]:%s锛孍rrors[$i][Message]:%s\n", $error ['Key'], $error ['VersionId'], $error ['Code'], $error ['Message']);
            $i ++;
        }
        $i = 0;
        foreach ( $resp ['Deleteds'] as $delete ) {
            printf("Deleteds[$i][Key]:%s,Deleted[$i][VersionId]:%s锛孌eleted[$i][DeleteMarker]:%s锛孌eleted[$i][DeleteMarkerVersionId]:%s\n", $delete ['Key'], $delete ['VersionId'], $delete ['DeleteMarker'], $delete ['DeleteMarkerVersionId']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// set object acl
function SetObjectAcl() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "set object ACL start...\n";
    try {
        $resp = $obsClient->setObjectAcl(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'Delivered' => true,
                'Grants' => array (
                        0 => array (
                                'Grantee' => array (
                                        'ID' => 'userid',
                                ),
                                'Permission' => ObsClient::PermissionWrite
                        ),
                        1 => array (
                                'Grantee' => array (
                                        'URI' => ObsClient::AllUsers
                                ),
                                'Permission' => ObsClient::PermissionRead
                        )
                ),
                'Owner' => array (
                        'ID' => 'ownerid'
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get object acl
function GetObjectAcl() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "get bucket ACL start...\n";
    try {
        $resp = $obsClient->getObjectAcl(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Owner[ID]:%s\n", $resp ['Owner'] ['ID']);
        printf("Delivered:%s\n", $resp['Delivered'] ? 'true' : 'false');
        $i = 0;
        foreach ( $resp ['Grants'] as $grant ) {
            printf("Grants[$i][Grantee][ID]:%s,Grants[$i][Grantee][URI]:%s\n", $grant ['Grantee'] ['ID'], $grant ['Grantee'] ['URI']);
            printf("Grants[$i][Permission]:%s\n", $grant ['Permission']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// restore object
function RestoreObject() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "restore object start...\n";

    try {
        $resp = $obsClient->restoreObject(array (
                "Bucket" => $bucketName,
                "Key" => $objectKey,
                "VersionId" => NULL,
                "Days" => 1,
                "Tier" => ObsClient::RestoreTierExpedited
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ["RequestId"]);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// put object
function PutObject() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "put object start...\n";
    try {
        $resp = $obsClient->putObject(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'Metadata' => array (
                        'test' => "value"
                ),
                // 'Body'=>'msg to put',
                'ContentType' => 'text/plain',
                'SourceFile' => '/temp/test.txt'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("ETag:%s,VersionId:%s\n", $resp ['ETag'], $resp ['VersionId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get object metadata
function GetObjectMetadata() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "get object metadata start...\n";
    try {
        $resp = $obsClient->getObjectMetadata(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Expiration:%s,LastModified:%s,ContentLength:%d,StorageClass:%s\n", $resp ['Expiration'], $resp ['LastModified'], $resp ['ContentLength'], $resp ['StorageClass']);
        printf("ETag:%s,VersionId:%s,WebsiteRedirectLocation:%s\n", $resp ['ETag'], $resp ['VersionId'], $resp ['WebsiteRedirectLocation']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// get object
function GetObject() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "get object start...\n";
    try {
        $resp = $obsClient->getObject(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'Range' => 'bytes=0-10',
                'SaveAsFile' => '/temp/test.txt'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Metadata:%s\n", json_encode($resp ['Metadata']));
        printf("DeleteMarker:%s,Expiration:%s,LastModified:%s\n", $resp ['DeleteMarker'], $resp ['Expiration'], $resp ['LastModified']);
        printf("ContentLength:%d,ETag:%s,VersionId:%s,SaveAsFile:%s\n", $resp ['ContentLength'], $resp ['ETag'], $resp ['VersionId'], $resp ['SaveAsFile']);
        printf("Expires:%s,WebsiteRedirectLocation:%s\n", $resp ['Expires'], $resp ['WebsiteRedirectLocation']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// copy object
function CopyObject() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "copy object start...\n";
    try {
        $resp = $obsClient->copyObject(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'CopySource' => 'bucket003/test',
                'Metadata' => array (
                        'test' => "value"
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("ETag:%s,VersionId:%s,LastModified:%s,CopySourceVersionId:%s\n", $resp ['ETag'], $resp ['VersionId'], $resp ['LastModified'], $resp ['CopySourceVersionId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// initiate multipart upload
function InitiateMultipartUpload() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "initiate mutipart upload start...\n";
    try {
        $resp = $obsClient->initiateMultipartUpload(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Bucket:%s,Key:%s,UploadId:%s\n", $resp ['Bucket'], $resp ['Key'], $resp ['UploadId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// abort multipart upload
function AbortMultipartUpload() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "abort mutipart upload start...\n";
    try {
        $resp = $obsClient->abortMultipartUpload(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'UploadId' => 'uploadid'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// list multipart uploads
function ListMultipartUploads() {
    global $obsClient;
    global $bucketName;
    echo "list mutipart upload start...\n";
    try {
        $resp = $obsClient->listMultipartUploads(array (
                'Bucket' => $bucketName,
                'MaxUploads' => 1000
        ));
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Bucket锛�%s,KeyMarker:%s,UploadIdMarker:%s,NextKeyMarker:%s\n", $resp ['Bucket'], $resp ['KeyMarker'], $resp ['UploadIdMarker'], $resp ['NextKeyMarker']);
        printf("Prefix:%s,Delimiter:%s,NextUploadIdMarker:%s,MaxUploads:%d,IsTruncated:%s\n", $resp ['Prefix'], $resp ['Delimiter'], $resp ['NextUploadIdMarker'], $resp ['MaxUploads'], $resp ['IsTruncated']);
        $i = 0;
        foreach ( $resp ['CommonPrefixes'] as $common ) {
            printf("CommonPrefixes[$i][Prefix]:%s\n", $common ['Prefix']);
            $i ++;
        }
        $i = 0;
        foreach ( $resp ['Uploads'] as $upload ) {
            printf("Uploads[$i][Key]:%s,Uploads[$i][UploadId]:%s,Uploads[$i][StorageClass]:%s,Uploads[$i][Initiated]:%s\n", $upload ['Key'], $upload ['UploadId'], $upload ['StorageClass'], $upload ['Initiated']);
            printf("Uploads[$i][Initiator][ID]:%s,Uploads[$i][Initiator][DisplayName]:%s\n", $upload ['Initiator'] ['ID'], $upload ['Initiator'] ['DisplayName']);
            printf("Uploads[$i][Owner][ID]:%s,Uploads[$i][Owner][DisplayName]:%s\n", $upload ['Owner'] ['ID'], $upload ['Owner'] ['DisplayName']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// upload part
function UploadPart() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "upload part start...\n";
    try {
        $resp = $obsClient->uploadPart(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'UploadId' => 'uploadid',
                'PartNumber' => 1,
                // 'Body' => 'test',
                'SourceFile' => '/temp/test.txt'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("ETag:%s\n", $resp ['ETag']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// copry part
function CopyPart() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "copy part start...\n";
    try {
        $resp = $obsClient->copyPart(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'UploadId' => 'uploadid',
                'PartNumber' => 1,
                'CopySource' => 'bucket003/test'
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("ETag:%s,LastModified:%s\n", $resp ['ETag'], $resp ['LastModified']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// list parts
function ListParts() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "list parts start...\n";
    try {
        $resp = $obsClient->listParts(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'UploadId' => 'uploadid',
                'MaxParts' => 500,
                'PartNumberMarker' => 0
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Bucket锛�%s,Key:%s,UploadId:%s,PartNumberMarker:%d\n", $resp ['Bucket'], $resp ['Key'], $resp ['UploadId'], $resp ['PartNumberMarker']);
        printf("NextPartNumberMarker:%d,MaxParts:%d,IsTruncated:%d,StorageClass:%s\n", $resp ['NextPartNumberMarker'], $resp ['MaxParts'], $resp ['IsTruncated'], $resp ['StorageClass']);
        printf("Initiator[ID]:%s,Initiator[DisplayName]:%s\n", $resp ['Initiator'] ['ID'], $resp ['Initiator'] ['DisplayName']);
        printf("Owner[ID]:%s,Owner[DisplayName]:%s\n", $resp ['Owner'] ['ID'], $resp ['Owner'] ['DisplayName']);
        $i = 0;
        foreach ( $resp ['Parts'] as $part ) {
            printf("Parts[$i][PartNumber]:%s,Parts[$i][LastModified]:%s,Parts[$i][ETag]:%s,Parts[$i][Size]:%d\n", $part ['PartNumber'], $part ['LastModified'], $part ['ETag'], $part ['Size']);
            $i ++;
        }
    } catch ( ObsException $e ) {
        echo $e;
    }
}

// merge parts
function CompleteMultipartUpload() {
    global $obsClient;
    global $bucketName;
    global $objectKey;
    echo "complete multipart upload start...\n";
    try {
        $resp = $obsClient->completeMultipartUpload(array (
                'Bucket' => $bucketName,
                'Key' => $objectKey,
                'UploadId' => 'uploadid',
                'Parts' => array (
                        0 => array (
                                'PartNumber' => 1,
                                'ETag' => 'etagvalue'
                        )
                )
        ));
        printf("HttpStatusCode:%s\n", $resp ['HttpStatusCode']);
        printf("RequestId:%s\n", $resp ['RequestId']);
        printf("Bucket:%s,Key:%s,ETag:%s,VersionId:%s,Location:%s\n", $resp ['Bucket'], $resp ['Key'], $resp ['ETag'], $resp ['VersionId'], $resp ['Location']);
    } catch ( ObsException $e ) {
        echo $e;
    }
}

//----bucket related apis---
// CreateBucket();
// ListBuckets();
// DeleteBucket();
// ListObjects();
// ListVersions();
// HeadBucket();
// GetBucketMetadata();
// GetBucketLocation();
// GetBucketStorageInfo();
// SetBucketQuota();
// GetBucketQuota();
// SetBucketStoragePolicy();
// GetBucketStoragePolicy();
// SetBucketAcl();
// GetBucketAcl();
// SetBucketLogging();
// GetBucketLogging();
// SetBucketPolicy();
// GetBucketPolicy();
// DeleteBucketPolicy();
// SetBucketLifecycle();
// GetBucketLifecycle();
// DeleteBucketLifecycle();
// SetBucketWebsite();
// GetBucketWebsite();
// DeleteBucketWebsite();
// SetBucketVersioning();
// GetBucketVersioning();
// SetBucketCors();
// GetBucketCors();
// DeleteBucketCors();
// OptionsBucket();
// SetBucketTagging();
// GetBucketTagging();
// DeleteBucketTagging();
// SetBucketNotification();
// GetBucketNotification();

//-----object related apis--------
// DeleteObject();
// OptionsObject();
// SetObjectAcl();
// GetObjectAcl();
// RestoreObject();
// DeleteObjects();
// PutObject();
// GetObject();
// CopyObject();
// GetObjectMetadata();
// InitiateMultipartUpload();
// ListMultipartUploads();
// AbortMultipartUpload();
// UploadPart();
// ListParts();
// CompleteMultipartUpload();
// CopyPart();








