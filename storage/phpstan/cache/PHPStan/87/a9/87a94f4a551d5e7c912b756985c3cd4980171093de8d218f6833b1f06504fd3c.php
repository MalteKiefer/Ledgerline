<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Support/NominatimClient.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Support\NominatimClient
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-8f215ff164df909202afd0f2f6b928404eefd47e9cbba41180a7f7d020a5eb59',
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
 * Shared, rate-limited client for OpenStreetMap\'s Nominatim service. Serialises
 * requests across all workers so the one-per-second usage policy is honoured
 * during bulk imports as well as interactive lookups. Nothing location-bearing
 * is cached here — only a request timestamp for the throttle window.
 *
 * NOTE — this is a DELIBERATE third-party egress: a user-initiated place/address
 * lookup (or a photo\'s GPS) is sent to nominatim.openstreetmap.org, so it leaves
 * the zero-knowledge boundary. It is never automatic on upload. Requests go
 * through the SSRF guard like every other outbound call; self-host Nominatim or
 * Photon to keep the lookup in-boundary.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 23,
    'endLine' => 86,
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
      'BASE' => 
      array (
        'declaringClassName' => 'App\\Services\\Support\\NominatimClient',
        'implementingClassName' => 'App\\Services\\Support\\NominatimClient',
        'name' => 'BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://nominatim.openstreetmap.org\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 46,
            'startFilePos' => 886,
            'endTokenPos' => 46,
            'endFilePos' => 922,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 63,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
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
            'startLine' => 34,
            'endLine' => 34,
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
            'startLine' => 34,
            'endLine' => 34,
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
        'startLine' => 34,
        'endLine' => 53,
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
        'startLine' => 60,
        'endLine' => 85,
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