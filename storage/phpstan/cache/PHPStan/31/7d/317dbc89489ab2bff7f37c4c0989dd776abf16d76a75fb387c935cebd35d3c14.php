<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanBlobs.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\SweepOrphanBlobs
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-d1b5530e32e5e08c8244e357db195999cce8ec120217efb9fa2d70d2c7c72b50',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/SweepOrphanBlobs.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\SweepOrphanBlobs',
    'shortName' => 'SweepOrphanBlobs',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => '/**
 * Crash-safety sweep for a zero-knowledge blob store. The client reclaims blobs
 * its (sealed) manifest no longer references via the module\'s /blobs/reconcile;
 * this command handles the one case the client cannot: stored bytes on disk with
 * NO ledger row at all — e.g. bytes leaked by a crash between a committed delete
 * and the post-unlink, or an aborted multipart upload. Age-gated by lastModified
 * so an in-flight upload (whose ledger row may not exist yet) is never touched.
 * The server cannot read the manifest, so it never removes a still-referenced
 * blob here — only bytes with no ownership record are swept.
 *
 * Concrete per-module commands (files:sweep-orphans, gallery:sweep-orphans) only
 * supply the disk prefix and ownership-ledger model; the sweep body is shared.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 70,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
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
      'prefix' => 
      array (
        'name' => 'prefix',
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
        'docComment' => '/** Disk prefix the module stores its content blobs under (e.g. \'files\'). */',
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'aliasName' => NULL,
      ),
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
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'aliasName' => NULL,
      ),
      'configNs' => 
      array (
        'name' => 'configNs',
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
        'docComment' => '/** Config namespace holding blob_orphan_grace_hours (e.g. \'files\'). */',
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 66,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
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
        'docComment' => NULL,
        'startLine' => 37,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'implementingClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
        'currentClassName' => 'App\\Console\\Commands\\SweepOrphanBlobs',
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