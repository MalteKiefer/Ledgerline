<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanGalleryBlobs.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\SweepOrphanGalleryBlobs
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-276565c2e30bc64c01cce08768b73c594bb8e40576a0c42581069b5156099fe4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanGalleryBlobs.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
    'shortName' => 'SweepOrphanGalleryBlobs',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Reclaim stored gallery bytes on disk (gallery/{blob}) that have no ownership
 * ledger row — leaked/aborted uploads the client\'s reconcile cannot see, and any
 * bytes orphaned by an interrupted account erasure. Scheduled daily.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 34,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
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
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'gallery:sweep-orphans\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 38,
            'startFilePos' => 421,
            'endTokenPos' => 38,
            'endFilePos' => 443,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 51,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Reclaim stored gallery bytes on disk that have no ownership ledger row (leaked/aborted uploads)\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 47,
            'startFilePos' => 476,
            'endTokenPos' => 47,
            'endFilePos' => 572,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 127,
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
      'prefix' => 
      array (
        'name' => 'prefix',
        'parameters' => 
        array (
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
        'startLine' => 20,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'aliasName' => NULL,
      ),
      'blobModel' => 
      array (
        'name' => 'blobModel',
        'parameters' => 
        array (
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
        'startLine' => 25,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'aliasName' => NULL,
      ),
      'configNs' => 
      array (
        'name' => 'configNs',
        'parameters' => 
        array (
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
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanGalleryBlobs',
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