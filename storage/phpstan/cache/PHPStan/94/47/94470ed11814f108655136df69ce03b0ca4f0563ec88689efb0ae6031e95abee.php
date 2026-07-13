<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Http/Client/Factory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Http\Client\Factory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-521296d98b59f63595a946aacd244f53ba3a6c125d86847c87ff0d4df063ef29-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Http\\Client\\Factory',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Http/Client/Factory.php',
      ),
    ),
    'namespace' => 'Illuminate\\Http\\Client',
    'name' => 'Illuminate\\Http\\Client\\Factory',
    'shortName' => 'Factory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin \\Illuminate\\Http\\Client\\PendingRequest
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 638,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'dispatcher' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'dispatcher',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher implementation.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'globalMiddleware' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'globalMiddleware',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 42,
            'startTokenPos' => 128,
            'startFilePos' => 952,
            'endTokenPos' => 129,
            'endFilePos' => 953,
          ),
        ),
        'docComment' => '/**
 * The middleware to apply to every request.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'globalOptions' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'globalOptions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 140,
            'startFilePos' => 1084,
            'endTokenPos' => 141,
            'endFilePos' => 1085,
          ),
        ),
        'docComment' => '/**
 * The options to apply to every request.
 *
 * @var \\Closure|array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stubCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'stubCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The stub callables that will handle requests.
 *
 * @var \\Illuminate\\Support\\Collection
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'recording' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'recording',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 63,
            'endLine' => 63,
            'startTokenPos' => 159,
            'startFilePos' => 1375,
            'endTokenPos' => 159,
            'endFilePos' => 1379,
          ),
        ),
        'docComment' => '/**
 * Indicates if the factory is recording requests and responses.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'recorded' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'recorded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 70,
            'endLine' => 70,
            'startTokenPos' => 170,
            'startFilePos' => 1570,
            'endTokenPos' => 171,
            'endFilePos' => 1571,
          ),
        ),
        'docComment' => '/**
 * The recorded response array.
 *
 * @var list<array{0: \\Illuminate\\Http\\Client\\Request, 1: \\Illuminate\\Http\\Client\\Response|null}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 70,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'responseSequences' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'responseSequences',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 77,
            'endLine' => 77,
            'startTokenPos' => 182,
            'startFilePos' => 1731,
            'endTokenPos' => 183,
            'endFilePos' => 1732,
          ),
        ),
        'docComment' => '/**
 * All created response sequences.
 *
 * @var list<\\Illuminate\\Http\\Client\\ResponseSequence>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 77,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preventStrayRequests' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'preventStrayRequests',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 84,
            'endLine' => 84,
            'startTokenPos' => 194,
            'startFilePos' => 1895,
            'endTokenPos' => 194,
            'endFilePos' => 1899,
          ),
        ),
        'docComment' => '/**
 * Indicates that an exception should be thrown if any request is not faked.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 84,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedStrayRequestUrls' => 
      array (
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'name' => 'allowedStrayRequestUrls',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 91,
            'endLine' => 91,
            'startTokenPos' => 205,
            'startFilePos' => 2080,
            'endTokenPos' => 206,
            'endFilePos' => 2081,
          ),
        ),
        'docComment' => '/**
 * A list of URL patterns that are allowed to bypass the stray request guard.
 *
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 91,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 44,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'dispatcher' => 
          array (
            'name' => 'dispatcher',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 98,
                'endLine' => 98,
                'startTokenPos' => 224,
                'startFilePos' => 2277,
                'endTokenPos' => 224,
                'endFilePos' => 2280,
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
                      'name' => 'Illuminate\\Contracts\\Events\\Dispatcher',
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
            'startLine' => 98,
            'endLine' => 98,
            'startColumn' => 33,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new factory instance.
 *
 * @param  \\Illuminate\\Contracts\\Events\\Dispatcher|null  $dispatcher
 */',
        'startLine' => 98,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalMiddleware' => 
      array (
        'name' => 'globalMiddleware',
        'parameters' => 
        array (
          'middleware' => 
          array (
            'name' => 'middleware',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 38,
            'endColumn' => 48,
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
 * Add middleware to apply to every request.
 *
 * @param  callable  $middleware
 * @return $this
 */',
        'startLine' => 111,
        'endLine' => 116,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalRequestMiddleware' => 
      array (
        'name' => 'globalRequestMiddleware',
        'parameters' => 
        array (
          'middleware' => 
          array (
            'name' => 'middleware',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 124,
            'endLine' => 124,
            'startColumn' => 45,
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
 * Add request middleware to apply to every request.
 *
 * @param  callable  $middleware
 * @return $this
 */',
        'startLine' => 124,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalResponseMiddleware' => 
      array (
        'name' => 'globalResponseMiddleware',
        'parameters' => 
        array (
          'middleware' => 
          array (
            'name' => 'middleware',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 46,
            'endColumn' => 56,
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
 * Add response middleware to apply to every request.
 *
 * @param  callable  $middleware
 * @return $this
 */',
        'startLine' => 137,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'globalOptions' => 
      array (
        'name' => 'globalOptions',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 35,
            'endColumn' => 42,
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
 * Set the options to apply to every request.
 *
 * @param  \\Closure|array  $options
 * @return $this
 */',
        'startLine' => 150,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'response' => 
      array (
        'name' => 'response',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 397,
                'startFilePos' => 3797,
                'endTokenPos' => 397,
                'endFilePos' => 3800,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 404,
                'startFilePos' => 3813,
                'endTokenPos' => 404,
                'endFilePos' => 3815,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 411,
                'startFilePos' => 3829,
                'endTokenPos' => 412,
                'endFilePos' => 3830,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 66,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new response instance for use during stubbing.
 *
 * @param  array|string|null  $body
 * @param  int  $status
 * @param  array  $headers
 * @return \\GuzzleHttp\\Promise\\PromiseInterface
 */',
        'startLine' => 165,
        'endLine' => 170,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'psr7Response' => 
      array (
        'name' => 'psr7Response',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 182,
                'endLine' => 182,
                'startTokenPos' => 456,
                'startFilePos' => 4294,
                'endTokenPos' => 456,
                'endFilePos' => 4297,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 182,
            'endLine' => 182,
            'startColumn' => 41,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 182,
                'endLine' => 182,
                'startTokenPos' => 463,
                'startFilePos' => 4310,
                'endTokenPos' => 463,
                'endFilePos' => 4312,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 182,
            'endLine' => 182,
            'startColumn' => 55,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 182,
                'endLine' => 182,
                'startTokenPos' => 470,
                'startFilePos' => 4326,
                'endTokenPos' => 471,
                'endFilePos' => 4327,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 182,
            'endLine' => 182,
            'startColumn' => 70,
            'endColumn' => 82,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new PSR-7 response instance for use during stubbing.
 *
 * @param  array|string|null  $body
 * @param  int  $status
 * @param  array<string, mixed>  $headers
 * @return \\GuzzleHttp\\Psr7\\Response
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 182,
        'endLine' => 199,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'normalizeResponseHeaders' => 
      array (
        'name' => 'normalizeResponseHeaders',
        'parameters' => 
        array (
          'headers' => 
          array (
            'name' => 'headers',
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 56,
            'endColumn' => 69,
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
 * Normalize the given fake response headers.
 *
 * @param  array  $headers
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 209,
        'endLine' => 242,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'normalizeScalarString' => 
      array (
        'name' => 'normalizeScalarString',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 250,
            'endLine' => 250,
            'startColumn' => 53,
            'endColumn' => 58,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Normalize a scalar to a string without triggering PHP 8.5 non-finite float warnings.
 *
 * @param  scalar  $value
 * @return string
 */',
        'startLine' => 250,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'failedRequest' => 
      array (
        'name' => 'failedRequest',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 271,
                'endLine' => 271,
                'startTokenPos' => 970,
                'startFilePos' => 7381,
                'endTokenPos' => 970,
                'endFilePos' => 7384,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 271,
            'endLine' => 271,
            'startColumn' => 42,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'status' => 
          array (
            'name' => 'status',
            'default' => 
            array (
              'code' => '200',
              'attributes' => 
              array (
                'startLine' => 271,
                'endLine' => 271,
                'startTokenPos' => 977,
                'startFilePos' => 7397,
                'endTokenPos' => 977,
                'endFilePos' => 7399,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 271,
            'endLine' => 271,
            'startColumn' => 56,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 271,
                'endLine' => 271,
                'startTokenPos' => 984,
                'startFilePos' => 7413,
                'endTokenPos' => 985,
                'endFilePos' => 7414,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 271,
            'endLine' => 271,
            'startColumn' => 71,
            'endColumn' => 83,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new RequestException instance for use during stubbing.
 *
 * @param  array|string|null  $body
 * @param  int  $status
 * @param  array<string, mixed>  $headers
 * @return \\Illuminate\\Http\\Client\\RequestException
 */',
        'startLine' => 271,
        'endLine' => 274,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'failedConnection' => 
      array (
        'name' => 'failedConnection',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 282,
                'endLine' => 282,
                'startTokenPos' => 1032,
                'startFilePos' => 7805,
                'endTokenPos' => 1032,
                'endFilePos' => 7808,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 282,
            'endLine' => 282,
            'startColumn' => 45,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new connection exception for use during stubbing.
 *
 * @param  string|null  $message
 * @return \\Closure(\\Illuminate\\Http\\Client\\Request): \\GuzzleHttp\\Promise\\PromiseInterface
 */',
        'startLine' => 282,
        'endLine' => 290,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'sequence' => 
      array (
        'name' => 'sequence',
        'parameters' => 
        array (
          'responses' => 
          array (
            'name' => 'responses',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 298,
                'endLine' => 298,
                'startTokenPos' => 1131,
                'startFilePos' => 8485,
                'endTokenPos' => 1132,
                'endFilePos' => 8486,
              ),
            ),
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
            'startLine' => 298,
            'endLine' => 298,
            'startColumn' => 30,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get an invokable object that returns a sequence of responses in order for use during stubbing.
 *
 * @param  array  $responses
 * @return \\Illuminate\\Http\\Client\\ResponseSequence
 */',
        'startLine' => 298,
        'endLine' => 301,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'fake' => 
      array (
        'name' => 'fake',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 309,
                'endLine' => 309,
                'startTokenPos' => 1169,
                'startFilePos' => 8821,
                'endTokenPos' => 1169,
                'endFilePos' => 8824,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 309,
            'endLine' => 309,
            'startColumn' => 26,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a stub callable that will intercept requests and be able to return stub responses.
 *
 * @param  callable|array<string, mixed>|null  $callback
 * @return $this
 */',
        'startLine' => 309,
        'endLine' => 349,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'fakeSequence' => 
      array (
        'name' => 'fakeSequence',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => 
            array (
              'code' => '\'*\'',
              'attributes' => 
              array (
                'startLine' => 357,
                'endLine' => 357,
                'startTokenPos' => 1434,
                'startFilePos' => 10147,
                'endTokenPos' => 1434,
                'endFilePos' => 10149,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 357,
            'endLine' => 357,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a response sequence for the given URL pattern.
 *
 * @param  string  $url
 * @return \\Illuminate\\Http\\Client\\ResponseSequence
 */',
        'startLine' => 357,
        'endLine' => 362,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'stubUrl' => 
      array (
        'name' => 'stubUrl',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 373,
            'endLine' => 373,
            'startColumn' => 29,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 373,
            'endLine' => 373,
            'startColumn' => 35,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Stub the given URL using the given callback.
 *
 * @param  string  $url
 * @param  \\Illuminate\\Http\\Client\\Response|\\GuzzleHttp\\Promise\\PromiseInterface|callable|int|string|array|\\Illuminate\\Http\\Client\\ResponseSequence  $callback
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 373,
        'endLine' => 398,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'preventStrayRequests' => 
      array (
        'name' => 'preventStrayRequests',
        'parameters' => 
        array (
          'prevent' => 
          array (
            'name' => 'prevent',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 406,
                'endLine' => 406,
                'startTokenPos' => 1698,
                'startFilePos' => 11685,
                'endTokenPos' => 1698,
                'endFilePos' => 11688,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 406,
            'endLine' => 406,
            'startColumn' => 42,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that an exception should be thrown if any request is not faked.
 *
 * @param  bool  $prevent
 * @return $this
 */',
        'startLine' => 406,
        'endLine' => 411,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'preventingStrayRequests' => 
      array (
        'name' => 'preventingStrayRequests',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if stray requests are being prevented.
 *
 * @return bool
 */',
        'startLine' => 418,
        'endLine' => 421,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'allowStrayRequests' => 
      array (
        'name' => 'allowStrayRequests',
        'parameters' => 
        array (
          'only' => 
          array (
            'name' => 'only',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 429,
                'endLine' => 429,
                'startTokenPos' => 1755,
                'startFilePos' => 12208,
                'endTokenPos' => 1755,
                'endFilePos' => 12211,
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
                      'name' => 'array',
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
            'startLine' => 429,
            'endLine' => 429,
            'startColumn' => 40,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Allow stray, unfaked requests entirely, or optionally allow only specific URLs.
 *
 * @param  array<int, string>|null  $only
 * @return $this
 */',
        'startLine' => 429,
        'endLine' => 440,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'record' => 
      array (
        'name' => 'record',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin recording request / response pairs.
 *
 * @return $this
 */',
        'startLine' => 447,
        'endLine' => 452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'recordRequestResponsePair' => 
      array (
        'name' => 'recordRequestResponsePair',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 47,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 57,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Record a request response pair.
 *
 * @param  \\Illuminate\\Http\\Client\\Request  $request
 * @param  \\Illuminate\\Http\\Client\\Response|null  $response
 * @return void
 */',
        'startLine' => 461,
        'endLine' => 466,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSent' => 
      array (
        'name' => 'assertSent',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 474,
            'endLine' => 474,
            'startColumn' => 32,
            'endColumn' => 40,
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
 * Assert that a request / response pair was recorded matching a given truth test.
 *
 * @param  callable|(\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)  $callback
 * @return void
 */',
        'startLine' => 474,
        'endLine' => 480,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSentInOrder' => 
      array (
        'name' => 'assertSentInOrder',
        'parameters' => 
        array (
          'callbacks' => 
          array (
            'name' => 'callbacks',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 488,
            'endLine' => 488,
            'startColumn' => 39,
            'endColumn' => 48,
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
 * Assert that the given request was sent in the given order.
 *
 * @param  list<string|(\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)|callable>  $callbacks
 * @return void
 */',
        'startLine' => 488,
        'endLine' => 502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertNotSent' => 
      array (
        'name' => 'assertNotSent',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 510,
            'endLine' => 510,
            'startColumn' => 35,
            'endColumn' => 43,
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
 * Assert that a request / response pair was not recorded matching a given truth test.
 *
 * @param  callable|(\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)  $callback
 * @return void
 */',
        'startLine' => 510,
        'endLine' => 516,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertNothingSent' => 
      array (
        'name' => 'assertNothingSent',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that no request / response pair was recorded.
 *
 * @return void
 */',
        'startLine' => 523,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSentCount' => 
      array (
        'name' => 'assertSentCount',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 537,
            'endLine' => 537,
            'startColumn' => 37,
            'endColumn' => 42,
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
 * Assert how many requests have been recorded.
 *
 * @param  int  $count
 * @return void
 */',
        'startLine' => 537,
        'endLine' => 540,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'assertSequencesAreEmpty' => 
      array (
        'name' => 'assertSequencesAreEmpty',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that every created response sequence is empty.
 *
 * @return void
 */',
        'startLine' => 547,
        'endLine' => 555,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'recorded' => 
      array (
        'name' => 'recorded',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 563,
                'endLine' => 563,
                'startTokenPos' => 2222,
                'startFilePos' => 16027,
                'endTokenPos' => 2222,
                'endFilePos' => 16030,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 563,
            'endLine' => 563,
            'startColumn' => 30,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a collection of the request / response pairs matching the given truth test.
 *
 * @param  (\\Closure(\\Illuminate\\Http\\Client\\Request, \\Illuminate\\Http\\Client\\Response|null): bool)|callable  $callback
 * @return \\Illuminate\\Support\\Collection<int, array{0: \\Illuminate\\Http\\Client\\Request, 1: \\Illuminate\\Http\\Client\\Response|null}>
 */',
        'startLine' => 563,
        'endLine' => 576,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'createPendingRequest' => 
      array (
        'name' => 'createPendingRequest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new pending request instance for this factory.
 *
 * @return \\Illuminate\\Http\\Client\\PendingRequest
 */',
        'startLine' => 583,
        'endLine' => 591,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'newPendingRequest' => 
      array (
        'name' => 'newPendingRequest',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Instantiate a new pending request instance for this factory.
 *
 * @return \\Illuminate\\Http\\Client\\PendingRequest
 */',
        'startLine' => 598,
        'endLine' => 601,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'getDispatcher' => 
      array (
        'name' => 'getDispatcher',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current event dispatcher implementation.
 *
 * @return \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'startLine' => 608,
        'endLine' => 611,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      'getGlobalMiddleware' => 
      array (
        'name' => 'getGlobalMiddleware',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the array of global middleware.
 *
 * @return array
 */',
        'startLine' => 618,
        'endLine' => 621,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 630,
            'endLine' => 630,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 630,
            'endLine' => 630,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute a method against a new pending request instance.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 630,
        'endLine' => 637,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Http\\Client',
        'declaringClassName' => 'Illuminate\\Http\\Client\\Factory',
        'implementingClassName' => 'Illuminate\\Http\\Client\\Factory',
        'currentClassName' => 'Illuminate\\Http\\Client\\Factory',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));