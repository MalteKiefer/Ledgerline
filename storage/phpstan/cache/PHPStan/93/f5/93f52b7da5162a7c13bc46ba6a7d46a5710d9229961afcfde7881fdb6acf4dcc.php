<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../league/flysystem/src/UnableToDeleteFile.php-PHPStan\BetterReflection\Reflection\ReflectionClass-League\Flysystem\UnableToDeleteFile
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-06407459d1238dbeea487b6e1b61aeeaef3c8139981450eda6532f4db20a2b40-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'League\\Flysystem\\UnableToDeleteFile',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../league/flysystem/src/UnableToDeleteFile.php',
      ),
    ),
    'namespace' => 'League\\Flysystem',
    'name' => 'League\\Flysystem\\UnableToDeleteFile',
    'shortName' => 'UnableToDeleteFile',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 45,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'RuntimeException',
    'implementsClassNames' => 
    array (
      0 => 'League\\Flysystem\\FilesystemOperationFailed',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'location' => 
      array (
        'declaringClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'implementingClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'name' => 'location',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 49,
            'startFilePos' => 254,
            'endTokenPos' => 49,
            'endFilePos' => 255,
          ),
        ),
        'docComment' => '/**
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'reason' => 
      array (
        'declaringClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'implementingClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'name' => 'reason',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 20,
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
      'atLocation' => 
      array (
        'name' => 'atLocation',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'reason' => 
          array (
            'name' => 'reason',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 22,
                'endLine' => 22,
                'startTokenPos' => 78,
                'startFilePos' => 389,
                'endTokenPos' => 78,
                'endFilePos' => 390,
              ),
            ),
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 57,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'previous' => 
          array (
            'name' => 'previous',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 22,
                'endLine' => 22,
                'startTokenPos' => 88,
                'startFilePos' => 416,
                'endTokenPos' => 88,
                'endFilePos' => 419,
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
                      'name' => 'Throwable',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 78,
            'endColumn' => 104,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'League\\Flysystem\\UnableToDeleteFile',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 22,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'implementingClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'currentClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'aliasName' => NULL,
      ),
      'operation' => 
      array (
        'name' => 'operation',
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
        'docComment' => NULL,
        'startLine' => 31,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'implementingClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'currentClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'aliasName' => NULL,
      ),
      'reason' => 
      array (
        'name' => 'reason',
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
        'docComment' => NULL,
        'startLine' => 36,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'implementingClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'currentClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'aliasName' => NULL,
      ),
      'location' => 
      array (
        'name' => 'location',
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
        'docComment' => NULL,
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'League\\Flysystem',
        'declaringClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'implementingClassName' => 'League\\Flysystem\\UnableToDeleteFile',
        'currentClassName' => 'League\\Flysystem\\UnableToDeleteFile',
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