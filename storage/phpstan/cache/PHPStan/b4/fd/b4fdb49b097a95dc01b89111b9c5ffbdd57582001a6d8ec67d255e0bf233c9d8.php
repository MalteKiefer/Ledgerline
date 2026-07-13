<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Foundation/helpers.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-auth
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-84a94be4f68fd0f749a87f05a7cb1b78ddc07c7d2121b56c6f05ff6ef80d34c6',
   'data' => 
  array (
    'name' => 'auth',
    'parameters' => 
    array (
      'guard' => 
      array (
        'name' => 'guard',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 174,
            'endLine' => 174,
            'startTokenPos' => 784,
            'startFilePos' => 5745,
            'endTokenPos' => 784,
            'endFilePos' => 5748,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 174,
        'endLine' => 174,
        'startColumn' => 19,
        'endColumn' => 31,
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
              'name' => 'Illuminate\\Contracts\\Auth\\Factory',
              'isIdentifier' => false,
            ),
          ),
          1 => 
          array (
            'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
            'data' => 
            array (
              'name' => 'Illuminate\\Contracts\\Auth\\Guard',
              'isIdentifier' => false,
            ),
          ),
        ),
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Get the available auth instance.
 *
 * @param  string|null  $guard
 * @return ($guard is null ? \\Illuminate\\Contracts\\Auth\\Factory : \\Illuminate\\Contracts\\Auth\\Guard)
 */',
    'startLine' => 174,
    'endLine' => 181,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => false,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'auth',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Foundation/helpers.php',
      ),
    ),
  ),
));