<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Queue/Middleware/WithoutOverlapping.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Queue\Middleware\WithoutOverlapping
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-5d45cc05a4a8aaef579293a958d9a93e513a1a1da3ecfec0f90b27a9987400c0-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Queue/Middleware/WithoutOverlapping.php',
      ),
    ),
    'namespace' => 'Illuminate\\Queue\\Middleware',
    'name' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
    'shortName' => 'WithoutOverlapping',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 167,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\InteractsWithTime',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'key' => 
      array (
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'name' => 'key',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The job\'s unique key used for preventing overlaps.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 16,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'releaseAfter' => 
      array (
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'name' => 'releaseAfter',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of seconds before a job should be available again if no lock was acquired.
 *
 * @var \\DateTimeInterface|int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'expiresAfter' => 
      array (
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'name' => 'expiresAfter',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of seconds before the lock should expire.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'prefix' => 
      array (
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'name' => 'prefix',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'laravel-queue-overlap:\'',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 66,
            'startFilePos' => 758,
            'endTokenPos' => 66,
            'endFilePos' => 781,
          ),
        ),
        'docComment' => '/**
 * The prefix of the lock key.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shareKey' => 
      array (
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'name' => 'shareKey',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 77,
            'startFilePos' => 892,
            'endTokenPos' => 77,
            'endFilePos' => 896,
          ),
        ),
        'docComment' => '/**
 * Share the key across different jobs.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 29,
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
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 55,
                'endLine' => 55,
                'startTokenPos' => 92,
                'startFilePos' => 1142,
                'endTokenPos' => 92,
                'endFilePos' => 1143,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 55,
            'endLine' => 55,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'releaseAfter' => 
          array (
            'name' => 'releaseAfter',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 55,
                'endLine' => 55,
                'startTokenPos' => 99,
                'startFilePos' => 1162,
                'endTokenPos' => 99,
                'endFilePos' => 1162,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 55,
            'endLine' => 55,
            'startColumn' => 44,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'expiresAfter' => 
          array (
            'name' => 'expiresAfter',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 55,
                'endLine' => 55,
                'startTokenPos' => 106,
                'startFilePos' => 1181,
                'endTokenPos' => 106,
                'endFilePos' => 1181,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 55,
            'endLine' => 55,
            'startColumn' => 63,
            'endColumn' => 79,
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
 * Create a new middleware instance.
 *
 * @param  string  $key
 * @param  \\DateTimeInterface|int|null  $releaseAfter
 * @param  \\DateTimeInterface|int  $expiresAfter
 */',
        'startLine' => 55,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 28,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'next' => 
          array (
            'name' => 'next',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 34,
            'endColumn' => 38,
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
 * Process the job.
 *
 * @param  mixed  $job
 * @param  callable  $next
 * @return mixed
 */',
        'startLine' => 69,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'releaseAfter' => 
      array (
        'name' => 'releaseAfter',
        'parameters' => 
        array (
          'releaseAfter' => 
          array (
            'name' => 'releaseAfter',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 34,
            'endColumn' => 46,
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
 * Set the delay (in seconds) to release the job back to the queue.
 *
 * @param  \\DateTimeInterface|int  $releaseAfter
 * @return $this
 */',
        'startLine' => 92,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'dontRelease' => 
      array (
        'name' => 'dontRelease',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Do not release the job back to the queue if no lock can be acquired.
 *
 * @return $this
 */',
        'startLine' => 104,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'expireAfter' => 
      array (
        'name' => 'expireAfter',
        'parameters' => 
        array (
          'expiresAfter' => 
          array (
            'name' => 'expiresAfter',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 117,
            'endLine' => 117,
            'startColumn' => 33,
            'endColumn' => 45,
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
 * Set the maximum number of seconds that can elapse before the lock is released.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int  $expiresAfter
 * @return $this
 */',
        'startLine' => 117,
        'endLine' => 122,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'withPrefix' => 
      array (
        'name' => 'withPrefix',
        'parameters' => 
        array (
          'prefix' => 
          array (
            'name' => 'prefix',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 130,
            'endLine' => 130,
            'startColumn' => 32,
            'endColumn' => 45,
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
 * Set the prefix of the lock key.
 *
 * @param  string  $prefix
 * @return $this
 */',
        'startLine' => 130,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'shared' => 
      array (
        'name' => 'shared',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that the lock key may be shared across jobs belonging to different classes.
 *
 * @return $this
 */',
        'startLine' => 142,
        'endLine' => 147,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'aliasName' => NULL,
      ),
      'getLockKey' => 
      array (
        'name' => 'getLockKey',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 155,
            'endLine' => 155,
            'startColumn' => 32,
            'endColumn' => 35,
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
 * Get the lock key for the given job.
 *
 * @param  mixed  $job
 * @return string
 */',
        'startLine' => 155,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue\\Middleware',
        'declaringClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'implementingClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
        'currentClassName' => 'Illuminate\\Queue\\Middleware\\WithoutOverlapping',
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