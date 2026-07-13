<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/PaperlessTerm.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\PaperlessTerm
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-230c7ade2c25b0c5d9c11beece87d5b0eab356cde6f383b55be94f10614644da',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\PaperlessTerm',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/PaperlessTerm.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\PaperlessTerm',
    'shortName' => 'PaperlessTerm',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A cached Paperless term — a tag, document type or correspondent — mirrored
 * locally so the transfer modal can offer them without a live API round-trip.
 * Per-user: each user syncs terms from their own Paperless instance.
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
            'code' => '[\'user_id\', \'kind\', \'paperless_id\', \'name\', \'color\']',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 35,
              'startFilePos' => 439,
              'endTokenPos' => 49,
              'endFilePos' => 490,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 16,
    'endLine' => 29,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Models\\Concerns\\OwnsUserData',
    ),
    'immediateConstants' => 
    array (
      'KINDS' => 
      array (
        'declaringClassName' => 'App\\Models\\PaperlessTerm',
        'implementingClassName' => 'App\\Models\\PaperlessTerm',
        'name' => 'KINDS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'tag\', \'document_type\', \'correspondent\']',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 76,
            'startFilePos' => 578,
            'endTokenPos' => 84,
            'endFilePos' => 618,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
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
        'startLine' => 23,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\PaperlessTerm',
        'implementingClassName' => 'App\\Models\\PaperlessTerm',
        'currentClassName' => 'App\\Models\\PaperlessTerm',
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