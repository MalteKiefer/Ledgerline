<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Filesystem/LocalFilesystemAdapter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Filesystem\LocalFilesystemAdapter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-2054f95d66f334a4f10c2cabe266f4b31ab4fe329836ebfe090d3286baa33b3e-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Filesystem/LocalFilesystemAdapter.php',
      ),
    ),
    'namespace' => 'Illuminate\\Filesystem',
    'name' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
    'shortName' => 'LocalFilesystemAdapter',
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
    'endLine' => 152,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Filesystem\\FilesystemAdapter',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Conditionable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'disk' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'name' => 'disk',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name of the filesystem disk.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shouldServeSignedUrls' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'name' => 'shouldServeSignedUrls',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 52,
            'startFilePos' => 452,
            'endTokenPos' => 52,
            'endFilePos' => 456,
          ),
        ),
        'docComment' => '/**
 * Indicates if signed URLs should serve corresponding files.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'urlGeneratorResolver' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'name' => 'urlGeneratorResolver',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Closure that should be used to resolve the URL generator.
 *
 * @var \\Closure
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 36,
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
      'providesTemporaryUrls' => 
      array (
        'name' => 'providesTemporaryUrls',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if temporary URLs can be generated.
 *
 * @return bool
 */',
        'startLine' => 39,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'currentClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'aliasName' => NULL,
      ),
      'providesTemporaryUploadUrls' => 
      array (
        'name' => 'providesTemporaryUploadUrls',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if temporary upload URLs can be generated.
 *
 * @return bool
 */',
        'startLine' => 51,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'currentClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'aliasName' => NULL,
      ),
      'temporaryUrl' => 
      array (
        'name' => 'temporaryUrl',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 34,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expiration' => 
          array (
            'name' => 'expiration',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 41,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 68,
                'endLine' => 68,
                'startTokenPos' => 164,
                'startFilePos' => 1557,
                'endTokenPos' => 165,
                'endFilePos' => 1558,
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
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 54,
            'endColumn' => 72,
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
 * Get a temporary URL for the file at the given path.
 *
 * @param  string  $path
 * @param  \\DateTimeInterface  $expiration
 * @param  array  $options
 * @return string
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 68,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'currentClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'aliasName' => NULL,
      ),
      'temporaryUploadUrl' => 
      array (
        'name' => 'temporaryUploadUrl',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 40,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'expiration' => 
          array (
            'name' => 'expiration',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 47,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 100,
                'endLine' => 100,
                'startTokenPos' => 324,
                'startFilePos' => 2537,
                'endTokenPos' => 325,
                'endFilePos' => 2538,
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
            'startLine' => 100,
            'endLine' => 100,
            'startColumn' => 60,
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
 * Get a temporary upload URL for the file at the given path.
 *
 * @param  string  $path
 * @param  \\DateTimeInterface  $expiration
 * @param  array  $options
 * @return array
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 100,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'currentClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'aliasName' => NULL,
      ),
      'diskName' => 
      array (
        'name' => 'diskName',
        'parameters' => 
        array (
          'disk' => 
          array (
            'name' => 'disk',
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
            'startLine' => 131,
            'endLine' => 131,
            'startColumn' => 30,
            'endColumn' => 41,
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
 * Specify the name of the disk the adapter is managing.
 *
 * @param  string  $disk
 * @return $this
 */',
        'startLine' => 131,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'currentClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'aliasName' => NULL,
      ),
      'shouldServeSignedUrls' => 
      array (
        'name' => 'shouldServeSignedUrls',
        'parameters' => 
        array (
          'serve' => 
          array (
            'name' => 'serve',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 145,
                'endLine' => 145,
                'startTokenPos' => 535,
                'startFilePos' => 3813,
                'endTokenPos' => 535,
                'endFilePos' => 3816,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 145,
            'endLine' => 145,
            'startColumn' => 43,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'urlGeneratorResolver' => 
          array (
            'name' => 'urlGeneratorResolver',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 145,
                'endLine' => 145,
                'startTokenPos' => 545,
                'startFilePos' => 3852,
                'endTokenPos' => 545,
                'endFilePos' => 3855,
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
                      'name' => 'Closure',
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
            'startLine' => 145,
            'endLine' => 145,
            'startColumn' => 63,
            'endColumn' => 99,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Indicate that signed URLs should serve the corresponding files.
 *
 * @param  bool  $serve
 * @param  \\Closure|null  $urlGeneratorResolver
 * @return $this
 */',
        'startLine' => 145,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'implementingClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
        'currentClassName' => 'Illuminate\\Filesystem\\LocalFilesystemAdapter',
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