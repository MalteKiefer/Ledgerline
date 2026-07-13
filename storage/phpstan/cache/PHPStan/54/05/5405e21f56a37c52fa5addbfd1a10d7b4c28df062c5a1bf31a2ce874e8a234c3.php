<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Queue/InteractsWithQueue.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Queue\InteractsWithQueue
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-b6129ec818aeef62ef4cd5bcbe8f6431b4fcac811deaa0605bdea1c820e992f7-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Queue\\InteractsWithQueue',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Queue/InteractsWithQueue.php',
      ),
    ),
    'namespace' => 'Illuminate\\Queue',
    'name' => 'Illuminate\\Queue\\InteractsWithQueue',
    'shortName' => 'InteractsWithQueue',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 286,
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
      'job' => 
      array (
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'name' => 'job',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The underlying queue job instance.
 *
 * @var \\Illuminate\\Contracts\\Queue\\Job|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 16,
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
      'attempts' => 
      array (
        'name' => 'attempts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the number of times the job has been attempted.
 *
 * @return int
 */',
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete the job from the queue.
 *
 * @return void
 */',
        'startLine' => 40,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'fail' => 
      array (
        'name' => 'fail',
        'parameters' => 
        array (
          'exception' => 
          array (
            'name' => 'exception',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 55,
                'endLine' => 55,
                'startTokenPos' => 157,
                'startFilePos' => 1099,
                'endTokenPos' => 157,
                'endFilePos' => 1102,
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
            'startColumn' => 26,
            'endColumn' => 42,
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
 * Fail the job from the queue.
 *
 * @param  \\Throwable|string|null  $exception
 * @return void
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 55,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'release' => 
      array (
        'name' => 'release',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 76,
                'endLine' => 76,
                'startTokenPos' => 262,
                'startFilePos' => 1742,
                'endTokenPos' => 262,
                'endFilePos' => 1742,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 29,
            'endColumn' => 38,
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
 * Release the job back into the queue after (n) seconds.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @return void
 */',
        'startLine' => 76,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'withFakeQueueInteractions' => 
      array (
        'name' => 'withFakeQueueInteractions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that queue interactions like fail, delete, and release should be faked.
 *
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
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertDeleted' => 
      array (
        'name' => 'assertDeleted',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the job was deleted from the queue.
 *
 * @return $this
 */',
        'startLine' => 104,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertNotDeleted' => 
      array (
        'name' => 'assertNotDeleted',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the job was not deleted from the queue.
 *
 * @return $this
 */',
        'startLine' => 121,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertFailed' => 
      array (
        'name' => 'assertFailed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the job was manually failed.
 *
 * @return $this
 */',
        'startLine' => 138,
        'endLine' => 148,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertFailedWith' => 
      array (
        'name' => 'assertFailedWith',
        'parameters' => 
        array (
          'exception' => 
          array (
            'name' => 'exception',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 156,
            'endLine' => 156,
            'startColumn' => 38,
            'endColumn' => 47,
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
 * Assert that the job was manually failed with a specific exception.
 *
 * @param  \\Throwable|string  $exception
 * @return $this
 */',
        'startLine' => 156,
        'endLine' => 194,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertNotFailed' => 
      array (
        'name' => 'assertNotFailed',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the job was not manually failed.
 *
 * @return $this
 */',
        'startLine' => 201,
        'endLine' => 211,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertReleased' => 
      array (
        'name' => 'assertReleased',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 219,
                'endLine' => 219,
                'startTokenPos' => 805,
                'startFilePos' => 5535,
                'endTokenPos' => 805,
                'endFilePos' => 5538,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 36,
            'endColumn' => 48,
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
 * Assert that the job was released back onto the queue.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int|null  $delay
 * @return $this
 */',
        'startLine' => 219,
        'endLine' => 241,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'assertNotReleased' => 
      array (
        'name' => 'assertNotReleased',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Assert that the job was not released back onto the queue.
 *
 * @return $this
 */',
        'startLine' => 248,
        'endLine' => 258,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'ensureQueueInteractionsHaveBeenFaked' => 
      array (
        'name' => 'ensureQueueInteractionsHaveBeenFaked',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure that queue interactions have been faked.
 *
 * @return void
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 267,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'aliasName' => NULL,
      ),
      'setJob' => 
      array (
        'name' => 'setJob',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Queue\\Job',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 280,
            'endLine' => 280,
            'startColumn' => 28,
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
 * Set the base queue job instance.
 *
 * @param  \\Illuminate\\Contracts\\Queue\\Job  $job
 * @return $this
 */',
        'startLine' => 280,
        'endLine' => 285,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Queue',
        'declaringClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'implementingClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
        'currentClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
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