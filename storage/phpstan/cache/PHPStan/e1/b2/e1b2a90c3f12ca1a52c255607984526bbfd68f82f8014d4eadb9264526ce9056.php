<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/Concerns/OwnsUserData.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\Concerns\OwnsUserData
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-7af1aab422f8c877a845b61ee8ea772c9534c7958bc143a63b39db8ea4e4d8b3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\Concerns\\OwnsUserData',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/Concerns/OwnsUserData.php',
      ),
    ),
    'namespace' => 'App\\Models\\Concerns',
    'name' => 'App\\Models\\Concerns\\OwnsUserData',
    'shortName' => 'OwnsUserData',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Makes a model\'s rows private to their owning user. While a user is
 * authenticated (i.e. every web request, which all sit behind the `auth`
 * middleware) a global scope constrains every query to `user_id = Auth::id()`
 * and new rows get that user_id automatically.
 *
 * Outside web auth — queue jobs and scheduled commands — no automatic
 * constraint is applied; those paths already scope explicitly by the owning
 * record. This keeps strict per-user isolation on the web without breaking
 * background processing.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 34,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Models\\Concerns\\AssignsOwner',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'bootOwnsUserData' => 
      array (
        'name' => 'bootOwnsUserData',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => 25,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'App\\Models\\Concerns',
        'declaringClassName' => 'App\\Models\\Concerns\\OwnsUserData',
        'implementingClassName' => 'App\\Models\\Concerns\\OwnsUserData',
        'currentClassName' => 'App\\Models\\Concerns\\OwnsUserData',
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