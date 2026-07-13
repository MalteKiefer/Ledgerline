<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/Concerns/AssignsOwner.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Models\Concerns\AssignsOwner
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-4a5485d80e4c543c55eb08b5c043cfc1941a1490d9e02060135384388316a115',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Models\\Concerns\\AssignsOwner',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Models/Concerns/AssignsOwner.php',
      ),
    ),
    'namespace' => 'App\\Models\\Concerns',
    'name' => 'App\\Models\\Concerns\\AssignsOwner',
    'shortName' => 'AssignsOwner',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Shared foundation for the per-user ownership traits: names the owning-user
 * column and stamps it on new rows from the authenticated user. OwnsUserData
 * builds its read scope on top of this; the trait is pulled in once per model.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 45,
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
      'ownerColumn' => 
      array (
        'name' => 'ownerColumn',
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
        'docComment' => '/** The column holding the owning user id (override per model, e.g. Photo). */',
        'startLine' => 18,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Concerns',
        'declaringClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'implementingClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'currentClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'aliasName' => NULL,
      ),
      'scopeOwnedBy' => 
      array (
        'name' => 'scopeOwnedBy',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 34,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'userId' => 
          array (
            'name' => 'userId',
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
                      'name' => 'int',
                      'isIdentifier' => true,
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
                  2 => 
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
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 50,
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
            'name' => 'Illuminate\\Database\\Eloquent\\Builder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Strictly the given user\'s OWN rows, with the auth-gated read scope removed
 * (never merely-shared rows). The single, model-aware way to owner-scope a
 * query — replaces hand-written withoutGlobalScopes()->where(\'<column>\', …)
 * chains so the owner column (e.g. Photo\'s uploaded_by) is never hardcoded.
 */',
        'startLine' => 29,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Models\\Concerns',
        'declaringClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'implementingClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'currentClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'aliasName' => NULL,
      ),
      'bootAssignsOwner' => 
      array (
        'name' => 'bootAssignsOwner',
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
        'startLine' => 36,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'App\\Models\\Concerns',
        'declaringClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'implementingClassName' => 'App\\Models\\Concerns\\AssignsOwner',
        'currentClassName' => 'App\\Models\\Concerns\\AssignsOwner',
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