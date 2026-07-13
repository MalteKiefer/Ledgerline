<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../league/flysystem/src/FilesystemReader.php-PHPStan\BetterReflection\Reflection\ReflectionClass-League\Flysystem\FilesystemReader
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-5d1728fbb7db3dc0bd4048266a351e70c1ac4a49b95c6396319f7295386c0f4e-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'League\\Flysystem\\FilesystemReader',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../league/flysystem/src/FilesystemReader.php',
      ),
    ),
    'namespace' => 'League\\Flysystem',
    'name' => 'League\\Flysystem\\FilesystemReader',
    'shortName' => 'FilesystemReader',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * This interface contains everything to read from and inspect
 * a filesystem. All methods containing are non-destructive.
 *
 * @method string publicUrl(string $path, array $config = []) Will be added in 4.0
 * @method string temporaryUrl(string $path, DateTimeInterface $expiresAt, array $config = []) Will be added in 4.0
 * @method string checksum(string $path, array $config = []) Will be added in 4.0
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 85,
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
      'LIST_SHALLOW' => 
      array (
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'name' => 'LIST_SHALLOW',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 36,
            'startFilePos' => 563,
            'endTokenPos' => 36,
            'endFilePos' => 567,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'LIST_DEEP' => 
      array (
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'name' => 'LIST_DEEP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 47,
            'startFilePos' => 599,
            'endTokenPos' => 47,
            'endFilePos' => 602,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'fileExists' => 
      array (
        'name' => 'fileExists',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 32,
            'endColumn' => 47,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws FilesystemException
 * @throws UnableToCheckExistence
 */',
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'directoryExists' => 
      array (
        'name' => 'directoryExists',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
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
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 37,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws FilesystemException
 * @throws UnableToCheckExistence
 */',
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 60,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'has' => 
      array (
        'name' => 'has',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 25,
            'endColumn' => 40,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws FilesystemException
 * @throws UnableToCheckExistence
 */',
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'read' => 
      array (
        'name' => 'read',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
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
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 26,
            'endColumn' => 41,
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
 * @throws UnableToReadFile
 * @throws FilesystemException
 */',
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'readStream' => 
      array (
        'name' => 'readStream',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
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
            'startLine' => 52,
            'endLine' => 52,
            'startColumn' => 32,
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
 * @return resource
 *
 * @throws UnableToReadFile
 * @throws FilesystemException
 */',
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'listContents' => 
      array (
        'name' => 'listContents',
        'parameters' => 
        array (
          'location' => 
          array (
            'name' => 'location',
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
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 34,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'deep' => 
          array (
            'name' => 'deep',
            'default' => 
            array (
              'code' => 'self::LIST_SHALLOW',
              'attributes' => 
              array (
                'startLine' => 60,
                'endLine' => 60,
                'startTokenPos' => 151,
                'startFilePos' => 1552,
                'endTokenPos' => 153,
                'endFilePos' => 1569,
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
            'startLine' => 60,
            'endLine' => 60,
            'startColumn' => 52,
            'endColumn' => 82,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'League\\Flysystem\\DirectoryListing',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return DirectoryListing<StorageAttributes>
 *
 * @throws FilesystemException
 * @throws UnableToListContents
 */',
        'startLine' => 60,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 102,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'lastModified' => 
      array (
        'name' => 'lastModified',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 34,
            'endColumn' => 45,
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
        'docComment' => '/**
 * @throws UnableToRetrieveMetadata
 * @throws FilesystemException
 */',
        'startLine' => 66,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'fileSize' => 
      array (
        'name' => 'fileSize',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 30,
            'endColumn' => 41,
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
        'docComment' => '/**
 * @throws UnableToRetrieveMetadata
 * @throws FilesystemException
 */',
        'startLine' => 72,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'mimeType' => 
      array (
        'name' => 'mimeType',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 30,
            'endColumn' => 41,
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
 * @throws UnableToRetrieveMetadata
 * @throws FilesystemException
 */',
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
        'aliasName' => NULL,
      ),
      'visibility' => 
      array (
        'name' => 'visibility',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 32,
            'endColumn' => 43,
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
 * @throws UnableToRetrieveMetadata
 * @throws FilesystemException
 */',
        'startLine' => 84,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 53,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\FilesystemReader',
        'implementingClassName' => 'League\\Flysystem\\FilesystemReader',
        'currentClassName' => 'League\\Flysystem\\FilesystemReader',
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