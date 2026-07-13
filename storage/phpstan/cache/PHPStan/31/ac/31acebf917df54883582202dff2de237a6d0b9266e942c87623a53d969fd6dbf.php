<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/StorageSnapshot.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\StorageSnapshot
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-7805c9ee370a8adf508b760c7e3c57cb6c9bbdd4ba52326aa34de8969cb962a4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\StorageSnapshot',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/StorageSnapshot.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\StorageSnapshot',
    'shortName' => 'StorageSnapshot',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * One day\'s storage usage per module. Written daily by ops:snapshot-storage
 * (and on demand by {@see StorageHistory}); read for the System page trend.
 */',
    'attributes' => 
    array (
      0 => 
      array (
        'name' => 'Illuminate\\Database\\Eloquent\\Attributes\\Fillable',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '[\'captured_on\', \'files_bytes\', \'gallery_bytes\', \'database_bytes\', \'total_bytes\']',
            'attributes' => 
            array (
              'startLine' => 15,
              'endLine' => 17,
              'startTokenPos' => 35,
              'startFilePos' => 361,
              'endTokenPos' => 52,
              'endFilePos' => 447,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 15,
    'endLine' => 30,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
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
      'casts' => 
      array (
        'name' => 'casts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 20,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\StorageSnapshot',
        'implementingClassName' => 'App\\Models\\StorageSnapshot',
        'currentClassName' => 'App\\Models\\StorageSnapshot',
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