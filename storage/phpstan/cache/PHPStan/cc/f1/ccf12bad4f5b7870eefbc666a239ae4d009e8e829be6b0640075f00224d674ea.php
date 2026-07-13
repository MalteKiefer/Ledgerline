<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Enums/FileType.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Enums\FileType
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-746d3f62a3b48f5b36eb0a834225f0263fd8478aaeb9f842ada78edd9bbf15a4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Enums\\FileType',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Enums/FileType.php',
      ),
    ),
    'namespace' => 'App\\Enums',
    'name' => 'App\\Enums\\FileType',
    'shortName' => 'FileType',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => true,
    'isBackedEnum' => true,
    'modifiers' => 0,
    'docComment' => '/**
 * A coarse, user-facing category for an uploaded file, derived from its MIME
 * type. Stored as the backing value; the label is for display and filtering.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 124,
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
      'name' => 
      array (
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'name' => 'name',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'value' => 
      array (
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'name' => 'value',
        'modifiers' => 2177,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
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
      'label' => 
      array (
        'name' => 'label',
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
 * Human-readable, English label.
 */',
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
        'aliasName' => NULL,
      ),
      'isTextExtractable' => 
      array (
        'name' => 'isTextExtractable',
        'parameters' => 
        array (
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
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 39,
            'endColumn' => 50,
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
 * Whether this category\'s text content can be extracted for search when the
 * file is not encrypted (plain-text and CSV/JSON here; richer formats are a
 * future extension).
 */',
        'startLine' => 44,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
        'aliasName' => NULL,
      ),
      'fromMime' => 
      array (
        'name' => 'fromMime',
        'parameters' => 
        array (
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
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 37,
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
 * Detect the category from a MIME type.
 *
 * MIME-only (server has no filename here). The client\'s richer counterpart
 * is fileCategory() in resources/js/app.js, which also uses the file
 * extension — keep the two category sets in sync.
 */',
        'startLine' => 57,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
        'aliasName' => NULL,
      ),
      'options' => 
      array (
        'name' => 'options',
        'parameters' => 
        array (
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
 * All cases as value/label pairs for filters.
 *
 * @return array<int, array{value: string, label: string}>
 */',
        'startLine' => 117,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
        'aliasName' => NULL,
      ),
      'cases' => 
      array (
        'name' => 'cases',
        'parameters' => 
        array (
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
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
        'aliasName' => NULL,
      ),
      'from' => 
      array (
        'name' => 'from',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
                      'name' => 'int',
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
            'startLine' => NULL,
            'endLine' => NULL,
            'startColumn' => -1,
            'endColumn' => -1,
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
            'name' => 'static',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
        'aliasName' => NULL,
      ),
      'tryFrom' => 
      array (
        'name' => 'tryFrom',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
                      'name' => 'int',
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
            'startLine' => NULL,
            'endLine' => NULL,
            'startColumn' => -1,
            'endColumn' => -1,
            'parameterIndex' => 0,
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
                  'name' => 'static',
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
        'docComment' => NULL,
        'startLine' => NULL,
        'endLine' => NULL,
        'startColumn' => -1,
        'endColumn' => -1,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Enums',
        'declaringClassName' => 'App\\Enums\\FileType',
        'implementingClassName' => 'App\\Enums\\FileType',
        'currentClassName' => 'App\\Enums\\FileType',
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
    'backingType' => 
    array (
      'name' => 'string',
      'isIdentifier' => true,
    ),
    'cases' => 
    array (
      'IMAGE' => 
      array (
        'name' => 'IMAGE',
        'value' => 
        array (
          'code' => '\'IMAGE\'',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 37,
            'startFilePos' => 289,
            'endTokenPos' => 37,
            'endFilePos' => 295,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'VECTOR' => 
      array (
        'name' => 'VECTOR',
        'value' => 
        array (
          'code' => '\'VECTOR\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 46,
            'startFilePos' => 316,
            'endTokenPos' => 46,
            'endFilePos' => 323,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
      'VIDEO' => 
      array (
        'name' => 'VIDEO',
        'value' => 
        array (
          'code' => '\'VIDEO\'',
          'attributes' => 
          array (
            'startLine' => 17,
            'endLine' => 17,
            'startTokenPos' => 55,
            'startFilePos' => 343,
            'endTokenPos' => 55,
            'endFilePos' => 349,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'AUDIO' => 
      array (
        'name' => 'AUDIO',
        'value' => 
        array (
          'code' => '\'AUDIO\'',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 64,
            'startFilePos' => 369,
            'endTokenPos' => 64,
            'endFilePos' => 375,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'PDF' => 
      array (
        'name' => 'PDF',
        'value' => 
        array (
          'code' => '\'PDF\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 73,
            'startFilePos' => 393,
            'endTokenPos' => 73,
            'endFilePos' => 397,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 21,
      ),
      'DOCUMENT' => 
      array (
        'name' => 'DOCUMENT',
        'value' => 
        array (
          'code' => '\'DOCUMENT\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 82,
            'startFilePos' => 420,
            'endTokenPos' => 82,
            'endFilePos' => 429,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'EBOOK' => 
      array (
        'name' => 'EBOOK',
        'value' => 
        array (
          'code' => '\'EBOOK\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 91,
            'startFilePos' => 449,
            'endTokenPos' => 91,
            'endFilePos' => 455,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'SPREADSHEET' => 
      array (
        'name' => 'SPREADSHEET',
        'value' => 
        array (
          'code' => '\'SPREADSHEET\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 100,
            'startFilePos' => 481,
            'endTokenPos' => 100,
            'endFilePos' => 493,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'PRESENTATION' => 
      array (
        'name' => 'PRESENTATION',
        'value' => 
        array (
          'code' => '\'PRESENTATION\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 109,
            'startFilePos' => 520,
            'endTokenPos' => 109,
            'endFilePos' => 533,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'ARCHIVE' => 
      array (
        'name' => 'ARCHIVE',
        'value' => 
        array (
          'code' => '\'ARCHIVE\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 118,
            'startFilePos' => 555,
            'endTokenPos' => 118,
            'endFilePos' => 563,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'DISK' => 
      array (
        'name' => 'DISK',
        'value' => 
        array (
          'code' => '\'DISK\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 127,
            'startFilePos' => 582,
            'endTokenPos' => 127,
            'endFilePos' => 587,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 23,
      ),
      'CODE' => 
      array (
        'name' => 'CODE',
        'value' => 
        array (
          'code' => '\'CODE\'',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 136,
            'startFilePos' => 606,
            'endTokenPos' => 136,
            'endFilePos' => 611,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 23,
      ),
      'TEXT' => 
      array (
        'name' => 'TEXT',
        'value' => 
        array (
          'code' => '\'TEXT\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 145,
            'startFilePos' => 630,
            'endTokenPos' => 145,
            'endFilePos' => 635,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 23,
      ),
      'FONT' => 
      array (
        'name' => 'FONT',
        'value' => 
        array (
          'code' => '\'FONT\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 154,
            'startFilePos' => 654,
            'endTokenPos' => 154,
            'endFilePos' => 659,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 23,
      ),
      'OTHER' => 
      array (
        'name' => 'OTHER',
        'value' => 
        array (
          'code' => '\'OTHER\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 163,
            'startFilePos' => 679,
            'endTokenPos' => 163,
            'endFilePos' => 685,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
    ),
  ),
));