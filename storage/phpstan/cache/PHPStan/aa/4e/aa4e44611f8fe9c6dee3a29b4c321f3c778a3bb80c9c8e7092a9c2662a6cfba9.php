<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/GalleryStoreController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\GalleryStoreController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-9a8c08b05337ff077df08615d2b368fc65def7b7ba78dea08074a27bfc23e470',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\GalleryStoreController',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/GalleryStoreController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers',
    'name' => 'App\\Http\\Controllers\\GalleryStoreController',
    'shortName' => 'GalleryStoreController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Opaque zero-knowledge gallery index store: the whole photo/album/people
 * structure the browser seals with the vault key. The server only ever stores
 * and returns ciphertext + a version counter — no photo bytes, names, EXIF, GPS
 * or embeddings. The sealed blob is size-padded (see vault.js sealManifest), so
 * this store alone reveals no counts. (Residual structural metadata — photo
 * count, media type, face count — is inferable only from the separate content-
 * blob ledger, see GalleryBlobController.) The show/save protocol is shared via
 * SealedManifestStore.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 34,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\Controller',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'manifestModel' => 
      array (
        'name' => 'manifestModel',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 24,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\GalleryStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\GalleryStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\GalleryStoreController',
        'aliasName' => NULL,
      ),
      'manifestMaxBytes' => 
      array (
        'name' => 'manifestMaxBytes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Cap generously — this is the sealed index blob, not photo bytes (64 MiB). */',
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\GalleryStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\GalleryStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\GalleryStoreController',
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