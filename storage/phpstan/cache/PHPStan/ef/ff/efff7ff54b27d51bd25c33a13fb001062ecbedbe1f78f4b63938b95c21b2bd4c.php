<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Providers/AppServiceProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Providers\AppServiceProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-247bbdc0beffe194afe8658e98950201ccdfccf340697ed167a5ff19776666fb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Providers\\AppServiceProvider',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Providers/AppServiceProvider.php',
      ),
    ),
    'namespace' => 'App\\Providers',
    'name' => 'App\\Providers\\AppServiceProvider',
    'shortName' => 'AppServiceProvider',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 151,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Support\\ServiceProvider',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'SETTING_OVERRIDES' => 
      array (
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'name' => 'SETTING_OVERRIDES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    \'files_quota_mb\' => [\'files.quota_mb\', \'int\'],
    \'files_max_upload_mb\' => [\'files.max_upload_mb\', \'int\'],
    \'files_blob_orphan_grace_hours\' => [\'files.blob_orphan_grace_hours\', \'int\'],
    \'gallery_ml_enabled\' => [\'gallery.ml_enabled\', \'bool\'],
    \'gallery_ml_url\' => [\'gallery.ml_url\', \'string\'],
    \'gallery_ml_clip_model\' => [\'gallery.ml_clip_model\', \'string\'],
    \'gallery_face_enabled\' => [\'gallery.face_enabled\', \'bool\'],
    \'gallery_face_model\' => [\'gallery.face_model\', \'string\'],
    // NB: the ffmpeg/exiftool BINARY paths are intentionally NOT overridable
    // from the DB/UI — a settable executable path is a remote-code-execution
    // lever. They stay env/config-only.
    \'gallery_face_min_score\' => [\'gallery.face_min_score\', \'float\'],
    \'gallery_geocode_interval_ms\' => [\'gallery.geocode_interval_ms\', \'int\'],
]',
          'attributes' => 
          array (
            'startLine' => 98,
            'endLine' => 112,
            'startTokenPos' => 550,
            'startFilePos' => 3711,
            'endTokenPos' => 678,
            'endFilePos' => 4615,
          ),
        ),
        'docComment' => '/**
 * Admin-configured global overrides applied over the config/env defaults.
 * Each entry: db column => [config key, type]. A null column keeps the
 * built-in default. The Settings saves clear the cache key below.
 *
 * @var array<string, array{0: string, 1: string}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 98,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'OVERRIDES_CACHE_KEY' => 
      array (
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'name' => 'OVERRIDES_CACHE_KEY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'app-settings:overrides\'',
          'attributes' => 
          array (
            'startLine' => 114,
            'endLine' => 114,
            'startTokenPos' => 689,
            'startFilePos' => 4658,
            'endTokenPos' => 689,
            'endFilePos' => 4681,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 114,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 64,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'register' => 
      array (
        'name' => 'register',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register any application services.
 */',
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'currentClassName' => 'App\\Providers\\AppServiceProvider',
        'aliasName' => NULL,
      ),
      'boot' => 
      array (
        'name' => 'boot',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Bootstrap any application services.
 */',
        'startLine' => 36,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'currentClassName' => 'App\\Providers\\AppServiceProvider',
        'aliasName' => NULL,
      ),
      'cronRunKey' => 
      array (
        'name' => 'cronRunKey',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 39,
            'endColumn' => 50,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Cache key holding the last run for a scheduled command. */',
        'startLine' => 68,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'currentClassName' => 'App\\Providers\\AppServiceProvider',
        'aliasName' => NULL,
      ),
      'cronName' => 
      array (
        'name' => 'cronName',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'object',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 37,
            'endColumn' => 49,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Extract the artisan command name from a scheduled Event (or its summary). */',
        'startLine' => 74,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'currentClassName' => 'App\\Providers\\AppServiceProvider',
        'aliasName' => NULL,
      ),
      'recordCronRun' => 
      array (
        'name' => 'recordCronRun',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'object',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 83,
            'endLine' => 83,
            'startColumn' => 43,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ok' => 
          array (
            'name' => 'ok',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 83,
            'endLine' => 83,
            'startColumn' => 58,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 83,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'currentClassName' => 'App\\Providers\\AppServiceProvider',
        'aliasName' => NULL,
      ),
      'applySettingOverrides' => 
      array (
        'name' => 'applySettingOverrides',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Overlay admin settings onto config. Cached (settings saves clear it) so it
 * adds no DB query per request.
 */',
        'startLine' => 120,
        'endLine' => 150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\AppServiceProvider',
        'implementingClassName' => 'App\\Providers\\AppServiceProvider',
        'currentClassName' => 'App\\Providers\\AppServiceProvider',
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