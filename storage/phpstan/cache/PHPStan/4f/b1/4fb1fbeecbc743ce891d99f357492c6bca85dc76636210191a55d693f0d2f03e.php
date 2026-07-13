<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/PendingEventAttributes.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Scheduling\PendingEventAttributes
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-33b424243fca55daeacad3bbce75fad8d42a7f6ab9f69c9f89047e5bbff06027-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/PendingEventAttributes.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Scheduling',
    'name' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
    'shortName' => 'PendingEventAttributes',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin \\Illuminate\\Console\\Scheduling\\Schedule
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 150,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Console\\Scheduling\\ManagesAttributes',
      1 => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
    ),
    'immediateConstants' => 
    array (
      'DEFERRED_EVENT_METHODS' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'name' => 'DEFERRED_EVENT_METHODS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'before\', \'after\', \'then\', \'thenWithOutput\', \'onSuccess\', \'onSuccessWithOutput\', \'onFailure\', \'onFailureWithOutput\', \'pingBefore\', \'pingBeforeIf\', \'thenPing\', \'thenPingIf\', \'pingOnSuccess\', \'pingOnSuccessIf\', \'pingOnFailure\', \'pingOnFailureIf\', \'sendOutputTo\', \'appendOutputTo\', \'emailOutputTo\', \'emailWrittenOutputTo\', \'emailOutputOnFailure\']',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 39,
            'startTokenPos' => 33,
            'startFilePos' => 389,
            'endTokenPos' => 98,
            'endFilePos' => 907,
          ),
        ),
        'docComment' => '/**
 * Event lifecycle and output methods that should be deferred and replayed on each event in the group.
 *
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'macros' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'name' => 'macros',
        'modifiers' => 2,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 111,
            'startFilePos' => 1086,
            'endTokenPos' => 112,
            'endFilePos' => 1087,
          ),
        ),
        'docComment' => '/**
 * The recorded macro and deferred method calls to replay on each event.
 *
 * @var array<int, array{string, array}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'schedule' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'name' => 'schedule',
        'modifiers' => 2,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Console\\Scheduling\\Schedule',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 9,
        'endColumn' => 36,
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
          'schedule' => 
          array (
            'name' => 'schedule',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Console\\Scheduling\\Schedule',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 9,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new pending event attributes instance.
 */',
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'aliasName' => NULL,
      ),
      'withoutOverlapping' => 
      array (
        'name' => 'withoutOverlapping',
        'parameters' => 
        array (
          'expiresAt' => 
          array (
            'name' => 'expiresAt',
            'default' => 
            array (
              'code' => '1440',
              'attributes' => 
              array (
                'startLine' => 65,
                'endLine' => 65,
                'startTokenPos' => 149,
                'startFilePos' => 1570,
                'endTokenPos' => 149,
                'endFilePos' => 1573,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 40,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'releaseOnTerminationSignals' => 
          array (
            'name' => 'releaseOnTerminationSignals',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 65,
                'endLine' => 65,
                'startTokenPos' => 156,
                'startFilePos' => 1607,
                'endTokenPos' => 156,
                'endFilePos' => 1610,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 59,
            'endColumn' => 93,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Do not allow the event to overlap each other.
 *
 * The expiration time of the underlying cache lock may be specified in minutes.
 *
 * @param  int  $expiresAt
 * @param  bool  $releaseOnTerminationSignals
 * @return $this
 */',
        'startLine' => 65,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'aliasName' => NULL,
      ),
      'mergeAttributes' => 
      array (
        'name' => 'mergeAttributes',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Console\\Scheduling\\Event',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 37,
            'endColumn' => 48,
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
 * Merge the current attributes into the given event.
 */',
        'startLine' => 79,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
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
            'startLine' => 140,
            'endLine' => 140,
            'startColumn' => 28,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
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
            'startLine' => 140,
            'endLine' => 140,
            'startColumn' => 44,
            'endColumn' => 60,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Proxy missing methods onto the underlying schedule.
 */',
        'startLine' => 140,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\PendingEventAttributes',
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