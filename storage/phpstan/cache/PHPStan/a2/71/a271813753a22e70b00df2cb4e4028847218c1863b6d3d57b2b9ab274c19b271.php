<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/KeepBlankSecrets.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\KeepBlankSecrets
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-47eaa61e60f248f07b2eadc0a5301ac26286bdc9b6a39aaeb37639ecbe5dfd22',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\KeepBlankSecrets',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/KeepBlankSecrets.php',
      ),
    ),
    'namespace' => 'App\\Support',
    'name' => 'App\\Support\\KeepBlankSecrets',
    'shortName' => 'KeepBlankSecrets',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Secret fields whose form input is left blank should keep the stored value
 * rather than overwriting it with an empty string (the form never renders the
 * current secret back, so a blank submission means "leave it unchanged").
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 32,
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
      'preserve' => 
      array (
        'name' => 'preserve',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'keys' => 
          array (
            'name' => 'keys',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 50,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Drop any of $keys whose value in $data is empty/blank, so a blank
 * submission preserves the stored secret instead of wiping it.
 *
 * @param  array<string, mixed>  $data
 * @param  list<string>  $keys
 * @return array<string, mixed>
 */',
        'startLine' => 22,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\KeepBlankSecrets',
        'implementingClassName' => 'App\\Support\\KeepBlankSecrets',
        'currentClassName' => 'App\\Support\\KeepBlankSecrets',
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