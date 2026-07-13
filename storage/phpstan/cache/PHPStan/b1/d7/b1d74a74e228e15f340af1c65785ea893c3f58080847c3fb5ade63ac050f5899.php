<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Ops/SystemStatus.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Ops\SystemStatus
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-bd818f43a59ff7231a2fe71b8d0dd98d3a5f1988af518f3917428d0a2526863b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Ops\\SystemStatus',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Ops/SystemStatus.php',
      ),
    ),
    'namespace' => 'App\\Services\\Ops',
    'name' => 'App\\Services\\Ops\\SystemStatus',
    'shortName' => 'SystemStatus',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Collects operational health signals — queue depth, storage use per module,
 * error counts, last backup, scheduler liveness — from a single place so the
 * System settings card and the Prometheus /metrics endpoint never drift.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 123,
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
      'snapshot' => 
      array (
        'name' => 'snapshot',
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
 * @return array{
 *   version: string,
 *   queue: array{pending: int, failed: int},
 *   storage: array{files: int, gallery: int, database: int, total: int},
 *   errors: array{unresolved: int, total: int, lastAt: ?string},
 *   backup: array{lastSuccessAt: ?string},
 *   scheduler: array{lastRunAt: ?string},
 *   disk: array{free: int, total: int}
 * }
 */',
        'startLine' => 36,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\SystemStatus',
        'implementingClassName' => 'App\\Services\\Ops\\SystemStatus',
        'currentClassName' => 'App\\Services\\Ops\\SystemStatus',
        'aliasName' => NULL,
      ),
      'tableCount' => 
      array (
        'name' => 'tableCount',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
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
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 33,
            'endColumn' => 45,
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
        'docComment' => NULL,
        'startLine' => 75,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\SystemStatus',
        'implementingClassName' => 'App\\Services\\Ops\\SystemStatus',
        'currentClassName' => 'App\\Services\\Ops\\SystemStatus',
        'aliasName' => NULL,
      ),
      'databaseBytes' => 
      array (
        'name' => 'databaseBytes',
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
        'docComment' => '/** On-disk size of the application database (driver-aware, best effort). */',
        'startLine' => 85,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\SystemStatus',
        'implementingClassName' => 'App\\Services\\Ops\\SystemStatus',
        'currentClassName' => 'App\\Services\\Ops\\SystemStatus',
        'aliasName' => NULL,
      ),
      'schedulerLastRun' => 
      array (
        'name' => 'schedulerLastRun',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
          'data' => 
          array (
            'types' => 
            array (
              0 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Latest recorded run across all scheduled maintenance tasks. */',
        'startLine' => 105,
        'endLine' => 122,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\SystemStatus',
        'implementingClassName' => 'App\\Services\\Ops\\SystemStatus',
        'currentClassName' => 'App\\Services\\Ops\\SystemStatus',
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