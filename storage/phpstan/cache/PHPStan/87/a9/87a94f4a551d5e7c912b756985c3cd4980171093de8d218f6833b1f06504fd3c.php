<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Support/NominatimClient.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Support\NominatimClient
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-56c52b47a02f4f94ead2abfac9aa4388fc3794eef6ba9302108a2329460f76fa',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Support\\NominatimClient',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Support/NominatimClient.php',
      ),
    ),
    'namespace' => 'App\\Services\\Support',
    'name' => 'App\\Services\\Support\\NominatimClient',
    'shortName' => 'NominatimClient',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Shared, rate-limited client for a Nominatim-compatible geocoding endpoint
 * (config gallery.geocoder_url; the OSM public server by default). Serialises
 * requests across all workers so the one-per-second usage policy is honoured
 * during bulk imports as well as interactive lookups. Nothing location-bearing
 * is cached here — only a request timestamp for the throttle window.
 *
 * NOTE — when geocoder_url is the public OSM server this is a third-party
 * egress: the (coarsened) coordinates leave the zero-knowledge boundary. That
 * is why automatic on-upload geocoding is OFF by default (gallery.geocode_on_
 * upload) and only the user-initiated place-picker triggers a lookup. Point
 * geocoder_url at a self-hosted Nominatim (docker compose --profile geocode) to
 * keep every lookup in-boundary. Requests go through the SSRF guard regardless.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 92,
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
      'base' => 
      array (
        'name' => 'base',
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
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Support',
        'declaringClassName' => 'App\\Services\\Support\\NominatimClient',
        'implementingClassName' => 'App\\Services\\Support\\NominatimClient',
        'currentClassName' => 'App\\Services\\Support\\NominatimClient',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'endpoint' => 
          array (
            'name' => 'endpoint',
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
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 25,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'query' => 
          array (
            'name' => 'query',
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
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 43,
            'endColumn' => 54,
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
                  'name' => 'array',
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
 * Perform a throttled Nominatim request through the SSRF-guarded client,
 * returning the decoded JSON body or null on any failure.
 *
 * @param  array<string, mixed>  $query
 * @return array<mixed>|null
 */',
        'startLine' => 39,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Support',
        'declaringClassName' => 'App\\Services\\Support\\NominatimClient',
        'implementingClassName' => 'App\\Services\\Support\\NominatimClient',
        'currentClassName' => 'App\\Services\\Support\\NominatimClient',
        'aliasName' => NULL,
      ),
      'throttle' => 
      array (
        'name' => 'throttle',
        'parameters' => 
        array (
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
 * Space requests across all workers so Nominatim\'s one-per-second policy is
 * respected. A short lock serialises workers; the stored timestamp enforces
 * the interval.
 */',
        'startLine' => 66,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Support',
        'declaringClassName' => 'App\\Services\\Support\\NominatimClient',
        'implementingClassName' => 'App\\Services\\Support\\NominatimClient',
        'currentClassName' => 'App\\Services\\Support\\NominatimClient',
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