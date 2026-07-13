<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/ScheduleWorkCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Scheduling\ScheduleWorkCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-df84a73e2d4dbcdc2ac152575402f7738eb6652b6d65845799b17399da138fad-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/ScheduleWorkCommand.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Scheduling',
    'name' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
    'shortName' => 'ScheduleWorkCommand',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
      0 => 
      array (
        'name' => 'Symfony\\Component\\Console\\Attribute\\AsCommand',
        'isRepeated' => false,
        'arguments' => 
        array (
          'name' => 
          array (
            'code' => '\'schedule:work\'',
            'attributes' => 
            array (
              'startLine' => 13,
              'endLine' => 13,
              'startTokenPos' => 48,
              'startFilePos' => 348,
              'endTokenPos' => 48,
              'endFilePos' => 362,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 13,
    'endLine' => 137,
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
      'signature' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'schedule:work
        {--run-output-file= : The file to direct <info>schedule:run</info> output to}
        {--whisper : Do not output message indicating that no jobs were ready to run}\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 23,
            'startTokenPos' => 70,
            'startFilePos' => 533,
            'endTokenPos' => 70,
            'endFilePos' => 719,
          ),
        ),
        'docComment' => '/**
 * The name and signature of the console command.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 87,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Start the schedule worker\'',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 81,
            'startFilePos' => 834,
            'endTokenPos' => 81,
            'endFilePos' => 860,
          ),
        ),
        'docComment' => '/**
 * The console command description.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 57,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'executions' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'name' => 'executions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 92,
            'startFilePos' => 1029,
            'endTokenPos' => 93,
            'endFilePos' => 1030,
          ),
        ),
        'docComment' => '/**
 * The "schedule:run" executions that are currently running.
 *
 * @var \\Symfony\\Component\\Process\\Process[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shouldQuit' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'name' => 'shouldQuit',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 44,
            'startTokenPos' => 104,
            'startFilePos' => 1155,
            'endTokenPos' => 104,
            'endFilePos' => 1159,
          ),
        ),
        'docComment' => '/**
 * Indicates if the schedule worker should exit.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 34,
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
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the console command.
 *
 * @return int
 */',
        'startLine' => 51,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'aliasName' => NULL,
      ),
      'work' => 
      array (
        'name' => 'work',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 29,
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
 * Run the schedule worker loop until it is signalled to stop.
 *
 * @param  string  $command
 * @return int
 */',
        'startLine' => 79,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'aliasName' => NULL,
      ),
      'listenForSignals' => 
      array (
        'name' => 'listenForSignals',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Listen for the signals that should terminate the schedule worker.
 *
 * @return void
 */',
        'startLine' => 121,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'aliasName' => NULL,
      ),
      'sleep' => 
      array (
        'name' => 'sleep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sleep for a short period before the next worker tick.
 *
 * @return void
 */',
        'startLine' => 133,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ScheduleWorkCommand',
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