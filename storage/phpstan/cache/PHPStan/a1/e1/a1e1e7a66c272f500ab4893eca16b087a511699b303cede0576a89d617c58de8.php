<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/ArchiveName.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\ArchiveName
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-0ea9e84b1de047c098e1f35cff2a1b20cfa15ff19200502e3c85012e17167016',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\ArchiveName',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/ArchiveName.php',
      ),
    ),
    'namespace' => 'App\\Support',
    'name' => 'App\\Support\\ArchiveName',
    'shortName' => 'ArchiveName',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Shared helpers for generating collision-free archive (zip/tar) entry names.
 *
 * Every module that streams files into an archive needs the same "if this
 * entry name was already used, append a counter before the extension" logic.
 * This class captures that once so archive builders (e.g. ExportArchiver)
 * stay in sync.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 74,
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
      'unique' => 
      array (
        'name' => 'unique',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 35,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'used' => 
          array (
            'name' => 'used',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 49,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'separator' => 
          array (
            'name' => 'separator',
            'default' => 
            array (
              'code' => '\'-\'',
              'attributes' => 
              array (
                'startLine' => 35,
                'endLine' => 35,
                'startTokenPos' => 52,
                'startFilePos' => 1483,
                'endTokenPos' => 52,
                'endFilePos' => 1485,
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 63,
            'endColumn' => 85,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'parenthesize' => 
          array (
            'name' => 'parenthesize',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 35,
                'endLine' => 35,
                'startTokenPos' => 61,
                'startFilePos' => 1509,
                'endTokenPos' => 61,
                'endFilePos' => 1513,
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 88,
            'endColumn' => 113,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return an entry name unique within the archive. On the first use the name
 * is returned unchanged; on a collision a counter is appended before the
 * file extension (e.g. "photo.jpg" -> "photo-2.jpg" -> "photo-3.jpg").
 *
 * The extension is detected exactly like the original helpers: the last "."
 * that is not the very first character splits base/extension; a name with no
 * "." (or a leading dot only) is treated as having no extension.
 *
 * The chosen name is recorded in $used (by reference) so subsequent calls
 * see it as taken.
 *
 * @param  array<string, bool>  $used  map of already-used entry names
 * @param  string  $separator  glue placed between base and counter for the
 *                             plain form ("_" or "-")
 * @param  bool  $parenthesize  when true, use the "base (N)ext" form and
 *                              ignore $separator (matches the gallery helper)
 */',
        'startLine' => 35,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\ArchiveName',
        'implementingClassName' => 'App\\Support\\ArchiveName',
        'currentClassName' => 'App\\Support\\ArchiveName',
        'aliasName' => NULL,
      ),
      'sanitize' => 
      array (
        'name' => 'sanitize',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sanitise a zip member path against Zip-Slip: normalise backslashes, split
 * on "/", scrub each segment (also dropping "", "." and ".."), and rejoin.
 * The result can never contain a ".." segment or a leading "/", so no member
 * can escape the extraction root — however naive the extractor.
 */',
        'startLine' => 60,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\ArchiveName',
        'implementingClassName' => 'App\\Support\\ArchiveName',
        'currentClassName' => 'App\\Support\\ArchiveName',
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