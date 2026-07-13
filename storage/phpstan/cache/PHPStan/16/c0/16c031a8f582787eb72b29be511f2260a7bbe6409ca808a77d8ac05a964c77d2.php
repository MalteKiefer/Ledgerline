<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3Client.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Aws\S3\S3Client
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-9a99762201ac8202543bc4c0dbc742f45646c58f3c94e3ecbd5387c5436a05ad-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Aws\\S3\\S3Client',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3Client.php',
      ),
    ),
    'namespace' => 'Aws\\S3',
    'name' => 'Aws\\S3\\S3Client',
    'shortName' => 'S3Client',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Client used to interact with **Amazon Simple Storage Service (Amazon S3)**.
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
    'startLine' => 272,
    'endLine' => 1383,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Aws\\AwsClient',
    'implementsClassNames' => 
    array (
      0 => 'Aws\\S3\\S3ClientInterface',
    ),
    'traitClassNames' => 
    array (
      0 => 'Aws\\S3\\S3ClientTrait',
    ),
    'immediateConstants' => 
    array (
      'DIRECTORY_BUCKET_REGEX' => 
      array (
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'name' => 'DIRECTORY_BUCKET_REGEX',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'/^[a-zA-Z0-9_-]+--[a-z0-9]+-az\\d+--x-s3\' . \'(?!.*(?:-s3alias|--ol-s3|\\.mrap))$/\'',
          'attributes' => 
          array (
            'startLine' => 274,
            'endLine' => 275,
            'startTokenPos' => 201,
            'startFilePos' => 18633,
            'endTokenPos' => 204,
            'endFilePos' => 18756,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 274,
        'endLine' => 275,
        'startColumn' => 5,
        'endColumn' => 83,
      ),
    ),
    'immediateProperties' => 
    array (
      'mandatoryAttributes' => 
      array (
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'name' => 'mandatoryAttributes',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'Bucket\', \'Key\']',
          'attributes' => 
          array (
            'startLine' => 279,
            'endLine' => 279,
            'startTokenPos' => 222,
            'startFilePos' => 18847,
            'endTokenPos' => 227,
            'endFilePos' => 18863,
          ),
        ),
        'docComment' => '/** @var array */',
        'attributes' => 
        array (
        ),
        'startLine' => 279,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 60,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'checksumOptionEnum' => 
      array (
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'name' => 'checksumOptionEnum',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'when_supported\' => true, \'when_required\' => true]',
          'attributes' => 
          array (
            'startLine' => 282,
            'endLine' => 285,
            'startTokenPos' => 240,
            'startFilePos' => 18930,
            'endTokenPos' => 255,
            'endFilePos' => 19002,
          ),
        ),
        'docComment' => '/** @var array */',
        'attributes' => 
        array (
        ),
        'startLine' => 282,
        'endLine' => 285,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'getArguments' => 
      array (
        'name' => 'getArguments',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 287,
        'endLine' => 386,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'startLine' => 443,
            'endLine' => 443,
            'startColumn' => 33,
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
 * {@inheritdoc}
 *
 * In addition to the options available to
 * {@see Aws\\AwsClient::__construct}, S3Client accepts the following
 * options:
 *
 * - bucket_endpoint: (bool) Set to true to send requests to a
 *   hardcoded bucket endpoint rather than create an endpoint as a result
 *   of injecting the bucket into the URL. This option is useful for
 *   interacting with CNAME endpoints. Note: if you are using version 2.243.0
 *   and above and do not expect the bucket name to appear in the host, you will
 *   also need to set `use_path_style_endpoint` to `true`.
 * - calculate_md5: (bool) Set to false to disable calculating an MD5
 *   for all Amazon S3 signed uploads.
 * - s3_us_east_1_regional_endpoint:
 *   (Aws\\S3\\RegionalEndpoint\\ConfigurationInterface|Aws\\CacheInterface\\|callable|string|array)
 *   Specifies whether to use regional or legacy endpoints for the us-east-1
 *   region. Provide an Aws\\S3\\RegionalEndpoint\\ConfigurationInterface object, an
 *   instance of Aws\\CacheInterface, a callable configuration provider used
 *   to create endpoint configuration, a string value of `legacy` or
 *   `regional`, or an associative array with the following keys:
 *   endpoint_types: (string)  Set to `legacy` or `regional`, defaults to
 *   `legacy`
 * - use_accelerate_endpoint: (bool) Set to true to send requests to an S3
 *   Accelerate endpoint by default. Can be enabled or disabled on
 *   individual operations by setting \'@use_accelerate_endpoint\' to true or
 *   false. Note: you must enable S3 Accelerate on a bucket before it can be
 *   accessed via an Accelerate endpoint.
 * - use_arn_region: (Aws\\S3\\UseArnRegion\\ConfigurationInterface,
 *   Aws\\CacheInterface, bool, callable) Set to true to enable the client
 *   to use the region from a supplied ARN argument instead of the client\'s
 *   region. Provide an instance of Aws\\S3\\UseArnRegion\\ConfigurationInterface,
 *   an instance of Aws\\CacheInterface, a callable that provides a promise for
 *   a Configuration object, or a boolean value. Defaults to false (i.e.
 *   the SDK will not follow the ARN region if it conflicts with the client
 *   region and instead throw an error).
 * - use_dual_stack_endpoint: (bool) Set to true to send requests to an S3
 *   Dual Stack endpoint by default, which enables IPv6 Protocol.
 *   Can be enabled or disabled on individual operations by setting
 *   \'@use_dual_stack_endpoint\\\' to true or false. Note:
 *   you cannot use it together with an accelerate endpoint.
 * - use_path_style_endpoint: (bool) Set to true to send requests to an S3
 *   path style endpoint by default.
 *   Can be enabled or disabled on individual operations by setting
 *   \'@use_path_style_endpoint\\\' to true or false. Note:
 *   you cannot use it together with an accelerate endpoint.
 * - disable_multiregion_access_points: (bool) Set to true to disable
 *   sending multi region requests.  They are enabled by default.
 *   Can be enabled or disabled on individual operations by setting
 *   \'@disable_multiregion_access_points\\\' to true or false. Note:
 *   you cannot use it together with an accelerate or dualstack endpoint.
 *
 * @param array $args
 */',
        'startLine' => 443,
        'endLine' => 531,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'isBucketDnsCompatible' => 
      array (
        'name' => 'isBucketDnsCompatible',
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
            'startLine' => 544,
            'endLine' => 544,
            'startColumn' => 50,
            'endColumn' => 56,
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
 * Determine if a string is a valid name for a DNS compatible Amazon S3
 * bucket.
 *
 * DNS compatible bucket names can be used as a subdomain in a URL (e.g.,
 * "<bucket>.s3.amazonaws.com").
 *
 * @param string $bucket Bucket name to check.
 *
 * @return bool
 */',
        'startLine' => 544,
        'endLine' => 555,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_apply_use_arn_region' => 
      array (
        'name' => '_apply_use_arn_region',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 557,
            'endLine' => 557,
            'startColumn' => 50,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 557,
            'endLine' => 557,
            'startColumn' => 58,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'list' => 
          array (
            'name' => 'list',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\HandlerList',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 557,
            'endLine' => 557,
            'startColumn' => 72,
            'endColumn' => 88,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 557,
        'endLine' => 574,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_default_request_checksum_calculation' => 
      array (
        'name' => '_default_request_checksum_calculation',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'startLine' => 576,
            'endLine' => 576,
            'startColumn' => 66,
            'endColumn' => 76,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 576,
        'endLine' => 584,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_apply_request_checksum_calculation' => 
      array (
        'name' => '_apply_request_checksum_calculation',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 587,
            'endLine' => 587,
            'startColumn' => 9,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 588,
            'endLine' => 588,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 586,
        'endLine' => 601,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_default_response_checksum_validation' => 
      array (
        'name' => '_default_response_checksum_validation',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'startLine' => 603,
            'endLine' => 603,
            'startColumn' => 66,
            'endColumn' => 76,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 603,
        'endLine' => 611,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_apply_response_checksum_validation' => 
      array (
        'name' => '_apply_response_checksum_validation',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 614,
            'endLine' => 614,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 615,
            'endLine' => 615,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 613,
        'endLine' => 628,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_default_disable_express_session_auth' => 
      array (
        'name' => '_default_disable_express_session_auth',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 630,
            'endLine' => 630,
            'startColumn' => 66,
            'endColumn' => 77,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 630,
        'endLine' => 638,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_default_s3_express_identity_provider' => 
      array (
        'name' => '_default_s3_express_identity_provider',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'startLine' => 640,
            'endLine' => 640,
            'startColumn' => 66,
            'endColumn' => 76,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 640,
        'endLine' => 646,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
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
            'startLine' => 648,
            'endLine' => 648,
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
            'startLine' => 648,
            'endLine' => 648,
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
                'startLine' => 648,
                'endLine' => 648,
                'startTokenPos' => 2201,
                'startFilePos' => 35920,
                'endTokenPos' => 2202,
                'endFilePos' => 35921,
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
            'startLine' => 648,
            'endLine' => 648,
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
        'docComment' => NULL,
        'startLine' => 648,
        'endLine' => 699,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
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
            'startLine' => 714,
            'endLine' => 714,
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
            'startLine' => 714,
            'endLine' => 714,
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
        'startLine' => 714,
        'endLine' => 722,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'encodeKey' => 
      array (
        'name' => 'encodeKey',
        'parameters' => 
        array (
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
            'startLine' => 731,
            'endLine' => 731,
            'startColumn' => 38,
            'endColumn' => 41,
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
 * Raw URL encode a key and allow for \'/\' characters
 *
 * @param string $key Key to encode
 *
 * @return string Returns the encoded key
 */',
        'startLine' => 731,
        'endLine' => 734,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getLocationConstraintMiddleware' => 
      array (
        'name' => 'getLocationConstraintMiddleware',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides a middleware that removes the need to specify LocationConstraint on CreateBucket.
 *
 * @return \\Closure
 */',
        'startLine' => 741,
        'endLine' => 766,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getSaveAsParameter' => 
      array (
        'name' => 'getSaveAsParameter',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides a middleware that supports the `SaveAs` parameter.
 *
 * @return \\Closure
 */',
        'startLine' => 773,
        'endLine' => 785,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getHeadObjectMiddleware' => 
      array (
        'name' => 'getHeadObjectMiddleware',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides a middleware that disables content decoding on HeadObject
 * commands.
 *
 * @return \\Closure
 */',
        'startLine' => 793,
        'endLine' => 809,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getEncodingTypeMiddleware' => 
      array (
        'name' => 'getEncodingTypeMiddleware',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides a middleware that autopopulates the EncodingType parameter on
 * ListObjects commands.
 *
 * @return \\Closure
 */',
        'startLine' => 817,
        'endLine' => 865,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getEmptyPathWithQuery' => 
      array (
        'name' => 'getEmptyPathWithQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides a middleware that checks for an empty path and a
 * non-empty query string.
 *
 * @return \\Closure
 */',
        'startLine' => 873,
        'endLine' => 886,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getDisableExpressSessionAuthMiddleware' => 
      array (
        'name' => 'getDisableExpressSessionAuthMiddleware',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides a middleware that disables express session auth when
 * customers opt out of it.
 *
 * @return \\Closure
 */',
        'startLine' => 894,
        'endLine' => 909,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getSigningName' => 
      array (
        'name' => 'getSigningName',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 918,
            'endLine' => 918,
            'startColumn' => 37,
            'endColumn' => 41,
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
 * Special handling for when the service name is s3-object-lambda.
 * So, if the host contains s3-object-lambda, then the service name
 * returned is s3-object-lambda, otherwise the default signing service is returned.
 * @param string $host The host to validate if is a s3-object-lambda URL.
 * @return string returns the signing service name to be used
 */',
        'startLine' => 918,
        'endLine' => 925,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'processModel' => 
      array (
        'name' => 'processModel',
        'parameters' => 
        array (
          'isUseEndpointV2' => 
          array (
            'name' => 'isUseEndpointV2',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 939,
            'endLine' => 939,
            'startColumn' => 35,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * If EndpointProviderV2 is used, removes `Bucket` from request URIs.
 * This is now handled by the endpoint ruleset.
 *
 * Additionally adds a synthetic shape `ExpiresString` and modifies
 * `Expires` type to ensure it remains set to `timestamp`.
 *
 * @param array $args
 * @return void
 *
 * @internal
 */',
        'startLine' => 939,
        'endLine' => 978,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'addBuiltIns' => 
      array (
        'name' => 'addBuiltIns',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 985,
            'endLine' => 985,
            'startColumn' => 34,
            'endColumn' => 38,
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
 * Adds service-specific client built-in values
 *
 * @return void
 */',
        'startLine' => 985,
        'endLine' => 1021,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'isDirectoryBucket' => 
      array (
        'name' => 'isDirectoryBucket',
        'parameters' => 
        array (
          'bucket' => 
          array (
            'name' => 'bucket',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1030,
            'endLine' => 1030,
            'startColumn' => 46,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determines whether a bucket is a directory bucket.
 * Only considers the availability zone/suffix format
 *
 * @param string $bucket
 * @return bool
 */',
        'startLine' => 1030,
        'endLine' => 1033,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_applyRetryConfig' => 
      array (
        'name' => '_applyRetryConfig',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1036,
            'endLine' => 1036,
            'startColumn' => 46,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1036,
            'endLine' => 1036,
            'startColumn' => 54,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'list' => 
          array (
            'name' => 'list',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\HandlerList',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1036,
            'endLine' => 1036,
            'startColumn' => 61,
            'endColumn' => 77,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/** @internal */',
        'startLine' => 1036,
        'endLine' => 1055,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'appendLegacyModeRetries' => 
      array (
        'name' => 'appendLegacyModeRetries',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\Retry\\ConfigurationInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1058,
            'endLine' => 1058,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'list' => 
          array (
            'name' => 'list',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\HandlerList',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1059,
            'endLine' => 1059,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1057,
        'endLine' => 1083,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'appendStandardModeRetries' => 
      array (
        'name' => 'appendStandardModeRetries',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\Retry\\ConfigurationInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1086,
            'endLine' => 1086,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1087,
            'endLine' => 1087,
            'startColumn' => 9,
            'endColumn' => 13,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'list' => 
          array (
            'name' => 'list',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\HandlerList',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1088,
            'endLine' => 1088,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1085,
        'endLine' => 1121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'appendStandardModeRetriesNew' => 
      array (
        'name' => 'appendStandardModeRetriesNew',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\Retry\\ConfigurationInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1124,
            'endLine' => 1124,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1125,
            'endLine' => 1125,
            'startColumn' => 9,
            'endColumn' => 13,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'list' => 
          array (
            'name' => 'list',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\HandlerList',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1126,
            'endLine' => 1126,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1123,
        'endLine' => 1150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'isS3SocketIssue' => 
      array (
        'name' => 'isS3SocketIssue',
        'parameters' => 
        array (
          'error' => 
          array (
            'name' => 'error',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\Exception\\AwsException',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1152,
            'endLine' => 1152,
            'startColumn' => 45,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'commandName' => 
          array (
            'name' => 'commandName',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1152,
            'endLine' => 1152,
            'startColumn' => 66,
            'endColumn' => 84,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1152,
        'endLine' => 1166,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      '_applyApiProvider' => 
      array (
        'name' => '_applyApiProvider',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1169,
            'endLine' => 1169,
            'startColumn' => 46,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1169,
            'endLine' => 1169,
            'startColumn' => 54,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'list' => 
          array (
            'name' => 'list',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\HandlerList',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1169,
            'endLine' => 1169,
            'startColumn' => 68,
            'endColumn' => 84,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/** @internal */',
        'startLine' => 1169,
        'endLine' => 1190,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'applyDocFilters' => 
      array (
        'name' => 'applyDocFilters',
        'parameters' => 
        array (
          'api' => 
          array (
            'name' => 'api',
            'default' => NULL,
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
            'startLine' => 1196,
            'endLine' => 1196,
            'startColumn' => 44,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'docs' => 
          array (
            'name' => 'docs',
            'default' => NULL,
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
            'startLine' => 1196,
            'endLine' => 1196,
            'startColumn' => 56,
            'endColumn' => 66,
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
 * @internal
 * @codeCoverageIgnore
 */',
        'startLine' => 1196,
        'endLine' => 1316,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'addDocExamples' => 
      array (
        'name' => 'addDocExamples',
        'parameters' => 
        array (
          'examples' => 
          array (
            'name' => 'examples',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1322,
            'endLine' => 1322,
            'startColumn' => 43,
            'endColumn' => 51,
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
 * @internal
 * @codeCoverageIgnore
 */',
        'startLine' => 1322,
        'endLine' => 1372,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
        'aliasName' => NULL,
      ),
      'getSignatureVersionFromCommand' => 
      array (
        'name' => 'getSignatureVersionFromCommand',
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
            'startLine' => 1378,
            'endLine' => 1378,
            'startColumn' => 53,
            'endColumn' => 77,
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
 * @param CommandInterface $command
 * @return array|mixed|null
 */',
        'startLine' => 1378,
        'endLine' => 1382,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3Client',
        'implementingClassName' => 'Aws\\S3\\S3Client',
        'currentClassName' => 'Aws\\S3\\S3Client',
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