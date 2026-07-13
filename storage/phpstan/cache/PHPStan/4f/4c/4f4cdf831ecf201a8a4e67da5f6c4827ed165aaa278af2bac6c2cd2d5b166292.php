<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Database/Console/WipeCommand.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Console\WipeCommand
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-bc6da1a886a7e8432a21c96342a7d690a060e83a26b3378a7b47d7ad9f8d7231-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Console\\WipeCommand',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Database/Console/WipeCommand.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Console',
    'name' => 'Illuminate\\Database\\Console\\WipeCommand',
    'shortName' => 'WipeCommand',
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
            'code' => '\'db:wipe\'',
            'attributes' => 
            array (
              'startLine' => 12,
              'endLine' => 12,
              'startTokenPos' => 43,
              'startFilePos' => 349,
              'endTokenPos' => 43,
              'endFilePos' => 357,
            ),
          ),
        ),
      ),
    ),
    'startLine' => 12,
    'endLine' => 130,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Console\\Command',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Console\\ConfirmableTrait',
      1 => 'Illuminate\\Console\\Prohibitable',
      2 => 'Illuminate\\Database\\Console\\Concerns\\InteractsWithPooledConnections',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'name' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'name' => 'name',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'db:wipe\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 76,
            'startFilePos' => 567,
            'endTokenPos' => 76,
            'endFilePos' => 575,
          ),
        ),
        'docComment' => '/**
 * The console command name.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Drop all tables, views, and types\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 87,
            'startFilePos' => 690,
            'endTokenPos' => 87,
            'endFilePos' => 724,
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 65,
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
        'startLine' => 36,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Console',
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'currentClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'aliasName' => NULL,
      ),
      'dropAllTables' => 
      array (
        'name' => 'dropAllTables',
        'parameters' => 
        array (
          'database' => 
          array (
            'name' => 'database',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 38,
            'endColumn' => 46,
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
 * Drop all of the database tables.
 *
 * @param  string  $database
 * @return void
 */',
        'startLine' => 72,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Console',
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'currentClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'aliasName' => NULL,
      ),
      'dropAllViews' => 
      array (
        'name' => 'dropAllViews',
        'parameters' => 
        array (
          'database' => 
          array (
            'name' => 'database',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 85,
            'endLine' => 85,
            'startColumn' => 37,
            'endColumn' => 45,
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
 * Drop all of the database views.
 *
 * @param  string  $database
 * @return void
 */',
        'startLine' => 85,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Console',
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'currentClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'aliasName' => NULL,
      ),
      'dropAllTypes' => 
      array (
        'name' => 'dropAllTypes',
        'parameters' => 
        array (
          'database' => 
          array (
            'name' => 'database',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 98,
            'endLine' => 98,
            'startColumn' => 37,
            'endColumn' => 45,
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
 * Drop all of the database types.
 *
 * @param  string  $database
 * @return void
 */',
        'startLine' => 98,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Console',
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'currentClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'aliasName' => NULL,
      ),
      'flushDatabaseConnection' => 
      array (
        'name' => 'flushDatabaseConnection',
        'parameters' => 
        array (
          'database' => 
          array (
            'name' => 'database',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 48,
            'endColumn' => 56,
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
 * Flush the given database connection.
 *
 * @param  string  $database
 * @return void
 */',
        'startLine' => 111,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Console',
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'currentClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'aliasName' => NULL,
      ),
      'getOptions' => 
      array (
        'name' => 'getOptions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the console command options.
 *
 * @return array
 */',
        'startLine' => 121,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Console',
        'declaringClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'implementingClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
        'currentClassName' => 'Illuminate\\Database\\Console\\WipeCommand',
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