<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../dragonmantank/cron-expression/src/Cron/CronExpression.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Cron\CronExpression
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-d257d01d5f331236e20854c4b0bb611e8dbcc41d9f2c09fa1398a30e00027ca2-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Cron\\CronExpression',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../dragonmantank/cron-expression/src/Cron/CronExpression.php',
      ),
    ),
    'namespace' => 'Cron',
    'name' => 'Cron\\CronExpression',
    'shortName' => 'CronExpression',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * CRON expression parser that can determine whether or not a CRON expression is
 * due to run, the next run date and previous run date of a CRON expression.
 * The determinations made by this class are accurate if checked run once per
 * minute (seconds are dropped from date time comparisons).
 *
 * Schedule parts must map to:
 * minute [0-59], hour [0-23], day of month, month [1-12|JAN-DEC], day of week
 * [1-7|MON-SUN], and an optional year.
 *
 * @see http://en.wikipedia.org/wiki/Cron
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 591,
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
      'MINUTE' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'MINUTE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 71,
            'startFilePos' => 767,
            'endTokenPos' => 71,
            'endFilePos' => 767,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
      'HOUR' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'HOUR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 82,
            'startFilePos' => 794,
            'endTokenPos' => 82,
            'endFilePos' => 794,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 26,
      ),
      'DAY' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'DAY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 93,
            'startFilePos' => 820,
            'endTokenPos' => 93,
            'endFilePos' => 820,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'MONTH' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'MONTH',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 104,
            'startFilePos' => 848,
            'endTokenPos' => 104,
            'endFilePos' => 848,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
      'WEEKDAY' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'WEEKDAY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 115,
            'startFilePos' => 878,
            'endTokenPos' => 115,
            'endFilePos' => 878,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'YEAR' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'YEAR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 128,
            'startFilePos' => 929,
            'endTokenPos' => 128,
            'endFilePos' => 929,
          ),
        ),
        'docComment' => '/** @deprecated */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 26,
      ),
      'MAPPINGS' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'MAPPINGS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'@yearly\' => \'0 0 1 1 *\', \'@annually\' => \'0 0 1 1 *\', \'@monthly\' => \'0 0 1 * *\', \'@weekly\' => \'0 0 * * 0\', \'@daily\' => \'0 0 * * *\', \'@midnight\' => \'0 0 * * *\', \'@hourly\' => \'0 * * * *\']',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 47,
            'startTokenPos' => 139,
            'startFilePos' => 961,
            'endTokenPos' => 190,
            'endFilePos' => 1209,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'cronParts' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'cronParts',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var array<int, string> CRON expression parts
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fieldFactory' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'fieldFactory',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var FieldFactoryInterface CRON field factory
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'maxIterationCount' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'maxIterationCount',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '1000',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 215,
            'startFilePos' => 1528,
            'endTokenPos' => 215,
            'endFilePos' => 1531,
          ),
        ),
        'docComment' => '/**
 * @var int Max iteration count when searching for next run date
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'order' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'order',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[self::YEAR, self::MONTH, self::DAY, self::WEEKDAY, self::HOUR, self::MINUTE]',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 74,
            'startTokenPos' => 228,
            'startFilePos' => 1646,
            'endTokenPos' => 260,
            'endFilePos' => 1777,
          ),
        ),
        'docComment' => '/**
 * @var array<int, int> Order in which to test of cron parts
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'registeredAliases' => 
      array (
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'name' => 'registeredAliases',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'self::MAPPINGS',
          'attributes' => 
          array (
            'startLine' => 79,
            'endLine' => 79,
            'startTokenPos' => 273,
            'startFilePos' => 1871,
            'endTokenPos' => 275,
            'endFilePos' => 1884,
          ),
        ),
        'docComment' => '/**
 * @var array<string, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 79,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 55,
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
      'registerAlias' => 
      array (
        'name' => 'registerAlias',
        'parameters' => 
        array (
          'alias' => 
          array (
            'name' => 'alias',
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
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expression' => 
          array (
            'name' => 'expression',
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
            'startLine' => 87,
            'endLine' => 87,
            'startColumn' => 57,
            'endColumn' => 74,
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
 * Registered a user defined CRON Expression Alias.
 *
 * @throws LogicException If the expression or the alias name are invalid
 *                         or if the alias is already registered.
 */',
        'startLine' => 87,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'unregisterAlias' => 
      array (
        'name' => 'unregisterAlias',
        'parameters' => 
        array (
          'alias' => 
          array (
            'name' => 'alias',
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
            'startLine' => 112,
            'endLine' => 112,
            'startColumn' => 44,
            'endColumn' => 56,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Unregistered a user defined CRON Expression Alias.
 *
 * @throws LogicException If the user tries to unregister a built-in alias
 */',
        'startLine' => 112,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'supportsAlias' => 
      array (
        'name' => 'supportsAlias',
        'parameters' => 
        array (
          'alias' => 
          array (
            'name' => 'alias',
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
            'startLine' => 131,
            'endLine' => 131,
            'startColumn' => 42,
            'endColumn' => 54,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Tells whether a CRON Expression alias is registered.
 */',
        'startLine' => 131,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getAliases' => 
      array (
        'name' => 'getAliases',
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
 * Returns all registered aliases as an associated array where the aliases are the key
 * and their associated expressions are the values.
 *
 * @return array<string, string>
 */',
        'startLine' => 142,
        'endLine' => 145,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'factory' => 
      array (
        'name' => 'factory',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
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
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 36,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fieldFactory' => 
          array (
            'name' => 'fieldFactory',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 150,
                'endLine' => 150,
                'startTokenPos' => 629,
                'startFilePos' => 4249,
                'endTokenPos' => 629,
                'endFilePos' => 4252,
              ),
            ),
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
                      'name' => 'Cron\\FieldFactoryInterface',
                      'isIdentifier' => false,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 56,
            'endColumn' => 98,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Cron\\CronExpression',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @deprecated since version 3.0.2, use __construct instead.
 */',
        'startLine' => 150,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'isValidExpression' => 
      array (
        'name' => 'isValidExpression',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
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
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 46,
            'endColumn' => 63,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate a CronExpression.
 *
 * @param string $expression the CRON expression to validate
 *
 * @return bool True if a valid CRON expression was passed. False if not.
 */',
        'startLine' => 163,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
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
            'startLine' => 181,
            'endLine' => 181,
            'startColumn' => 33,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fieldFactory' => 
          array (
            'name' => 'fieldFactory',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 181,
                'endLine' => 181,
                'startTokenPos' => 732,
                'startFilePos' => 5175,
                'endTokenPos' => 732,
                'endFilePos' => 5178,
              ),
            ),
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
                      'name' => 'Cron\\FieldFactoryInterface',
                      'isIdentifier' => false,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 181,
            'endLine' => 181,
            'startColumn' => 53,
            'endColumn' => 95,
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
 * Parse a CRON expression.
 *
 * @param string $expression CRON expression (e.g. \'8 * * * *\')
 * @param null|FieldFactoryInterface $fieldFactory Factory to create cron fields
 * @throws InvalidArgumentException
 */',
        'startLine' => 181,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'setExpression' => 
      array (
        'name' => 'setExpression',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 199,
            'endLine' => 199,
            'startColumn' => 35,
            'endColumn' => 47,
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
            'name' => 'Cron\\CronExpression',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set or change the CRON expression.
 *
 * @param string $value CRON expression (e.g. 8 * * * *)
 *
 * @throws \\InvalidArgumentException if not a valid CRON expression
 *
 * @return CronExpression
 */',
        'startLine' => 199,
        'endLine' => 230,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'setPart' => 
      array (
        'name' => 'setPart',
        'parameters' => 
        array (
          'position' => 
          array (
            'name' => 'position',
            'default' => NULL,
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
            'startLine' => 242,
            'endLine' => 242,
            'startColumn' => 29,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 242,
            'endLine' => 242,
            'startColumn' => 44,
            'endColumn' => 56,
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
            'name' => 'Cron\\CronExpression',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set part of the CRON expression.
 *
 * @param int $position The position of the CRON expression to set
 * @param string $value The value to set
 *
 * @throws \\InvalidArgumentException if the value is not valid for the part
 *
 * @return CronExpression
 */',
        'startLine' => 242,
        'endLine' => 253,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'setMaxIterationCount' => 
      array (
        'name' => 'setMaxIterationCount',
        'parameters' => 
        array (
          'maxIterationCount' => 
          array (
            'name' => 'maxIterationCount',
            'default' => NULL,
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
            'startLine' => 262,
            'endLine' => 262,
            'startColumn' => 42,
            'endColumn' => 63,
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
            'name' => 'Cron\\CronExpression',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set max iteration count for searching next run dates.
 *
 * @param int $maxIterationCount Max iteration count when searching for next run date
 *
 * @return CronExpression
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
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getNextRunDate' => 
      array (
        'name' => 'getNextRunDate',
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
                'startLine' => 289,
                'endLine' => 289,
                'startTokenPos' => 1200,
                'startFilePos' => 9236,
                'endTokenPos' => 1200,
                'endFilePos' => 9240,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 36,
            'endColumn' => 55,
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
                'startLine' => 289,
                'endLine' => 289,
                'startTokenPos' => 1209,
                'startFilePos' => 9254,
                'endTokenPos' => 1209,
                'endFilePos' => 9254,
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
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 58,
            'endColumn' => 69,
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
                'startLine' => 289,
                'endLine' => 289,
                'startTokenPos' => 1218,
                'startFilePos' => 9282,
                'endTokenPos' => 1218,
                'endFilePos' => 9286,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 72,
            'endColumn' => 101,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'timeZone' => 
          array (
            'name' => 'timeZone',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 289,
                'endLine' => 289,
                'startTokenPos' => 1225,
                'startFilePos' => 9301,
                'endTokenPos' => 1225,
                'endFilePos' => 9304,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 104,
            'endColumn' => 119,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'DateTime',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a next run date relative to the current date or a specific date
 *
 * @param string|\\DateTimeInterface $currentTime      Relative calculation date
 * @param int                       $nth              Number of matches to skip before returning a
 *                                                    matching next run date.  0, the default, will return the
 *                                                    current date and time if the next run date falls on the
 *                                                    current date and time.  Setting this value to 1 will
 *                                                    skip the first match and go to the second match.
 *                                                    Setting this value to 2 will skip the first 2
 *                                                    matches and so on.
 * @param bool                      $allowCurrentDate Set to TRUE to return the current date if
 *                                                    it matches the cron expression.
 * @param null|string               $timeZone         TimeZone to use instead of the system default
 *
 * @throws \\RuntimeException on too many iterations
 * @throws \\Exception
 *
 * @return \\DateTime
 */',
        'startLine' => 289,
        'endLine' => 292,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getPreviousRunDate' => 
      array (
        'name' => 'getPreviousRunDate',
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
                'startLine' => 310,
                'endLine' => 310,
                'startTokenPos' => 1269,
                'startFilePos' => 10233,
                'endTokenPos' => 1269,
                'endFilePos' => 10237,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 310,
            'endLine' => 310,
            'startColumn' => 40,
            'endColumn' => 59,
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
                'startLine' => 310,
                'endLine' => 310,
                'startTokenPos' => 1278,
                'startFilePos' => 10251,
                'endTokenPos' => 1278,
                'endFilePos' => 10251,
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
            'startLine' => 310,
            'endLine' => 310,
            'startColumn' => 62,
            'endColumn' => 73,
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
                'startLine' => 310,
                'endLine' => 310,
                'startTokenPos' => 1287,
                'startFilePos' => 10279,
                'endTokenPos' => 1287,
                'endFilePos' => 10283,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 310,
            'endLine' => 310,
            'startColumn' => 76,
            'endColumn' => 105,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'timeZone' => 
          array (
            'name' => 'timeZone',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 310,
                'endLine' => 310,
                'startTokenPos' => 1294,
                'startFilePos' => 10298,
                'endTokenPos' => 1294,
                'endFilePos' => 10301,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 310,
            'endLine' => 310,
            'startColumn' => 108,
            'endColumn' => 123,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'DateTime',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a previous run date relative to the current date or a specific date.
 *
 * @param string|\\DateTimeInterface $currentTime      Relative calculation date
 * @param int                       $nth              Number of matches to skip before returning
 * @param bool                      $allowCurrentDate Set to TRUE to return the
 *                                                    current date if it matches the cron expression
 * @param null|string               $timeZone         TimeZone to use instead of the system default
 *
 * @throws \\RuntimeException on too many iterations
 * @throws \\Exception
 *
 * @return \\DateTime
 *
 * @see \\Cron\\CronExpression::getNextRunDate
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
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getMultipleRunDates' => 
      array (
        'name' => 'getMultipleRunDates',
        'parameters' => 
        array (
          'total' => 
          array (
            'name' => 'total',
            'default' => NULL,
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
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 41,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'currentTime' => 
          array (
            'name' => 'currentTime',
            'default' => 
            array (
              'code' => '\'now\'',
              'attributes' => 
              array (
                'startLine' => 327,
                'endLine' => 327,
                'startTokenPos' => 1343,
                'startFilePos' => 11097,
                'endTokenPos' => 1343,
                'endFilePos' => 11101,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 53,
            'endColumn' => 72,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'invert' => 
          array (
            'name' => 'invert',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 327,
                'endLine' => 327,
                'startTokenPos' => 1352,
                'startFilePos' => 11119,
                'endTokenPos' => 1352,
                'endFilePos' => 11123,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 75,
            'endColumn' => 94,
            'parameterIndex' => 2,
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
                'startLine' => 327,
                'endLine' => 327,
                'startTokenPos' => 1361,
                'startFilePos' => 11151,
                'endTokenPos' => 1361,
                'endFilePos' => 11155,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 97,
            'endColumn' => 126,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'timeZone' => 
          array (
            'name' => 'timeZone',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 327,
                'endLine' => 327,
                'startTokenPos' => 1368,
                'startFilePos' => 11170,
                'endTokenPos' => 1368,
                'endFilePos' => 11173,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 129,
            'endColumn' => 144,
            'parameterIndex' => 4,
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
 * Get multiple run dates starting at the current date or a specific date.
 *
 * @param int $total Set the total number of dates to calculate
 * @param string|\\DateTimeInterface|null $currentTime Relative calculation date
 * @param bool $invert Set to TRUE to retrieve previous dates
 * @param bool $allowCurrentDate Set to TRUE to return the
 *                               current date if it matches the cron expression
 * @param null|string $timeZone TimeZone to use instead of the system default
 *
 * @return \\DateTime[] Returns an array of run dates
 */',
        'startLine' => 327,
        'endLine' => 361,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getExpression' => 
      array (
        'name' => 'getExpression',
        'parameters' => 
        array (
          'part' => 
          array (
            'name' => 'part',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 372,
                'endLine' => 372,
                'startTokenPos' => 1658,
                'startFilePos' => 12744,
                'endTokenPos' => 1658,
                'endFilePos' => 12747,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 372,
            'endLine' => 372,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
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
        'docComment' => '/**
 * Get all or part of the CRON expression.
 *
 * @param int|string|null $part specify the part to retrieve or NULL to get the full
 *                     cron schedule string
 *
 * @return null|string Returns the CRON expression, a part of the
 *                     CRON expression, or NULL if the part was specified but not found
 */',
        'startLine' => 372,
        'endLine' => 383,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getParts' => 
      array (
        'name' => 'getParts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the parts of the cron expression as an array.
 *
 * @return string[]
 *   The array of parts that make up this expression.
 */',
        'startLine' => 391,
        'endLine' => 394,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      '__toString' => 
      array (
        'name' => '__toString',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Helper method to output the full expression.
 *
 * @return string Full CRON expression
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
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'isDue' => 
      array (
        'name' => 'isDue',
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
                'startLine' => 416,
                'endLine' => 416,
                'startTokenPos' => 1790,
                'startFilePos' => 13994,
                'endTokenPos' => 1790,
                'endFilePos' => 13998,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 27,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'timeZone' => 
          array (
            'name' => 'timeZone',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 416,
                'endLine' => 416,
                'startTokenPos' => 1797,
                'startFilePos' => 14013,
                'endTokenPos' => 1797,
                'endFilePos' => 14016,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 49,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the cron is due to run based on the current date or a
 * specific date.  This method assumes that the current number of
 * seconds are irrelevant, and should be called once per minute.
 *
 * @param string|\\DateTimeInterface $currentTime Relative calculation date
 * @param null|string               $timeZone    TimeZone to use instead of the system default
 *
 * @return bool Returns TRUE if the cron is due to run or FALSE if not
 */',
        'startLine' => 416,
        'endLine' => 444,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'getRunDate' => 
      array (
        'name' => 'getRunDate',
        'parameters' => 
        array (
          'currentTime' => 
          array (
            'name' => 'currentTime',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 461,
                'endLine' => 461,
                'startTokenPos' => 2060,
                'startFilePos' => 15796,
                'endTokenPos' => 2060,
                'endFilePos' => 15799,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 35,
            'endColumn' => 53,
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
                'startLine' => 461,
                'endLine' => 461,
                'startTokenPos' => 2069,
                'startFilePos' => 15813,
                'endTokenPos' => 2069,
                'endFilePos' => 15813,
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
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 56,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'invert' => 
          array (
            'name' => 'invert',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 461,
                'endLine' => 461,
                'startTokenPos' => 2078,
                'startFilePos' => 15831,
                'endTokenPos' => 2078,
                'endFilePos' => 15835,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 70,
            'endColumn' => 89,
            'parameterIndex' => 2,
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
                'startLine' => 461,
                'endLine' => 461,
                'startTokenPos' => 2087,
                'startFilePos' => 15863,
                'endTokenPos' => 2087,
                'endFilePos' => 15867,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 92,
            'endColumn' => 121,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'timeZone' => 
          array (
            'name' => 'timeZone',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 461,
                'endLine' => 461,
                'startTokenPos' => 2094,
                'startFilePos' => 15882,
                'endTokenPos' => 2094,
                'endFilePos' => 15885,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 124,
            'endColumn' => 139,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'DateTime',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the next or previous run date of the expression relative to a date.
 *
 * @param string|\\DateTimeInterface|null $currentTime Relative calculation date
 * @param int $nth Number of matches to skip before returning
 * @param bool $invert Set to TRUE to go backwards in time
 * @param bool $allowCurrentDate Set to TRUE to return the
 *                               current date if it matches the cron expression
 * @param string|null $timeZone  TimeZone to use instead of the system default
 *
 * @throws \\RuntimeException on too many iterations
 * @throws Exception
 *
 * @return \\DateTime
 */',
        'startLine' => 461,
        'endLine' => 569,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
        'aliasName' => NULL,
      ),
      'determineTimeZone' => 
      array (
        'name' => 'determineTimeZone',
        'parameters' => 
        array (
          'currentTime' => 
          array (
            'name' => 'currentTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 579,
            'endLine' => 579,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'timeZone' => 
          array (
            'name' => 'timeZone',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 579,
            'endLine' => 579,
            'startColumn' => 56,
            'endColumn' => 72,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Workout what timeZone should be used.
 *
 * @param string|\\DateTimeInterface|null $currentTime Relative calculation date
 * @param string|null $timeZone TimeZone to use instead of the system default
 *
 * @return string
 */',
        'startLine' => 579,
        'endLine' => 590,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Cron',
        'declaringClassName' => 'Cron\\CronExpression',
        'implementingClassName' => 'Cron\\CronExpression',
        'currentClassName' => 'Cron\\CronExpression',
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