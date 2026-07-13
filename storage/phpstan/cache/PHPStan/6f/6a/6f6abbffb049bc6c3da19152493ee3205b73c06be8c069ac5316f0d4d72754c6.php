<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Gallery/FaceCropper.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Gallery\FaceCropper
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-328f2ccbb794d2358cb05c73acc8ca756e481eb88ed93f4bac7cce41f9730de1',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Gallery\\FaceCropper',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Gallery/FaceCropper.php',
      ),
    ),
    'namespace' => 'App\\Services\\Gallery',
    'name' => 'App\\Services\\Gallery\\FaceCropper',
    'shortName' => 'FaceCropper',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Crops a detected face out of an image into a small square JPEG thumbnail.
 * Boxes arrive normalised (0..1); a little padding is added around the face.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 63,
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
      'SIZE' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\FaceCropper',
        'implementingClassName' => 'App\\Services\\Gallery\\FaceCropper',
        'name' => 'SIZE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '256',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 36,
            'startFilePos' => 290,
            'endTokenPos' => 36,
            'endFilePos' => 292,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'PAD' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\FaceCropper',
        'implementingClassName' => 'App\\Services\\Gallery\\FaceCropper',
        'name' => 'PAD',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0.15',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 47,
            'startFilePos' => 320,
            'endTokenPos' => 47,
            'endFilePos' => 323,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'crop' => 
      array (
        'name' => 'crop',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 26,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'box' => 
          array (
            'name' => 'box',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 40,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{0: float, 1: float, 2: float, 3: float}  $box  normalised x1,y1,x2,y2
 */',
        'startLine' => 22,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\FaceCropper',
        'implementingClassName' => 'App\\Services\\Gallery\\FaceCropper',
        'currentClassName' => 'App\\Services\\Gallery\\FaceCropper',
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