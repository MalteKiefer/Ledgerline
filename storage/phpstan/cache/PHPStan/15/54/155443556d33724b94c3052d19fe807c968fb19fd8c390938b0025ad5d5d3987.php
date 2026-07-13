<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/Event.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Scheduling\Event
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-d1f32f4956d280c24c2ab11b81e65a645930ce03d0d32f6649eb6fcdf1236a22-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Scheduling\\Event',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/Event.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Scheduling',
    'name' => 'Illuminate\\Console\\Scheduling\\Event',
    'shortName' => 'Event',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 947,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Macroable',
      1 => 'Illuminate\\Console\\Scheduling\\ManagesAttributes',
      2 => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
      3 => 'Illuminate\\Support\\Traits\\ReflectsClosures',
      4 => 'Illuminate\\Support\\Traits\\Tappable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'command' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'command',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The command string.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'output' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'output',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'/dev/null\'',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 148,
            'startFilePos' => 1072,
            'endTokenPos' => 148,
            'endFilePos' => 1082,
          ),
        ),
        'docComment' => '/**
 * The location that output should be sent to.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shouldAppendOutput' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'shouldAppendOutput',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 48,
            'endLine' => 48,
            'startTokenPos' => 159,
            'startFilePos' => 1211,
            'endTokenPos' => 159,
            'endFilePos' => 1215,
          ),
        ),
        'docComment' => '/**
 * Indicates whether output should be appended.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'beforeCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'beforeCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 170,
            'startFilePos' => 1362,
            'endTokenPos' => 171,
            'endFilePos' => 1363,
          ),
        ),
        'docComment' => '/**
 * The array of callbacks to be run before the event is started.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'afterCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'afterCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 182,
            'startFilePos' => 1509,
            'endTokenPos' => 183,
            'endFilePos' => 1510,
          ),
        ),
        'docComment' => '/**
 * The array of callbacks to be run after the event is finished.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mutex' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'mutex',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event mutex implementation.
 *
 * @var \\Illuminate\\Console\\Scheduling\\EventMutex
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mutexNameResolver' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'mutexNameResolver',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The mutex name resolver callback.
 *
 * @var \\Closure|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 76,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lastChecked' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'lastChecked',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The last time the event was checked for eligibility to run.
 *
 * Utilized by sub-minute repeated events.
 *
 * @var \\Illuminate\\Support\\Carbon|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exitCode' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'exitCode',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The exit status code of the command.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'skippedBecauseOverlapping' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'name' => 'skippedBecauseOverlapping',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 99,
            'endLine' => 99,
            'startTokenPos' => 222,
            'startFilePos' => 2272,
            'endTokenPos' => 222,
            'endFilePos' => 2276,
          ),
        ),
        'docComment' => '/**
 * Indicates whether the execution was skipped due to the mutex already being reserved.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 99,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
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
          'mutex' => 
          array (
            'name' => 'mutex',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Console\\Scheduling\\EventMutex',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 52,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'timezone' => 
          array (
            'name' => 'timezone',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 108,
                'endLine' => 108,
                'startTokenPos' => 245,
                'startFilePos' => 2561,
                'endTokenPos' => 245,
                'endFilePos' => 2564,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 62,
            'endColumn' => 77,
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
 * Create a new event instance.
 *
 * @param  \\Illuminate\\Console\\Scheduling\\EventMutex  $mutex
 * @param  string  $command
 * @param  \\DateTimeZone|string|null  $timezone
 */',
        'startLine' => 108,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'getDefaultOutput' => 
      array (
        'name' => 'getDefaultOutput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default output depending on the OS.
 *
 * @return string
 */',
        'startLine' => 122,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 25,
            'endColumn' => 44,
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
 * Run the given event.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return void
 *
 * @throws \\Throwable
 */',
        'startLine' => 135,
        'endLine' => 152,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'shouldSkipDueToOverlapping' => 
      array (
        'name' => 'shouldSkipDueToOverlapping',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event should skip because another process is overlapping.
 *
 * @return bool
 */',
        'startLine' => 159,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'isRepeatable' => 
      array (
        'name' => 'isRepeatable',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event has been configured to repeat multiple times per minute.
 *
 * @return bool
 */',
        'startLine' => 169,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'shouldRepeatNow' => 
      array (
        'name' => 'shouldRepeatNow',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event is ready to repeat.
 *
 * @return bool
 */',
        'startLine' => 179,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'start' => 
      array (
        'name' => 'start',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 193,
            'endLine' => 193,
            'startColumn' => 30,
            'endColumn' => 39,
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
 * Run the command process.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return int
 *
 * @throws \\Throwable
 */',
        'startLine' => 193,
        'endLine' => 204,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'execute' => 
      array (
        'name' => 'execute',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 32,
            'endColumn' => 41,
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
 * Run the command process.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return int
 */',
        'startLine' => 212,
        'endLine' => 223,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'finish' => 
      array (
        'name' => 'finish',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 232,
            'endLine' => 232,
            'startColumn' => 28,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'exitCode' => 
          array (
            'name' => 'exitCode',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 232,
            'endLine' => 232,
            'startColumn' => 50,
            'endColumn' => 58,
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
 * Mark the command process as finished and run callbacks/cleanup.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @param  int  $exitCode
 * @return void
 */',
        'startLine' => 232,
        'endLine' => 241,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'callBeforeCallbacks' => 
      array (
        'name' => 'callBeforeCallbacks',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 249,
            'endLine' => 249,
            'startColumn' => 41,
            'endColumn' => 60,
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
 * Call all of the "before" callbacks for the event.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return void
 */',
        'startLine' => 249,
        'endLine' => 254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'callAfterCallbacks' => 
      array (
        'name' => 'callAfterCallbacks',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 262,
            'endLine' => 262,
            'startColumn' => 40,
            'endColumn' => 59,
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
 * Call all of the "after" callbacks for the event.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return void
 */',
        'startLine' => 262,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'buildCommand' => 
      array (
        'name' => 'buildCommand',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the command string.
 *
 * @return string
 */',
        'startLine' => 274,
        'endLine' => 277,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'isDue' => 
      array (
        'name' => 'isDue',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 285,
            'endLine' => 285,
            'startColumn' => 27,
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
 * Determine if the given event should run based on the Cron expression.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 * @return bool
 */',
        'startLine' => 285,
        'endLine' => 293,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'runsInMaintenanceMode' => 
      array (
        'name' => 'runsInMaintenanceMode',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event runs in maintenance mode.
 *
 * @return bool
 */',
        'startLine' => 300,
        'endLine' => 303,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'runsWhenPaused' => 
      array (
        'name' => 'runsWhenPaused',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the event runs when the scheduler is paused.
 *
 * @return bool
 */',
        'startLine' => 310,
        'endLine' => 313,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'expressionPasses' => 
      array (
        'name' => 'expressionPasses',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the Cron expression passes.
 *
 * @return bool
 */',
        'startLine' => 320,
        'endLine' => 329,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'runsInEnvironment' => 
      array (
        'name' => 'runsInEnvironment',
        'parameters' => 
        array (
          'environment' => 
          array (
            'name' => 'environment',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 337,
            'endLine' => 337,
            'startColumn' => 39,
            'endColumn' => 50,
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
 * Determine if the event runs in the given environment.
 *
 * @param  string  $environment
 * @return bool
 */',
        'startLine' => 337,
        'endLine' => 340,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'filtersPass' => 
      array (
        'name' => 'filtersPass',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 348,
            'endLine' => 348,
            'startColumn' => 33,
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
 * Determine if the filters pass for the event.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 * @return bool
 */',
        'startLine' => 348,
        'endLine' => 365,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'storeOutput' => 
      array (
        'name' => 'storeOutput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure that the output is stored on disk in a log file.
 *
 * @return $this
 */',
        'startLine' => 372,
        'endLine' => 377,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'sendOutputTo' => 
      array (
        'name' => 'sendOutputTo',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 386,
            'endLine' => 386,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'append' => 
          array (
            'name' => 'append',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 386,
                'endLine' => 386,
                'startTokenPos' => 1253,
                'startFilePos' => 9305,
                'endTokenPos' => 1253,
                'endFilePos' => 9309,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 386,
            'endLine' => 386,
            'startColumn' => 45,
            'endColumn' => 59,
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
 * Send the output of the command to a given location.
 *
 * @param  string  $location
 * @param  bool  $append
 * @return $this
 */',
        'startLine' => 386,
        'endLine' => 393,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'appendOutputTo' => 
      array (
        'name' => 'appendOutputTo',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 401,
            'endLine' => 401,
            'startColumn' => 36,
            'endColumn' => 44,
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
 * Append the output of the command to a given location.
 *
 * @param  string  $location
 * @return $this
 */',
        'startLine' => 401,
        'endLine' => 404,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'emailOutputTo' => 
      array (
        'name' => 'emailOutputTo',
        'parameters' => 
        array (
          'addresses' => 
          array (
            'name' => 'addresses',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 415,
            'endLine' => 415,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'onlyIfOutputExists' => 
          array (
            'name' => 'onlyIfOutputExists',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 415,
                'endLine' => 415,
                'startTokenPos' => 1326,
                'startFilePos' => 9957,
                'endTokenPos' => 1326,
                'endFilePos' => 9960,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 415,
            'endLine' => 415,
            'startColumn' => 47,
            'endColumn' => 72,
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
 * E-mail the results of the scheduled operation.
 *
 * @param  mixed  $addresses
 * @param  bool  $onlyIfOutputExists
 * @return $this
 *
 * @throws \\LogicException
 */',
        'startLine' => 415,
        'endLine' => 424,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'emailWrittenOutputTo' => 
      array (
        'name' => 'emailWrittenOutputTo',
        'parameters' => 
        array (
          'addresses' => 
          array (
            'name' => 'addresses',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 434,
            'endLine' => 434,
            'startColumn' => 42,
            'endColumn' => 51,
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
 * E-mail the results of the scheduled operation if it produces output.
 *
 * @param  mixed  $addresses
 * @return $this
 *
 * @throws \\LogicException
 */',
        'startLine' => 434,
        'endLine' => 437,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'emailOutputOnFailure' => 
      array (
        'name' => 'emailOutputOnFailure',
        'parameters' => 
        array (
          'addresses' => 
          array (
            'name' => 'addresses',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 445,
            'endLine' => 445,
            'startColumn' => 42,
            'endColumn' => 51,
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
 * E-mail the results of the scheduled operation if it fails.
 *
 * @param  mixed  $addresses
 * @return $this
 */',
        'startLine' => 445,
        'endLine' => 454,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'ensureOutputIsBeingCaptured' => 
      array (
        'name' => 'ensureOutputIsBeingCaptured',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure that the command output is being captured.
 *
 * @return void
 */',
        'startLine' => 461,
        'endLine' => 466,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'emailOutput' => 
      array (
        'name' => 'emailOutput',
        'parameters' => 
        array (
          'mailer' => 
          array (
            'name' => 'mailer',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Mail\\Mailer',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 476,
            'endLine' => 476,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'addresses' => 
          array (
            'name' => 'addresses',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 476,
            'endLine' => 476,
            'startColumn' => 52,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'onlyIfOutputExists' => 
          array (
            'name' => 'onlyIfOutputExists',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 476,
                'endLine' => 476,
                'startTokenPos' => 1582,
                'startFilePos' => 11683,
                'endTokenPos' => 1582,
                'endFilePos' => 11686,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 476,
            'endLine' => 476,
            'startColumn' => 64,
            'endColumn' => 89,
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
 * E-mail the output of the event to the recipients.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailer  $mailer
 * @param  array  $addresses
 * @param  bool  $onlyIfOutputExists
 * @return void
 */',
        'startLine' => 476,
        'endLine' => 487,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'getEmailSubject' => 
      array (
        'name' => 'getEmailSubject',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the e-mail subject line for output results.
 *
 * @return string
 */',
        'startLine' => 494,
        'endLine' => 501,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingBefore' => 
      array (
        'name' => 'pingBefore',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 509,
            'endLine' => 509,
            'startColumn' => 32,
            'endColumn' => 35,
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
 * Register a callback to ping a given URL before the job runs.
 *
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 509,
        'endLine' => 512,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingBeforeIf' => 
      array (
        'name' => 'pingBeforeIf',
        'parameters' => 
        array (
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
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 42,
            'endColumn' => 45,
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
 * Register a callback to ping a given URL before the job runs if the given condition is true.
 *
 * @param  bool  $value
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 521,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'thenPing' => 
      array (
        'name' => 'thenPing',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 532,
            'endLine' => 532,
            'startColumn' => 30,
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
 * Register a callback to ping a given URL after the job runs.
 *
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 532,
        'endLine' => 535,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'thenPingIf' => 
      array (
        'name' => 'thenPingIf',
        'parameters' => 
        array (
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
            'startLine' => 544,
            'endLine' => 544,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 544,
            'endLine' => 544,
            'startColumn' => 40,
            'endColumn' => 43,
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
 * Register a callback to ping a given URL after the job runs if the given condition is true.
 *
 * @param  bool  $value
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 544,
        'endLine' => 547,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingOnSuccess' => 
      array (
        'name' => 'pingOnSuccess',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 35,
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
 * Register a callback to ping a given URL if the operation succeeds.
 *
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 555,
        'endLine' => 558,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingOnSuccessIf' => 
      array (
        'name' => 'pingOnSuccessIf',
        'parameters' => 
        array (
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
            'startLine' => 567,
            'endLine' => 567,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 567,
            'endLine' => 567,
            'startColumn' => 45,
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
 * Register a callback to ping a given URL if the operation succeeds and if the given condition is true.
 *
 * @param  bool  $value
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 567,
        'endLine' => 570,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingOnFailure' => 
      array (
        'name' => 'pingOnFailure',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 578,
            'endLine' => 578,
            'startColumn' => 35,
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
 * Register a callback to ping a given URL if the operation fails.
 *
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 578,
        'endLine' => 581,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingOnFailureIf' => 
      array (
        'name' => 'pingOnFailureIf',
        'parameters' => 
        array (
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
            'startLine' => 590,
            'endLine' => 590,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 590,
            'endLine' => 590,
            'startColumn' => 45,
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
 * Register a callback to ping a given URL if the operation fails and if the given condition is true.
 *
 * @param  bool  $value
 * @param  string  $url
 * @return $this
 */',
        'startLine' => 590,
        'endLine' => 593,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'pingCallback' => 
      array (
        'name' => 'pingCallback',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 601,
            'endLine' => 601,
            'startColumn' => 37,
            'endColumn' => 40,
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
 * Get the callback that pings the given URL.
 *
 * @param  string  $url
 * @return \\Closure
 */',
        'startLine' => 601,
        'endLine' => 610,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'getHttpClient' => 
      array (
        'name' => 'getHttpClient',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 618,
            'endLine' => 618,
            'startColumn' => 38,
            'endColumn' => 57,
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
 * Get the Guzzle HTTP client to use to send pings.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return \\GuzzleHttp\\ClientInterface
 */',
        'startLine' => 618,
        'endLine' => 629,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'before' => 
      array (
        'name' => 'before',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 637,
            'endLine' => 637,
            'startColumn' => 28,
            'endColumn' => 44,
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
 * Register a callback to be called before the operation.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 637,
        'endLine' => 642,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'after' => 
      array (
        'name' => 'after',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 650,
            'endLine' => 650,
            'startColumn' => 27,
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
 * Register a callback to be called after the operation.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 650,
        'endLine' => 653,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'then' => 
      array (
        'name' => 'then',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 661,
            'endLine' => 661,
            'startColumn' => 26,
            'endColumn' => 42,
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
 * Register a callback to be called after the operation.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 661,
        'endLine' => 672,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'thenWithOutput' => 
      array (
        'name' => 'thenWithOutput',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 681,
            'endLine' => 681,
            'startColumn' => 36,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'onlyIfOutputExists' => 
          array (
            'name' => 'onlyIfOutputExists',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 681,
                'endLine' => 681,
                'startTokenPos' => 2346,
                'startFilePos' => 17061,
                'endTokenPos' => 2346,
                'endFilePos' => 17065,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 681,
            'endLine' => 681,
            'startColumn' => 55,
            'endColumn' => 81,
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
 * Register a callback that uses the output after the job runs.
 *
 * @param  \\Closure  $callback
 * @param  bool  $onlyIfOutputExists
 * @return $this
 */',
        'startLine' => 681,
        'endLine' => 686,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'onSuccess' => 
      array (
        'name' => 'onSuccess',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 694,
            'endLine' => 694,
            'startColumn' => 31,
            'endColumn' => 47,
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
 * Register a callback to be called if the operation succeeds.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 694,
        'endLine' => 707,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'onSuccessWithOutput' => 
      array (
        'name' => 'onSuccessWithOutput',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 716,
            'endLine' => 716,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'onlyIfOutputExists' => 
          array (
            'name' => 'onlyIfOutputExists',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 716,
                'endLine' => 716,
                'startTokenPos' => 2511,
                'startFilePos' => 18110,
                'endTokenPos' => 2511,
                'endFilePos' => 18114,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 716,
            'endLine' => 716,
            'startColumn' => 60,
            'endColumn' => 86,
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
 * Register a callback that uses the output if the operation succeeds.
 *
 * @param  \\Closure  $callback
 * @param  bool  $onlyIfOutputExists
 * @return $this
 */',
        'startLine' => 716,
        'endLine' => 721,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'onFailure' => 
      array (
        'name' => 'onFailure',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 729,
            'endLine' => 729,
            'startColumn' => 31,
            'endColumn' => 47,
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
 * Register a callback to be called if the operation fails.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 729,
        'endLine' => 742,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'onFailureWithOutput' => 
      array (
        'name' => 'onFailureWithOutput',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 751,
            'endLine' => 751,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'onlyIfOutputExists' => 
          array (
            'name' => 'onlyIfOutputExists',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 751,
                'endLine' => 751,
                'startTokenPos' => 2676,
                'startFilePos' => 19158,
                'endTokenPos' => 2676,
                'endFilePos' => 19162,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 751,
            'endLine' => 751,
            'startColumn' => 60,
            'endColumn' => 86,
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
 * Register a callback that uses the output if the operation fails.
 *
 * @param  \\Closure  $callback
 * @param  bool  $onlyIfOutputExists
 * @return $this
 */',
        'startLine' => 751,
        'endLine' => 756,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'withOutputCallback' => 
      array (
        'name' => 'withOutputCallback',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 765,
            'endLine' => 765,
            'startColumn' => 43,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'onlyIfOutputExists' => 
          array (
            'name' => 'onlyIfOutputExists',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 765,
                'endLine' => 765,
                'startTokenPos' => 2725,
                'startFilePos' => 19567,
                'endTokenPos' => 2725,
                'endFilePos' => 19571,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 765,
            'endLine' => 765,
            'startColumn' => 62,
            'endColumn' => 88,
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
 * Get a callback that provides output.
 *
 * @param  \\Closure  $callback
 * @param  bool  $onlyIfOutputExists
 * @return \\Closure
 */',
        'startLine' => 765,
        'endLine' => 774,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'callEventCallback' => 
      array (
        'name' => 'callEventCallback',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 784,
            'endLine' => 784,
            'startColumn' => 42,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 784,
            'endLine' => 784,
            'startColumn' => 64,
            'endColumn' => 81,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 784,
                'endLine' => 784,
                'startTokenPos' => 2853,
                'startFilePos' => 20312,
                'endTokenPos' => 2854,
                'endFilePos' => 20313,
              ),
            ),
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
            'startLine' => 784,
            'endLine' => 784,
            'startColumn' => 84,
            'endColumn' => 105,
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
 * Call the given event callback.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @param  callable  $callback
 * @param  array<string, mixed>  $parameters
 * @return mixed
 */',
        'startLine' => 784,
        'endLine' => 793,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'eventParametersForCallback' => 
      array (
        'name' => 'eventParametersForCallback',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 801,
            'endLine' => 801,
            'startColumn' => 51,
            'endColumn' => 67,
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
 * Get the event parameters for the given callback.
 *
 * @param  \\Closure  $callback
 * @return array
 */',
        'startLine' => 801,
        'endLine' => 812,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'getSummaryForDisplay' => 
      array (
        'name' => 'getSummaryForDisplay',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the summary of the event for display.
 *
 * @return string
 */',
        'startLine' => 819,
        'endLine' => 826,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'nextRunDate' => 
      array (
        'name' => 'nextRunDate',
        'parameters' => 
        array (
          'currentTime' => 
          array (
            'name' => 'currentTime',
            'default' => 
            array (
              'code' => '\'now\'',
              'attributes' => 
              array (
                'startLine' => 836,
                'endLine' => 836,
                'startTokenPos' => 3052,
                'startFilePos' => 21608,
                'endTokenPos' => 3052,
                'endFilePos' => 21612,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'nth' => 
          array (
            'name' => 'nth',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 836,
                'endLine' => 836,
                'startTokenPos' => 3059,
                'startFilePos' => 21622,
                'endTokenPos' => 3059,
                'endFilePos' => 21622,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 55,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'allowCurrentDate' => 
          array (
            'name' => 'allowCurrentDate',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 836,
                'endLine' => 836,
                'startTokenPos' => 3066,
                'startFilePos' => 21645,
                'endTokenPos' => 3066,
                'endFilePos' => 21649,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 836,
            'endLine' => 836,
            'startColumn' => 65,
            'endColumn' => 89,
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
 * Determine the next due date for an event.
 *
 * @param  \\DateTimeInterface|string  $currentTime
 * @param  int  $nth
 * @param  bool  $allowCurrentDate
 * @return \\Illuminate\\Support\\Carbon
 */',
        'startLine' => 836,
        'endLine' => 840,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'getExpression' => 
      array (
        'name' => 'getExpression',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the Cron expression for the event.
 *
 * @return string
 */',
        'startLine' => 847,
        'endLine' => 850,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'preventOverlapsUsing' => 
      array (
        'name' => 'preventOverlapsUsing',
        'parameters' => 
        array (
          'mutex' => 
          array (
            'name' => 'mutex',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Console\\Scheduling\\EventMutex',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 858,
            'endLine' => 858,
            'startColumn' => 42,
            'endColumn' => 58,
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
 * Set the event mutex implementation to be used.
 *
 * @param  \\Illuminate\\Console\\Scheduling\\EventMutex  $mutex
 * @return $this
 */',
        'startLine' => 858,
        'endLine' => 863,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'mutexName' => 
      array (
        'name' => 'mutexName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the mutex name for the scheduled command.
 *
 * @return string
 */',
        'startLine' => 870,
        'endLine' => 880,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'createMutexNameUsing' => 
      array (
        'name' => 'createMutexNameUsing',
        'parameters' => 
        array (
          'mutexName' => 
          array (
            'name' => 'mutexName',
            'default' => NULL,
            'type' => 
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
                      'name' => 'Closure',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 888,
            'endLine' => 888,
            'startColumn' => 42,
            'endColumn' => 66,
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
 * Set the mutex name or name resolver callback.
 *
 * @param  \\Closure|string  $mutexName
 * @return $this
 */',
        'startLine' => 888,
        'endLine' => 893,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'ensureMutexIsReleasedOnSignal' => 
      array (
        'name' => 'ensureMutexIsReleasedOnSignal',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the mutex is released if the process receives a termination signal.
 *
 * @return void
 */',
        'startLine' => 900,
        'endLine' => 917,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'removeMutex' => 
      array (
        'name' => 'removeMutex',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete the mutex for the event.
 *
 * @return void
 */',
        'startLine' => 924,
        'endLine' => 929,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'aliasName' => NULL,
      ),
      'normalizeCommand' => 
      array (
        'name' => 'normalizeCommand',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 937,
            'endLine' => 937,
            'startColumn' => 45,
            'endColumn' => 52,
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
 * Format the given command string with a normalized PHP binary path.
 *
 * @param  string  $command
 * @return string
 */',
        'startLine' => 937,
        'endLine' => 946,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\Event',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\Event',
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