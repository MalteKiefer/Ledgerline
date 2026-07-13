<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/UserData/GalleryData.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\UserData\GalleryData
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-25f243ea3dfcd7a5242854b5687cc4ceaeb1ba3b267323d38d96b118865d783a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\UserData\\GalleryData',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/UserData/GalleryData.php',
      ),
    ),
    'namespace' => 'App\\Support\\UserData',
    'name' => 'App\\Support\\UserData\\GalleryData',
    'shortName' => 'GalleryData',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Per-user data contributor for the gallery module under zero-knowledge. The
 * whole gallery structure (photo list, albums, people, metadata, EXIF, faces)
 * lives sealed inside the gallery index (gallery_store) and the only other
 * server-side state is the opaque content blobs + their ownership ledger
 * (gallery_blobs). The export is therefore the sealed index ciphertext plus the
 * ciphertext blob inventory (ids/sizes — no plaintext); purge deletes the user\'s
 * stored bytes, thumbnails, ledger rows and sealed index so no orphans remain.
 *
 * Without this contributor a purge relied on the gallery_blobs / gallery_store FK
 * cascade, which drops the ledger rows but leaves the ciphertext bytes and thumbs
 * on disk forever — unreclaimable, since the orphan sweep never scanned them.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 74,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Support\\UserData\\UserDataContributor',
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
      'key' => 
      array (
        'name' => 'key',
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
        'modifiers' => 1,
        'namespace' => 'App\\Support\\UserData',
        'declaringClassName' => 'App\\Support\\UserData\\GalleryData',
        'implementingClassName' => 'App\\Support\\UserData\\GalleryData',
        'currentClassName' => 'App\\Support\\UserData\\GalleryData',
        'aliasName' => NULL,
      ),
      'export' => 
      array (
        'name' => 'export',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\User',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 33,
            'endLine' => 33,
            'startColumn' => 28,
            'endColumn' => 37,
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
        'docComment' => NULL,
        'startLine' => 33,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\UserData',
        'declaringClassName' => 'App\\Support\\UserData\\GalleryData',
        'implementingClassName' => 'App\\Support\\UserData\\GalleryData',
        'currentClassName' => 'App\\Support\\UserData\\GalleryData',
        'aliasName' => NULL,
      ),
      'purge' => 
      array (
        'name' => 'purge',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\User',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 27,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 52,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\UserData',
        'declaringClassName' => 'App\\Support\\UserData\\GalleryData',
        'implementingClassName' => 'App\\Support\\UserData\\GalleryData',
        'currentClassName' => 'App\\Support\\UserData\\GalleryData',
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