<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Files/ReverseGeocoder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Files\ReverseGeocoder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-3dc82e1ae1c9b1b815fc2bfa0aa2ceacd1af258d50d4c2912e4d9d742e605a8d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Files\\ReverseGeocoder',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Files/ReverseGeocoder.php',
      ),
    ),
    'namespace' => 'App\\Services\\Files',
    'name' => 'App\\Services\\Files\\ReverseGeocoder',
    'shortName' => 'ReverseGeocoder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Reverse-geocodes coordinates to a human-readable address via OpenStreetMap\'s
 * Nominatim service. Runs only inside the transient zero-knowledge
 * /gallery/process window: the resolved place is handed straight back to the
 * browser (which seals it into an opaque blob) and is NEVER cached server-side —
 * caching the resolved address at rest would be a plaintext-location leak. Only
 * a Nominatim rate-limit timestamp (no location content) is kept in the cache.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 90,
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
      'nominatim' => 
      array (
        'declaringClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'implementingClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'name' => 'nominatim',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Services\\Support\\NominatimClient',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 33,
        'endColumn' => 75,
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
          'nominatim' => 
          array (
            'name' => 'nominatim',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Services\\Support\\NominatimClient',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 33,
            'endColumn' => 75,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 79,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Files',
        'declaringClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'implementingClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'currentClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'aliasName' => NULL,
      ),
      'lookup' => 
      array (
        'name' => 'lookup',
        'parameters' => 
        array (
          'lat' => 
          array (
            'name' => 'lat',
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'lon' => 
          array (
            'name' => 'lon',
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
            'startLine' => 23,
            'endLine' => 23,
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
        'docComment' => NULL,
        'startLine' => 23,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Files',
        'declaringClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'implementingClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'currentClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'aliasName' => NULL,
      ),
      'lookupDetailed' => 
      array (
        'name' => 'lookupDetailed',
        'parameters' => 
        array (
          'lat' => 
          array (
            'name' => 'lat',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 36,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'lon' => 
          array (
            'name' => 'lon',
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
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 48,
            'endColumn' => 57,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reverse-geocode to both the full display name and the structured address
 * parts (road, city, state, postcode, country, …). The result is returned to
 * the caller only and never persisted server-side.
 *
 * @return array{display: ?string, address: array<string, string>}
 */',
        'startLine' => 35,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Files',
        'declaringClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'implementingClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'currentClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'aliasName' => NULL,
      ),
      'request' => 
      array (
        'name' => 'request',
        'parameters' => 
        array (
          'lat' => 
          array (
            'name' => 'lat',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'lon' => 
          array (
            'name' => 'lon',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 42,
            'endColumn' => 51,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array{display: ?string, address: array<string, string>}
 */',
        'startLine' => 46,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Files',
        'declaringClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'implementingClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'currentClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'aliasName' => NULL,
      ),
      'snapToGrid' => 
      array (
        'name' => 'snapToGrid',
        'parameters' => 
        array (
          'lat' => 
          array (
            'name' => 'lat',
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
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 33,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'lon' => 
          array (
            'name' => 'lon',
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
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 45,
            'endColumn' => 54,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Snap coordinates to the configured grid (in km) so photos taken close
 * together resolve to the same cached place instead of each hitting OSM.
 *
 * @return array{0: float, 1: float}
 */',
        'startLine' => 72,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Files',
        'declaringClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'implementingClassName' => 'App\\Services\\Files\\ReverseGeocoder',
        'currentClassName' => 'App\\Services\\Files\\ReverseGeocoder',
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