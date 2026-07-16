<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Gallery/GalleryProcessor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Gallery\GalleryProcessor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-e42391e02c16725925eeadd29b2d1b3038228ce10888b73133471e3a2c328709',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Gallery\\GalleryProcessor',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Gallery/GalleryProcessor.php',
      ),
    ),
    'namespace' => 'App\\Services\\Gallery',
    'name' => 'App\\Services\\Gallery\\GalleryProcessor',
    'shortName' => 'GalleryProcessor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Zero-knowledge gallery transform. Given ONE photo/video\'s plaintext on a local
 * (tmpfs) path, produce all derived data — EXIF, thumbnail + medium renditions,
 * motion clip (Live), CLIP embedding, detected faces (+ crops), perceptual hash,
 * reverse-geocoded place. Pure: reads the path, returns bytes/scalars, writes
 * nothing to the DB or the object store. The caller (the controller) is
 * responsible for handing the plaintext in and deleting it afterwards; the
 * browser encrypts the returned derived data and stores it as opaque blobs.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 208,
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
      'THUMB_WIDTH' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'THUMB_WIDTH',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '400',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 51,
            'startFilePos' => 821,
            'endTokenPos' => 51,
            'endFilePos' => 823,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'MEDIUM_WIDTH' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'MEDIUM_WIDTH',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1600',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 62,
            'startFilePos' => 860,
            'endTokenPos' => 62,
            'endFilePos' => 863,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
    ),
    'immediateProperties' => 
    array (
      'exif' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'exif',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Gallery\\ExifReader',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 9,
        'endColumn' => 41,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'images' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'images',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\ImageManagerFactory',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 9,
        'endColumn' => 52,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'motion' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'motion',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Gallery\\MotionPhotoExtractor',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 9,
        'endColumn' => 53,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'video' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'video',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Gallery\\VideoProcessor',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 9,
        'endColumn' => 46,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'ml' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'ml',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Gallery\\MachineLearning',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 9,
        'endColumn' => 44,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'faces' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'faces',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Gallery\\FaceCropper',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 9,
        'endColumn' => 43,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'phash' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'phash',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Gallery\\PerceptualHash',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 9,
        'endColumn' => 46,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'geo' => 
      array (
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'name' => 'geo',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Files\\ReverseGeocoder',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 9,
        'endColumn' => 45,
        'isPromoted' => true,
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
          'exif' => 
          array (
            'name' => 'exif',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Gallery\\ExifReader',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 28,
            'endLine' => 28,
            'startColumn' => 9,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'images' => 
          array (
            'name' => 'images',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\ImageManagerFactory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 9,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'motion' => 
          array (
            'name' => 'motion',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Gallery\\MotionPhotoExtractor',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 9,
            'endColumn' => 53,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'video' => 
          array (
            'name' => 'video',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Gallery\\VideoProcessor',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 9,
            'endColumn' => 46,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'ml' => 
          array (
            'name' => 'ml',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Gallery\\MachineLearning',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 9,
            'endColumn' => 44,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
          'faces' => 
          array (
            'name' => 'faces',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Gallery\\FaceCropper',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 33,
            'endLine' => 33,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 5,
            'isOptional' => false,
          ),
          'phash' => 
          array (
            'name' => 'phash',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Gallery\\PerceptualHash',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 9,
            'endColumn' => 46,
            'parameterIndex' => 6,
            'isOptional' => false,
          ),
          'geo' => 
          array (
            'name' => 'geo',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Files\\ReverseGeocoder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 9,
            'endColumn' => 45,
            'parameterIndex' => 7,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 27,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'aliasName' => NULL,
      ),
      'process' => 
      array (
        'name' => 'process',
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
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 29,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mime' => 
          array (
            'name' => 'mime',
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
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 43,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'withMl' => 
          array (
            'name' => 'withMl',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 47,
                'endLine' => 47,
                'startTokenPos' => 173,
                'startFilePos' => 1805,
                'endTokenPos' => 173,
                'endFilePos' => 1808,
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
            'startLine' => 47,
            'endLine' => 47,
            'startColumn' => 57,
            'endColumn' => 75,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array{
 *   media_type: string, width: ?int, height: ?int, duration: ?float,
 *   content_id: ?string, exif: array<string,mixed>, place: array<string,mixed>,
 *   embedding: ?list<float>, phash: ?int,
 *   faces: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>,
 *   thumb: ?string, medium: ?string, motion: ?string
 * }
 */',
        'startLine' => 47,
        'endLine' => 163,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'aliasName' => NULL,
      ),
      'analyze' => 
      array (
        'name' => 'analyze',
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
            'startLine' => 173,
            'endLine' => 173,
            'startColumn' => 29,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Deferred vision pass: run the CLIP embedding + face detection on an already
 * decoded image (the client sends the medium rendition). Returns only the ML
 * outputs so the client can merge them into a photo\'s sealed metadata after
 * the fast upload has already made the photo visible.
 *
 * @return array{embedding: ?list<float>, faces: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>}
 */',
        'startLine' => 173,
        'endLine' => 178,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'aliasName' => NULL,
      ),
      'analyzeSource' => 
      array (
        'name' => 'analyzeSource',
        'parameters' => 
        array (
          'imageSource' => 
          array (
            'name' => 'imageSource',
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
            'startLine' => 187,
            'endLine' => 187,
            'startColumn' => 36,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the vision models on one decoded image source and collect the CLIP
 * embedding plus detected faces (each with its crop). Shared by the inline
 * process() and the deferred analyze().
 *
 * @return array{0: ?list<float>, 1: list<array{score: float, box: array{0:float,1:float,2:float,3:float}, embedding: list<float>, crop: ?string}>}
 */',
        'startLine' => 187,
        'endLine' => 207,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\GalleryProcessor',
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