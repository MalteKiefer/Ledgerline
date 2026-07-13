<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/BackupDestination.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\BackupDestination
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-06c99f9a72cceb61afc2f9dc0379e0231093678bbd39424c1eae46eca744e444',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\BackupDestination',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/BackupDestination.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\BackupDestination',
    'shortName' => 'BackupDestination',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A remote storage target for backups (S3, Backblaze B2, SFTP or WebDAV).
 *
 * The driver config (bucket/keys or host/credentials) is stored as an encrypted
 * JSON blob — usable in the clear at runtime, unreadable in a database dump.
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
            'code' => '[\'name\', \'driver\', \'config\']',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 30,
              'startFilePos' => 409,
              'endTokenPos' => 38,
              'endFilePos' => 436,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 16,
    'endLine' => 27,
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
      'DRIVERS' => 
      array (
        'declaringClassName' => 'App\\Models\\BackupDestination',
        'implementingClassName' => 'App\\Models\\BackupDestination',
        'name' => 'DRIVERS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'s3\', \'b2\', \'sftp\', \'webdav\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 60,
            'startFilePos' => 507,
            'endTokenPos' => 71,
            'endFilePos' => 536,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 58,
      ),
    ),
    'immediateProperties' => 
    array (
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
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\BackupDestination',
        'implementingClassName' => 'App\\Models\\BackupDestination',
        'currentClassName' => 'App\\Models\\BackupDestination',
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