<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Gallery/VideoProcessor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Gallery\VideoProcessor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-d45418d4c673384df1f669eee7414f204f9f5806e1339205273b53d597223228',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Gallery\\VideoProcessor',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Gallery/VideoProcessor.php',
      ),
    ),
    'namespace' => 'App\\Services\\Gallery',
    'name' => 'App\\Services\\Gallery\\VideoProcessor',
    'shortName' => 'VideoProcessor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Reads video metadata (ffprobe) and extracts a poster frame (ffmpeg). Binary
 * paths come from the gallery settings, falling back to an environment variable
 * and finally the system PATH, so the same code runs locally (Homebrew ffmpeg)
 * and on Laravel Cloud (a static build installed by deploy/ffmpeg.sh).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 194,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'probe' => 
      array (
        'name' => 'probe',
        'parameters' => 
        array (
          'localPath' => 
          array (
            'name' => 'localPath',
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
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 27,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Probe a local video file for its dimensions, duration and a full dump.
 *
 * @return array{width: ?int, height: ?int, duration: ?int, raw: ?array<string, mixed>}
 */',
        'startLine' => 24,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'isSideways' => 
      array (
        'name' => 'isSideways',
        'parameters' => 
        array (
          'stream' => 
          array (
            'name' => 'stream',
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
            'startLine' => 80,
            'endLine' => 80,
            'startColumn' => 33,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Whether a video stream is rotated a quarter turn (portrait), from either
 * the display-matrix side data or the legacy rotate tag.
 *
 * @param  array<string, mixed>  $stream
 */',
        'startLine' => 80,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'poster' => 
      array (
        'name' => 'poster',
        'parameters' => 
        array (
          'localPath' => 
          array (
            'name' => 'localPath',
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 28,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 47,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destJpg' => 
          array (
            'name' => 'destJpg',
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 60,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Extract a single poster frame at the given second into a JPEG file.
 *
 * @throws RuntimeException when the frame cannot be produced
 */',
        'startLine' => 99,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'writeMetadata' => 
      array (
        'name' => 'writeMetadata',
        'parameters' => 
        array (
          'srcPath' => 
          array (
            'name' => 'srcPath',
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 35,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'destPath' => 
          array (
            'name' => 'destPath',
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 52,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'metadata' => 
          array (
            'name' => 'metadata',
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 70,
            'endColumn' => 84,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Remux a video to $destPath, copying all streams (no re-encode) and writing
 * the given container metadata (e.g. creation_time, location, model).
 *
 * @param  array<string, string>  $metadata
 */',
        'startLine' => 135,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'available' => 
      array (
        'name' => 'available',
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
 * Whether ffmpeg appears to be available.
 */',
        'startLine' => 156,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'binaryPath' => 
      array (
        'name' => 'binaryPath',
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
        'docComment' => '/**
 * The resolved ffmpeg binary path: a per-workspace override, otherwise the
 * configured path (read through config so it survives config caching).
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
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'ffmpeg' => 
      array (
        'name' => 'ffmpeg',
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
        'startLine' => 180,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'aliasName' => NULL,
      ),
      'ffprobe' => 
      array (
        'name' => 'ffprobe',
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
        'startLine' => 185,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Gallery',
        'declaringClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'implementingClassName' => 'App\\Services\\Gallery\\VideoProcessor',
        'currentClassName' => 'App\\Services\\Gallery\\VideoProcessor',
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