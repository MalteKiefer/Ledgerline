<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Paperless/PaperlessSync.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Paperless\PaperlessSync
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-1630f317e1b780300ae263c13a2bd8d041adc3e73b8b89a72bc21ea2e3bb9c0e',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Paperless\\PaperlessSync',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Paperless/PaperlessSync.php',
      ),
    ),
    'namespace' => 'App\\Services\\Paperless',
    'name' => 'App\\Services\\Paperless\\PaperlessSync',
    'shortName' => 'PaperlessSync',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Refreshes a user\'s local cache of Paperless tags, document types and
 * correspondents from their instance. Upserts current terms and drops any that
 * no longer exist (scoped to the user), then stamps the sync time.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 51,
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
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'userId' => 
          array (
            'name' => 'userId',
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 25,
            'endColumn' => 35,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array<string,int> counts per kind, e.g. [\'tag\' => 12, ...]
 */',
        'startLine' => 21,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Paperless',
        'declaringClassName' => 'App\\Services\\Paperless\\PaperlessSync',
        'implementingClassName' => 'App\\Services\\Paperless\\PaperlessSync',
        'currentClassName' => 'App\\Services\\Paperless\\PaperlessSync',
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