<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/BackupJob.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\BackupJob
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-b6bdfceb9eceae26b03d26e31ff6ebd47db62fad36af9f2c624cf7f47c62eb6d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\BackupJob',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/BackupJob.php',
      ),
    ),
    'namespace' => 'App\\Models',
    'name' => 'App\\Models\\BackupJob',
    'shortName' => 'BackupJob',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * One scheduled backup task: a source, a destination, a cron schedule, how many
 * versions to keep, optional archive encryption and a notification channel.
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
            'code' => '[\'name\', \'source\', \'mode\', \'backup_destination_id\', \'cron\', \'retention\', \'encrypt\', \'passphrase\', \'notify_channels\', \'enabled\']',
            'attributes' => 
            array (
              'startLine' => 18,
              'endLine' => 21,
              'startTokenPos' => 50,
              'startFilePos' => 478,
              'endTokenPos' => 82,
              'endFilePos' => 615,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 18,
    'endLine' => 100,
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
      'SOURCES' => 
      array (
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'name' => 'SOURCES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'database\', \'files\', \'gallery\']',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 104,
            'startFilePos' => 678,
            'endTokenPos' => 112,
            'endFilePos' => 709,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 60,
      ),
      'MODES' => 
      array (
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'name' => 'MODES',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'mirror\', \'archive\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 125,
            'startFilePos' => 822,
            'endTokenPos' => 130,
            'endFilePos' => 842,
          ),
        ),
        'docComment' => '/** Backup mode for the file-based sources (database is always a full dump). */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 47,
      ),
      'NOTIFY_CHANNELS' => 
      array (
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'name' => 'NOTIFY_CHANNELS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'desktop\', \'mail\', \'ntfy\', \'webhook\']',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 143,
            'startFilePos' => 962,
            'endTokenPos' => 154,
            'endFilePos' => 999,
          ),
        ),
        'docComment' => '/** Notification channels a job may fire on completion (any combination). */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 74,
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
        'startLine' => 32,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'currentClassName' => 'App\\Models\\BackupJob',
        'aliasName' => NULL,
      ),
      'destination' => 
      array (
        'name' => 'destination',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return BelongsTo<BackupDestination, $this> */',
        'startLine' => 47,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'currentClassName' => 'App\\Models\\BackupJob',
        'aliasName' => NULL,
      ),
      'runs' => 
      array (
        'name' => 'runs',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return HasMany<BackupRun, $this> */',
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'currentClassName' => 'App\\Models\\BackupJob',
        'aliasName' => NULL,
      ),
      'statistics' => 
      array (
        'name' => 'statistics',
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
        'docComment' => '/**
 * Aggregate run statistics for this job: counts, success rate, last/average
 * duration, last/total stored size, last run age and next scheduled run.
 *
 * @return array{runs:int, ok:int, failed:int, successRate:?int,
 *     lastStatus:?string, lastRun:?\\Illuminate\\Support\\Carbon,
 *     lastDuration:?int, avgDuration:?int, lastBytes:?int, totalBytes:int,
 *     nextRun:?\\Illuminate\\Support\\Carbon}
 */',
        'startLine' => 67,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models',
        'declaringClassName' => 'App\\Models\\BackupJob',
        'implementingClassName' => 'App\\Models\\BackupJob',
        'currentClassName' => 'App\\Models\\BackupJob',
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