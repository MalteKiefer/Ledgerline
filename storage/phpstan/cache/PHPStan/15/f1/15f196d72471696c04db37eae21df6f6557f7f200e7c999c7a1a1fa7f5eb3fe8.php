<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/AppSettings.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\AppSettings
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-496c60e5c2188084bd9ff341427c4737f7850e3e1d6503f57cc7b93aeaec2ee4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\AppSettings',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/AppSettings.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\AppSettings',
    'shortName' => 'AppSettings',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The single, global workspace settings row: gallery, mail and integration options.
 *
 * There is only ever one row; use current() to fetch (or lazily create) it.
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
            'code' => '[\'gallery_trip_gap_days\', \'gallery_trip_radius_km\', \'gallery_filename_template\', \'gallery_map_zoom\', \'gallery_max_upload_mb\', \'gallery_video_frame\', \'gallery_geocode_grid_km\', \'vault_remember_days\', \'vault_public_idle_minutes\', \'mail_enabled\', \'smtp_host\', \'smtp_port\', \'smtp_encryption\', \'smtp_username\', \'smtp_password\', \'smtp_from_address\', \'smtp_from_name\', \'ntfy_enabled\', \'ntfy_url\', \'ntfy_topic\', \'ntfy_token\', \'webhook_enabled\', \'webhook_url\', \'webhook_secret\', \'export_files_max_zip_mb\', \'export_gallery_max_zip_mb\', \'export_notify_desktop\', \'export_notify_ntfy\', \'export_notify_mail\', \'export_notify_webhook\', \'files_quota_mb\', \'files_max_upload_mb\', \'files_blob_orphan_grace_hours\', \'gallery_ml_enabled\', \'gallery_ml_url\', \'gallery_ml_clip_model\', \'gallery_face_enabled\', \'gallery_face_model\', \'gallery_duplicate_threshold\', \'gallery_phash_max_distance\', \'gallery_face_min_score\', \'gallery_face_min_size\', \'gallery_face_cluster_threshold\', \'gallery_face_min_per_person\', \'gallery_geocode_interval_ms\']',
            'attributes' => 
            array (
              'startLine' => 15,
              'endLine' => 61,
              'startTokenPos' => 30,
              'startFilePos' => 335,
              'endTokenPos' => 167,
              'endFilePos' => 1529,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 15,
    'endLine' => 133,
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
      'MEMO_KEY' => 
      array (
        'declaringClassName' => 'App\\Models\\AppSettings',
        'implementingClassName' => 'App\\Models\\AppSettings',
        'name' => 'MEMO_KEY',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'memo.app_settings.current\'',
          'attributes' => 
          array (
            'startLine' => 123,
            'endLine' => 123,
            'startTokenPos' => 510,
            'startFilePos' => 4141,
            'endTokenPos' => 510,
            'endFilePos' => 4167,
          ),
        ),
        'docComment' => '/** Request-scoped memo of the single global settings row (read on many pages).
 *  Held in the container, not a static, so it is per-request in prod (fresh
 *  app per FPM request) and reset between tests. */',
        'attributes' => 
        array (
        ),
        'startLine' => 123,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 57,
      ),
    ),
    'immediateProperties' => 
    array (
      'table' => 
      array (
        'declaringClassName' => 'App\\Models\\AppSettings',
        'implementingClassName' => 'App\\Models\\AppSettings',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'app_settings\'',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 187,
            'startFilePos' => 1590,
            'endTokenPos' => 187,
            'endFilePos' => 1603,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 38,
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
        'docComment' => '/**
 * @return array<string, string>
 */',
        'startLine' => 69,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AppSettings',
        'implementingClassName' => 'App\\Models\\AppSettings',
        'currentClassName' => 'App\\Models\\AppSettings',
        'aliasName' => NULL,
      ),
      'current' => 
      array (
        'name' => 'current',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 125,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\AppSettings',
        'implementingClassName' => 'App\\Models\\AppSettings',
        'currentClassName' => 'App\\Models\\AppSettings',
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