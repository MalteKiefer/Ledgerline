<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/ManagesFrequencies.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Scheduling\ManagesFrequencies
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-40425617ce5e7382bed073b8806ce13b4e0a62643340de8005352060a718f3e6-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/ManagesFrequencies.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Scheduling',
    'name' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
    'shortName' => 'ManagesFrequencies',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 699,
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
      'cron' => 
      array (
        'name' => 'cron',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 26,
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
 * The Cron expression representing the event\'s frequency.
 *
 * @param  string  $expression
 * @return $this
 */',
        'startLine' => 18,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'between' => 
      array (
        'name' => 'between',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endTime' => 
          array (
            'name' => 'endTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 41,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run between start and end time.
 *
 * @param  string  $startTime
 * @param  string  $endTime
 * @return $this
 */',
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'unlessBetween' => 
      array (
        'name' => 'unlessBetween',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endTime' => 
          array (
            'name' => 'endTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 47,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to not run between start and end time.
 *
 * @param  string  $startTime
 * @param  string  $endTime
 * @return $this
 */',
        'startLine' => 44,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'inTimeInterval' => 
      array (
        'name' => 'inTimeInterval',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 37,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endTime' => 
          array (
            'name' => 'endTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 49,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run between start and end time.
 *
 * @param  string  $startTime
 * @param  string  $endTime
 * @return \\Closure
 */',
        'startLine' => 56,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everySecond' => 
      array (
        'name' => 'everySecond',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every second.
 *
 * @return $this
 */',
        'startLine' => 89,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwoSeconds' => 
      array (
        'name' => 'everyTwoSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every two seconds.
 *
 * @return $this
 */',
        'startLine' => 99,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFiveSeconds' => 
      array (
        'name' => 'everyFiveSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every five seconds.
 *
 * @return $this
 */',
        'startLine' => 109,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTenSeconds' => 
      array (
        'name' => 'everyTenSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every ten seconds.
 *
 * @return $this
 */',
        'startLine' => 119,
        'endLine' => 122,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFifteenSeconds' => 
      array (
        'name' => 'everyFifteenSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every fifteen seconds.
 *
 * @return $this
 */',
        'startLine' => 129,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwentySeconds' => 
      array (
        'name' => 'everyTwentySeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every twenty seconds.
 *
 * @return $this
 */',
        'startLine' => 139,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThirtySeconds' => 
      array (
        'name' => 'everyThirtySeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every thirty seconds.
 *
 * @return $this
 */',
        'startLine' => 149,
        'endLine' => 152,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'repeatEvery' => 
      array (
        'name' => 'repeatEvery',
        'parameters' => 
        array (
          'seconds' => 
          array (
            'name' => 'seconds',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 36,
            'endColumn' => 43,
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
 * Schedule the event to run multiple times per minute.
 *
 * @param  int<1, 59>  $seconds
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 162,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyMinute' => 
      array (
        'name' => 'everyMinute',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every minute.
 *
 * @return $this
 */',
        'startLine' => 182,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwoMinutes' => 
      array (
        'name' => 'everyTwoMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every two minutes.
 *
 * @return $this
 */',
        'startLine' => 192,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThreeMinutes' => 
      array (
        'name' => 'everyThreeMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every three minutes.
 *
 * @return $this
 */',
        'startLine' => 202,
        'endLine' => 205,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFourMinutes' => 
      array (
        'name' => 'everyFourMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every four minutes.
 *
 * @return $this
 */',
        'startLine' => 212,
        'endLine' => 215,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFiveMinutes' => 
      array (
        'name' => 'everyFiveMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every five minutes.
 *
 * @return $this
 */',
        'startLine' => 222,
        'endLine' => 225,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTenMinutes' => 
      array (
        'name' => 'everyTenMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every ten minutes.
 *
 * @return $this
 */',
        'startLine' => 232,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFifteenMinutes' => 
      array (
        'name' => 'everyFifteenMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every fifteen minutes.
 *
 * @return $this
 */',
        'startLine' => 242,
        'endLine' => 245,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThirtyMinutes' => 
      array (
        'name' => 'everyThirtyMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every thirty minutes.
 *
 * @return $this
 */',
        'startLine' => 252,
        'endLine' => 255,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'hourly' => 
      array (
        'name' => 'hourly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run hourly.
 *
 * @return $this
 */',
        'startLine' => 262,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'hourlyAt' => 
      array (
        'name' => 'hourlyAt',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 273,
            'endLine' => 273,
            'startColumn' => 30,
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
 * Schedule the event to run hourly at a given offset in the hour.
 *
 * @param  array|string|int<0, 59>|int<0, 59>[]  $offset
 * @return $this
 */',
        'startLine' => 273,
        'endLine' => 276,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyOddHour' => 
      array (
        'name' => 'everyOddHour',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 872,
                'startFilePos' => 6215,
                'endTokenPos' => 872,
                'endFilePos' => 6215,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every odd hour.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 284,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwoHours' => 
      array (
        'name' => 'everyTwoHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 295,
                'endLine' => 295,
                'startTokenPos' => 904,
                'startFilePos' => 6470,
                'endTokenPos' => 904,
                'endFilePos' => 6470,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every two hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 295,
        'endLine' => 298,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThreeHours' => 
      array (
        'name' => 'everyThreeHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 306,
                'endLine' => 306,
                'startTokenPos' => 936,
                'startFilePos' => 6726,
                'endTokenPos' => 936,
                'endFilePos' => 6726,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 306,
            'endLine' => 306,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every three hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 306,
        'endLine' => 309,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFourHours' => 
      array (
        'name' => 'everyFourHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 317,
                'endLine' => 317,
                'startTokenPos' => 968,
                'startFilePos' => 6980,
                'endTokenPos' => 968,
                'endFilePos' => 6980,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 317,
            'endLine' => 317,
            'startColumn' => 36,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every four hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 317,
        'endLine' => 320,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everySixHours' => 
      array (
        'name' => 'everySixHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 328,
                'endLine' => 328,
                'startTokenPos' => 1000,
                'startFilePos' => 7232,
                'endTokenPos' => 1000,
                'endFilePos' => 7232,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every six hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 328,
        'endLine' => 331,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'daily' => 
      array (
        'name' => 'daily',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run daily.
 *
 * @return $this
 */',
        'startLine' => 338,
        'endLine' => 341,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'at' => 
      array (
        'name' => 'at',
        'parameters' => 
        array (
          'time' => 
          array (
            'name' => 'time',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 349,
            'endLine' => 349,
            'startColumn' => 24,
            'endColumn' => 28,
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
 * Schedule the command at a given time.
 *
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 349,
        'endLine' => 352,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'dailyAt' => 
      array (
        'name' => 'dailyAt',
        'parameters' => 
        array (
          'time' => 
          array (
            'name' => 'time',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 29,
            'endColumn' => 33,
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
 * Schedule the event to run daily at a given time (10:00, 19:30, etc).
 *
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 360,
        'endLine' => 368,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'twiceDaily' => 
      array (
        'name' => 'twiceDaily',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 377,
                'endLine' => 377,
                'startTokenPos' => 1152,
                'startFilePos' => 8259,
                'endTokenPos' => 1152,
                'endFilePos' => 8259,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 377,
            'endLine' => 377,
            'startColumn' => 32,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '13',
              'attributes' => 
              array (
                'startLine' => 377,
                'endLine' => 377,
                'startTokenPos' => 1159,
                'startFilePos' => 8272,
                'endTokenPos' => 1159,
                'endFilePos' => 8273,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 377,
            'endLine' => 377,
            'startColumn' => 44,
            'endColumn' => 55,
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
 * Schedule the event to run twice daily.
 *
 * @param  int<0, 23>  $first
 * @param  int<0, 23>  $second
 * @return $this
 */',
        'startLine' => 377,
        'endLine' => 380,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'twiceDailyAt' => 
      array (
        'name' => 'twiceDailyAt',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 390,
                'endLine' => 390,
                'startTokenPos' => 1194,
                'startFilePos' => 8599,
                'endTokenPos' => 1194,
                'endFilePos' => 8599,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '13',
              'attributes' => 
              array (
                'startLine' => 390,
                'endLine' => 390,
                'startTokenPos' => 1201,
                'startFilePos' => 8612,
                'endTokenPos' => 1201,
                'endFilePos' => 8613,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 390,
                'endLine' => 390,
                'startTokenPos' => 1208,
                'startFilePos' => 8626,
                'endTokenPos' => 1208,
                'endFilePos' => 8626,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 60,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run twice daily at a given offset.
 *
 * @param  int<0, 23>  $first
 * @param  int<0, 23>  $second
 * @param  int<0, 59>  $offset
 * @return $this
 */',
        'startLine' => 390,
        'endLine' => 395,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'hourBasedSchedule' => 
      array (
        'name' => 'hourBasedSchedule',
        'parameters' => 
        array (
          'minutes' => 
          array (
            'name' => 'minutes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 404,
            'endLine' => 404,
            'startColumn' => 42,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'hours' => 
          array (
            'name' => 'hours',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 404,
            'endLine' => 404,
            'startColumn' => 52,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run at the given minutes and hours.
 *
 * @param  array|string|int<0, 59>  $minutes
 * @param  array|string|int<0, 23>  $hours
 * @return $this
 */',
        'startLine' => 404,
        'endLine' => 412,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weekdays' => 
      array (
        'name' => 'weekdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on weekdays.
 *
 * @return $this
 */',
        'startLine' => 419,
        'endLine' => 422,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weekends' => 
      array (
        'name' => 'weekends',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on weekends.
 *
 * @return $this
 */',
        'startLine' => 429,
        'endLine' => 432,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'mondays' => 
      array (
        'name' => 'mondays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Mondays.
 *
 * @return $this
 */',
        'startLine' => 439,
        'endLine' => 442,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'tuesdays' => 
      array (
        'name' => 'tuesdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Tuesdays.
 *
 * @return $this
 */',
        'startLine' => 449,
        'endLine' => 452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'wednesdays' => 
      array (
        'name' => 'wednesdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Wednesdays.
 *
 * @return $this
 */',
        'startLine' => 459,
        'endLine' => 462,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'thursdays' => 
      array (
        'name' => 'thursdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Thursdays.
 *
 * @return $this
 */',
        'startLine' => 469,
        'endLine' => 472,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'fridays' => 
      array (
        'name' => 'fridays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Fridays.
 *
 * @return $this
 */',
        'startLine' => 479,
        'endLine' => 482,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'saturdays' => 
      array (
        'name' => 'saturdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Saturdays.
 *
 * @return $this
 */',
        'startLine' => 489,
        'endLine' => 492,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'sundays' => 
      array (
        'name' => 'sundays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Sundays.
 *
 * @return $this
 */',
        'startLine' => 499,
        'endLine' => 502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weekly' => 
      array (
        'name' => 'weekly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run weekly.
 *
 * @return $this
 */',
        'startLine' => 509,
        'endLine' => 514,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weeklyOn' => 
      array (
        'name' => 'weeklyOn',
        'parameters' => 
        array (
          'dayOfWeek' => 
          array (
            'name' => 'dayOfWeek',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 523,
            'endLine' => 523,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 523,
                'endLine' => 523,
                'startTokenPos' => 1633,
                'startFilePos' => 11450,
                'endTokenPos' => 1633,
                'endFilePos' => 11454,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 523,
            'endLine' => 523,
            'startColumn' => 42,
            'endColumn' => 54,
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
 * Schedule the event to run weekly on a given day and time.
 *
 * @param  mixed  $dayOfWeek
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 523,
        'endLine' => 528,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'monthly' => 
      array (
        'name' => 'monthly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run monthly.
 *
 * @return $this
 */',
        'startLine' => 535,
        'endLine' => 540,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'monthlyOn' => 
      array (
        'name' => 'monthlyOn',
        'parameters' => 
        array (
          'dayOfMonth' => 
          array (
            'name' => 'dayOfMonth',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 549,
                'endLine' => 549,
                'startTokenPos' => 1715,
                'startFilePos' => 12019,
                'endTokenPos' => 1715,
                'endFilePos' => 12019,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 549,
            'endLine' => 549,
            'startColumn' => 31,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 549,
                'endLine' => 549,
                'startTokenPos' => 1722,
                'startFilePos' => 12030,
                'endTokenPos' => 1722,
                'endFilePos' => 12034,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 549,
            'endLine' => 549,
            'startColumn' => 48,
            'endColumn' => 60,
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
 * Schedule the event to run monthly on a given day and time.
 *
 * @param  int<1, 31>  $dayOfMonth
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 549,
        'endLine' => 554,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'twiceMonthly' => 
      array (
        'name' => 'twiceMonthly',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 564,
                'endLine' => 564,
                'startTokenPos' => 1762,
                'startFilePos' => 12388,
                'endTokenPos' => 1762,
                'endFilePos' => 12388,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 564,
            'endLine' => 564,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '16',
              'attributes' => 
              array (
                'startLine' => 564,
                'endLine' => 564,
                'startTokenPos' => 1769,
                'startFilePos' => 12401,
                'endTokenPos' => 1769,
                'endFilePos' => 12402,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 564,
            'endLine' => 564,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 564,
                'endLine' => 564,
                'startTokenPos' => 1776,
                'startFilePos' => 12413,
                'endTokenPos' => 1776,
                'endFilePos' => 12417,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 564,
            'endLine' => 564,
            'startColumn' => 60,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run twice monthly at a given time.
 *
 * @param  int<1, 31>  $first
 * @param  int<1, 31>  $second
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 564,
        'endLine' => 571,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'lastDayOfMonth' => 
      array (
        'name' => 'lastDayOfMonth',
        'parameters' => 
        array (
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 579,
                'endLine' => 579,
                'startTokenPos' => 1827,
                'startFilePos' => 12747,
                'endTokenPos' => 1827,
                'endFilePos' => 12751,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 579,
            'endLine' => 579,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run on the last day of the month.
 *
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 579,
        'endLine' => 584,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'daysOfMonth' => 
      array (
        'name' => 'daysOfMonth',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 592,
            'endLine' => 592,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run on specific days of the month.
 *
 * @param  array<int<1, 31>>|int<1, 31>  ...$days
 * @return $this
 */',
        'startLine' => 592,
        'endLine' => 599,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'quarterly' => 
      array (
        'name' => 'quarterly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run quarterly.
 *
 * @return $this
 */',
        'startLine' => 606,
        'endLine' => 612,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'quarterlyOn' => 
      array (
        'name' => 'quarterlyOn',
        'parameters' => 
        array (
          'dayOfQuarter' => 
          array (
            'name' => 'dayOfQuarter',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 621,
                'endLine' => 621,
                'startTokenPos' => 2009,
                'startFilePos' => 13799,
                'endTokenPos' => 2009,
                'endFilePos' => 13799,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 621,
            'endLine' => 621,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 621,
                'endLine' => 621,
                'startTokenPos' => 2016,
                'startFilePos' => 13810,
                'endTokenPos' => 2016,
                'endFilePos' => 13814,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 621,
            'endLine' => 621,
            'startColumn' => 52,
            'endColumn' => 64,
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
 * Schedule the event to run quarterly on a given day and time.
 *
 * @param  int  $dayOfQuarter
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 621,
        'endLine' => 627,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'yearly' => 
      array (
        'name' => 'yearly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run yearly.
 *
 * @return $this
 */',
        'startLine' => 634,
        'endLine' => 640,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'yearlyOn' => 
      array (
        'name' => 'yearlyOn',
        'parameters' => 
        array (
          'month' => 
          array (
            'name' => 'month',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 650,
                'endLine' => 650,
                'startTokenPos' => 2119,
                'startFilePos' => 14517,
                'endTokenPos' => 2119,
                'endFilePos' => 14517,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 650,
            'endLine' => 650,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'dayOfMonth' => 
          array (
            'name' => 'dayOfMonth',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 650,
                'endLine' => 650,
                'startTokenPos' => 2126,
                'startFilePos' => 14534,
                'endTokenPos' => 2126,
                'endFilePos' => 14534,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 650,
            'endLine' => 650,
            'startColumn' => 42,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 650,
                'endLine' => 650,
                'startTokenPos' => 2133,
                'startFilePos' => 14545,
                'endTokenPos' => 2133,
                'endFilePos' => 14549,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 650,
            'endLine' => 650,
            'startColumn' => 59,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run yearly on a given month, day, and time.
 *
 * @param  int  $month
 * @param  int<1, 31>|string  $dayOfMonth
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 650,
        'endLine' => 656,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'days' => 
      array (
        'name' => 'days',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 664,
            'endLine' => 664,
            'startColumn' => 26,
            'endColumn' => 30,
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
 * Set the days of the week the command should run on.
 *
 * @param  mixed  $days
 * @return $this
 */',
        'startLine' => 664,
        'endLine' => 669,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'timezone' => 
      array (
        'name' => 'timezone',
        'parameters' => 
        array (
          'timezone' => 
          array (
            'name' => 'timezone',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 677,
            'endLine' => 677,
            'startColumn' => 30,
            'endColumn' => 38,
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
 * Set the timezone the date should be evaluated on.
 *
 * @param  \\UnitEnum|\\DateTimeZone|string  $timezone
 * @return $this
 */',
        'startLine' => 677,
        'endLine' => 682,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'spliceIntoPosition' => 
      array (
        'name' => 'spliceIntoPosition',
        'parameters' => 
        array (
          'position' => 
          array (
            'name' => 'position',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 691,
            'endLine' => 691,
            'startColumn' => 43,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 691,
            'endLine' => 691,
            'startColumn' => 54,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Splice the given value into the given position of the expression.
 *
 * @param  int  $position
 * @param  string|int  $value
 * @return $this
 */',
        'startLine' => 691,
        'endLine' => 698,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
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