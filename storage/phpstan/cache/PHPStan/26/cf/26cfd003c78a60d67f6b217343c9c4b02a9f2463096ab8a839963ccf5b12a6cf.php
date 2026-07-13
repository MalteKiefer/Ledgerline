<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/GalleryStore.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\GalleryStore
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-c8306bd76b7b25c029874ef38bae1db2f197bc9547d86f0a37d4b23498de4134',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\GalleryStore',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/GalleryStore.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\GalleryStore',
    'shortName' => 'GalleryStore',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The user\'s sealed gallery index (photo list + album + people structure) as a
 * single opaque ciphertext + optimistic-concurrency version. Separate from the
 * shared vault_store so gallery churn never re-seals notes/todos.
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
            'code' => '[\'user_id\', \'ciphertext\', \'version\']',
            'attributes' => 
            array (
              'startLine' => 15,
              'endLine' => 15,
              'startTokenPos' => 30,
              'startFilePos' => 397,
              'endTokenPos' => 38,
              'endFilePos' => 432,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 15,
    'endLine' => 23,
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
      'table' => 
      array (
        'declaringClassName' => 'App\\Models\\GalleryStore',
        'implementingClassName' => 'App\\Models\\GalleryStore',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'gallery_store\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 58,
            'startFilePos' => 494,
            'endTokenPos' => 58,
            'endFilePos' => 508,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'primaryKey' => 
      array (
        'declaringClassName' => 'App\\Models\\GalleryStore',
        'implementingClassName' => 'App\\Models\\GalleryStore',
        'name' => 'primaryKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'user_id\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 67,
            'startFilePos' => 540,
            'endTokenPos' => 67,
            'endFilePos' => 548,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'incrementing' => 
      array (
        'declaringClassName' => 'App\\Models\\GalleryStore',
        'implementingClassName' => 'App\\Models\\GalleryStore',
        'name' => 'incrementing',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 76,
            'startFilePos' => 579,
            'endTokenPos' => 76,
            'endFilePos' => 583,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 33,
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