<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanContactBlobs.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\SweepOrphanContactBlobs
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-c05fdd251d3597513b8f555a3df6e8145c19fb7a350853f59c3ea9e6b444cdff',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanContactBlobs.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
    'shortName' => 'SweepOrphanContactBlobs',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Reclaim stored contact avatar bytes on disk (contacts/{blob}) that have no
 * ownership ledger row — leaked/aborted uploads the client\'s reconcile cannot
 * see, and any bytes orphaned by an interrupted account erasure. Scheduled daily.
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
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'contacts:sweep-orphans\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 38,
            'startFilePos' => 429,
            'endTokenPos' => 38,
            'endFilePos' => 452,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 52,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Reclaim stored contact avatar bytes on disk that have no ownership ledger row (leaked/aborted uploads)\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 47,
            'startFilePos' => 485,
            'endTokenPos' => 47,
            'endFilePos' => 588,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 134,
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
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
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
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
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
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanContactBlobs',
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