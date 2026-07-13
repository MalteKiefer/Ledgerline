<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/ResultInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Aws\ResultInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-4a68dc9bc193324aaa4e5d014420515ed7c38ace9adde463b5845a4d422d5e07-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Aws\\ResultInterface',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/ResultInterface.php',
      ),
    ),
    'namespace' => 'Aws',
    'name' => 'Aws\\ResultInterface',
    'shortName' => 'ResultInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Represents an AWS result object that is returned from executing an operation.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 54,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'ArrayAccess',
      1 => 'IteratorAggregate',
      2 => 'Countable',
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
      '__toString' => 
      array (
        'name' => '__toString',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Provides debug information about the result object
 *
 * @return string
 */',
        'startLine' => 14,
        'endLine' => 14,
        'startColumn' => 5,
        'endColumn' => 33,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\ResultInterface',
        'implementingClassName' => 'Aws\\ResultInterface',
        'currentClassName' => 'Aws\\ResultInterface',
        'aliasName' => NULL,
      ),
      'toArray' => 
      array (
        'name' => 'toArray',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Convert the result to an array.
 *
 * @return array
 */',
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 30,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\ResultInterface',
        'implementingClassName' => 'Aws\\ResultInterface',
        'currentClassName' => 'Aws\\ResultInterface',
        'aliasName' => NULL,
      ),
      'hasKey' => 
      array (
        'name' => 'hasKey',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 28,
            'endColumn' => 32,
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
 * Check if the model contains a key by name
 *
 * @param string $name Name of the key to retrieve
 *
 * @return bool
 */',
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\ResultInterface',
        'implementingClassName' => 'Aws\\ResultInterface',
        'currentClassName' => 'Aws\\ResultInterface',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 25,
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
 * Get a specific key value from the result model.
 *
 * @param string $key Key to retrieve.
 *
 * @return mixed|null Value of the key or NULL if not found.
 */',
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 30,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\ResultInterface',
        'implementingClassName' => 'Aws\\ResultInterface',
        'currentClassName' => 'Aws\\ResultInterface',
        'aliasName' => NULL,
      ),
      'search' => 
      array (
        'name' => 'search',
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
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 28,
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
 * Returns the result of executing a JMESPath expression on the contents
 * of the Result model.
 *
 *     $result = $client->execute($command);
 *     $jpResult = $result->search(\'foo.*.bar[?baz > `10`]\');
 *
 * @param string $expression JMESPath expression to execute
 *
 * @return mixed Returns the result of the JMESPath expression.
 * @link http://jmespath.readthedocs.org/en/latest/ JMESPath documentation
 */',
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 40,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\ResultInterface',
        'implementingClassName' => 'Aws\\ResultInterface',
        'currentClassName' => 'Aws\\ResultInterface',
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