<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/AlertErrors.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Console\Commands\AlertErrors
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-64b924a544564bc9c398d7fc362efa15d70c7de82b5c7a5887b0f9835f015e69',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Console\\Commands\\AlertErrors',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Console/Commands/AlertErrors.php',
      ),
    ),
    'namespace' => 'App\\Console\\Commands',
    'name' => 'App\\Console\\Commands\\AlertErrors',
    'shortName' => 'AlertErrors',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Notifies the configured channels when new server errors have been recorded
 * since the last alert. Keeps operators aware of faults without an external
 * error-tracking service. Runs hourly.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 79,
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
      'CURSOR' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\AlertErrors',
        'implementingClassName' => 'App\\Console\\Commands\\AlertErrors',
        'name' => 'CURSOR',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ops:error-alert:since\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 88,
            'startFilePos' => 698,
            'endTokenPos' => 88,
            'endFilePos' => 720,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
    ),
    'immediateProperties' => 
    array (
      'signature' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\AlertErrors',
        'implementingClassName' => 'App\\Console\\Commands\\AlertErrors',
        'name' => 'signature',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'ops:alert-errors\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 68,
            'startFilePos' => 565,
            'endTokenPos' => 68,
            'endFilePos' => 582,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'description' => 
      array (
        'declaringClassName' => 'App\\Console\\Commands\\AlertErrors',
        'implementingClassName' => 'App\\Console\\Commands\\AlertErrors',
        'name' => 'description',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Alert configured channels about new recorded errors\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 77,
            'startFilePos' => 615,
            'endTokenPos' => 77,
            'endFilePos' => 667,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 83,
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
          'notifier' => 
          array (
            'name' => 'notifier',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Notifications\\ChannelNotifier',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 28,
            'endLine' => 28,
            'startColumn' => 28,
            'endColumn' => 52,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 28,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\AlertErrors',
        'implementingClassName' => 'App\\Console\\Commands\\AlertErrors',
        'currentClassName' => 'App\\Console\\Commands\\AlertErrors',
        'aliasName' => NULL,
      ),
      'channels' => 
      array (
        'name' => 'channels',
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
        'docComment' => '/** Globally enabled notification channels for ops alerts. */',
        'startLine' => 69,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Console\\Commands',
        'declaringClassName' => 'App\\Console\\Commands\\AlertErrors',
        'implementingClassName' => 'App\\Console\\Commands\\AlertErrors',
        'currentClassName' => 'App\\Console\\Commands\\AlertErrors',
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