<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/Vault.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\Vault
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-0f36d428eec42fd20631866294fc261aa5c4b8482d8f4c87beee388b2dbb9eab',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\Vault',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/Vault.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\Vault',
    'shortName' => 'Vault',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A user\'s zero-knowledge encryption vault row. Stores only opaque ciphertext
 * and key-derivation parameters; never the passphrase or the vault key. One row
 * per user (user_id is stamped server-side, never mass-assigned).
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
            'code' => '[\'salt\', \'kdf_ops\', \'kdf_mem\', \'wrapped_vault_key\', \'wrap_nonce\', \'wrapped_vault_key_recovery\', \'recovery_nonce\']',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 24,
              'startTokenPos' => 35,
              'startFilePos' => 434,
              'endTokenPos' => 58,
              'endFilePos' => 577,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 16,
    'endLine' => 34,
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
      'current' => 
      array (
        'name' => 'current',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'self',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The current user\'s vault row, if they have set up encryption. */',
        'startLine' => 28,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\Vault',
        'implementingClassName' => 'App\\Models\\Vault',
        'currentClassName' => 'App\\Models\\Vault',
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