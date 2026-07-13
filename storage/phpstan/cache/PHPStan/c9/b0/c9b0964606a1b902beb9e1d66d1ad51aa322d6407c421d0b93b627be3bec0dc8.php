<?php declare(strict_types = 1);

// ftm-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v5-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      '50a2b0d685c71dda33eca03436490dde' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'b6705b95c9b1cf01bd3d90680061d33d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '489610a52039918b7acfeaea7f9bb3c3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\Api\\Parser',
         'uses' => 
        array (
          'parserexception' => 'Aws\\Api\\Parser\\Exception\\ParserException',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\Api\\Parser\\PayloadParserTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\Api\\Parser\\PayloadParserTrait',
          3 => 'Aws\\S3\\S3ClientTrait',
          4 => NULL,
        ),
      )),
      '4ef84e8b95be573ddfe207a59206ef3c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\Api\\Parser',
         'uses' => 
        array (
          'parserexception' => 'Aws\\Api\\Parser\\Exception\\ParserException',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'parseJson',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\Api\\Parser\\PayloadParserTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\Api\\Parser\\PayloadParserTrait',
          3 => 'Aws\\S3\\S3ClientTrait',
          4 => NULL,
        ),
      )),
      '34a7a42cede5a346cb6c443145e013d0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\Api\\Parser',
         'uses' => 
        array (
          'parserexception' => 'Aws\\Api\\Parser\\Exception\\ParserException',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'parseXml',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\Api\\Parser\\PayloadParserTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\Api\\Parser\\PayloadParserTrait',
          3 => 'Aws\\S3\\S3ClientTrait',
          4 => NULL,
        ),
      )),
      '14c5ff20b89798f4015f10c30e70c45f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'upload',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '754dd8d1a625d879e8e6e6d9eeb91f46' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'uploadAsync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'fe8d79cd6a55fc66031adda9356761c6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'copy',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'a972578e357ab1f33ae7bdadc98885c2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'copyAsync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '8065cab03041a842b52528ff89026b40' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'registerStreamWrapper',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '3ac4f4a1eaf5649b50b4b1933e564498' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'registerStreamWrapperV2',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'a211926c69151ba5499e0dfd3d6ff3b3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'deleteMatchingObjects',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '971bb350cdc566741da0007f3478b455' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'deleteMatchingObjectsAsync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'd9302b084578dc14d60a2a4835e48982' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'uploadDirectory',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '27c6869f03a959dc8e84226af4b08b27' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'uploadDirectoryAsync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'a11e5721d6d9b9a05732812004c12895' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'downloadBucket',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'f76f8dfd829d12c392895ae5631ad304' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'downloadBucketAsync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'd6ee6c0f340e048ce5fddc668d406781' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'determineBucketRegion',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '13a303d0dc093d50b8bcc6d06c1657ff' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'determineBucketRegionAsync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'f6f2d1eca30497aa0cf0bc769d0d520f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'determineBucketRegionFromExceptionBody',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '0b3d7efdae8d165d09c90f1c8fba6f60' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'doesBucketExist',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '64f822581243a3da57f60affb0f44b1a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'doesBucketExistV2',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '1d58dccc3a62eb0fc2ab9e71a73d6741' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'doesObjectExist',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '019d1d1cdc77b1a7afbe76e46a976040' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'doesObjectExistV2',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '3ad2bac2e3f85162389ac95be25bb7a0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'useDeleteMarkers',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '78631972cde522e4e674db77e8ffc532' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'checkExistenceWithCommand',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '4f01243410f505395cf5f4569f83f088' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'execute',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '9f1cd3c81c8ccdcaf38a62b41bd03e07' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getCommand',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ae1697f1bf749b7a365642621f56b3f1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getHandlerList',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '511ead7e3e4d1405aa0bdee3567ac695' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
          'commandinterface' => 'Aws\\CommandInterface',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          'resultinterface' => 'Aws\\ResultInterface',
          'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
          's3exception' => 'Aws\\S3\\Exception\\S3Exception',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
          'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getIterator',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'payloadparsertrait' => 'Aws\\Api\\Parser\\PayloadParserTrait',
            'commandinterface' => 'Aws\\CommandInterface',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            'resultinterface' => 'Aws\\ResultInterface',
            'permanentredirectexception' => 'Aws\\S3\\Exception\\PermanentRedirectException',
            's3exception' => 'Aws\\S3\\Exception\\S3Exception',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'rejectedpromise' => 'GuzzleHttp\\Promise\\RejectedPromise',
            'responseinterface' => 'Psr\\Http\\Message\\ResponseInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Aws\\S3\\S3ClientTrait',
         'traitData' => 
        array (
          0 => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php',
          1 => 'Aws\\S3\\S3Client',
          2 => 'Aws\\S3\\S3ClientTrait',
          3 => NULL,
          4 => NULL,
        ),
      )),
      '37f4cbd886cd34a5167840f6a6c6264a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getArguments',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '959f80a2422e77ee82d02f4b40668941' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '22afdf33cd1f278f6fda80401cc9eb6c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'isBucketDnsCompatible',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5a36aaa3ced505e9c6a225179ad34a37' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_apply_use_arn_region',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c17e0c2fb2a8f27fe2c4dc016cdb080a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_default_request_checksum_calculation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '48cb6b966d315fe55ae218805d5e81b0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_apply_request_checksum_calculation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '2bb2954f753524e14166a0c0e685e6ac' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_default_response_checksum_validation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '5c17b129019dc405ff68a6fd3966d37b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_apply_response_checksum_validation',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '7d987282ce9b67f140106b487be4b17a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_default_disable_express_session_auth',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '7ba1af2fab5e0aea6e53afb1cc14c83e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_default_s3_express_identity_provider',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3c7b38773500400208e97707412466e5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'createPresignedRequest',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c148eb223b14640fa0565221c5cc03f3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getObjectUrl',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '8b6928d5ea4b9153aba5463a6203e378' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'encodeKey',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'f74484f6ef14ec248a5bfb4a52a2688f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getLocationConstraintMiddleware',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '91ab5074d571537e08f81cd4abbf5413' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getSaveAsParameter',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '34d8d013d21de8d13c52d8d6d7de91a0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getHeadObjectMiddleware',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'bb1f084854f5f81fcb5bf8ac4cfaa4bb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getEncodingTypeMiddleware',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'bb309197fc9b0b1ff8d3c275e3857adf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getEmptyPathWithQuery',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'f709e1ef53e7586bdb2935aaca8d1506' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getDisableExpressSessionAuthMiddleware',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '2a57fd9e8ea45b5aa7a8389994bce2b4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getSigningName',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '672f8c07b24d162bf8fc69f3769798ee' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'processModel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '83e4f028b549f61fd97d678eeb46f428' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'addBuiltIns',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c35efc0af3f8c24660269897e3642899' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'isDirectoryBucket',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c9159879e3efb75135e352f88779c8a1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_applyRetryConfig',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'cdfead0790ab0ec8bcb46e5ff882d871' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'appendLegacyModeRetries',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '67167aaeee3c4de67d5c6262e3e97a43' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'appendStandardModeRetries',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c1fdbd7d35da414931ad1431b50dd3f4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'appendStandardModeRetriesNew',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a49056928ff23074360d5647e2469234' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'isS3SocketIssue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'c1353fe30168fc873dc5b2a9a941ab26' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => '_applyApiProvider',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'e7bf11b5e7304d5ef25ff431dbe40bbc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'applyDocFilters',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '788abc5b3bf2c957c314daf8b2f2fad3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'addDocExamples',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '757593a0579aebbcf28d214b7ae76faf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Aws\\S3',
         'uses' => 
        array (
          'apiprovider' => 'Aws\\Api\\ApiProvider',
          'docmodel' => 'Aws\\Api\\DocModel',
          'service' => 'Aws\\Api\\Service',
          'awsclient' => 'Aws\\AwsClient',
          'cacheinterface' => 'Aws\\CacheInterface',
          'clientresolver' => 'Aws\\ClientResolver',
          'command' => 'Aws\\Command',
          'commandinterface' => 'Aws\\CommandInterface',
          'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
          'awsexception' => 'Aws\\Exception\\AwsException',
          'handlerlist' => 'Aws\\HandlerList',
          's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
          'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
          'middleware' => 'Aws\\Middleware',
          'resultinterface' => 'Aws\\ResultInterface',
          'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
          'quotamanager' => 'Aws\\Retry\\QuotaManager',
          'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
          'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
          'retrymiddleware' => 'Aws\\RetryMiddleware',
          'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
          'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
          's3parser' => 'Aws\\S3\\Parser\\S3Parser',
          'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
          'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
          'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
          'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
          'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
          'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
          'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
          'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
        ),
         'className' => 'Aws\\S3\\S3Client',
         'functionName' => 'getSignatureVersionFromCommand',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Aws\\S3',
           'uses' => 
          array (
            'apiprovider' => 'Aws\\Api\\ApiProvider',
            'docmodel' => 'Aws\\Api\\DocModel',
            'service' => 'Aws\\Api\\Service',
            'awsclient' => 'Aws\\AwsClient',
            'cacheinterface' => 'Aws\\CacheInterface',
            'clientresolver' => 'Aws\\ClientResolver',
            'command' => 'Aws\\Command',
            'commandinterface' => 'Aws\\CommandInterface',
            'configurationresolver' => 'Aws\\Configuration\\ConfigurationResolver',
            'awsexception' => 'Aws\\Exception\\AwsException',
            'handlerlist' => 'Aws\\HandlerList',
            's3expressidentityprovider' => 'Aws\\Identity\\S3\\S3ExpressIdentityProvider',
            'inputvalidationmiddleware' => 'Aws\\InputValidationMiddleware',
            'middleware' => 'Aws\\Middleware',
            'resultinterface' => 'Aws\\ResultInterface',
            'retryconfigurationinterface' => 'Aws\\Retry\\ConfigurationInterface',
            'quotamanager' => 'Aws\\Retry\\QuotaManager',
            'newretriesoptin' => 'Aws\\Retry\\V3\\OptIn',
            'retryv3middleware' => 'Aws\\Retry\\V3\\RetryMiddleware',
            'retrymiddleware' => 'Aws\\RetryMiddleware',
            'retrymiddlewarev2' => 'Aws\\RetryMiddlewareV2',
            'getbucketlocationresultmutator' => 'Aws\\S3\\Parser\\GetBucketLocationResultMutator',
            's3parser' => 'Aws\\S3\\Parser\\S3Parser',
            'validateresponsechecksumresultmutator' => 'Aws\\S3\\Parser\\ValidateResponseChecksumResultMutator',
            'configurationprovider' => 'Aws\\S3\\RegionalEndpoint\\ConfigurationProvider',
            'configuration' => 'Aws\\S3\\UseArnRegion\\Configuration',
            'configurationinterface' => 'Aws\\S3\\UseArnRegion\\ConfigurationInterface',
            'usearnregionconfigurationprovider' => 'Aws\\S3\\UseArnRegion\\ConfigurationProvider',
            'requestexception' => 'GuzzleHttp\\Exception\\RequestException',
            'promiseinterface' => 'GuzzleHttp\\Promise\\PromiseInterface',
            'requestinterface' => 'Psr\\Http\\Message\\RequestInterface',
          ),
           'className' => 'Aws\\S3\\S3Client',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/aws/aws-sdk-php/src/S3/S3Client.php' => '9a99762201ac8202543bc4c0dbc742f45646c58f3c94e3ecbd5387c5436a05ad',
      '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3ClientTrait.php' => '6c6d6f4a7f04704d4e889b01605546c8871f50740199d86e41b75293797f55a2',
      '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/Api/Parser/PayloadParserTrait.php' => '9578e4bb5900a9bb27e4e40af35674ca6b9e6c812b3d577ecd2c4dbb43a5ecd7',
    ),
  ),
));