<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Backup/BackupDestinationFactory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Backup\BackupDestinationFactory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-33ab769ac3c1fd0289f3a5992e5b604c27f7401200f434e75314ad6ed6f696e2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Backup/BackupDestinationFactory.php',
      ),
    ),
    'namespace' => 'App\\Services\\Backup',
    'name' => 'App\\Services\\Backup\\BackupDestinationFactory',
    'shortName' => 'BackupDestinationFactory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Builds a Flysystem filesystem for a backup destination. S3 and Backblaze B2
 * share the S3 adapter (B2 via its S3-compatible endpoint); SFTP and WebDAV use
 * their own adapters.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 163,
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
      'make' => 
      array (
        'name' => 'make',
        'parameters' => 
        array (
          'destination' => 
          array (
            'name' => 'destination',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\BackupDestination',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 26,
            'endColumn' => 55,
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
            'name' => 'League\\Flysystem\\Filesystem',
            'isIdentifier' => false,
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
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      'makeFromParts' => 
      array (
        'name' => 'makeFromParts',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
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
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'c' => 
          array (
            'name' => 'c',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 51,
            'endColumn' => 58,
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
            'name' => 'League\\Flysystem\\Filesystem',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build a filesystem from a raw driver + config (used to test a destination
 * before it is saved).
 *
 * @param  array<string, mixed>  $c
 */',
        'startLine' => 36,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      'test' => 
      array (
        'name' => 'test',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
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
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 26,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
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
            'startColumn' => 42,
            'endColumn' => 54,
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
        'docComment' => '/**
 * Verify a destination is reachable and writable by writing then deleting a
 * tiny probe object. Throws with the underlying reason on failure.
 *
 * @param  array<string, mixed>  $config
 */',
        'startLine' => 52,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      'ensureRoot' => 
      array (
        'name' => 'ensureRoot',
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
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 32,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'driver' => 
          array (
            'name' => 'driver',
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
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 48,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 64,
            'endColumn' => 76,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Best-effort create of the configured destination root directory. Directory
 * based drivers (SFTP/WebDAV) do not auto-create the root prefix when a file
 * is written at its top level — so a valid host whose target folder does not
 * exist yet would fail the connection test and the first backup. Object
 * stores (S3/B2) create keys on write and need nothing here.
 *
 * Throws (does not swallow) so a genuine failure — e.g. no permission to
 * create the folder — is surfaced to the user rather than hidden.
 *
 * @param  array<string, mixed>  $config
 */',
        'startLine' => 77,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      's3' => 
      array (
        'name' => 's3',
        'parameters' => 
        array (
          'c' => 
          array (
            'name' => 'c',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 91,
            'endLine' => 91,
            'startColumn' => 25,
            'endColumn' => 32,
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
            'name' => 'League\\Flysystem\\AwsS3V3\\AwsS3V3Adapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 91,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      'sftp' => 
      array (
        'name' => 'sftp',
        'parameters' => 
        array (
          'c' => 
          array (
            'name' => 'c',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 27,
            'endColumn' => 34,
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
            'name' => 'League\\Flysystem\\PhpseclibV3\\SftpAdapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 111,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      'webdav' => 
      array (
        'name' => 'webdav',
        'parameters' => 
        array (
          'c' => 
          array (
            'name' => 'c',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 29,
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
            'name' => 'League\\Flysystem\\WebDAV\\WebDAVAdapter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 138,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'aliasName' => NULL,
      ),
      'assertHostAllowed' => 
      array (
        'name' => 'assertHostAllowed',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
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
            'startLine' => 157,
            'endLine' => 157,
            'startColumn' => 40,
            'endColumn' => 51,
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
        'docComment' => '/**
 * Re-check the resolved destination host against the shared outbound allow
 * logic immediately before connecting, so a DNS-rebind between validation
 * and connect (or a config that bypassed validation) cannot reach a
 * link-local / cloud-metadata address. Fails closed.
 */',
        'startLine' => 157,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'implementingClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
        'currentClassName' => 'App\\Services\\Backup\\BackupDestinationFactory',
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