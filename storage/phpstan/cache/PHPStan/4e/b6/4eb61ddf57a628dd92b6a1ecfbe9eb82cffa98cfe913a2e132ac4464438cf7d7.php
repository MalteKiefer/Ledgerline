<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/ContactBlob.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\ContactBlob
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-2496fcb0019941a27f20d89b4c73c25015c7dcfa4f4842f3ff86c68f26935293',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\ContactBlob',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/ContactBlob.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\ContactBlob',
    'shortName' => 'ContactBlob',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Ownership ledger for a stored contact avatar blob (contacts/{blob}). One row
 * per blob the user uploaded; drives quota, owner-scoped access, and lets a
 * reconcile/sweep reclaim bytes the sealed contacts manifest no longer
 * references.
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
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 30,
              'startFilePos' => 414,
              'endTokenPos' => 41,
              'endFilePos' => 454,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 16,
    'endLine' => 31,
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
        'declaringClassName' => 'App\\Models\\ContactBlob',
        'implementingClassName' => 'App\\Models\\ContactBlob',
        'name' => 'timestamps',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 61,
            'startFilePos' => 517,
            'endTokenPos' => 61,
            'endFilePos' => 521,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
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
        'declaringClassName' => 'App\\Models\\ContactBlob',
        'implementingClassName' => 'App\\Models\\ContactBlob',
        'name' => 'incrementing',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 70,
            'startFilePos' => 552,
            'endTokenPos' => 70,
            'endFilePos' => 556,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
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
        'declaringClassName' => 'App\\Models\\ContactBlob',
        'implementingClassName' => 'App\\Models\\ContactBlob',
        'name' => 'primaryKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'blob\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 79,
            'startFilePos' => 588,
            'endTokenPos' => 79,
            'endFilePos' => 593,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
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
        'declaringClassName' => 'App\\Models\\ContactBlob',
        'implementingClassName' => 'App\\Models\\ContactBlob',
        'name' => 'keyType',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'string\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 88,
            'startFilePos' => 622,
            'endTokenPos' => 88,
            'endFilePos' => 629,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
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
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\ContactBlob',
        'implementingClassName' => 'App\\Models\\ContactBlob',
        'currentClassName' => 'App\\Models\\ContactBlob',
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