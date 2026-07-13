<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Ops/StorageHistory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Ops\StorageHistory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-5ac80aca347404bdcf2e9c23dc5feeb64e1fc6a46c96da8e379ae9638da1b78d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Ops\\StorageHistory',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Ops/StorageHistory.php',
      ),
    ),
    'namespace' => 'App\\Services\\Ops',
    'name' => 'App\\Services\\Ops\\StorageHistory',
    'shortName' => 'StorageHistory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Captures and reads the daily storage-usage history that powers the System
 * page\'s growth trend. Capture is idempotent per day (one row), so it is safe
 * to run from the scheduler and on demand when the page is viewed.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 73,
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
      'status' => 
      array (
        'declaringClassName' => 'App\\Services\\Ops\\StorageHistory',
        'implementingClassName' => 'App\\Services\\Ops\\StorageHistory',
        'name' => 'status',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Ops\\SystemStatus',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 33,
        'endColumn' => 69,
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
          'status' => 
          array (
            'name' => 'status',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Ops\\SystemStatus',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 33,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 73,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\StorageHistory',
        'implementingClassName' => 'App\\Services\\Ops\\StorageHistory',
        'currentClassName' => 'App\\Services\\Ops\\StorageHistory',
        'aliasName' => NULL,
      ),
      'capture' => 
      array (
        'name' => 'capture',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\StorageSnapshot',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Record today\'s usage (idempotent — updates the existing row for today). */',
        'startLine' => 21,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\StorageHistory',
        'implementingClassName' => 'App\\Services\\Ops\\StorageHistory',
        'currentClassName' => 'App\\Services\\Ops\\StorageHistory',
        'aliasName' => NULL,
      ),
      'trend' => 
      array (
        'name' => 'trend',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => 
            array (
              'code' => '30',
              'attributes' => 
              array (
                'startLine' => 49,
                'endLine' => 49,
                'startTokenPos' => 177,
                'startFilePos' => 1587,
                'endTokenPos' => 177,
                'endFilePos' => 1588,
              ),
            ),
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
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 27,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
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
        'docComment' => '/**
 * Trend over the last $days days: the point series (for a sparkline) and the
 * growth delta per module.
 *
 * @return array{
 *   points: list<array{date: string, total: int}>,
 *   deltaBytes: int,
 *   deltaDays: int
 * }
 */',
        'startLine' => 49,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Ops',
        'declaringClassName' => 'App\\Services\\Ops\\StorageHistory',
        'implementingClassName' => 'App\\Services\\Ops\\StorageHistory',
        'currentClassName' => 'App\\Services\\Ops\\StorageHistory',
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