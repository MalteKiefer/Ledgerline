<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Backup/BackupManager.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Backup\BackupManager
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-66be815b555fe016ef50606e0be55e5107c99ada842993b2c0c4bcd1cd0d0145',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Backup\\BackupManager',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Backup/BackupManager.php',
      ),
    ),
    'namespace' => 'App\\Services\\Backup',
    'name' => 'App\\Services\\Backup\\BackupManager',
    'shortName' => 'BackupManager',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Runs one backup job end to end: build the source archive, optionally encrypt
 * it, upload it to the destination, prune old versions to the retention limit,
 * record the run and notify. Never throws — every run is recorded as success or
 * failure and reported through the job\'s notification channel.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 286,
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
      'MIRROR_SOURCES' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'MIRROR_SOURCES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'files\', \'gallery\']',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 105,
            'startFilePos' => 983,
            'endTokenPos' => 110,
            'endFilePos' => 1002,
          ),
        ),
        'docComment' => '/** Sources mirrored object-by-object (already-encrypted blobs), not archived. */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
      'MIRROR_PREFIX' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'MIRROR_PREFIX',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'files\' => \'files\', \'gallery\' => \'gallery\']',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 125,
            'startFilePos' => 1207,
            'endTokenPos' => 138,
            'endFilePos' => 1250,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 79,
      ),
      'MIRROR_LEDGER' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'MIRROR_LEDGER',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'files\' => \\App\\Models\\FileBlob::class, \'gallery\' => \\App\\Models\\GalleryBlob::class]',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 151,
            'startFilePos' => 1386,
            'endTokenPos' => 168,
            'endFilePos' => 1446,
          ),
        ),
        'docComment' => '/** Blob ownership ledger per mirror source — drives the incremental delta + byte total. */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 96,
      ),
    ),
    'immediateProperties' => 
    array (
      'destinations' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'destinations',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Backup\\BackupDestinationFactory',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 63,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cipher' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'cipher',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Backup\\ArchiveCipher',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 9,
        'endColumn' => 46,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'notifier' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'notifier',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Backup\\BackupNotifier',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 9,
        'endColumn' => 49,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mirror' => 
      array (
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'name' => 'mirror',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Backup\\DiskMirror',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 9,
        'endColumn' => 43,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'destinations' => 
          array (
            'name' => 'destinations',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Backup\\BackupDestinationFactory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 9,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cipher' => 
          array (
            'name' => 'cipher',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Backup\\ArchiveCipher',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 9,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'notifier' => 
          array (
            'name' => 'notifier',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Backup\\BackupNotifier',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 43,
            'endLine' => 43,
            'startColumn' => 9,
            'endColumn' => 49,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'mirror' => 
          array (
            'name' => 'mirror',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Backup\\DiskMirror',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 40,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'currentClassName' => 'App\\Services\\Backup\\BackupManager',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\BackupJob',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 25,
            'endColumn' => 38,
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
            'name' => 'App\\Models\\BackupRun',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 47,
        'endLine' => 224,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'currentClassName' => 'App\\Services\\Backup\\BackupManager',
        'aliasName' => NULL,
      ),
      'describe' => 
      array (
        'name' => 'describe',
        'parameters' => 
        array (
          'e' => 
          array (
            'name' => 'e',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Throwable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 227,
            'endLine' => 227,
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
        'docComment' => '/** Full exception chain as a readable one-liner (root cause included). */',
        'startLine' => 227,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'currentClassName' => 'App\\Services\\Backup\\BackupManager',
        'aliasName' => NULL,
      ),
      'redact' => 
      array (
        'name' => 'redact',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
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
            'startLine' => 242,
            'endLine' => 242,
            'startColumn' => 29,
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
        'docComment' => '/**
 * Strip credentials a dumper/driver may have echoed into its error (e.g. a
 * mysqldump command line or a connection URI), since this detail is stored
 * on the run and shown in the UI.
 */',
        'startLine' => 242,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'currentClassName' => 'App\\Services\\Backup\\BackupManager',
        'aliasName' => NULL,
      ),
      'source' => 
      array (
        'name' => 'source',
        'parameters' => 
        array (
          'source' => 
          array (
            'name' => 'source',
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
            'startLine' => 254,
            'endLine' => 254,
            'startColumn' => 29,
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
            'name' => 'App\\Services\\Backup\\Sources\\BackupSource',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 254,
        'endLine' => 262,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'currentClassName' => 'App\\Services\\Backup\\BackupManager',
        'aliasName' => NULL,
      ),
      'prune' => 
      array (
        'name' => 'prune',
        'parameters' => 
        array (
          'fs' => 
          array (
            'name' => 'fs',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'League\\Flysystem\\Filesystem',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 28,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 44,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'retention' => 
          array (
            'name' => 'retention',
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
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 60,
            'endColumn' => 73,
            'parameterIndex' => 2,
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
        'docComment' => '/** Keep only the newest $retention objects under the job\'s prefix; returns how many were deleted. */',
        'startLine' => 265,
        'endLine' => 285,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupManager',
        'implementingClassName' => 'App\\Services\\Backup\\BackupManager',
        'currentClassName' => 'App\\Services\\Backup\\BackupManager',
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