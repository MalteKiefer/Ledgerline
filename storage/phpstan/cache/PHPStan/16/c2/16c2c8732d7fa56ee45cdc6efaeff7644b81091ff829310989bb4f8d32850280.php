<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/HasMonitoringEventsTrait.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Aws\HasMonitoringEventsTrait
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-fa52481a049cb048ec87fdb83bbf517527524698e76d8e2073793b0d099ab53a-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Aws\\HasMonitoringEventsTrait',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/HasMonitoringEventsTrait.php',
      ),
    ),
    'namespace' => 'Aws',
    'name' => 'Aws\\HasMonitoringEventsTrait',
    'shortName' => 'HasMonitoringEventsTrait',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 39,
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
      'monitoringEvents' => 
      array (
        'declaringClassName' => 'Aws\\HasMonitoringEventsTrait',
        'implementingClassName' => 'Aws\\HasMonitoringEventsTrait',
        'name' => 'monitoringEvents',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 7,
            'endLine' => 7,
            'startTokenPos' => 18,
            'startFilePos' => 88,
            'endTokenPos' => 19,
            'endFilePos' => 89,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 7,
        'endLine' => 7,
        'startColumn' => 5,
        'endColumn' => 35,
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
      'getMonitoringEvents' => 
      array (
        'name' => 'getMonitoringEvents',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get client-side monitoring events attached to this object. Each event is
 * represented as an associative array within the returned array.
 *
 * @return array
 */',
        'startLine' => 15,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\HasMonitoringEventsTrait',
        'implementingClassName' => 'Aws\\HasMonitoringEventsTrait',
        'currentClassName' => 'Aws\\HasMonitoringEventsTrait',
        'aliasName' => NULL,
      ),
      'prependMonitoringEvent' => 
      array (
        'name' => 'prependMonitoringEvent',
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
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 44,
            'endColumn' => 55,
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
 * Prepend a client-side monitoring event to this object\'s event list
 *
 * @param array $event
 */',
        'startLine' => 25,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\HasMonitoringEventsTrait',
        'implementingClassName' => 'Aws\\HasMonitoringEventsTrait',
        'currentClassName' => 'Aws\\HasMonitoringEventsTrait',
        'aliasName' => NULL,
      ),
      'appendMonitoringEvent' => 
      array (
        'name' => 'appendMonitoringEvent',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 43,
            'endColumn' => 54,
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
 * Append a client-side monitoring event to this object\'s event list
 *
 * @param array $event
 */',
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\HasMonitoringEventsTrait',
        'implementingClassName' => 'Aws\\HasMonitoringEventsTrait',
        'currentClassName' => 'Aws\\HasMonitoringEventsTrait',
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