<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/DevicePairing.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\DevicePairing
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-68999c4d0ebac5421072deb26bb3520df29885b4ffa2f00a729a513124c955af',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\DevicePairing',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/DevicePairing.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\DevicePairing',
    'shortName' => 'DevicePairing',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A short-lived QR device-pairing session (see the migration). Its lifecycle is
 * a small state machine driven by App\\Services\\Auth\\Pairing.
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
            'code' => '[\'user_id\', \'code_hash\', \'device_name\', \'status\', \'token_id\', \'expires_at\']',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 40,
              'startFilePos' => 422,
              'endTokenPos' => 57,
              'endFilePos' => 496,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 16,
    'endLine' => 45,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
    ),
    'immediateConstants' => 
    array (
      'PENDING_SCAN' => 
      array (
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'name' => 'PENDING_SCAN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'pending_scan\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 84,
            'startFilePos' => 589,
            'endTokenPos' => 84,
            'endFilePos' => 602,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'PENDING_APPROVAL' => 
      array (
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'name' => 'PENDING_APPROVAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'pending_approval\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 97,
            'startFilePos' => 688,
            'endTokenPos' => 97,
            'endFilePos' => 705,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 55,
      ),
      'APPROVED' => 
      array (
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'name' => 'APPROVED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'approved\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 110,
            'startFilePos' => 778,
            'endTokenPos' => 110,
            'endFilePos' => 787,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'CONSUMED' => 
      array (
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'name' => 'CONSUMED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'consumed\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 123,
            'startFilePos' => 876,
            'endTokenPos' => 123,
            'endFilePos' => 885,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'REJECTED' => 
      array (
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'name' => 'REJECTED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'rejected\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 136,
            'startFilePos' => 972,
            'endTokenPos' => 136,
            'endFilePos' => 981,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 39,
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
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'currentClassName' => 'App\\Models\\DevicePairing',
        'aliasName' => NULL,
      ),
      'user' => 
      array (
        'name' => 'user',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'currentClassName' => 'App\\Models\\DevicePairing',
        'aliasName' => NULL,
      ),
      'isExpired' => 
      array (
        'name' => 'isExpired',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\DevicePairing',
        'implementingClassName' => 'App\\Models\\DevicePairing',
        'currentClassName' => 'App\\Models\\DevicePairing',
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