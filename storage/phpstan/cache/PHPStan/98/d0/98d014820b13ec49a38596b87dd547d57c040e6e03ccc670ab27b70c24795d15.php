<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/BlobStoreController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\BlobStoreController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-26c471eb043fb5e951e6148d3c51d0a486d86e5d0ac86520e124868fd3c8b1bb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\BlobStoreController',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/BlobStoreController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers',
    'name' => 'App\\Http\\Controllers\\BlobStoreController',
    'shortName' => 'BlobStoreController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => '/**
 * Shared zero-knowledge blob store. The files and gallery modules both keep the
 * whole structure/metadata sealed inside their opaque index and expose only the
 * OPAQUE CONTENT BLOBS here: ciphertext bytes stored at "{module}/{blob}" plus an
 * ownership ledger ({module}_blobs) for quota + access control. The server cannot
 * read a blob\'s contents, its metadata, or which index entry references it.
 *
 * Everything below — upload (whole + S3 multipart), quota, per-user write lock,
 * reconcile, orphan-safe raw stream and owner-scoped delete — is identical across
 * the two modules; a concrete subclass only supplies its ledger model and module
 * name (and, for the gallery, the hour-snapped created_at stamp).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 29,
    'endLine' => 362,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\Controller',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'CHUNK_PART_SIZE' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'name' => 'CHUNK_PART_SIZE',
        'modifiers' => 2,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8 * 1024 * 1024',
          'attributes' => 
          array (
            'startLine' => 149,
            'endLine' => 149,
            'startTokenPos' => 937,
            'startFilePos' => 6290,
            'endTokenPos' => 945,
            'endFilePos' => 6304,
          ),
        ),
        'docComment' => '/** Part size the client should slice with (S3 requires >= 5 MiB per part). */',
        'attributes' => 
        array (
        ),
        'startLine' => 149,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 54,
      ),
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
        'docComment' => '/** Fully-qualified ownership-ledger model (FileBlob / GalleryBlob). */',
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
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
        'docComment' => '/** Module slug — the disk prefix, config namespace and lock-key stem. */',
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'maxUploadMb' => 
      array (
        'name' => 'maxUploadMb',
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
        'docComment' => '/** Whole-upload body cap (MiB). Gallery caps tighter than files. */',
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
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
 * Ledger timestamp for a newly stored blob. Files record the real time; the
 * gallery snaps to the hour so the per-photo blob cluster (original/thumb/
 * medium/meta/crops uploaded within seconds) can\'t be grouped by upload time.
 */',
        'startLine' => 48,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'disk' => 
      array (
        'name' => 'disk',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'usage' => 
      array (
        'name' => 'usage',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
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
            'startColumn' => 27,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Current storage usage for the user (live blob bytes vs quota). */',
        'startLine' => 59,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'reconcile' => 
      array (
        'name' => 'reconcile',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 31,
            'endColumn' => 46,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reclaim blobs the sealed index no longer references. The server can\'t see
 * the (sealed) reference graph, so the client sends the set of blob ids its
 * index still points at; any of the caller\'s OWN blobs not in that set — and
 * older than the grace window, so an in-flight upload not yet saved into the
 * index is never reaped — are freed. This is how removed content releases its
 * quota under zero-knowledge.
 */',
        'startLine' => 75,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'upload' => 
      array (
        'name' => 'upload',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 110,
            'endLine' => 110,
            'startColumn' => 28,
            'endColumn' => 43,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Store one uploaded (already encrypted) blob and return its id. */',
        'startLine' => 110,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'chunkInit' => 
      array (
        'name' => 'chunkInit',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 31,
            'endColumn' => 46,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Begin a multipart upload; returns an opaque session token + the blob id. */',
        'startLine' => 152,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'chunkPart' => 
      array (
        'name' => 'chunkPart',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 175,
            'endLine' => 175,
            'startColumn' => 31,
            'endColumn' => 46,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Upload one part; returns its ETag for the completion call. */',
        'startLine' => 175,
        'endLine' => 190,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'chunkComplete' => 
      array (
        'name' => 'chunkComplete',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 193,
            'endLine' => 193,
            'startColumn' => 35,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Finish the upload and register the blob (same contract as upload()). */',
        'startLine' => 193,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'chunkAbort' => 
      array (
        'name' => 'chunkAbort',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 232,
            'endLine' => 232,
            'startColumn' => 32,
            'endColumn' => 47,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Abort a multipart upload and drop the staged parts. */',
        'startLine' => 232,
        'endLine' => 249,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      's3' => 
      array (
        'name' => 's3',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Aws\\S3\\S3Client',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 251,
        'endLine' => 254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'bucket' => 
      array (
        'name' => 'bucket',
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
        'startLine' => 256,
        'endLine' => 259,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'chunkKey' => 
      array (
        'name' => 'chunkKey',
        'parameters' => 
        array (
          'token' => 
          array (
            'name' => 'token',
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
            'startLine' => 261,
            'endLine' => 261,
            'startColumn' => 31,
            'endColumn' => 43,
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
        'docComment' => NULL,
        'startLine' => 261,
        'endLine' => 264,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'chunkSession' => 
      array (
        'name' => 'chunkSession',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 267,
            'endLine' => 267,
            'startColumn' => 35,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Load + authorise a chunk session (must belong to the current user). */',
        'startLine' => 267,
        'endLine' => 273,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'usedBytes' => 
      array (
        'name' => 'usedBytes',
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
            'startLine' => 276,
            'endLine' => 276,
            'startColumn' => 34,
            'endColumn' => 44,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Bytes the user currently occupies (every blob in their ledger). */',
        'startLine' => 276,
        'endLine' => 281,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'quotaBytes' => 
      array (
        'name' => 'quotaBytes',
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
        'docComment' => '/** Per-user quota in bytes (0 / null = unlimited). */',
        'startLine' => 284,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'quotaExceeded' => 
      array (
        'name' => 'quotaExceeded',
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
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 36,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'incoming' => 
          array (
            'name' => 'incoming',
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
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 49,
            'endColumn' => 61,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 289,
        'endLine' => 294,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'withUserLock' => 
      array (
        'name' => 'withUserLock',
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
            'startLine' => 301,
            'endLine' => 301,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fn' => 
          array (
            'name' => 'fn',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 301,
            'endLine' => 301,
            'startColumn' => 48,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Serialize a user\'s storage-mutating operation so concurrent uploads and a
 * reconcile can\'t each read a stale ledger baseline and collectively
 * overshoot the quota or reap a just-referenced blob.
 */',
        'startLine' => 301,
        'endLine' => 310,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'raw' => 
      array (
        'name' => 'raw',
        'parameters' => 
        array (
          'blob' => 
          array (
            'name' => 'blob',
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
            'startLine' => 313,
            'endLine' => 313,
            'startColumn' => 25,
            'endColumn' => 36,
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
            'name' => 'Symfony\\Component\\HttpFoundation\\StreamedResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Stream a stored blob\'s ciphertext back to the browser (owner only). */',
        'startLine' => 313,
        'endLine' => 338,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'aliasName' => NULL,
      ),
      'deleteBlob' => 
      array (
        'name' => 'deleteBlob',
        'parameters' => 
        array (
          'blob' => 
          array (
            'name' => 'blob',
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
            'startLine' => 346,
            'endLine' => 346,
            'startColumn' => 32,
            'endColumn' => 43,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete an owned blob\'s bytes + ledger row. The client calls this when its
 * sealed index stops referencing the blob (permanent delete, version-cap
 * overflow, rendition swap). Owner-scoped; unknown blob = already gone
 * (idempotent).
 */',
        'startLine' => 346,
        'endLine' => 361,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'implementingClassName' => 'App\\Http\\Controllers\\BlobStoreController',
        'currentClassName' => 'App\\Http\\Controllers\\BlobStoreController',
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