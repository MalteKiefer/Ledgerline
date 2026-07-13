<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3ClientTrait.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Aws\S3\S3ClientTrait
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6c6d6f4a7f04704d4e889b01605546c8871f50740199d86e41b75293797f55a2-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Aws\\S3\\S3ClientTrait',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/S3/S3ClientTrait.php',
      ),
    ),
    'namespace' => 'Aws\\S3',
    'name' => 'Aws\\S3\\S3ClientTrait',
    'shortName' => 'S3ClientTrait',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A trait providing S3-specific functionality. This is meant to be used in
 * classes implementing \\Aws\\S3\\S3ClientInterface
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 399,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Aws\\Api\\Parser\\PayloadParserTrait',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
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
            'startLine' => 27,
            'endLine' => 27,
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
            'startLine' => 28,
            'endLine' => 28,
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
            'startLine' => 29,
            'endLine' => 29,
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
                'startLine' => 30,
                'endLine' => 30,
                'startTokenPos' => 91,
                'startFilePos' => 703,
                'endTokenPos' => 91,
                'endFilePos' => 711,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
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
                'startLine' => 31,
                'endLine' => 31,
                'startTokenPos' => 100,
                'startFilePos' => 739,
                'endTokenPos' => 101,
                'endFilePos' => 740,
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
            'startLine' => 31,
            'endLine' => 31,
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
 * @see S3ClientInterface::upload()
 */',
        'startLine' => 26,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 42,
            'endLine' => 42,
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
            'startLine' => 43,
            'endLine' => 43,
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
            'startLine' => 44,
            'endLine' => 44,
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
                'startLine' => 45,
                'endLine' => 45,
                'startTokenPos' => 159,
                'startFilePos' => 1019,
                'endTokenPos' => 159,
                'endFilePos' => 1027,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 45,
            'endLine' => 45,
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
                'startLine' => 46,
                'endLine' => 46,
                'startTokenPos' => 168,
                'startFilePos' => 1055,
                'endTokenPos' => 169,
                'endFilePos' => 1056,
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
            'startLine' => 46,
            'endLine' => 46,
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
 * @see S3ClientInterface::uploadAsync()
 */',
        'startLine' => 41,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'copy' => 
      array (
        'name' => 'copy',
        'parameters' => 
        array (
          'fromB' => 
          array (
            'name' => 'fromB',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fromK' => 
          array (
            'name' => 'fromK',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destB' => 
          array (
            'name' => 'destB',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'destK' => 
          array (
            'name' => 'destK',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 59,
            'endLine' => 59,
            'startColumn' => 9,
            'endColumn' => 14,
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
                'startLine' => 60,
                'endLine' => 60,
                'startTokenPos' => 234,
                'startFilePos' => 1338,
                'endTokenPos' => 234,
                'endFilePos' => 1346,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'opts' => 
          array (
            'name' => 'opts',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 61,
                'endLine' => 61,
                'startTokenPos' => 243,
                'startFilePos' => 1371,
                'endTokenPos' => 244,
                'endFilePos' => 1372,
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
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 9,
            'endColumn' => 24,
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
 * @see S3ClientInterface::copy()
 */',
        'startLine' => 55,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'copyAsync' => 
      array (
        'name' => 'copyAsync',
        'parameters' => 
        array (
          'fromB' => 
          array (
            'name' => 'fromB',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fromK' => 
          array (
            'name' => 'fromK',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destB' => 
          array (
            'name' => 'destB',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 9,
            'endColumn' => 14,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'destK' => 
          array (
            'name' => 'destK',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 9,
            'endColumn' => 14,
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
                'startLine' => 75,
                'endLine' => 75,
                'startTokenPos' => 307,
                'startFilePos' => 1657,
                'endTokenPos' => 307,
                'endFilePos' => 1665,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'opts' => 
          array (
            'name' => 'opts',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 76,
                'endLine' => 76,
                'startTokenPos' => 316,
                'startFilePos' => 1690,
                'endTokenPos' => 317,
                'endFilePos' => 1691,
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
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 9,
            'endColumn' => 24,
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
 * @see S3ClientInterface::copyAsync()
 */',
        'startLine' => 70,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
 * @see S3ClientInterface::registerStreamWrapper()
 */',
        'startLine' => 97,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
 * @see S3ClientInterface::registerStreamWrapperV2()
 */',
        'startLine' => 105,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 119,
            'endLine' => 119,
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
                'startLine' => 120,
                'endLine' => 120,
                'startTokenPos' => 499,
                'startFilePos' => 2672,
                'endTokenPos' => 499,
                'endFilePos' => 2673,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 120,
            'endLine' => 120,
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
                'startLine' => 121,
                'endLine' => 121,
                'startTokenPos' => 506,
                'startFilePos' => 2693,
                'endTokenPos' => 506,
                'endFilePos' => 2694,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 121,
            'endLine' => 121,
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
                'startLine' => 122,
                'endLine' => 122,
                'startTokenPos' => 515,
                'startFilePos' => 2722,
                'endTokenPos' => 516,
                'endFilePos' => 2723,
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
            'startLine' => 122,
            'endLine' => 122,
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
 * @see S3ClientInterface::deleteMatchingObjects()
 */',
        'startLine' => 118,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 132,
            'endLine' => 132,
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
                'startLine' => 133,
                'endLine' => 133,
                'startTokenPos' => 562,
                'startFilePos' => 2999,
                'endTokenPos' => 562,
                'endFilePos' => 3000,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 133,
            'endLine' => 133,
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
                'startLine' => 134,
                'endLine' => 134,
                'startTokenPos' => 569,
                'startFilePos' => 3020,
                'endTokenPos' => 569,
                'endFilePos' => 3021,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 134,
            'endLine' => 134,
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
                'startLine' => 135,
                'endLine' => 135,
                'startTokenPos' => 578,
                'startFilePos' => 3049,
                'endTokenPos' => 579,
                'endFilePos' => 3050,
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
            'startLine' => 135,
            'endLine' => 135,
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
 * @see S3ClientInterface::deleteMatchingObjectsAsync()
 */',
        'startLine' => 131,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 160,
            'endLine' => 160,
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
            'startLine' => 161,
            'endLine' => 161,
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
                'startLine' => 162,
                'endLine' => 162,
                'startTokenPos' => 749,
                'startFilePos' => 3793,
                'endTokenPos' => 749,
                'endFilePos' => 3796,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 162,
            'endLine' => 162,
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
                'startLine' => 163,
                'endLine' => 163,
                'startTokenPos' => 758,
                'startFilePos' => 3824,
                'endTokenPos' => 759,
                'endFilePos' => 3825,
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
            'startLine' => 163,
            'endLine' => 163,
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
 * @see S3ClientInterface::uploadDirectory()
 */',
        'startLine' => 159,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 173,
            'endLine' => 173,
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
            'startLine' => 174,
            'endLine' => 174,
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
                'startLine' => 175,
                'endLine' => 175,
                'startTokenPos' => 808,
                'startFilePos' => 4113,
                'endTokenPos' => 808,
                'endFilePos' => 4116,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 175,
            'endLine' => 175,
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
                'startLine' => 176,
                'endLine' => 176,
                'startTokenPos' => 817,
                'startFilePos' => 4144,
                'endTokenPos' => 818,
                'endFilePos' => 4145,
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
            'startLine' => 176,
            'endLine' => 176,
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
 * @see S3ClientInterface::uploadDirectoryAsync()
 */',
        'startLine' => 172,
        'endLine' => 180,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 186,
            'endLine' => 186,
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
            'startLine' => 187,
            'endLine' => 187,
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
                'startLine' => 188,
                'endLine' => 188,
                'startTokenPos' => 904,
                'startFilePos' => 4475,
                'endTokenPos' => 904,
                'endFilePos' => 4476,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 188,
            'endLine' => 188,
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
                'startLine' => 189,
                'endLine' => 189,
                'startTokenPos' => 913,
                'startFilePos' => 4504,
                'endTokenPos' => 914,
                'endFilePos' => 4505,
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
            'startLine' => 189,
            'endLine' => 189,
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
 * @see S3ClientInterface::downloadBucket()
 */',
        'startLine' => 185,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 199,
            'endLine' => 199,
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
            'startLine' => 200,
            'endLine' => 200,
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
                'startLine' => 201,
                'endLine' => 201,
                'startTokenPos' => 963,
                'startFilePos' => 4790,
                'endTokenPos' => 963,
                'endFilePos' => 4791,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 201,
            'endLine' => 201,
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
                'startLine' => 202,
                'endLine' => 202,
                'startTokenPos' => 972,
                'startFilePos' => 4819,
                'endTokenPos' => 973,
                'endFilePos' => 4820,
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
            'startLine' => 202,
            'endLine' => 202,
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
 * @see S3ClientInterface::downloadBucketAsync()
 */',
        'startLine' => 198,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 211,
            'endLine' => 211,
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
 * @see S3ClientInterface::determineBucketRegion()
 */',
        'startLine' => 211,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 223,
            'endLine' => 223,
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
 * @see S3ClientInterface::determineBucketRegionAsync()
 *
 * @param string $bucketName
 *
 * @return PromiseInterface
 */',
        'startLine' => 223,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'determineBucketRegionFromExceptionBody' => 
      array (
        'name' => 'determineBucketRegionFromExceptionBody',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\ResponseInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 254,
            'endLine' => 254,
            'startColumn' => 61,
            'endColumn' => 87,
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
        'startLine' => 254,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 270,
            'endLine' => 270,
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
 * @see S3ClientInterface::doesBucketExist()
 */',
        'startLine' => 270,
        'endLine' => 275,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 280,
            'endLine' => 280,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'accept403' => 
          array (
            'name' => 'accept403',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 280,
                'endLine' => 280,
                'startTokenPos' => 1433,
                'startFilePos' => 7310,
                'endTokenPos' => 1433,
                'endFilePos' => 7314,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 280,
            'endLine' => 280,
            'startColumn' => 48,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @see S3ClientInterface::doesBucketExistV2()
 */',
        'startLine' => 280,
        'endLine' => 299,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 304,
            'endLine' => 304,
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
            'startLine' => 304,
            'endLine' => 304,
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
                'startLine' => 304,
                'endLine' => 304,
                'startTokenPos' => 1579,
                'startFilePos' => 7971,
                'endTokenPos' => 1580,
                'endFilePos' => 7972,
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
            'startLine' => 304,
            'endLine' => 304,
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
 * @see S3ClientInterface::doesObjectExist()
 */',
        'startLine' => 304,
        'endLine' => 312,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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
            'startLine' => 318,
            'endLine' => 318,
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
            'startLine' => 319,
            'endLine' => 319,
            'startColumn' => 9,
            'endColumn' => 12,
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
                'startLine' => 320,
                'endLine' => 320,
                'startTokenPos' => 1645,
                'startFilePos' => 8371,
                'endTokenPos' => 1645,
                'endFilePos' => 8375,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 9,
            'endColumn' => 37,
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
                'startLine' => 321,
                'endLine' => 321,
                'startTokenPos' => 1654,
                'startFilePos' => 8403,
                'endTokenPos' => 1655,
                'endFilePos' => 8404,
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
            'startLine' => 321,
            'endLine' => 321,
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
 * @see S3ClientInterface::doesObjectExistV2()
 */',
        'startLine' => 317,
        'endLine' => 343,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'useDeleteMarkers' => 
      array (
        'name' => 'useDeleteMarkers',
        'parameters' => 
        array (
          'exception' => 
          array (
            'name' => 'exception',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 39,
            'endColumn' => 48,
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
        'startLine' => 345,
        'endLine' => 350,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'checkExistenceWithCommand' => 
      array (
        'name' => 'checkExistenceWithCommand',
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 48,
            'endColumn' => 72,
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
 * Determines whether or not a resource exists using a command
 *
 * @param CommandInterface $command Command used to poll for the resource
 *
 * @return bool
 * @throws S3Exception|\\Exception if there is an unhandled exception
 */',
        'startLine' => 360,
        'endLine' => 374,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'execute' => 
      array (
        'name' => 'execute',
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
            'startLine' => 379,
            'endLine' => 379,
            'startColumn' => 38,
            'endColumn' => 62,
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
 * @see S3ClientInterface::execute()
 */',
        'startLine' => 379,
        'endLine' => 379,
        'startColumn' => 5,
        'endColumn' => 64,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 65,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'getCommand' => 
      array (
        'name' => 'getCommand',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 384,
            'endLine' => 384,
            'startColumn' => 41,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 384,
                'endLine' => 384,
                'startTokenPos' => 1957,
                'startFilePos' => 10097,
                'endTokenPos' => 1958,
                'endFilePos' => 10098,
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
            'startLine' => 384,
            'endLine' => 384,
            'startColumn' => 48,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @see S3ClientInterface::getCommand()
 */',
        'startLine' => 384,
        'endLine' => 384,
        'startColumn' => 5,
        'endColumn' => 65,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 65,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'getHandlerList' => 
      array (
        'name' => 'getHandlerList',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @see S3ClientInterface::getHandlerList()
 *
 * @return HandlerList
 */',
        'startLine' => 391,
        'endLine' => 391,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 65,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
        'aliasName' => NULL,
      ),
      'getIterator' => 
      array (
        'name' => 'getIterator',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 398,
            'endLine' => 398,
            'startColumn' => 42,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 398,
                'endLine' => 398,
                'startTokenPos' => 1994,
                'startFilePos' => 10404,
                'endTokenPos' => 1995,
                'endFilePos' => 10405,
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
            'startLine' => 398,
            'endLine' => 398,
            'startColumn' => 49,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @see S3ClientInterface::getIterator()
 *
 * @return \\Iterator
 */',
        'startLine' => 398,
        'endLine' => 398,
        'startColumn' => 5,
        'endColumn' => 66,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 65,
        'namespace' => 'Aws\\S3',
        'declaringClassName' => 'Aws\\S3\\S3ClientTrait',
        'implementingClassName' => 'Aws\\S3\\S3ClientTrait',
        'currentClassName' => 'Aws\\S3\\S3ClientTrait',
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