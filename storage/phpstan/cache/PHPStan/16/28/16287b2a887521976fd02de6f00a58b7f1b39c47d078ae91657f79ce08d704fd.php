<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/FileBlob.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\FileBlob
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-a0e7de575d782b629e100a85f2fccc74168e93a552bf915cb31a16a0a9ee726f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\FileBlob',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/FileBlob.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\FileBlob',
    'shortName' => 'FileBlob',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Ownership ledger for a stored content blob (files/{blob}). One row per blob
 * the user uploaded; drives the per-user storage quota and access control, and
 * lets a reconcile/sweep reclaim bytes the sealed manifest no longer references.
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
            'code' => '[\'blob\', \'user_id\', \'size\', \'created_at\']',
            'attributes' => 
            array (
              'startLine' => 15,
              'endLine' => 15,
              'startTokenPos' => 30,
              'startFilePos' => 411,
              'endTokenPos' => 41,
              'endFilePos' => 451,
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
      'timestamps' => 
      array (
        'declaringClassName' => 'App\\Models\\FileBlob',
        'implementingClassName' => 'App\\Models\\FileBlob',
        'name' => 'timestamps',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 61,
            'startFilePos' => 511,
            'endTokenPos' => 61,
            'endFilePos' => 515,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'incrementing' => 
      array (
        'declaringClassName' => 'App\\Models\\FileBlob',
        'implementingClassName' => 'App\\Models\\FileBlob',
        'name' => 'incrementing',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 70,
            'startFilePos' => 546,
            'endTokenPos' => 70,
            'endFilePos' => 550,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'primaryKey' => 
      array (
        'declaringClassName' => 'App\\Models\\FileBlob',
        'implementingClassName' => 'App\\Models\\FileBlob',
        'name' => 'primaryKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'blob\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 79,
            'startFilePos' => 582,
            'endTokenPos' => 79,
            'endFilePos' => 587,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'keyType' => 
      array (
        'declaringClassName' => 'App\\Models\\FileBlob',
        'implementingClassName' => 'App\\Models\\FileBlob',
        'name' => 'keyType',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'string\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 88,
            'startFilePos' => 616,
            'endTokenPos' => 88,
            'endFilePos' => 623,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 34,
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
        'startLine' => 26,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\FileBlob',
        'implementingClassName' => 'App\\Models\\FileBlob',
        'currentClassName' => 'App\\Models\\FileBlob',
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