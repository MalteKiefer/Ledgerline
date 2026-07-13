<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/GalleryBlobController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\GalleryBlobController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-f7c09d9b41c5b94f71bc522bf01deab4a4649de3a32bcce4fa345509716a0315',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\GalleryBlobController',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/GalleryBlobController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers',
    'name' => 'App\\Http\\Controllers\\GalleryBlobController',
    'shortName' => 'GalleryBlobController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Zero-knowledge gallery blob store. The whole gallery structure — photo/album/
 * people organisation, names, metadata, EXIF, faces, derived renditions and the
 * reference graph — lives inside the user\'s sealed gallery index (the opaque
 * store, see GalleryStoreController); the server never sees any of it. This
 * controller only handles the OPAQUE CONTENT BLOBS at "gallery/{blob}" plus the
 * ownership ledger (gallery_blobs) for quota + access control — all of which
 * lives in the shared BlobStoreController.
 *
 * Residual side-channel (accepted): the ledger keeps per-blob owner, stored size
 * and created_at. Sizes are length-hidden by client-side Padmé padding (app.js
 * padBlob) and created_at is snapped to the hour (stampedAt below), so exact
 * lengths and the per-photo upload burst are blurred — but the blob COUNT itself
 * is still visible, from which photo count and rough per-photo face count remain
 * inferable. No content, name or location leaks.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 47,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
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
      'blobModel' => 
      array (
        'name' => 'blobModel',
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
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'implementingClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'currentClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'aliasName' => NULL,
      ),
      'module' => 
      array (
        'name' => 'module',
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
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'implementingClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'currentClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'aliasName' => NULL,
      ),
      'stampedAt' => 
      array (
        'name' => 'stampedAt',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Carbon\\Carbon',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Snap the ledger timestamp to the hour so the per-photo blob cluster
 * (original/thumb/medium/meta/crops uploaded within seconds) can\'t be grouped
 * by upload time.
 */',
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'implementingClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
        'currentClassName' => 'App\\Http\\Controllers\\GalleryBlobController',
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