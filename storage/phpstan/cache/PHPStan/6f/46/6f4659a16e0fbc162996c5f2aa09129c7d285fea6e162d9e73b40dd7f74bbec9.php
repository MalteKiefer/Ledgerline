<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../intervention/image/src/Interfaces/ImageInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Intervention\Image\Interfaces\ImageInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-f7f92b809066526b934a370fcf35882d13d25b35742c57fa3daa6b112ac9f4ad-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../intervention/image/src/Interfaces/ImageInterface.php',
      ),
    ),
    'namespace' => 'Intervention\\Image\\Interfaces',
    'name' => 'Intervention\\Image\\Interfaces\\ImageInterface',
    'shortName' => 'ImageInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @extends IteratorAggregate<FrameInterface>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 573,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'IteratorAggregate',
      1 => 'Countable',
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
      'driver' => 
      array (
        'name' => 'driver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\DriverInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the image driver.
 */',
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'core' => 
      array (
        'name' => 'core',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\CoreInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the image core.
 */',
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 42,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'origin' => 
      array (
        'name' => 'origin',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\OriginInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the image origin.
 */',
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setOrigin' => 
      array (
        'name' => 'setOrigin',
        'parameters' => 
        array (
          'origin' => 
          array (
            'name' => 'origin',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Interfaces\\OriginInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 31,
            'endColumn' => 53,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the image origin.
 */',
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 61,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'width' => 
      array (
        'name' => 'width',
        'parameters' => 
        array (
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
 * Return the image width in pixels.
 *
 * @link https://image.intervention.io/v4/basics/meta-information#read-the-pixel-width
 */',
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 33,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'height' => 
      array (
        'name' => 'height',
        'parameters' => 
        array (
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
 * Return the image height in pixels.
 *
 * @link https://image.intervention.io/v4/basics/meta-information#read-the-pixel-height
 */',
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'size' => 
      array (
        'name' => 'size',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\SizeInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the image size as an object.
 *
 * @link https://image.intervention.io/v4/basics/meta-information#read-the-image-size-as-an-object
 */',
        'startLine' => 66,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 42,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'save' => 
      array (
        'name' => 'save',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 74,
                'endLine' => 74,
                'startTokenPos' => 216,
                'startFilePos' => 1936,
                'endTokenPos' => 216,
                'endFilePos' => 1939,
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
                      'name' => 'string',
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
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 26,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 48,
            'endColumn' => 64,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Save the image to the given path. If no path is given, the image will
 * be saved at its original location.
 *
 * @link https://image.intervention.io/v4/basics/image-output#encode--save-combined
 */',
        'startLine' => 74,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 72,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'modify' => 
      array (
        'name' => 'modify',
        'parameters' => 
        array (
          'modifier' => 
          array (
            'name' => 'modifier',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Interfaces\\ModifierInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 81,
            'endLine' => 81,
            'startColumn' => 28,
            'endColumn' => 54,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Apply the given modifier to the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/custom-modifiers
 */',
        'startLine' => 81,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 62,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'analyze' => 
      array (
        'name' => 'analyze',
        'parameters' => 
        array (
          'analyzer' => 
          array (
            'name' => 'analyzer',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Interfaces\\AnalyzerInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 86,
            'endLine' => 86,
            'startColumn' => 29,
            'endColumn' => 55,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Analyze the image with the given analyzer.
 */',
        'startLine' => 86,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 64,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'isAnimated' => 
      array (
        'name' => 'isAnimated',
        'parameters' => 
        array (
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
 * Determine if the image is animated.
 *
 * @link https://image.intervention.io/v4/modifying-images/animations#check-the-current-image-instance-for-animation
 */',
        'startLine' => 93,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 39,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'removeAnimation' => 
      array (
        'name' => 'removeAnimation',
        'parameters' => 
        array (
          'position' => 
          array (
            'name' => 'position',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 104,
                'endLine' => 104,
                'startTokenPos' => 293,
                'startFilePos' => 2972,
                'endTokenPos' => 293,
                'endFilePos' => 2972,
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
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 37,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove all frames but keep the one at the specified position.
 *
 * Integer values select the exact frame position, while string values
 * represent a percentage between \'0%\' and \'100%\' to determine the
 * approximate frame position.
 *
 * @link https://image.intervention.io/v4/modifying-images/animations#remove-animation
 */',
        'startLine' => 104,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 68,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'sliceAnimation' => 
      array (
        'name' => 'sliceAnimation',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 111,
                'endLine' => 111,
                'startTokenPos' => 314,
                'startFilePos' => 3236,
                'endTokenPos' => 314,
                'endFilePos' => 3236,
              ),
            ),
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
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 36,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'length' => 
          array (
            'name' => 'length',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 111,
                'endLine' => 111,
                'startTokenPos' => 324,
                'startFilePos' => 3254,
                'endTokenPos' => 324,
                'endFilePos' => 3257,
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
                      'name' => 'int',
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
            'startLine' => 111,
            'endLine' => 111,
            'startColumn' => 53,
            'endColumn' => 71,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Keep only the frames defined by offset and length, discarding the rest.
 *
 * @link https://image.intervention.io/v4/modifying-images/animations#change-the-animation-frames
 */',
        'startLine' => 111,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 79,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'loops' => 
      array (
        'name' => 'loops',
        'parameters' => 
        array (
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
 * Return the animation loop count.
 *
 * @link https://image.intervention.io/v4/modifying-images/animations#read-the-animation-iteration-count
 */',
        'startLine' => 118,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 33,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setLoops' => 
      array (
        'name' => 'setLoops',
        'parameters' => 
        array (
          'loops' => 
          array (
            'name' => 'loops',
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
            'startLine' => 125,
            'endLine' => 125,
            'startColumn' => 30,
            'endColumn' => 39,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the animation loop count.
 *
 * @link https://image.intervention.io/v4/modifying-images/animations#change-the-animation-iteration-count
 */',
        'startLine' => 125,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'exif' => 
      array (
        'name' => 'exif',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 132,
                'endLine' => 132,
                'startTokenPos' => 377,
                'startFilePos' => 3889,
                'endTokenPos' => 377,
                'endFilePos' => 3892,
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
                      'name' => 'string',
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
            'startLine' => 132,
            'endLine' => 132,
            'startColumn' => 26,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the EXIF data of the image.
 *
 * @link https://image.intervention.io/v4/basics/meta-information#exif-information
 */',
        'startLine' => 132,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setExif' => 
      array (
        'name' => 'setExif',
        'parameters' => 
        array (
          'exif' => 
          array (
            'name' => 'exif',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Interfaces\\CollectionInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 29,
            'endColumn' => 53,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the EXIF data of the image.
 */',
        'startLine' => 137,
        'endLine' => 137,
        'startColumn' => 5,
        'endColumn' => 61,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'resolution' => 
      array (
        'name' => 'resolution',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\ResolutionInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the image resolution in DPI.
 *
 * @link https://image.intervention.io/v4/basics/meta-information#image-resolution
 */',
        'startLine' => 144,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 54,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setResolution' => 
      array (
        'name' => 'setResolution',
        'parameters' => 
        array (
          'x' => 
          array (
            'name' => 'x',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 45,
            'endColumn' => 52,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the image resolution in DPI.
 *
 * @link https://image.intervention.io/v4/basics/meta-information#image-resolution
 */',
        'startLine' => 151,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 60,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'colorspace' => 
      array (
        'name' => 'colorspace',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\ColorspaceInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the image colorspace.
 *
 * @link https://image.intervention.io/v4/basics/colors#read-the-image-colorspace
 */',
        'startLine' => 158,
        'endLine' => 158,
        'startColumn' => 5,
        'endColumn' => 54,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setColorspace' => 
      array (
        'name' => 'setColorspace',
        'parameters' => 
        array (
          'colorspace' => 
          array (
            'name' => 'colorspace',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Interfaces\\ColorspaceInterface',
                      'isIdentifier' => false,
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
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 35,
            'endColumn' => 72,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Transform the image to the given colorspace.
 *
 * @link https://image.intervention.io/v4/basics/colors#change-the-image-colorspace
 */',
        'startLine' => 165,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 80,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'colorAt' => 
      array (
        'name' => 'colorAt',
        'parameters' => 
        array (
          'x' => 
          array (
            'name' => 'x',
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
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
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
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'frame' => 
          array (
            'name' => 'frame',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 172,
                'endLine' => 172,
                'startTokenPos' => 494,
                'startFilePos' => 5117,
                'endTokenPos' => 494,
                'endFilePos' => 5117,
              ),
            ),
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
            'startLine' => 172,
            'endLine' => 172,
            'startColumn' => 45,
            'endColumn' => 58,
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
            'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the color of the pixel at the given position and frame.
 *
 * @link https://image.intervention.io/v4/basics/colors#color-information
 */',
        'startLine' => 172,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 76,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'colorsAt' => 
      array (
        'name' => 'colorsAt',
        'parameters' => 
        array (
          'x' => 
          array (
            'name' => 'x',
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
            'startLine' => 179,
            'endLine' => 179,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
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
            'startLine' => 179,
            'endLine' => 179,
            'startColumn' => 38,
            'endColumn' => 43,
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
            'name' => 'Intervention\\Image\\Interfaces\\CollectionInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the colors of the pixel at the given position across all frames.
 *
 * @link https://image.intervention.io/v4/basics/colors#read-all-colors-of-certain-pixels-in-animated-images
 */',
        'startLine' => 179,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 66,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'backgroundColor' => 
      array (
        'name' => 'backgroundColor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the background color used to replace transparent areas during
 * encoding to formats that do not support transparency.
 *
 * @link https://image.intervention.io/v4/basics/configuration-drivers#configuration-options
 */',
        'startLine' => 187,
        'endLine' => 187,
        'startColumn' => 5,
        'endColumn' => 54,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setBackgroundColor' => 
      array (
        'name' => 'setBackgroundColor',
        'parameters' => 
        array (
          'color' => 
          array (
            'name' => 'color',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 195,
            'endLine' => 195,
            'startColumn' => 40,
            'endColumn' => 67,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the background color used to replace transparent areas during
 * encoding to formats that do not support transparency.
 *
 * @link https://image.intervention.io/v4/basics/configuration-drivers#configuration-options
 */',
        'startLine' => 195,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 75,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'fillTransparentAreas' => 
      array (
        'name' => 'fillTransparentAreas',
        'parameters' => 
        array (
          'color' => 
          array (
            'name' => 'color',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 202,
                'endLine' => 202,
                'startTokenPos' => 574,
                'startFilePos' => 6350,
                'endTokenPos' => 574,
                'endFilePos' => 6353,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 202,
            'endLine' => 202,
            'startColumn' => 42,
            'endColumn' => 81,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Replace transparent areas with the given color or the configured background color.
 *
 * @link https://image.intervention.io/v4/basics/colors#merge-transparent-areas-with-color
 */',
        'startLine' => 202,
        'endLine' => 202,
        'startColumn' => 5,
        'endColumn' => 89,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'profile' => 
      array (
        'name' => 'profile',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\ProfileInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the ICC color profile.
 *
 * @link https://image.intervention.io/v4/basics/colors#color-profiles
 */',
        'startLine' => 209,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'setProfile' => 
      array (
        'name' => 'setProfile',
        'parameters' => 
        array (
          'profile' => 
          array (
            'name' => 'profile',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Interfaces\\ProfileInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 216,
            'endLine' => 216,
            'startColumn' => 32,
            'endColumn' => 56,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the ICC color profile.
 *
 * @link https://image.intervention.io/v4/basics/colors#color-profiles
 */',
        'startLine' => 216,
        'endLine' => 216,
        'startColumn' => 5,
        'endColumn' => 64,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'removeProfile' => 
      array (
        'name' => 'removeProfile',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remove the ICC color profile.
 *
 * @link https://image.intervention.io/v4/basics/colors#color-profiles
 */',
        'startLine' => 223,
        'endLine' => 223,
        'startColumn' => 5,
        'endColumn' => 42,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'reduceColors' => 
      array (
        'name' => 'reduceColors',
        'parameters' => 
        array (
          'limit' => 
          array (
            'name' => 'limit',
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
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 230,
                'endLine' => 230,
                'startTokenPos' => 649,
                'startFilePos' => 7188,
                'endTokenPos' => 649,
                'endFilePos' => 7191,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 46,
            'endColumn' => 90,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reduce the number of colors in the image to the given limit.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#reduce-colors
 */',
        'startLine' => 230,
        'endLine' => 230,
        'startColumn' => 5,
        'endColumn' => 98,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'sharpen' => 
      array (
        'name' => 'sharpen',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => 
            array (
              'code' => '10',
              'attributes' => 
              array (
                'startLine' => 237,
                'endLine' => 237,
                'startTokenPos' => 670,
                'startFilePos' => 7400,
                'endTokenPos' => 670,
                'endFilePos' => 7401,
              ),
            ),
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
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 29,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sharpen the image by the given level.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#sharpening-effect
 */',
        'startLine' => 237,
        'endLine' => 237,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'grayscale' => 
      array (
        'name' => 'grayscale',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Turn the image into a grayscale version.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#convert-image-to-a-grayscale-version
 */',
        'startLine' => 244,
        'endLine' => 244,
        'startColumn' => 5,
        'endColumn' => 38,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'brightness' => 
      array (
        'name' => 'brightness',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
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
            'startLine' => 251,
            'endLine' => 251,
            'startColumn' => 32,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Adjust the image brightness by the given level.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#change-the-image-brightness
 */',
        'startLine' => 251,
        'endLine' => 251,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'contrast' => 
      array (
        'name' => 'contrast',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
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
            'startLine' => 258,
            'endLine' => 258,
            'startColumn' => 30,
            'endColumn' => 39,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Adjust the image contrast by the given level.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#change-the-image-contrast
 */',
        'startLine' => 258,
        'endLine' => 258,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'gamma' => 
      array (
        'name' => 'gamma',
        'parameters' => 
        array (
          'gamma' => 
          array (
            'name' => 'gamma',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 27,
            'endColumn' => 38,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Apply gamma correction to the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#gamma-correction
 */',
        'startLine' => 265,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'colorize' => 
      array (
        'name' => 'colorize',
        'parameters' => 
        array (
          'red' => 
          array (
            'name' => 'red',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 272,
                'endLine' => 272,
                'startTokenPos' => 756,
                'startFilePos' => 8490,
                'endTokenPos' => 756,
                'endFilePos' => 8490,
              ),
            ),
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
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 30,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'green' => 
          array (
            'name' => 'green',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 272,
                'endLine' => 272,
                'startTokenPos' => 765,
                'startFilePos' => 8506,
                'endTokenPos' => 765,
                'endFilePos' => 8506,
              ),
            ),
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
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 44,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'blue' => 
          array (
            'name' => 'blue',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 272,
                'endLine' => 272,
                'startTokenPos' => 774,
                'startFilePos' => 8521,
                'endTokenPos' => 774,
                'endFilePos' => 8521,
              ),
            ),
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
            'startLine' => 272,
            'endLine' => 272,
            'startColumn' => 60,
            'endColumn' => 72,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Adjust the intensity of the RGB color channels.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#color-correction
 */',
        'startLine' => 272,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 80,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'flip' => 
      array (
        'name' => 'flip',
        'parameters' => 
        array (
          'direction' => 
          array (
            'name' => 'direction',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Direction::HORIZONTAL',
              'attributes' => 
              array (
                'startLine' => 279,
                'endLine' => 279,
                'startTokenPos' => 795,
                'startFilePos' => 8736,
                'endTokenPos' => 797,
                'endFilePos' => 8756,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Direction',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 279,
            'endLine' => 279,
            'startColumn' => 26,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Mirror the image in the given direction.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#mirror-images
 */',
        'startLine' => 279,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 77,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'blur' => 
      array (
        'name' => 'blur',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => 
            array (
              'code' => '5',
              'attributes' => 
              array (
                'startLine' => 286,
                'endLine' => 286,
                'startTokenPos' => 818,
                'startFilePos' => 8960,
                'endTokenPos' => 818,
                'endFilePos' => 8960,
              ),
            ),
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
            'startLine' => 286,
            'endLine' => 286,
            'startColumn' => 26,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Apply a blur effect with the given level.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#blur-effect
 */',
        'startLine' => 286,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'invert' => 
      array (
        'name' => 'invert',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Invert the image colors.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#invert-colors
 */',
        'startLine' => 293,
        'endLine' => 293,
        'startColumn' => 5,
        'endColumn' => 35,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'pixelate' => 
      array (
        'name' => 'pixelate',
        'parameters' => 
        array (
          'size' => 
          array (
            'name' => 'size',
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
            'startLine' => 300,
            'endLine' => 300,
            'startColumn' => 30,
            'endColumn' => 38,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Apply a pixelation effect with the given tile size.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#pixelation-effect
 */',
        'startLine' => 300,
        'endLine' => 300,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'rotate' => 
      array (
        'name' => 'rotate',
        'parameters' => 
        array (
          'angle' => 
          array (
            'name' => 'angle',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 307,
            'endLine' => 307,
            'startColumn' => 28,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 307,
                'endLine' => 307,
                'startTokenPos' => 879,
                'startFilePos' => 9612,
                'endTokenPos' => 879,
                'endFilePos' => 9615,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 307,
            'endLine' => 307,
            'startColumn' => 42,
            'endColumn' => 86,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Rotate the image clockwise by the given angle.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#image-rotation
 */',
        'startLine' => 307,
        'endLine' => 307,
        'startColumn' => 5,
        'endColumn' => 94,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'orient' => 
      array (
        'name' => 'orient',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Orient the image upright based on EXIF data.
 *
 * @link https://image.intervention.io/v4/modifying-images/effects#image-orientation-according-to-exif-data
 */',
        'startLine' => 314,
        'endLine' => 314,
        'startColumn' => 5,
        'endColumn' => 35,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'text' => 
      array (
        'name' => 'text',
        'parameters' => 
        array (
          'text' => 
          array (
            'name' => 'text',
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 26,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'x' => 
          array (
            'name' => 'x',
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 48,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'font' => 
          array (
            'name' => 'font',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Interfaces\\FontInterface',
                      'isIdentifier' => false,
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 56,
            'endColumn' => 83,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw text on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/text-fonts
 */',
        'startLine' => 321,
        'endLine' => 321,
        'startColumn' => 5,
        'endColumn' => 91,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'resize' => 
      array (
        'name' => 'resize',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 328,
                'endLine' => 328,
                'startTokenPos' => 952,
                'startFilePos' => 10300,
                'endTokenPos' => 952,
                'endFilePos' => 10303,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 28,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 328,
                'endLine' => 328,
                'startTokenPos' => 965,
                'startFilePos' => 10334,
                'endTokenPos' => 965,
                'endFilePos' => 10337,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 61,
            'endColumn' => 92,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image to the given width and/or height.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#simple-image-resizing
 */',
        'startLine' => 328,
        'endLine' => 328,
        'startColumn' => 5,
        'endColumn' => 100,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'resizeDown' => 
      array (
        'name' => 'resizeDown',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 336,
                'endLine' => 336,
                'startTokenPos' => 990,
                'startFilePos' => 10651,
                'endTokenPos' => 990,
                'endFilePos' => 10654,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 32,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 336,
                'endLine' => 336,
                'startTokenPos' => 1003,
                'startFilePos' => 10685,
                'endTokenPos' => 1003,
                'endFilePos' => 10688,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 65,
            'endColumn' => 96,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image to the given width and/or height without exceeding
 * the original dimensions.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#resize-without-exceeding-the-original-size
 */',
        'startLine' => 336,
        'endLine' => 336,
        'startColumn' => 5,
        'endColumn' => 104,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'scale' => 
      array (
        'name' => 'scale',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 344,
                'endLine' => 344,
                'startTokenPos' => 1028,
                'startFilePos' => 10976,
                'endTokenPos' => 1028,
                'endFilePos' => 10979,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 344,
            'endLine' => 344,
            'startColumn' => 27,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 344,
                'endLine' => 344,
                'startTokenPos' => 1041,
                'startFilePos' => 11010,
                'endTokenPos' => 1041,
                'endFilePos' => 11013,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 344,
            'endLine' => 344,
            'startColumn' => 60,
            'endColumn' => 91,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image to the given width and/or height while maintaining
 * the aspect ratio.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#resize-images-proportionally
 */',
        'startLine' => 344,
        'endLine' => 344,
        'startColumn' => 5,
        'endColumn' => 99,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'scaleDown' => 
      array (
        'name' => 'scaleDown',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 352,
                'endLine' => 352,
                'startTokenPos' => 1066,
                'startFilePos' => 11371,
                'endTokenPos' => 1066,
                'endFilePos' => 11374,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 352,
            'endLine' => 352,
            'startColumn' => 31,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 352,
                'endLine' => 352,
                'startTokenPos' => 1079,
                'startFilePos' => 11405,
                'endTokenPos' => 1079,
                'endFilePos' => 11408,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 352,
            'endLine' => 352,
            'startColumn' => 64,
            'endColumn' => 95,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image to the given width and/or height while maintaining
 * the aspect ratio and without exceeding the original dimensions.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#scale-images-but-do-not-exceed-the-original-size
 */',
        'startLine' => 352,
        'endLine' => 352,
        'startColumn' => 5,
        'endColumn' => 103,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'cover' => 
      array (
        'name' => 'cover',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'height' => 
          array (
            'name' => 'height',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 361,
            'endLine' => 361,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::CENTER',
              'attributes' => 
              array (
                'startLine' => 362,
                'endLine' => 362,
                'startTokenPos' => 1117,
                'startFilePos' => 11738,
                'endTokenPos' => 1119,
                'endFilePos' => 11754,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 362,
            'endLine' => 362,
            'startColumn' => 9,
            'endColumn' => 55,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Crop and resize the image to cover the given dimensions exactly.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#cropping--resizing-combined
 */',
        'startLine' => 359,
        'endLine' => 363,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'coverDown' => 
      array (
        'name' => 'coverDown',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 372,
            'endLine' => 372,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'height' => 
          array (
            'name' => 'height',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 373,
            'endLine' => 373,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::CENTER',
              'attributes' => 
              array (
                'startLine' => 374,
                'endLine' => 374,
                'startTokenPos' => 1159,
                'startFilePos' => 12159,
                'endTokenPos' => 1161,
                'endFilePos' => 12175,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 374,
            'endLine' => 374,
            'startColumn' => 9,
            'endColumn' => 55,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Crop and resize the image to cover the given dimensions without
 * exceeding the original dimensions.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#fitted-resizing-without-exceeding-the-original-size
 */',
        'startLine' => 371,
        'endLine' => 375,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'resizeCanvas' => 
      array (
        'name' => 'resizeCanvas',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 386,
                'endLine' => 386,
                'startTokenPos' => 1189,
                'startFilePos' => 12643,
                'endTokenPos' => 1189,
                'endFilePos' => 12646,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 386,
            'endLine' => 386,
            'startColumn' => 9,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 387,
                'endLine' => 387,
                'startTokenPos' => 1202,
                'startFilePos' => 12685,
                'endTokenPos' => 1202,
                'endFilePos' => 12688,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 9,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 388,
                'endLine' => 388,
                'startTokenPos' => 1215,
                'startFilePos' => 12740,
                'endTokenPos' => 1215,
                'endFilePos' => 12743,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 388,
            'endLine' => 388,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::CENTER',
              'attributes' => 
              array (
                'startLine' => 389,
                'endLine' => 389,
                'startTokenPos' => 1226,
                'startFilePos' => 12784,
                'endTokenPos' => 1228,
                'endFilePos' => 12800,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 389,
            'endLine' => 389,
            'startColumn' => 9,
            'endColumn' => 55,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image canvas to the given width and height without resampling
 *
 * The alignment position defines where the original image is fixed,
 * and new areas are filled with the given background color.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#resize-image-boundaries-without-resampling-the-original-image
 */',
        'startLine' => 385,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'resizeCanvasRelative' => 
      array (
        'name' => 'resizeCanvasRelative',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 402,
                'endLine' => 402,
                'startTokenPos' => 1256,
                'startFilePos' => 13310,
                'endTokenPos' => 1256,
                'endFilePos' => 13313,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 402,
            'endLine' => 402,
            'startColumn' => 9,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 403,
                'endLine' => 403,
                'startTokenPos' => 1269,
                'startFilePos' => 13352,
                'endTokenPos' => 1269,
                'endFilePos' => 13355,
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
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'int',
                      'isIdentifier' => true,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 403,
            'endLine' => 403,
            'startColumn' => 9,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 404,
                'endLine' => 404,
                'startTokenPos' => 1282,
                'startFilePos' => 13407,
                'endTokenPos' => 1282,
                'endFilePos' => 13410,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 404,
            'endLine' => 404,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::CENTER',
              'attributes' => 
              array (
                'startLine' => 405,
                'endLine' => 405,
                'startTokenPos' => 1293,
                'startFilePos' => 13451,
                'endTokenPos' => 1295,
                'endFilePos' => 13467,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 405,
            'endLine' => 405,
            'startColumn' => 9,
            'endColumn' => 55,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image canvas by adding or subtracting the given width and
 * height relative to the original dimensions.
 *
 * The alignment position defines where the original image is fixed,
 * and new areas are filled with the given background color.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#resize-image-boundaries-relative-to-the-original
 */',
        'startLine' => 401,
        'endLine' => 406,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'contain' => 
      array (
        'name' => 'contain',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 415,
            'endLine' => 415,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'height' => 
          array (
            'name' => 'height',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 417,
                'endLine' => 417,
                'startTokenPos' => 1337,
                'startFilePos' => 13891,
                'endTokenPos' => 1337,
                'endFilePos' => 13894,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 417,
            'endLine' => 417,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::CENTER',
              'attributes' => 
              array (
                'startLine' => 418,
                'endLine' => 418,
                'startTokenPos' => 1348,
                'startFilePos' => 13935,
                'endTokenPos' => 1350,
                'endFilePos' => 13951,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 418,
            'endLine' => 418,
            'startColumn' => 9,
            'endColumn' => 55,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image to fit within the given dimensions while maintaining
 * the aspect ratio. New areas are filled with the given background color.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#contain-resizing-1
 */',
        'startLine' => 414,
        'endLine' => 419,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'containDown' => 
      array (
        'name' => 'containDown',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'height' => 
          array (
            'name' => 'height',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 430,
            'endLine' => 430,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 431,
                'endLine' => 431,
                'startTokenPos' => 1392,
                'startFilePos' => 14448,
                'endTokenPos' => 1392,
                'endFilePos' => 14451,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 431,
            'endLine' => 431,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::CENTER',
              'attributes' => 
              array (
                'startLine' => 432,
                'endLine' => 432,
                'startTokenPos' => 1403,
                'startFilePos' => 14492,
                'endTokenPos' => 1405,
                'endFilePos' => 14508,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 432,
            'endLine' => 432,
            'startColumn' => 9,
            'endColumn' => 55,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resize the image to fit within the given dimensions while maintaining
 * the aspect ratio and without exceeding the original dimensions. New
 * areas are filled with the given background color.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#contain-resizing-without-upscaling
 */',
        'startLine' => 428,
        'endLine' => 433,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'crop' => 
      array (
        'name' => 'crop',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 442,
            'endLine' => 442,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'height' => 
          array (
            'name' => 'height',
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
                      'name' => 'Intervention\\Image\\Fraction',
                      'isIdentifier' => false,
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
            'startLine' => 443,
            'endLine' => 443,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'x' => 
          array (
            'name' => 'x',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 444,
                'endLine' => 444,
                'startTokenPos' => 1443,
                'startFilePos' => 14886,
                'endTokenPos' => 1443,
                'endFilePos' => 14886,
              ),
            ),
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
            'startLine' => 444,
            'endLine' => 444,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'y' => 
          array (
            'name' => 'y',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 445,
                'endLine' => 445,
                'startTokenPos' => 1452,
                'startFilePos' => 14906,
                'endTokenPos' => 1452,
                'endFilePos' => 14906,
              ),
            ),
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
            'startLine' => 445,
            'endLine' => 445,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'background' => 
          array (
            'name' => 'background',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 446,
                'endLine' => 446,
                'startTokenPos' => 1465,
                'startFilePos' => 14958,
                'endTokenPos' => 1465,
                'endFilePos' => 14961,
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
                      'name' => 'null',
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
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 446,
            'endLine' => 446,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::TOP_LEFT',
              'attributes' => 
              array (
                'startLine' => 447,
                'endLine' => 447,
                'startTokenPos' => 1476,
                'startFilePos' => 15002,
                'endTokenPos' => 1478,
                'endFilePos' => 15020,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 447,
            'endLine' => 447,
            'startColumn' => 9,
            'endColumn' => 57,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Cut out a rectangular part of the image with the given width and height
 * at the given alignment position offset by x and y.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#cut-out-a-rectangular-part
 */',
        'startLine' => 441,
        'endLine' => 448,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'trim' => 
      array (
        'name' => 'trim',
        'parameters' => 
        array (
          'tolerance' => 
          array (
            'name' => 'tolerance',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 455,
                'endLine' => 455,
                'startTokenPos' => 1501,
                'startFilePos' => 15281,
                'endTokenPos' => 1501,
                'endFilePos' => 15281,
              ),
            ),
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
            'startLine' => 455,
            'endLine' => 455,
            'startColumn' => 26,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Trim border areas of similar color within the given tolerance.
 *
 * @link https://image.intervention.io/v4/modifying-images/resizing#remove-border-areas-in-similar-color
 */',
        'startLine' => 455,
        'endLine' => 455,
        'startColumn' => 5,
        'endColumn' => 51,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'insert' => 
      array (
        'name' => 'insert',
        'parameters' => 
        array (
          'image' => 
          array (
            'name' => 'image',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 463,
            'endLine' => 463,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'x' => 
          array (
            'name' => 'x',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 464,
                'endLine' => 464,
                'startTokenPos' => 1528,
                'startFilePos' => 15555,
                'endTokenPos' => 1528,
                'endFilePos' => 15555,
              ),
            ),
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
            'startLine' => 464,
            'endLine' => 464,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'y' => 
          array (
            'name' => 'y',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 465,
                'endLine' => 465,
                'startTokenPos' => 1537,
                'startFilePos' => 15575,
                'endTokenPos' => 1537,
                'endFilePos' => 15575,
              ),
            ),
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
            'startLine' => 465,
            'endLine' => 465,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'alignment' => 
          array (
            'name' => 'alignment',
            'default' => 
            array (
              'code' => '\\Intervention\\Image\\Alignment::TOP_LEFT',
              'attributes' => 
              array (
                'startLine' => 466,
                'endLine' => 466,
                'startTokenPos' => 1548,
                'startFilePos' => 15616,
                'endTokenPos' => 1550,
                'endFilePos' => 15634,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Alignment',
                      'isIdentifier' => false,
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
            'startLine' => 466,
            'endLine' => 466,
            'startColumn' => 9,
            'endColumn' => 57,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'transparency' => 
          array (
            'name' => 'transparency',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 467,
                'endLine' => 467,
                'startTokenPos' => 1559,
                'startFilePos' => 15667,
                'endTokenPos' => 1559,
                'endFilePos' => 15667,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 467,
            'endLine' => 467,
            'startColumn' => 9,
            'endColumn' => 31,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Insert another image at the given position relative to the alignment position.
 *
 * @link https://image.intervention.io/v4/modifying-images/inserting#insert-images
 */',
        'startLine' => 462,
        'endLine' => 468,
        'startColumn' => 5,
        'endColumn' => 12,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'fill' => 
      array (
        'name' => 'fill',
        'parameters' => 
        array (
          'color' => 
          array (
            'name' => 'color',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 477,
            'endLine' => 477,
            'startColumn' => 26,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'x' => 
          array (
            'name' => 'x',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 477,
                'endLine' => 477,
                'startTokenPos' => 1590,
                'startFilePos' => 16060,
                'endTokenPos' => 1590,
                'endFilePos' => 16063,
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
                      'name' => 'int',
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
            'startLine' => 477,
            'endLine' => 477,
            'startColumn' => 56,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'y' => 
          array (
            'name' => 'y',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 477,
                'endLine' => 477,
                'startTokenPos' => 1600,
                'startFilePos' => 16076,
                'endTokenPos' => 1600,
                'endFilePos' => 16079,
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
                      'name' => 'int',
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
            'startLine' => 477,
            'endLine' => 477,
            'startColumn' => 72,
            'endColumn' => 85,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Fill the image with the given color. If coordinates are specified, the
 * fill is applied as a flood fill starting at that position. Otherwise
 * the entire image area is filled.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#fill-images-with-color
 */',
        'startLine' => 477,
        'endLine' => 477,
        'startColumn' => 5,
        'endColumn' => 93,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawPixel' => 
      array (
        'name' => 'drawPixel',
        'parameters' => 
        array (
          'x' => 
          array (
            'name' => 'x',
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
            'startLine' => 484,
            'endLine' => 484,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
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
            'startLine' => 484,
            'endLine' => 484,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'color' => 
          array (
            'name' => 'color',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Interfaces\\ColorInterface',
                      'isIdentifier' => false,
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
            'startLine' => 484,
            'endLine' => 484,
            'startColumn' => 47,
            'endColumn' => 74,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a single pixel at the given position in the given color.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-pixels
 */',
        'startLine' => 484,
        'endLine' => 484,
        'startColumn' => 5,
        'endColumn' => 82,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawRectangle' => 
      array (
        'name' => 'drawRectangle',
        'parameters' => 
        array (
          'rectangle' => 
          array (
            'name' => 'rectangle',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Geometry\\Rectangle',
                      'isIdentifier' => false,
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
            'startLine' => 491,
            'endLine' => 491,
            'startColumn' => 35,
            'endColumn' => 63,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a rectangle on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-a-rectangle
 */',
        'startLine' => 491,
        'endLine' => 491,
        'startColumn' => 5,
        'endColumn' => 71,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawEllipse' => 
      array (
        'name' => 'drawEllipse',
        'parameters' => 
        array (
          'ellipse' => 
          array (
            'name' => 'ellipse',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Geometry\\Ellipse',
                      'isIdentifier' => false,
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
            'startLine' => 498,
            'endLine' => 498,
            'startColumn' => 33,
            'endColumn' => 57,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw an ellipse on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-ellipses
 */',
        'startLine' => 498,
        'endLine' => 498,
        'startColumn' => 5,
        'endColumn' => 65,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawCircle' => 
      array (
        'name' => 'drawCircle',
        'parameters' => 
        array (
          'circle' => 
          array (
            'name' => 'circle',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Geometry\\Circle',
                      'isIdentifier' => false,
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
            'startLine' => 505,
            'endLine' => 505,
            'startColumn' => 32,
            'endColumn' => 54,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a circle on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-a-circle
 */',
        'startLine' => 505,
        'endLine' => 505,
        'startColumn' => 5,
        'endColumn' => 62,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawPolygon' => 
      array (
        'name' => 'drawPolygon',
        'parameters' => 
        array (
          'polygon' => 
          array (
            'name' => 'polygon',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Geometry\\Polygon',
                      'isIdentifier' => false,
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
            'startLine' => 512,
            'endLine' => 512,
            'startColumn' => 33,
            'endColumn' => 57,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a polygon on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-a-polygon
 */',
        'startLine' => 512,
        'endLine' => 512,
        'startColumn' => 5,
        'endColumn' => 65,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawLine' => 
      array (
        'name' => 'drawLine',
        'parameters' => 
        array (
          'line' => 
          array (
            'name' => 'line',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Geometry\\Line',
                      'isIdentifier' => false,
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
            'startLine' => 519,
            'endLine' => 519,
            'startColumn' => 30,
            'endColumn' => 48,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a line on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-a-line
 */',
        'startLine' => 519,
        'endLine' => 519,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'drawBezier' => 
      array (
        'name' => 'drawBezier',
        'parameters' => 
        array (
          'bezier' => 
          array (
            'name' => 'bezier',
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
                      'name' => 'callable',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\Geometry\\Bezier',
                      'isIdentifier' => false,
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
            'startLine' => 526,
            'endLine' => 526,
            'startColumn' => 32,
            'endColumn' => 54,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a bezier curve on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing#draw-bezier-curves
 */',
        'startLine' => 526,
        'endLine' => 526,
        'startColumn' => 5,
        'endColumn' => 62,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'draw' => 
      array (
        'name' => 'draw',
        'parameters' => 
        array (
          'drawable' => 
          array (
            'name' => 'drawable',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Interfaces\\DrawableInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 533,
            'endLine' => 533,
            'startColumn' => 26,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Draw a geometric object on the image.
 *
 * @link https://image.intervention.io/v4/modifying-images/drawing
 */',
        'startLine' => 533,
        'endLine' => 533,
        'startColumn' => 5,
        'endColumn' => 60,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'encode' => 
      array (
        'name' => 'encode',
        'parameters' => 
        array (
          'encoder' => 
          array (
            'name' => 'encoder',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 541,
                'endLine' => 541,
                'startTokenPos' => 1782,
                'startFilePos' => 18142,
                'endTokenPos' => 1782,
                'endFilePos' => 18145,
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
                      'name' => 'Intervention\\Image\\Interfaces\\EncoderInterface',
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
            'startLine' => 541,
            'endLine' => 541,
            'startColumn' => 28,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\Interfaces\\EncodedImageInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encode the image with the given encoder. If no encoder is provided,
 * the format is detected from the original image automatically.
 *
 * @link https://image.intervention.io/v4/basics/image-output#encode-images-with-encoder-objects
 */',
        'startLine' => 541,
        'endLine' => 541,
        'startColumn' => 5,
        'endColumn' => 85,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'encodeUsingFormat' => 
      array (
        'name' => 'encodeUsingFormat',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Intervention\\Image\\Format',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 548,
            'endLine' => 548,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 548,
            'endLine' => 548,
            'startColumn' => 55,
            'endColumn' => 71,
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
            'name' => 'Intervention\\Image\\Interfaces\\EncodedImageInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encode the image in the given format.
 *
 * @link https://image.intervention.io/v4/basics/image-output#encode-images-using-format
 */',
        'startLine' => 548,
        'endLine' => 548,
        'startColumn' => 5,
        'endColumn' => 96,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'encodeUsingMediaType' => 
      array (
        'name' => 'encodeUsingMediaType',
        'parameters' => 
        array (
          'mediaType' => 
          array (
            'name' => 'mediaType',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\MediaType',
                      'isIdentifier' => false,
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
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 42,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 71,
            'endColumn' => 87,
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
            'name' => 'Intervention\\Image\\Interfaces\\EncodedImageInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encode the image based on the given media (MIME) type.
 *
 * @link https://image.intervention.io/v4/basics/image-output#encode-images-using-media-mime-types
 */',
        'startLine' => 555,
        'endLine' => 555,
        'startColumn' => 5,
        'endColumn' => 112,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'encodeUsingFileExtension' => 
      array (
        'name' => 'encodeUsingFileExtension',
        'parameters' => 
        array (
          'fileExtension' => 
          array (
            'name' => 'fileExtension',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'Intervention\\Image\\FileExtension',
                      'isIdentifier' => false,
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
            'startLine' => 563,
            'endLine' => 563,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 564,
            'endLine' => 564,
            'startColumn' => 9,
            'endColumn' => 25,
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
            'name' => 'Intervention\\Image\\Interfaces\\EncodedImageInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encode the image based on the given file extension.
 *
 * @link https://image.intervention.io/v4/basics/image-output#encode-images-using-file-extensions
 */',
        'startLine' => 562,
        'endLine' => 565,
        'startColumn' => 5,
        'endColumn' => 29,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'aliasName' => NULL,
      ),
      'encodeUsingPath' => 
      array (
        'name' => 'encodeUsingPath',
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
            'startLine' => 572,
            'endLine' => 572,
            'startColumn' => 37,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 572,
            'endLine' => 572,
            'startColumn' => 51,
            'endColumn' => 67,
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
            'name' => 'Intervention\\Image\\Interfaces\\EncodedImageInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encode the image based on the given file path\'s extension.
 *
 * @link https://image.intervention.io/v4/basics/image-output#encode-images-using-file-paths
 */',
        'startLine' => 572,
        'endLine' => 572,
        'startColumn' => 5,
        'endColumn' => 92,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Interfaces',
        'declaringClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'implementingClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
        'currentClassName' => 'Intervention\\Image\\Interfaces\\ImageInterface',
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