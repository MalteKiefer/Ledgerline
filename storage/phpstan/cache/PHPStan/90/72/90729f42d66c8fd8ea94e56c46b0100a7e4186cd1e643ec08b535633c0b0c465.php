<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/Concerns/SealedManifestStore.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\Concerns\SealedManifestStore
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-6c5f9c05e9f3326724e5d441977b3b17fb4a1f00ff2eddd65d0ab2a88acb7607',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/Concerns/SealedManifestStore.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers\\Concerns',
    'name' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
    'shortName' => 'SealedManifestStore',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Shared show/save for an opaque zero-knowledge manifest store — one sealed
 * ciphertext blob per user with an optimistic-concurrency version counter. The
 * workspace store (notes/bookmarks/todos/files tree) and the gallery index store
 * are byte-for-byte the same protocol; a using controller only supplies its model
 * and the ciphertext cap. The server never sees anything but ciphertext + version.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 76,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
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
        'docComment' => '/** Fully-qualified per-user manifest model (VaultStore / GalleryStore). */',
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'App\\Http\\Controllers\\Concerns',
        'declaringClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'implementingClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'currentClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
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
        'docComment' => '/** Upper bound on the sealed ciphertext (manifest metadata, not file bytes). */',
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'App\\Http\\Controllers\\Concerns',
        'declaringClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'implementingClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'currentClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'aliasName' => NULL,
      ),
      'show' => 
      array (
        'name' => 'show',
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
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 26,
            'endColumn' => 41,
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
        'docComment' => '/** Return the current user\'s sealed manifest + version (empty on first use). */',
        'startLine' => 27,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Concerns',
        'declaringClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'implementingClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'currentClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'aliasName' => NULL,
      ),
      'save' => 
      array (
        'name' => 'save',
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
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 26,
            'endColumn' => 41,
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
 * Replace the sealed manifest. Optimistic concurrency: the client sends the
 * version it based its edit on; a mismatch means another tab/device wrote in
 * between, so we reject with 409 and the client re-loads + re-applies.
 */',
        'startLine' => 45,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Concerns',
        'declaringClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'implementingClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
        'currentClassName' => 'App\\Http\\Controllers\\Concerns\\SealedManifestStore',
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