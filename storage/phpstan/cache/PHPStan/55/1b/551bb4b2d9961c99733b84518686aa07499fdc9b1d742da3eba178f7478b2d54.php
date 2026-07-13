<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/ContactBlobController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\ContactBlobController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-a2ea9e2fc98d12136c8ba2576ac65466a263ab2aaea421e5f653017577606f67',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\ContactBlobController',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/ContactBlobController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers',
    'name' => 'App\\Http\\Controllers\\ContactBlobController',
    'shortName' => 'ContactBlobController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Zero-knowledge contacts blob store. Contact records — names, numbers, emails,
 * addresses, notes, groups — live inside the user\'s sealed /store workspace
 * manifest; the server never sees any of it. This controller only handles the
 * OPAQUE avatar CONTENT BLOBS at "contacts/{blob}" plus the ownership ledger
 * (contact_blobs) for quota + access control — all of which lives in the shared
 * BlobStoreController (owner-scoped raw/delete, immutable ciphertext caching).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 35,
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
        'startLine' => 20,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\ContactBlobController',
        'implementingClassName' => 'App\\Http\\Controllers\\ContactBlobController',
        'currentClassName' => 'App\\Http\\Controllers\\ContactBlobController',
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
        'startLine' => 25,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\ContactBlobController',
        'implementingClassName' => 'App\\Http\\Controllers\\ContactBlobController',
        'currentClassName' => 'App\\Http\\Controllers\\ContactBlobController',
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
        'docComment' => '/** Snap the ledger timestamp to the hour so upload times don\'t cluster. */',
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers',
        'declaringClassName' => 'App\\Http\\Controllers\\ContactBlobController',
        'implementingClassName' => 'App\\Http\\Controllers\\ContactBlobController',
        'currentClassName' => 'App\\Http\\Controllers\\ContactBlobController',
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