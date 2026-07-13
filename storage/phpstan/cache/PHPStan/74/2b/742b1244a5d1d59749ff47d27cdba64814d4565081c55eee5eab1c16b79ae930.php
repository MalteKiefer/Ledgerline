<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3ClientInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Aws\S3\S3ClientInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-87c7fcd2dbf9aa14ca83e9ce8ec4c63a906f3001b662ad47dd37bf9a3fb00a70-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Aws\\S3\\S3ClientInterface',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3ClientInterface.php',
      ),
    ),
    'namespace' => 'Aws\\S3',
    'name' => 'Aws\\S3\\S3ClientInterface',
    'shortName' => 'S3ClientInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * **Amazon Simple Storage Service** client.
 *
 * @method \\Aws\\Result abortMultipartUpload(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise abortMultipartUploadAsync(array $args = [])
 * @method \\Aws\\Result completeMultipartUpload(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise completeMultipartUploadAsync(array $args = [])
 * @method \\Aws\\Result copyObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise copyObjectAsync(array $args = [])
 * @method \\Aws\\Result createBucket(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise createBucketAsync(array $args = [])
 * @method \\Aws\\Result createBucketMetadataConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise createBucketMetadataConfigurationAsync(array $args = [])
 * @method \\Aws\\Result createBucketMetadataTableConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise createBucketMetadataTableConfigurationAsync(array $args = [])
 * @method \\Aws\\Result createMultipartUpload(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise createMultipartUploadAsync(array $args = [])
 * @method \\Aws\\Result createSession(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise createSessionAsync(array $args = [])
 * @method \\Aws\\Result deleteBucket(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketAnalyticsConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketCors(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketCorsAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketEncryption(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketEncryptionAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketIntelligentTieringConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketInventoryConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketInventoryConfigurationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketLifecycle(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketLifecycleAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketMetadataConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketMetadataConfigurationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketMetadataTableConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketMetadataTableConfigurationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketMetricsConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketMetricsConfigurationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketOwnershipControls(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketOwnershipControlsAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketPolicy(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketPolicyAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketReplication(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketReplicationAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketTagging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketTaggingAsync(array $args = [])
 * @method \\Aws\\Result deleteBucketWebsite(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteBucketWebsiteAsync(array $args = [])
 * @method \\Aws\\Result deleteObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteObjectAsync(array $args = [])
 * @method \\Aws\\Result deleteObjectAnnotation(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteObjectAnnotationAsync(array $args = [])
 * @method \\Aws\\Result deleteObjectTagging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteObjectTaggingAsync(array $args = [])
 * @method \\Aws\\Result deleteObjects(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deleteObjectsAsync(array $args = [])
 * @method \\Aws\\Result deletePublicAccessBlock(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise deletePublicAccessBlockAsync(array $args = [])
 * @method \\Aws\\Result getBucketAbac(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketAbacAsync(array $args = [])
 * @method \\Aws\\Result getBucketAccelerateConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketAccelerateConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketAcl(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketAclAsync(array $args = [])
 * @method \\Aws\\Result getBucketAnalyticsConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketCors(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketCorsAsync(array $args = [])
 * @method \\Aws\\Result getBucketEncryption(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketEncryptionAsync(array $args = [])
 * @method \\Aws\\Result getBucketIntelligentTieringConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketInventoryConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketInventoryConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketLifecycle(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketLifecycleAsync(array $args = [])
 * @method \\Aws\\Result getBucketLifecycleConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketLifecycleConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketLocation(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketLocationAsync(array $args = [])
 * @method \\Aws\\Result getBucketLogging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketLoggingAsync(array $args = [])
 * @method \\Aws\\Result getBucketMetadataConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketMetadataConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketMetadataTableConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketMetadataTableConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketMetricsConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketMetricsConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketNotification(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketNotificationAsync(array $args = [])
 * @method \\Aws\\Result getBucketNotificationConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketNotificationConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getBucketOwnershipControls(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketOwnershipControlsAsync(array $args = [])
 * @method \\Aws\\Result getBucketPolicy(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketPolicyAsync(array $args = [])
 * @method \\Aws\\Result getBucketPolicyStatus(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketPolicyStatusAsync(array $args = [])
 * @method \\Aws\\Result getBucketReplication(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketReplicationAsync(array $args = [])
 * @method \\Aws\\Result getBucketRequestPayment(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketRequestPaymentAsync(array $args = [])
 * @method \\Aws\\Result getBucketTagging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketTaggingAsync(array $args = [])
 * @method \\Aws\\Result getBucketVersioning(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketVersioningAsync(array $args = [])
 * @method \\Aws\\Result getBucketWebsite(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getBucketWebsiteAsync(array $args = [])
 * @method \\Aws\\Result getObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectAsync(array $args = [])
 * @method \\Aws\\Result getObjectAcl(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectAclAsync(array $args = [])
 * @method \\Aws\\Result getObjectAnnotation(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectAnnotationAsync(array $args = [])
 * @method \\Aws\\Result getObjectAttributes(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectAttributesAsync(array $args = [])
 * @method \\Aws\\Result getObjectLegalHold(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectLegalHoldAsync(array $args = [])
 * @method \\Aws\\Result getObjectLockConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectLockConfigurationAsync(array $args = [])
 * @method \\Aws\\Result getObjectRetention(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectRetentionAsync(array $args = [])
 * @method \\Aws\\Result getObjectTagging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectTaggingAsync(array $args = [])
 * @method \\Aws\\Result getObjectTorrent(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getObjectTorrentAsync(array $args = [])
 * @method \\Aws\\Result getPublicAccessBlock(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise getPublicAccessBlockAsync(array $args = [])
 * @method \\Aws\\Result headBucket(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise headBucketAsync(array $args = [])
 * @method \\Aws\\Result headObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise headObjectAsync(array $args = [])
 * @method \\Aws\\Result listBucketAnalyticsConfigurations(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listBucketAnalyticsConfigurationsAsync(array $args = [])
 * @method \\Aws\\Result listBucketIntelligentTieringConfigurations(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listBucketIntelligentTieringConfigurationsAsync(array $args = [])
 * @method \\Aws\\Result listBucketInventoryConfigurations(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listBucketInventoryConfigurationsAsync(array $args = [])
 * @method \\Aws\\Result listBucketMetricsConfigurations(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listBucketMetricsConfigurationsAsync(array $args = [])
 * @method \\Aws\\Result listBuckets(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listBucketsAsync(array $args = [])
 * @method \\Aws\\Result listDirectoryBuckets(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listDirectoryBucketsAsync(array $args = [])
 * @method \\Aws\\Result listMultipartUploads(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listMultipartUploadsAsync(array $args = [])
 * @method \\Aws\\Result listObjectAnnotations(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listObjectAnnotationsAsync(array $args = [])
 * @method \\Aws\\Result listObjectVersions(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listObjectVersionsAsync(array $args = [])
 * @method \\Aws\\Result listObjects(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listObjectsAsync(array $args = [])
 * @method \\Aws\\Result listObjectsV2(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listObjectsV2Async(array $args = [])
 * @method \\Aws\\Result listParts(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise listPartsAsync(array $args = [])
 * @method \\Aws\\Result putBucketAbac(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketAbacAsync(array $args = [])
 * @method \\Aws\\Result putBucketAccelerateConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketAccelerateConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketAcl(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketAclAsync(array $args = [])
 * @method \\Aws\\Result putBucketAnalyticsConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketAnalyticsConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketCors(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketCorsAsync(array $args = [])
 * @method \\Aws\\Result putBucketEncryption(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketEncryptionAsync(array $args = [])
 * @method \\Aws\\Result putBucketIntelligentTieringConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketIntelligentTieringConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketInventoryConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketInventoryConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketLifecycle(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketLifecycleAsync(array $args = [])
 * @method \\Aws\\Result putBucketLifecycleConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketLifecycleConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketLogging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketLoggingAsync(array $args = [])
 * @method \\Aws\\Result putBucketMetricsConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketMetricsConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketNotification(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketNotificationAsync(array $args = [])
 * @method \\Aws\\Result putBucketNotificationConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketNotificationConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putBucketOwnershipControls(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketOwnershipControlsAsync(array $args = [])
 * @method \\Aws\\Result putBucketPolicy(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketPolicyAsync(array $args = [])
 * @method \\Aws\\Result putBucketReplication(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketReplicationAsync(array $args = [])
 * @method \\Aws\\Result putBucketRequestPayment(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketRequestPaymentAsync(array $args = [])
 * @method \\Aws\\Result putBucketTagging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketTaggingAsync(array $args = [])
 * @method \\Aws\\Result putBucketVersioning(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketVersioningAsync(array $args = [])
 * @method \\Aws\\Result putBucketWebsite(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putBucketWebsiteAsync(array $args = [])
 * @method \\Aws\\Result putObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectAsync(array $args = [])
 * @method \\Aws\\Result putObjectAcl(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectAclAsync(array $args = [])
 * @method \\Aws\\Result putObjectAnnotation(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectAnnotationAsync(array $args = [])
 * @method \\Aws\\Result putObjectLegalHold(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectLegalHoldAsync(array $args = [])
 * @method \\Aws\\Result putObjectLockConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectLockConfigurationAsync(array $args = [])
 * @method \\Aws\\Result putObjectRetention(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectRetentionAsync(array $args = [])
 * @method \\Aws\\Result putObjectTagging(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putObjectTaggingAsync(array $args = [])
 * @method \\Aws\\Result putPublicAccessBlock(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise putPublicAccessBlockAsync(array $args = [])
 * @method \\Aws\\Result renameObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise renameObjectAsync(array $args = [])
 * @method \\Aws\\Result restoreObject(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise restoreObjectAsync(array $args = [])
 * @method \\Aws\\Result selectObjectContent(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise selectObjectContentAsync(array $args = [])
 * @method \\Aws\\Result updateBucketMetadataAnnotationTableConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise updateBucketMetadataAnnotationTableConfigurationAsync(array $args = [])
 * @method \\Aws\\Result updateBucketMetadataInventoryTableConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise updateBucketMetadataInventoryTableConfigurationAsync(array $args = [])
 * @method \\Aws\\Result updateBucketMetadataJournalTableConfiguration(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise updateBucketMetadataJournalTableConfigurationAsync(array $args = [])
 * @method \\Aws\\Result updateObjectEncryption(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise updateObjectEncryptionAsync(array $args = [])
 * @method \\Aws\\Result uploadPart(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise uploadPartAsync(array $args = [])
 * @method \\Aws\\Result uploadPartCopy(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise uploadPartCopyAsync(array $args = [])
 * @method \\Aws\\Result writeGetObjectResponse(array $args = [])
 * @method \\GuzzleHttp\\Promise\\Promise writeGetObjectResponseAsync(array $args = [])
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 247,
    'endLine' => 605,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Aws\\AwsClientInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'createPresignedRequest' => 
      array (
        'name' => 'createPresignedRequest',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\CommandInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 262,
            'endLine' => 262,
            'startColumn' => 44,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expires' => 
          array (
            'name' => 'expires',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 262,
            'endLine' => 262,
            'startColumn' => 71,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 262,
                'endLine' => 262,
                'startTokenPos' => 70,
                'startFilePos' => 18390,
                'endTokenPos' => 71,
                'endFilePos' => 18391,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 262,
            'endLine' => 262,
            'startColumn' => 81,
            'endColumn' => 99,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a pre-signed URL for the given S3 command object.
 *
 * @param CommandInterface              $command Command to create a pre-signed
 *                                               URL for.
 * @param int|string|\\DateTimeInterface $expires The time at which the URL should
 *                                               expire. This can be a Unix
 *                                               timestamp, a PHP DateTime object,
 *                                               or a string that can be evaluated
 *                                               by strtotime().
 *
 * @return RequestInterface
 */',
        'startLine' => 262,
        'endLine' => 262,
        'startColumn' => 5,
        'endColumn' => 101,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'getObjectUrl' => 
      array (
        'name' => 'getObjectUrl',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 277,
            'endLine' => 277,
            'startColumn' => 43,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the URL to an object identified by its bucket and key.
 *
 * The URL returned by this method is not signed nor does it ensure that the
 * bucket and key given to the method exist. If you need a signed URL, then
 * use the {@see \\Aws\\S3\\S3Client::createPresignedRequest} method and get
 * the URI of the signed request.
 *
 * @param string $bucket  The name of the bucket where the object is located
 * @param string $key     The key of the object
 *
 * @return string The URL to the object
 */',
        'startLine' => 277,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'doesBucketExist' => 
      array (
        'name' => 'doesBucketExist',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 288,
            'endLine' => 288,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @deprecated Use doesBucketExistV2() instead
 *
 * Determines whether or not a bucket exists by name.
 *
 * @param string $bucket  The name of the bucket
 *
 * @return bool
 */',
        'startLine' => 288,
        'endLine' => 288,
        'startColumn' => 5,
        'endColumn' => 45,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'doesBucketExistV2' => 
      array (
        'name' => 'doesBucketExistV2',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 304,
            'endLine' => 304,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'accept403' => 
          array (
            'name' => 'accept403',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 304,
            'endLine' => 304,
            'startColumn' => 48,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determines whether or not a bucket exists by name. This method uses S3\'s
 * HeadBucket operation and requires the relevant bucket permissions in the
 * default case to prevent errors.
 *
 * @param string $bucket  The name of the bucket
 * @param bool $accept403 Set to true for this method to return true in the case of
 *                        invalid bucket-level permissions. Credentials MUST be valid
 *                        to avoid inaccuracies. Using the default value of false will
 *                        cause an exception to be thrown instead.
 *
 * @return bool
 * @throws S3Exception|\\Exception if there is an unhandled exception
 */',
        'startLine' => 304,
        'endLine' => 304,
        'startColumn' => 5,
        'endColumn' => 59,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'doesObjectExist' => 
      array (
        'name' => 'doesObjectExist',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 318,
            'endLine' => 318,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 318,
            'endLine' => 318,
            'startColumn' => 46,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 318,
                'endLine' => 318,
                'startTokenPos' => 137,
                'startFilePos' => 20525,
                'endTokenPos' => 138,
                'endFilePos' => 20526,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 318,
            'endLine' => 318,
            'startColumn' => 52,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @deprecated Use doesObjectExistV2() instead
 *
 * Determines whether or not an object exists by name.
 *
 * @param string $bucket  The name of the bucket
 * @param string $key     The key of the object
 * @param array  $options Additional options available in the HeadObject
 *                        operation (e.g., VersionId).
 *
 * @return bool
 */',
        'startLine' => 318,
        'endLine' => 318,
        'startColumn' => 5,
        'endColumn' => 72,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'doesObjectExistV2' => 
      array (
        'name' => 'doesObjectExistV2',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 48,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'includeDeleteMarkers' => 
          array (
            'name' => 'includeDeleteMarkers',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 336,
                'endLine' => 336,
                'startTokenPos' => 160,
                'startFilePos' => 21449,
                'endTokenPos' => 160,
                'endFilePos' => 21453,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 54,
            'endColumn' => 82,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 336,
                'endLine' => 336,
                'startTokenPos' => 169,
                'startFilePos' => 21473,
                'endTokenPos' => 170,
                'endFilePos' => 21474,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 85,
            'endColumn' => 103,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determines whether or not an object exists by name. This method uses S3\'s HeadObject
 * operation and requires the relevant bucket and object permissions to prevent errors.
 *
 * @param string $bucket The name of the bucket
 * @param string $key The key of the object
 * @param bool $includeDeleteMarkers Set to true to consider delete markers
 *                                   existing objects. Using the default value
 *                                   of false will ignore delete markers and
 *                                   return false.
 * @param array $options Additional options available in the HeadObject
 *                        operation (e.g., VersionId).
 *
 * @return bool
 * @throws S3Exception|\\Exception if there is an unhandled exception
 */',
        'startLine' => 336,
        'endLine' => 336,
        'startColumn' => 5,
        'endColumn' => 105,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'registerStreamWrapper' => 
      array (
        'name' => 'registerStreamWrapper',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register the Amazon S3 stream wrapper with this client instance.
 */',
        'startLine' => 341,
        'endLine' => 341,
        'startColumn' => 5,
        'endColumn' => 44,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'registerStreamWrapperV2' => 
      array (
        'name' => 'registerStreamWrapperV2',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Registers the Amazon S3 stream wrapper with this client instance.
 *
 *This version uses doesObjectExistV2 and doesBucketExistV2 to check
 * resource existence.
 */',
        'startLine' => 349,
        'endLine' => 349,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'deleteMatchingObjects' => 
      array (
        'name' => 'deleteMatchingObjects',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 365,
            'endLine' => 365,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prefix' => 
          array (
            'name' => 'prefix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 366,
                'endLine' => 366,
                'startTokenPos' => 212,
                'startFilePos' => 22562,
                'endTokenPos' => 212,
                'endFilePos' => 22563,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 366,
            'endLine' => 366,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'regex' => 
          array (
            'name' => 'regex',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 367,
                'endLine' => 367,
                'startTokenPos' => 219,
                'startFilePos' => 22583,
                'endTokenPos' => 219,
                'endFilePos' => 22584,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 367,
            'endLine' => 367,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 368,
                'endLine' => 368,
                'startTokenPos' => 228,
                'startFilePos' => 22612,
                'endTokenPos' => 229,
                'endFilePos' => 22613,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 368,
            'endLine' => 368,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Deletes objects from Amazon S3 that match the result of a ListObjects
 * operation. For example, this allows you to do things like delete all
 * objects that match a specific key prefix.
 *
 * @param string $bucket  Bucket that contains the object keys
 * @param string $prefix  Optionally delete only objects under this key prefix
 * @param string $regex   Delete only objects that match this regex
 * @param array  $options Aws\\S3\\BatchDelete options array.
 *
 * @see Aws\\S3\\S3Client::listObjects
 * @throws \\RuntimeException if no prefix and no regex is given
 */',
        'startLine' => 364,
        'endLine' => 369,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'deleteMatchingObjectsAsync' => 
      array (
        'name' => 'deleteMatchingObjectsAsync',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prefix' => 
          array (
            'name' => 'prefix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 388,
                'endLine' => 388,
                'startTokenPos' => 250,
                'startFilePos' => 23405,
                'endTokenPos' => 250,
                'endFilePos' => 23406,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 388,
            'endLine' => 388,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'regex' => 
          array (
            'name' => 'regex',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 389,
                'endLine' => 389,
                'startTokenPos' => 257,
                'startFilePos' => 23426,
                'endTokenPos' => 257,
                'endFilePos' => 23427,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 389,
            'endLine' => 389,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 390,
                'endLine' => 390,
                'startTokenPos' => 266,
                'startFilePos' => 23455,
                'endTokenPos' => 267,
                'endFilePos' => 23456,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Deletes objects from Amazon S3 that match the result of a ListObjects
 * operation. For example, this allows you to do things like delete all
 * objects that match a specific key prefix.
 *
 * @param string $bucket  Bucket that contains the object keys
 * @param string $prefix  Optionally delete only objects under this key prefix
 * @param string $regex   Delete only objects that match this regex
 * @param array  $options Aws\\S3\\BatchDelete options array.
 *
 * @see Aws\\S3\\S3Client::listObjects
 *
 * @return PromiseInterface     A promise that is settled when matching
 *                              objects are deleted.
 */',
        'startLine' => 386,
        'endLine' => 391,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'upload' => 
      array (
        'name' => 'upload',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 426,
            'endLine' => 426,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 427,
            'endLine' => 427,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'body' => 
          array (
            'name' => 'body',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 428,
            'endLine' => 428,
            'startColumn' => 9,
            'endColumn' => 13,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'acl' => 
          array (
            'name' => 'acl',
            'default' => 
            array (
              'code' => '\'private\'',
              'attributes' => 
              array (
                'startLine' => 429,
                'endLine' => 429,
                'startTokenPos' => 294,
                'startFilePos' => 25333,
                'endTokenPos' => 294,
                'endFilePos' => 25341,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 429,
            'endLine' => 429,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 430,
                'endLine' => 430,
                'startTokenPos' => 303,
                'startFilePos' => 25369,
                'endTokenPos' => 304,
                'endFilePos' => 25370,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 430,
            'endLine' => 430,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Upload a file, stream, or string to a bucket.
 *
 * If the upload size exceeds the specified threshold, the upload will be
 * performed using concurrent multipart uploads.
 *
 * The options array accepts the following options:
 *
 * - before_upload: (callable) Callback to invoke before any upload
 *   operations during the upload process. The callback should have a
 *   function signature like `function (Aws\\Command $command) {...}`.
 * - concurrency: (int, default=int(3)) Maximum number of concurrent
 *   `UploadPart` operations allowed during a multipart upload.
 * - mup_threshold: (int, default=int(16777216)) The size, in bytes, allowed
 *   before the upload must be sent via a multipart upload. Default: 16 MB.
 * - params: (array, default=array([])) Custom parameters to use with the
 *   upload. For single uploads, they must correspond to those used for the
 *   `PutObject` operation. For multipart uploads, they correspond to the
 *   parameters of the `CreateMultipartUpload` operation.
 * - part_size: (int) Part size to use when doing a multipart upload.
 *
 * @param string $bucket  Bucket to upload the object.
 * @param string $key     Key of the object.
 * @param mixed  $body    Object data to upload. Can be a
 *                        StreamInterface, PHP stream resource, or a
 *                        string of data to upload.
 * @param string $acl     ACL to apply to the object (default: private).
 * @param array  $options Options used to configure the upload process.
 *
 * @see Aws\\S3\\MultipartUploader for more info about multipart uploads.
 * @return ResultInterface Returns the result of the upload.
 */',
        'startLine' => 425,
        'endLine' => 431,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'uploadAsync' => 
      array (
        'name' => 'uploadAsync',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 449,
            'endLine' => 449,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 450,
            'endLine' => 450,
            'startColumn' => 9,
            'endColumn' => 12,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'body' => 
          array (
            'name' => 'body',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 9,
            'endColumn' => 13,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'acl' => 
          array (
            'name' => 'acl',
            'default' => 
            array (
              'code' => '\'private\'',
              'attributes' => 
              array (
                'startLine' => 452,
                'endLine' => 452,
                'startTokenPos' => 331,
                'startFilePos' => 26193,
                'endTokenPos' => 331,
                'endFilePos' => 26201,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 452,
            'endLine' => 452,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 453,
                'endLine' => 453,
                'startTokenPos' => 340,
                'startFilePos' => 26229,
                'endTokenPos' => 341,
                'endFilePos' => 26230,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 453,
            'endLine' => 453,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Upload a file, stream, or string to a bucket asynchronously.
 *
 * @param string $bucket  Bucket to upload the object.
 * @param string $key     Key of the object.
 * @param mixed  $body    Object data to upload. Can be a
 *                        StreamInterface, PHP stream resource, or a
 *                        string of data to upload.
 * @param string $acl     ACL to apply to the object (default: private).
 * @param array  $options Options used to configure the upload process.
 *
 * @see self::upload
 * @return PromiseInterface     Returns a promise that will be fulfilled
 *                              with the result of the upload.
 */',
        'startLine' => 448,
        'endLine' => 454,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'copy' => 
      array (
        'name' => 'copy',
        'parameters' => 
        array (
          'fromBucket' => 
          array (
            'name' => 'fromBucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 486,
            'endLine' => 486,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fromKey' => 
          array (
            'name' => 'fromKey',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 9,
            'endColumn' => 16,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destBucket' => 
          array (
            'name' => 'destBucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 488,
            'endLine' => 488,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'destKey' => 
          array (
            'name' => 'destKey',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 489,
            'endLine' => 489,
            'startColumn' => 9,
            'endColumn' => 16,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'acl' => 
          array (
            'name' => 'acl',
            'default' => 
            array (
              'code' => '\'private\'',
              'attributes' => 
              array (
                'startLine' => 490,
                'endLine' => 490,
                'startTokenPos' => 371,
                'startFilePos' => 27954,
                'endTokenPos' => 371,
                'endFilePos' => 27962,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 490,
            'endLine' => 490,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 491,
                'endLine' => 491,
                'startTokenPos' => 380,
                'startFilePos' => 27990,
                'endTokenPos' => 381,
                'endFilePos' => 27991,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 491,
            'endLine' => 491,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Copy an object of any size to a different location.
 *
 * If the upload size exceeds the maximum allowable size for direct S3
 * copying, a multipart copy will be used.
 *
 * The options array accepts the following options:
 *
 * - before_upload: (callable) Callback to invoke before any upload
 *   operations during the upload process. The callback should have a
 *   function signature like `function (Aws\\Command $command) {...}`.
 * - concurrency: (int, default=int(5)) Maximum number of concurrent
 *   `UploadPart` operations allowed during a multipart upload.
 * - params: (array, default=array([])) Custom parameters to use with the
 *   upload. For single uploads, they must correspond to those used for the
 *   `CopyObject` operation. For multipart uploads, they correspond to the
 *   parameters of the `CreateMultipartUpload` operation.
 * - part_size: (int) Part size to use when doing a multipart upload.
 *
 * @param string $fromBucket    Bucket where the copy source resides.
 * @param string $fromKey       Key of the copy source.
 * @param string $destBucket    Bucket to which to copy the object.
 * @param string $destKey       Key to which to copy the object.
 * @param string $acl           ACL to apply to the copy (default: private).
 * @param array  $options       Options used to configure the upload process.
 *
 * @see Aws\\S3\\MultipartCopy for more info about multipart uploads.
 * @return ResultInterface Returns the result of the copy.
 */',
        'startLine' => 485,
        'endLine' => 492,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'copyAsync' => 
      array (
        'name' => 'copyAsync',
        'parameters' => 
        array (
          'fromBucket' => 
          array (
            'name' => 'fromBucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 509,
            'endLine' => 509,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fromKey' => 
          array (
            'name' => 'fromKey',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 510,
            'endLine' => 510,
            'startColumn' => 9,
            'endColumn' => 16,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destBucket' => 
          array (
            'name' => 'destBucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 511,
            'endLine' => 511,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'destKey' => 
          array (
            'name' => 'destKey',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 512,
            'endLine' => 512,
            'startColumn' => 9,
            'endColumn' => 16,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'acl' => 
          array (
            'name' => 'acl',
            'default' => 
            array (
              'code' => '\'private\'',
              'attributes' => 
              array (
                'startLine' => 513,
                'endLine' => 513,
                'startTokenPos' => 411,
                'startFilePos' => 28874,
                'endTokenPos' => 411,
                'endFilePos' => 28882,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 513,
            'endLine' => 513,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 514,
                'endLine' => 514,
                'startTokenPos' => 420,
                'startFilePos' => 28910,
                'endTokenPos' => 421,
                'endFilePos' => 28911,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 514,
            'endLine' => 514,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Copy an object of any size to a different location asynchronously.
 *
 * @param string $fromBucket    Bucket where the copy source resides.
 * @param string $fromKey       Key of the copy source.
 * @param string $destBucket    Bucket to which to copy the object.
 * @param string $destKey       Key to which to copy the object.
 * @param string $acl           ACL to apply to the copy (default: private).
 * @param array  $options       Options used to configure the upload process.
 *
 * @see self::copy for more info about the parameters above.
 * @return PromiseInterface     Returns a promise that will be fulfilled
 *                              with the result of the copy.
 */',
        'startLine' => 508,
        'endLine' => 515,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'uploadDirectory' => 
      array (
        'name' => 'uploadDirectory',
        'parameters' => 
        array (
          'directory' => 
          array (
            'name' => 'directory',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 528,
            'endLine' => 528,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 529,
            'endLine' => 529,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'keyPrefix' => 
          array (
            'name' => 'keyPrefix',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 530,
                'endLine' => 530,
                'startTokenPos' => 445,
                'startFilePos' => 29469,
                'endTokenPos' => 445,
                'endFilePos' => 29472,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 530,
            'endLine' => 530,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 531,
                'endLine' => 531,
                'startTokenPos' => 454,
                'startFilePos' => 29500,
                'endTokenPos' => 455,
                'endFilePos' => 29501,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 531,
            'endLine' => 531,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Recursively uploads all files in a given directory to a given bucket.
 *
 * @param string $directory Full path to a directory to upload
 * @param string $bucket    Name of the bucket
 * @param string $keyPrefix Virtual directory key prefix to add to each upload
 * @param array  $options   Options available in Aws\\S3\\Transfer::__construct
 *
 * @see Aws\\S3\\Transfer for more options and customization
 */',
        'startLine' => 527,
        'endLine' => 532,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'uploadDirectoryAsync' => 
      array (
        'name' => 'uploadDirectoryAsync',
        'parameters' => 
        array (
          'directory' => 
          array (
            'name' => 'directory',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 548,
            'endLine' => 548,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 549,
            'endLine' => 549,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'keyPrefix' => 
          array (
            'name' => 'keyPrefix',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 550,
                'endLine' => 550,
                'startTokenPos' => 479,
                'startFilePos' => 30190,
                'endTokenPos' => 479,
                'endFilePos' => 30193,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 550,
            'endLine' => 550,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 551,
                'endLine' => 551,
                'startTokenPos' => 488,
                'startFilePos' => 30221,
                'endTokenPos' => 489,
                'endFilePos' => 30222,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 551,
            'endLine' => 551,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Recursively uploads all files in a given directory to a given bucket.
 *
 * @param string $directory Full path to a directory to upload
 * @param string $bucket    Name of the bucket
 * @param string $keyPrefix Virtual directory key prefix to add to each upload
 * @param array  $options   Options available in Aws\\S3\\Transfer::__construct
 *
 * @see Aws\\S3\\Transfer for more options and customization
 *
 * @return PromiseInterface A promise that is settled when the upload is
 *                          complete.
 */',
        'startLine' => 547,
        'endLine' => 552,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'downloadBucket' => 
      array (
        'name' => 'downloadBucket',
        'parameters' => 
        array (
          'directory' => 
          array (
            'name' => 'directory',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 563,
            'endLine' => 563,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 564,
            'endLine' => 564,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'keyPrefix' => 
          array (
            'name' => 'keyPrefix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 565,
                'endLine' => 565,
                'startTokenPos' => 513,
                'startFilePos' => 30673,
                'endTokenPos' => 513,
                'endFilePos' => 30674,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 565,
            'endLine' => 565,
            'startColumn' => 9,
            'endColumn' => 23,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 566,
                'endLine' => 566,
                'startTokenPos' => 522,
                'startFilePos' => 30702,
                'endTokenPos' => 523,
                'endFilePos' => 30703,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 566,
            'endLine' => 566,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Downloads a bucket to the local filesystem
 *
 * @param string $directory Directory to download to
 * @param string $bucket    Bucket to download from
 * @param string $keyPrefix Only download objects that use this key prefix
 * @param array  $options   Options available in Aws\\S3\\Transfer::__construct
 */',
        'startLine' => 562,
        'endLine' => 567,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'downloadBucketAsync' => 
      array (
        'name' => 'downloadBucketAsync',
        'parameters' => 
        array (
          'directory' => 
          array (
            'name' => 'directory',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 581,
            'endLine' => 581,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 582,
            'endLine' => 582,
            'startColumn' => 9,
            'endColumn' => 15,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'keyPrefix' => 
          array (
            'name' => 'keyPrefix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 583,
                'endLine' => 583,
                'startTokenPos' => 547,
                'startFilePos' => 31287,
                'endTokenPos' => 547,
                'endFilePos' => 31288,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 583,
            'endLine' => 583,
            'startColumn' => 9,
            'endColumn' => 23,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 584,
                'endLine' => 584,
                'startTokenPos' => 556,
                'startFilePos' => 31316,
                'endTokenPos' => 557,
                'endFilePos' => 31317,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 584,
            'endLine' => 584,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Downloads a bucket to the local filesystem
 *
 * @param string $directory Directory to download to
 * @param string $bucket    Bucket to download from
 * @param string $keyPrefix Only download objects that use this key prefix
 * @param array  $options   Options available in Aws\\S3\\Transfer::__construct
 *
 * @return PromiseInterface A promise that is settled when the download is
 *                          complete.
 */',
        'startLine' => 580,
        'endLine' => 585,
        'startColumn' => 5,
        'endColumn' => 6,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'determineBucketRegion' => 
      array (
        'name' => 'determineBucketRegion',
        'parameters' => 
        array (
          'bucketName' => 
          array (
            'name' => 'bucketName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 594,
            'endLine' => 594,
            'startColumn' => 43,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the region in which a given bucket is located.
 *
 * @param string $bucketName
 *
 * @return string
 */',
        'startLine' => 594,
        'endLine' => 594,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
      'determineBucketRegionAsync' => 
      array (
        'name' => 'determineBucketRegionAsync',
        'parameters' => 
        array (
          'bucketName' => 
          array (
            'name' => 'bucketName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 604,
            'endLine' => 604,
            'startColumn' => 48,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a promise fulfilled with the region in which a given bucket is
 * located.
 *
 * @param string $bucketName
 *
 * @return PromiseInterface
 */',
        'startLine' => 604,
        'endLine' => 604,
        'startColumn' => 5,
        'endColumn' => 60,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientInterface',
        'implementingClassName' => 'Aws\\S3\\S3ClientInterface',
        'currentClassName' => 'Aws\\S3\\S3ClientInterface',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));