<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Middleware/SecurityHeaders.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Middleware\SecurityHeaders
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-cc790846afb6f65f248d935861812acd27615069f42214e5ee55c57567ddc91f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Middleware\\SecurityHeaders',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Middleware/SecurityHeaders.php',
      ),
    ),
    'namespace' => 'App\\Http\\Middleware',
    'name' => 'App\\Http\\Middleware\\SecurityHeaders',
    'shortName' => 'SecurityHeaders',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * App-wide security headers, incl. a Content-Security-Policy that acts as a
 * defence-in-depth backstop: even if untrusted content ever reached the app
 * origin, it could not load remote scripts, be framed, or post elsewhere.
 *
 * \'unsafe-eval\' is required by Alpine.js (it evaluates x-* expressions via the
 * Function constructor). No inline <script> or inline event handlers are
 * emitted anywhere in the app, so script-src omits \'unsafe-inline\'. This is a
 * defence-in-depth policy for the application shell only: script-src still
 * forbids loading scripts from other origins, and the real untrusted-content
 * surface — email bodies — renders in separate sandboxed iframes with their
 * own strict, script-less CSP.
 *
 * The CSP is skipped in local development so the Vite dev server / HMR (which
 * injects an inline client and connects to its own origin) keeps working.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 98,
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
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 28,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'next' => 
          array (
            'name' => 'next',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 46,
            'endColumn' => 58,
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
            'name' => 'Symfony\\Component\\HttpFoundation\\Response',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 30,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Middleware',
        'declaringClassName' => 'App\\Http\\Middleware\\SecurityHeaders',
        'implementingClassName' => 'App\\Http\\Middleware\\SecurityHeaders',
        'currentClassName' => 'App\\Http\\Middleware\\SecurityHeaders',
        'aliasName' => NULL,
      ),
      'appPolicy' => 
      array (
        'name' => 'appPolicy',
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
 * Defence-in-depth CSP for the authenticated application shell.
 *
 * @return list<string>
 */',
        'startLine' => 66,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Http\\Middleware',
        'declaringClassName' => 'App\\Http\\Middleware\\SecurityHeaders',
        'implementingClassName' => 'App\\Http\\Middleware\\SecurityHeaders',
        'currentClassName' => 'App\\Http\\Middleware\\SecurityHeaders',
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