<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/BlobStore.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\BlobStore
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-3284f7230ae4e6ee8b23791e19629532f5c0a03d9c65ea2d44007f809a25c291',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\BlobStore',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/BlobStore.php',
      ),
    ),
    'namespace' => 'App\\Support',
    'name' => 'App\\Support\\BlobStore',
    'shortName' => 'BlobStore',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Single entry point for the unencrypted blob disk that backs files, photos,
 * contact avatars and exports. Every module used to inline the files disk;
 * routing them through here makes the backing disk (local / S3 / R2) a
 * one-line change.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 22,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
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
      'disk' => 
      array (
        'name' => 'disk',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Contracts\\Filesystem\\Filesystem',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\BlobStore',
        'implementingClassName' => 'App\\Support\\BlobStore',
        'currentClassName' => 'App\\Support\\BlobStore',
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