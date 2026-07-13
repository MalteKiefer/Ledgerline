<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/UserSetting.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\UserSetting
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-9c29499d41a138afd54ed131a73adb991690f868f06e7945a3581ecdff3835b4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\UserSetting',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/UserSetting.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\UserSetting',
    'shortName' => 'UserSetting',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A user\'s personal preferences (Paperless, gallery, files, theme).
 * One row per user; use for() to fetch (or lazily create) the current user\'s
 * row. Infra/workspace settings live on AppSettings instead.
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
            'code' => '[\'user_id\', \'paperless_enabled\', \'paperless_url\', \'paperless_token\', \'paperless_synced_at\', \'gallery_columns\', \'file_max_versions\', \'theme\', \'contact_birthday_channels\', \'contact_anniversary_channels\']',
            'attributes' => 
            array (
              'startLine' => 15,
              'endLine' => 26,
              'startTokenPos' => 30,
              'startFilePos' => 379,
              'endTokenPos' => 62,
              'endFilePos' => 622,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 15,
    'endLine' => 68,
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
      'primaryKey' => 
      array (
        'declaringClassName' => 'App\\Models\\UserSetting',
        'implementingClassName' => 'App\\Models\\UserSetting',
        'name' => 'primaryKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'user_id\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 82,
            'startFilePos' => 688,
            'endTokenPos' => 82,
            'endFilePos' => 696,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
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
        'declaringClassName' => 'App\\Models\\UserSetting',
        'implementingClassName' => 'App\\Models\\UserSetting',
        'name' => 'incrementing',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 91,
            'startFilePos' => 727,
            'endTokenPos' => 91,
            'endFilePos' => 731,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'attributes' => 
      array (
        'declaringClassName' => 'App\\Models\\UserSetting',
        'implementingClassName' => 'App\\Models\\UserSetting',
        'name' => 'attributes',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'paperless_enabled\' => false, \'gallery_columns\' => 6, \'file_max_versions\' => 10, \'theme\' => \'system\']',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 39,
            'startTokenPos' => 102,
            'startFilePos' => 852,
            'endTokenPos' => 132,
            'endFilePos' => 992,
          ),
        ),
        'docComment' => '/** In-memory defaults so a freshly-created row reads correctly without a reload. */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 6,
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
        'startLine' => 41,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\UserSetting',
        'implementingClassName' => 'App\\Models\\UserSetting',
        'currentClassName' => 'App\\Models\\UserSetting',
        'aliasName' => NULL,
      ),
      'for' => 
      array (
        'name' => 'for',
        'parameters' => 
        array (
          'userId' => 
          array (
            'name' => 'userId',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 59,
            'endLine' => 59,
            'startColumn' => 32,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** The settings row for a user, creating defaults on first use. Memoised in
 *  the container (per-request in prod, reset between tests) since the layout
 *  and nav read the same row several times per page; update() mutates the
 *  cached instance in place. */',
        'startLine' => 59,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\UserSetting',
        'implementingClassName' => 'App\\Models\\UserSetting',
        'currentClassName' => 'App\\Models\\UserSetting',
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