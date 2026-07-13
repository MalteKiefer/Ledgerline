<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../intervention/image/src/Drivers/AbstractEncoder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Intervention\Image\Drivers\AbstractEncoder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-eecb95ead4f616974637a48a86cf83ce5342552817febe0f8ed097ce2caa8f1f-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../intervention/image/src/Drivers/AbstractEncoder.php',
      ),
    ),
    'namespace' => 'Intervention\\Image\\Drivers',
    'name' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
    'shortName' => 'AbstractEncoder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 78,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Intervention\\Image\\Interfaces\\EncoderInterface',
    ),
    'traitClassNames' => 
    array (
      0 => 'Intervention\\Image\\Traits\\CanBuildStream',
    ),
    'immediateConstants' => 
    array (
      'DEFAULT_QUALITY' => 
      array (
        'declaringClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'implementingClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'name' => 'DEFAULT_QUALITY',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'value' => 
        array (
          'code' => '75',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 89,
            'startFilePos' => 706,
            'endTokenPos' => 89,
            'endFilePos' => 707,
          ),
        ),
        'docComment' => '/**
 * Default encoding quality.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'encode' => 
      array (
        'name' => 'encode',
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
                'name' => 'Intervention\\Image\\Interfaces\\ImageInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 33,
            'endLine' => 33,
            'startColumn' => 28,
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
            'name' => 'Intervention\\Image\\Interfaces\\EncodedImageInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * {@inheritdoc}
 *
 * @see EncoderInterface::encode()
 *
 * @throws LogicException
 */',
        'startLine' => 33,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Drivers',
        'declaringClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'implementingClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'currentClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'aliasName' => NULL,
      ),
      'createEncodedImage' => 
      array (
        'name' => 'createEncodedImage',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 43,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mediaType' => 
          array (
            'name' => 'mediaType',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 50,
                'endLine' => 50,
                'startTokenPos' => 179,
                'startFilePos' => 1415,
                'endTokenPos' => 179,
                'endFilePos' => 1418,
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
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 63,
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
            'name' => 'Intervention\\Image\\EncodedImage',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build new stream, run callback with it and return result as encoded image.
 *
 * @throws InvalidArgumentException
 * @throws StreamException
 */',
        'startLine' => 50,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Intervention\\Image\\Drivers',
        'declaringClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'implementingClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'currentClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'aliasName' => NULL,
      ),
      'setOptions' => 
      array (
        'name' => 'setOptions',
        'parameters' => 
        array (
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 32,
            'endColumn' => 48,
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
 * {@inheritdoc}
 *
 * @see EncoderInterface::setOptions()
 *
 * @throws InvalidArgumentException
 */',
        'startLine' => 65,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Intervention\\Image\\Drivers',
        'declaringClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'implementingClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
        'currentClassName' => 'Intervention\\Image\\Drivers\\AbstractEncoder',
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