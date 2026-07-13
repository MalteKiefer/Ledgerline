<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/Concerns/RespondsFlexibly.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\Concerns\RespondsFlexibly
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-3d96968403d01f6f18929921d4b3550ff8cb16d39cd827c7777558fa2089d9ef',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\Concerns\\RespondsFlexibly',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Http/Controllers/Concerns/RespondsFlexibly.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers\\Concerns',
    'name' => 'App\\Http\\Controllers\\Concerns\\RespondsFlexibly',
    'shortName' => 'RespondsFlexibly',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Shared response tail for controllers that back a reload-free Alpine client:
 * an XHR gets a JSON acknowledgement, a plain web request gets a redirect back
 * (optionally to a named target) with an optional translated flash status.
 *
 * Matches the existing idiom exactly:
 *   $request->expectsJson() ? response()->json([\'ok\' => true, ...]) : back()
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 36,
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
      'flexible' => 
      array (
        'name' => 'flexible',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
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
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'json' => 
          array (
            'name' => 'json',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 26,
                'endLine' => 26,
                'startTokenPos' => 52,
                'startFilePos' => 1037,
                'endTokenPos' => 53,
                'endFilePos' => 1038,
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'flashKey' => 
          array (
            'name' => 'flashKey',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 26,
                'endLine' => 26,
                'startTokenPos' => 63,
                'startFilePos' => 1061,
                'endTokenPos' => 63,
                'endFilePos' => 1064,
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 69,
            'endColumn' => 92,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'flashParams' => 
          array (
            'name' => 'flashParams',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 26,
                'endLine' => 26,
                'startTokenPos' => 72,
                'startFilePos' => 1088,
                'endTokenPos' => 73,
                'endFilePos' => 1089,
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 95,
            'endColumn' => 117,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'back' => 
          array (
            'name' => 'back',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 26,
                'endLine' => 26,
                'startTokenPos' => 83,
                'startFilePos' => 1108,
                'endTokenPos' => 83,
                'endFilePos' => 1111,
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
            'startLine' => 26,
            'endLine' => 26,
            'startColumn' => 120,
            'endColumn' => 139,
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
            'name' => 'Symfony\\Component\\HttpFoundation\\Response',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<string, mixed>  $json  Extra payload merged alongside [\'ok\' => true] for XHR callers.
 * @param  string|null  $flashKey  Translation key flashed as \'status\' on the redirect branch (null = no flash).
 * @param  array<string, mixed>  $flashParams  Replacement params for the translated flash string.
 * @param  string|null  $back  Explicit redirect target; when null, redirects back().
 */',
        'startLine' => 26,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Http\\Controllers\\Concerns',
        'declaringClassName' => 'App\\Http\\Controllers\\Concerns\\RespondsFlexibly',
        'implementingClassName' => 'App\\Http\\Controllers\\Concerns\\RespondsFlexibly',
        'currentClassName' => 'App\\Http\\Controllers\\Concerns\\RespondsFlexibly',
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