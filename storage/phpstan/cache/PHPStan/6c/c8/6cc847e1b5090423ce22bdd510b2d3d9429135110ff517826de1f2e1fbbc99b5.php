<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanFileBlobs.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\SweepOrphanFileBlobs
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-84ba6741190853c3b9630f3c759b1071be1c10b2d0c842121439b9d835ac3ac8',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanFileBlobs.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
    'shortName' => 'SweepOrphanFileBlobs',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Reclaim stored file bytes on disk (files/{blob}) that have no ownership ledger
 * row — leaked/aborted uploads the client\'s reconcile cannot see. Scheduled daily.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 33,
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
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'files:sweep-orphans\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 38,
            'startFilePos' => 349,
            'endTokenPos' => 38,
            'endFilePos' => 369,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Reclaim stored file bytes on disk that have no ownership ledger row (leaked/aborted uploads)\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 47,
            'startFilePos' => 402,
            'endTokenPos' => 47,
            'endFilePos' => 495,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 124,
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
        'startLine' => 19,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
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
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
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
        'startLine' => 29,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanFileBlobs',
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