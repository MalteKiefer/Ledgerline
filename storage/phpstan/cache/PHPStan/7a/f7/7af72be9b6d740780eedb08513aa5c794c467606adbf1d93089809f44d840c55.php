<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../symfony/http-foundation/Request.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Symfony\Component\HttpFoundation\Request
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-0e55451ce06a6056ac2fa7b6e0c2e2a9ed378f564d75de491ebef4574c39295c-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Symfony\\Component\\HttpFoundation\\Request',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../symfony/http-foundation/Request.php',
      ),
    ),
    'namespace' => 'Symfony\\Component\\HttpFoundation',
    'name' => 'Symfony\\Component\\HttpFoundation\\Request',
    'shortName' => 'Request',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Request represents an HTTP request.
 *
 * The methods dealing with URL accept / return a raw path (% encoded):
 *   * getBasePath
 *   * getBaseUrl
 *   * getPathInfo
 *   * getRequestUri
 *   * getUri
 *   * getUriForPath
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 43,
    'endLine' => 2269,
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
      'HEADER_FORWARDED' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_FORWARDED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b1',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 113,
            'startFilePos' => 1326,
            'endTokenPos' => 113,
            'endFilePos' => 1333,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 45,
      ),
      'HEADER_X_FORWARDED_FOR' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_FOR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b10',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 126,
            'startFilePos' => 1401,
            'endTokenPos' => 126,
            'endFilePos' => 1408,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
      'HEADER_X_FORWARDED_HOST' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_HOST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b100',
          'attributes' => 
          array (
            'startLine' => 47,
            'endLine' => 47,
            'startTokenPos' => 137,
            'startFilePos' => 1454,
            'endTokenPos' => 137,
            'endFilePos' => 1461,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'HEADER_X_FORWARDED_PROTO' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_PROTO',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b1000',
          'attributes' => 
          array (
            'startLine' => 48,
            'endLine' => 48,
            'startTokenPos' => 148,
            'startFilePos' => 1508,
            'endTokenPos' => 148,
            'endFilePos' => 1515,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 53,
      ),
      'HEADER_X_FORWARDED_PORT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_PORT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b10000',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 159,
            'startFilePos' => 1561,
            'endTokenPos' => 159,
            'endFilePos' => 1568,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 52,
      ),
      'HEADER_X_FORWARDED_PREFIX' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_PREFIX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b100000',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 170,
            'startFilePos' => 1616,
            'endTokenPos' => 170,
            'endFilePos' => 1623,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 54,
      ),
      'HEADER_X_FORWARDED_AWS_ELB' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_AWS_ELB',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b11010',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 181,
            'startFilePos' => 1673,
            'endTokenPos' => 181,
            'endFilePos' => 1681,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
      'HEADER_X_FORWARDED_TRAEFIK' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'HEADER_X_FORWARDED_TRAEFIK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0b111110',
          'attributes' => 
          array (
            'startLine' => 53,
            'endLine' => 53,
            'startTokenPos' => 194,
            'startFilePos' => 1771,
            'endTokenPos' => 194,
            'endFilePos' => 1779,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 56,
      ),
      'METHOD_HEAD' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_HEAD',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'HEAD\'',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 207,
            'startFilePos' => 1875,
            'endTokenPos' => 207,
            'endFilePos' => 1880,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'METHOD_GET' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_GET',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'GET\'',
          'attributes' => 
          array (
            'startLine' => 56,
            'endLine' => 56,
            'startTokenPos' => 218,
            'startFilePos' => 1913,
            'endTokenPos' => 218,
            'endFilePos' => 1917,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'METHOD_POST' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_POST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'POST\'',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 229,
            'startFilePos' => 1951,
            'endTokenPos' => 229,
            'endFilePos' => 1956,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'METHOD_PUT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_PUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PUT\'',
          'attributes' => 
          array (
            'startLine' => 58,
            'endLine' => 58,
            'startTokenPos' => 240,
            'startFilePos' => 1989,
            'endTokenPos' => 240,
            'endFilePos' => 1993,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 58,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'METHOD_PATCH' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_PATCH',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PATCH\'',
          'attributes' => 
          array (
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 251,
            'startFilePos' => 2028,
            'endTokenPos' => 251,
            'endFilePos' => 2034,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'METHOD_DELETE' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_DELETE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'DELETE\'',
          'attributes' => 
          array (
            'startLine' => 60,
            'endLine' => 60,
            'startTokenPos' => 262,
            'startFilePos' => 2070,
            'endTokenPos' => 262,
            'endFilePos' => 2077,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 60,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 42,
      ),
      'METHOD_PURGE' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_PURGE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'PURGE\'',
          'attributes' => 
          array (
            'startLine' => 61,
            'endLine' => 61,
            'startTokenPos' => 273,
            'startFilePos' => 2112,
            'endTokenPos' => 273,
            'endFilePos' => 2118,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'METHOD_OPTIONS' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_OPTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'OPTIONS\'',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 284,
            'startFilePos' => 2155,
            'endTokenPos' => 284,
            'endFilePos' => 2163,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'METHOD_TRACE' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_TRACE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'TRACE\'',
          'attributes' => 
          array (
            'startLine' => 63,
            'endLine' => 63,
            'startTokenPos' => 295,
            'startFilePos' => 2198,
            'endTokenPos' => 295,
            'endFilePos' => 2204,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'METHOD_CONNECT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_CONNECT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'CONNECT\'',
          'attributes' => 
          array (
            'startLine' => 64,
            'endLine' => 64,
            'startTokenPos' => 306,
            'startFilePos' => 2241,
            'endTokenPos' => 306,
            'endFilePos' => 2249,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 64,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'METHOD_QUERY' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'METHOD_QUERY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'QUERY\'',
          'attributes' => 
          array (
            'startLine' => 65,
            'endLine' => 65,
            'startTokenPos' => 317,
            'startFilePos' => 2284,
            'endTokenPos' => 317,
            'endFilePos' => 2290,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 65,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'FORWARDED_PARAMS' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'FORWARDED_PARAMS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::HEADER_X_FORWARDED_FOR => \'for\', self::HEADER_X_FORWARDED_HOST => \'host\', self::HEADER_X_FORWARDED_PROTO => \'proto\', self::HEADER_X_FORWARDED_PORT => \'host\']',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 72,
            'startTokenPos' => 328,
            'startFilePos' => 2331,
            'endTokenPos' => 366,
            'endFilePos' => 2533,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'TRUSTED_HEADERS' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'TRUSTED_HEADERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[self::HEADER_FORWARDED => \'FORWARDED\', self::HEADER_X_FORWARDED_FOR => \'X_FORWARDED_FOR\', self::HEADER_X_FORWARDED_HOST => \'X_FORWARDED_HOST\', self::HEADER_X_FORWARDED_PROTO => \'X_FORWARDED_PROTO\', self::HEADER_X_FORWARDED_PORT => \'X_FORWARDED_PORT\', self::HEADER_X_FORWARDED_PREFIX => \'X_FORWARDED_PREFIX\']',
          'attributes' => 
          array (
            'startLine' => 83,
            'endLine' => 90,
            'startTokenPos' => 379,
            'startFilePos' => 2874,
            'endTokenPos' => 435,
            'endFilePos' => 3236,
          ),
        ),
        'docComment' => '/**
 * Names for headers that can be trusted when
 * using trusted proxies.
 *
 * The FORWARDED header is the standard as of rfc7239.
 *
 * The other headers are non-standard, but widely used
 * by popular reverse proxies (like Apache mod_proxy or Amazon EC2).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 83,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'STRUCTURED_SUFFIX_FORMATS' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'STRUCTURED_SUFFIX_FORMATS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'json\' => \'json\', \'xml\' => \'xml\', \'xhtml\' => \'html\', \'cbor\' => \'cbor\', \'zip\' => \'zip\', \'ber\' => \'asn1\', \'der\' => \'asn1\', \'tlv\' => \'tlv\', \'wbxml\' => \'xml\', \'yaml\' => \'yaml\']',
          'attributes' => 
          array (
            'startLine' => 101,
            'endLine' => 112,
            'startTokenPos' => 448,
            'startFilePos' => 3643,
            'endTokenPos' => 520,
            'endFilePos' => 3902,
          ),
        ),
        'docComment' => '/**
 * This mapping is used when no exact MIME match is found in $formats.
 *
 * It enables mappings like application/soap+xml -> xml.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6839
 * @see https://datatracker.ietf.org/doc/html/rfc7303
 * @see https://www.iana.org/assignments/media-types/media-types.xhtml
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 101,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'attributes' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'attributes',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\ParameterBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Custom parameters.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 117,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$attributes::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\ParameterBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 118,
            'endLine' => 122,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'request' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'request',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Request body parameters ($_POST).
 *
 * @see getPayload() for portability between content types
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 130,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$request::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 131,
            'endLine' => 135,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'query' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'query',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Query string parameters ($_GET).
 *
 * @var InputBag<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 143,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$query::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 144,
            'endLine' => 148,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'server' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'server',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\ServerBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Server and execution environment parameters ($_SERVER).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 154,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$server::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\ServerBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 155,
            'endLine' => 159,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'files' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'files',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\FileBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Uploaded files ($_FILES).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 165,
        'endLine' => 171,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$files::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\FileBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 166,
            'endLine' => 170,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'cookies' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'cookies',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Cookies ($_COOKIE).
 *
 * @var InputBag<string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 178,
        'endLine' => 184,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$cookies::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 179,
            'endLine' => 183,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'headers' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'headers',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\HeaderBag',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => '/**
 * Headers (taken from the $_SERVER).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 189,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 5,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
          'set' => 
          array (
            'name' => '$headers::set',
            'parameters' => 
            array (
              'value' => 
              array (
                'name' => 'value',
                'default' => NULL,
                'type' => 
                array (
                  'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                  'data' => 
                  array (
                    'name' => 'Symfony\\Component\\HttpFoundation\\HeaderBag',
                    'isIdentifier' => false,
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
                'name' => 'void',
                'isIdentifier' => true,
              ),
            ),
            'attributes' => 
            array (
            ),
            'docComment' => NULL,
            'startLine' => 190,
            'endLine' => 194,
            'startColumn' => 9,
            'endColumn' => 9,
            'couldThrow' => false,
            'isClosure' => false,
            'isGenerator' => false,
            'isVariadic' => false,
            'modifiers' => 0,
            'namespace' => NULL,
            'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
            'aliasName' => NULL,
          ),
        ),
      ),
      'content' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'content',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var string|resource|false|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 200,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'languages' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'languages',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 205,
            'endLine' => 205,
            'startTokenPos' => 849,
            'startFilePos' => 6828,
            'endTokenPos' => 849,
            'endFilePos' => 6831,
          ),
        ),
        'docComment' => '/**
 * @var string[]|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 205,
        'endLine' => 205,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'charsets' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'charsets',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 210,
            'endLine' => 210,
            'startTokenPos' => 863,
            'startFilePos' => 6910,
            'endTokenPos' => 863,
            'endFilePos' => 6913,
          ),
        ),
        'docComment' => '/**
 * @var string[]|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 210,
        'endLine' => 210,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encodings' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'encodings',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 215,
            'endLine' => 215,
            'startTokenPos' => 877,
            'startFilePos' => 6993,
            'endTokenPos' => 877,
            'endFilePos' => 6996,
          ),
        ),
        'docComment' => '/**
 * @var string[]|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 215,
        'endLine' => 215,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'acceptableContentTypes' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'acceptableContentTypes',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 220,
            'endLine' => 220,
            'startTokenPos' => 891,
            'startFilePos' => 7089,
            'endTokenPos' => 891,
            'endFilePos' => 7092,
          ),
        ),
        'docComment' => '/**
 * @var string[]|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 220,
        'endLine' => 220,
        'startColumn' => 5,
        'endColumn' => 52,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pathInfo' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'pathInfo',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 222,
            'endLine' => 222,
            'startTokenPos' => 903,
            'startFilePos' => 7130,
            'endTokenPos' => 903,
            'endFilePos' => 7133,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 222,
        'endLine' => 222,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'requestUri' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'requestUri',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 223,
            'endLine' => 223,
            'startTokenPos' => 915,
            'startFilePos' => 7172,
            'endTokenPos' => 915,
            'endFilePos' => 7175,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 223,
        'endLine' => 223,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'baseUrl' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'baseUrl',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 224,
            'endLine' => 224,
            'startTokenPos' => 927,
            'startFilePos' => 7211,
            'endTokenPos' => 927,
            'endFilePos' => 7214,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 224,
        'endLine' => 224,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'basePath' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'basePath',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 225,
            'endLine' => 225,
            'startTokenPos' => 939,
            'startFilePos' => 7251,
            'endTokenPos' => 939,
            'endFilePos' => 7254,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 225,
        'endLine' => 225,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'method' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'method',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 226,
            'endLine' => 226,
            'startTokenPos' => 951,
            'startFilePos' => 7289,
            'endTokenPos' => 951,
            'endFilePos' => 7292,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 226,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'format' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'format',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 227,
            'endLine' => 227,
            'startTokenPos' => 963,
            'startFilePos' => 7327,
            'endTokenPos' => 963,
            'endFilePos' => 7330,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 227,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'session' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'session',
        'modifiers' => 2,
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
                  'name' => 'Symfony\\Component\\HttpFoundation\\Session\\SessionInterface',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'Closure',
                  'isIdentifier' => false,
                ),
              ),
              2 => 
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 228,
            'endLine' => 228,
            'startTokenPos' => 978,
            'startFilePos' => 7389,
            'endTokenPos' => 978,
            'endFilePos' => 7392,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 228,
        'endLine' => 228,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'locale' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'locale',
        'modifiers' => 2,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 229,
            'endLine' => 229,
            'startTokenPos' => 990,
            'startFilePos' => 7427,
            'endTokenPos' => 990,
            'endFilePos' => 7430,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 229,
        'endLine' => 229,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultLocale' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'defaultLocale',
        'modifiers' => 2,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '\'en\'',
          'attributes' => 
          array (
            'startLine' => 230,
            'endLine' => 230,
            'startTokenPos' => 1001,
            'startFilePos' => 7471,
            'endTokenPos' => 1001,
            'endFilePos' => 7474,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 230,
        'endLine' => 230,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'formats' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'formats',
        'modifiers' => 18,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 235,
            'endLine' => 235,
            'startTokenPos' => 1017,
            'startFilePos' => 7574,
            'endTokenPos' => 1017,
            'endFilePos' => 7577,
          ),
        ),
        'docComment' => '/**
 * @var array<string, string[]>|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 235,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'trustedProxies' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'trustedProxies',
        'modifiers' => 18,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 240,
            'endLine' => 240,
            'startTokenPos' => 1032,
            'startFilePos' => 7663,
            'endTokenPos' => 1033,
            'endFilePos' => 7664,
          ),
        ),
        'docComment' => '/**
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 240,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'trustedHostPatterns' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'trustedHostPatterns',
        'modifiers' => 18,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 245,
            'endLine' => 245,
            'startTokenPos' => 1048,
            'startFilePos' => 7755,
            'endTokenPos' => 1049,
            'endFilePos' => 7756,
          ),
        ),
        'docComment' => '/**
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 245,
        'endLine' => 245,
        'startColumn' => 5,
        'endColumn' => 53,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'trustedHosts' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'trustedHosts',
        'modifiers' => 18,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 250,
            'endLine' => 250,
            'startTokenPos' => 1064,
            'startFilePos' => 7840,
            'endTokenPos' => 1065,
            'endFilePos' => 7841,
          ),
        ),
        'docComment' => '/**
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 250,
        'endLine' => 250,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'httpMethodParameterOverride' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'httpMethodParameterOverride',
        'modifiers' => 18,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 252,
            'endLine' => 252,
            'startTokenPos' => 1078,
            'startFilePos' => 7902,
            'endTokenPos' => 1078,
            'endFilePos' => 7906,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 252,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 63,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedHttpMethodOverride' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'allowedHttpMethodOverride',
        'modifiers' => 18,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 259,
            'endLine' => 259,
            'startTokenPos' => 1094,
            'startFilePos' => 8074,
            'endTokenPos' => 1094,
            'endFilePos' => 8077,
          ),
        ),
        'docComment' => '/**
 * The HTTP methods that can be overridden.
 *
 * @var uppercase-string[]|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 259,
        'endLine' => 259,
        'startColumn' => 5,
        'endColumn' => 62,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'requestFactory' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'requestFactory',
        'modifiers' => 18,
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
                  'name' => 'Closure',
                  'isIdentifier' => false,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 261,
            'endLine' => 261,
            'startTokenPos' => 1108,
            'startFilePos' => 8130,
            'endTokenPos' => 1108,
            'endFilePos' => 8133,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 261,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 54,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preferredFormat' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'preferredFormat',
        'modifiers' => 4,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 263,
            'endLine' => 263,
            'startTokenPos' => 1120,
            'startFilePos' => 8176,
            'endTokenPos' => 1120,
            'endFilePos' => 8179,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 263,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isHostValid' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'isHostValid',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 265,
            'endLine' => 265,
            'startTokenPos' => 1131,
            'startFilePos' => 8215,
            'endTokenPos' => 1131,
            'endFilePos' => 8218,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 265,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isForwardedValid' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'isForwardedValid',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 266,
            'endLine' => 266,
            'startTokenPos' => 1142,
            'startFilePos' => 8258,
            'endTokenPos' => 1142,
            'endFilePos' => 8261,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 266,
        'endLine' => 266,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isSafeContentPreferred' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'isSafeContentPreferred',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 267,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'trustedValuesCache' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'trustedValuesCache',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 269,
            'endLine' => 269,
            'startTokenPos' => 1160,
            'startFilePos' => 8347,
            'endTokenPos' => 1161,
            'endFilePos' => 8348,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 269,
        'endLine' => 269,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'trustedHeaderSet' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'trustedHeaderSet',
        'modifiers' => 20,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 271,
            'endLine' => 271,
            'startTokenPos' => 1174,
            'startFilePos' => 8395,
            'endTokenPos' => 1175,
            'endFilePos' => 8396,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 271,
        'endLine' => 271,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isIisRewrite' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'name' => 'isIisRewrite',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 273,
            'endLine' => 273,
            'startTokenPos' => 1186,
            'startFilePos' => 8433,
            'endTokenPos' => 1186,
            'endFilePos' => 8437,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 273,
        'endLine' => 273,
        'startColumn' => 5,
        'endColumn' => 39,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1203,
                'startFilePos' => 9022,
                'endTokenPos' => 1204,
                'endFilePos' => 9023,
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
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1213,
                'startFilePos' => 9043,
                'endTokenPos' => 1214,
                'endFilePos' => 9044,
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
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 52,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1223,
                'startFilePos' => 9067,
                'endTokenPos' => 1224,
                'endFilePos' => 9068,
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
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 73,
            'endColumn' => 94,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cookies' => 
          array (
            'name' => 'cookies',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1233,
                'startFilePos' => 9088,
                'endTokenPos' => 1234,
                'endFilePos' => 9089,
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
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 97,
            'endColumn' => 115,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1243,
                'startFilePos' => 9107,
                'endTokenPos' => 1244,
                'endFilePos' => 9108,
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
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 118,
            'endColumn' => 134,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'server' => 
          array (
            'name' => 'server',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1253,
                'startFilePos' => 9127,
                'endTokenPos' => 1254,
                'endFilePos' => 9128,
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
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 137,
            'endColumn' => 154,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'content' => 
          array (
            'name' => 'content',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 1261,
                'startFilePos' => 9142,
                'endTokenPos' => 1261,
                'endFilePos' => 9145,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 284,
            'endLine' => 284,
            'startColumn' => 157,
            'endColumn' => 171,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array                $query      The GET parameters
 * @param array                $request    The POST parameters
 * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
 * @param array                $cookies    The COOKIE parameters
 * @param array                $files      The FILES parameters
 * @param array                $server     The SERVER parameters
 * @param string|resource|null $content    The raw body data
 */',
        'startLine' => 284,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'initialize' => 
      array (
        'name' => 'initialize',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1308,
                'startFilePos' => 9950,
                'endTokenPos' => 1309,
                'endFilePos' => 9951,
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 32,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1318,
                'startFilePos' => 9971,
                'endTokenPos' => 1319,
                'endFilePos' => 9972,
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 51,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1328,
                'startFilePos' => 9995,
                'endTokenPos' => 1329,
                'endFilePos' => 9996,
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 72,
            'endColumn' => 93,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cookies' => 
          array (
            'name' => 'cookies',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1338,
                'startFilePos' => 10016,
                'endTokenPos' => 1339,
                'endFilePos' => 10017,
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 96,
            'endColumn' => 114,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1348,
                'startFilePos' => 10035,
                'endTokenPos' => 1349,
                'endFilePos' => 10036,
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 117,
            'endColumn' => 133,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'server' => 
          array (
            'name' => 'server',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1358,
                'startFilePos' => 10055,
                'endTokenPos' => 1359,
                'endFilePos' => 10056,
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 136,
            'endColumn' => 153,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'content' => 
          array (
            'name' => 'content',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 302,
                'endLine' => 302,
                'startTokenPos' => 1366,
                'startFilePos' => 10070,
                'endTokenPos' => 1366,
                'endFilePos' => 10073,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 156,
            'endColumn' => 170,
            'parameterIndex' => 6,
            'isOptional' => true,
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
 * Sets the parameters for this request.
 *
 * This method also re-initializes all properties.
 *
 * @param array                $query      The GET parameters
 * @param array                $request    The POST parameters
 * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
 * @param array                $cookies    The COOKIE parameters
 * @param array                $files      The FILES parameters
 * @param array                $server     The SERVER parameters
 * @param string|resource|null $content    The raw body data
 */',
        'startLine' => 302,
        'endLine' => 323,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'createFromGlobals' => 
      array (
        'name' => 'createFromGlobals',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a new request with values from PHP\'s super globals.
 */',
        'startLine' => 328,
        'endLine' => 342,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'create' => 
      array (
        'name' => 'create',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => 
            array (
              'code' => '\'GET\'',
              'attributes' => 
              array (
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 1788,
                'startFilePos' => 12467,
                'endTokenPos' => 1788,
                'endFilePos' => 12471,
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 48,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 1797,
                'startFilePos' => 12494,
                'endTokenPos' => 1798,
                'endFilePos' => 12495,
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 72,
            'endColumn' => 93,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cookies' => 
          array (
            'name' => 'cookies',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 1807,
                'startFilePos' => 12515,
                'endTokenPos' => 1808,
                'endFilePos' => 12516,
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 96,
            'endColumn' => 114,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 1817,
                'startFilePos' => 12534,
                'endTokenPos' => 1818,
                'endFilePos' => 12535,
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 117,
            'endColumn' => 133,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'server' => 
          array (
            'name' => 'server',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 1827,
                'startFilePos' => 12554,
                'endTokenPos' => 1828,
                'endFilePos' => 12555,
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
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 136,
            'endColumn' => 153,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'content' => 
          array (
            'name' => 'content',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 360,
                'endLine' => 360,
                'startTokenPos' => 1835,
                'startFilePos' => 12569,
                'endTokenPos' => 1835,
                'endFilePos' => 12572,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 360,
            'endLine' => 360,
            'startColumn' => 156,
            'endColumn' => 170,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a Request based on a given URI and configuration.
 *
 * The information contained in the URI always take precedence
 * over the other information (server and parameters).
 *
 * @param string               $uri        The URI
 * @param string               $method     The HTTP method
 * @param array                $parameters The query (GET) or request (POST) parameters
 * @param array                $cookies    The request cookies ($_COOKIE)
 * @param array                $files      The request files ($_FILES)
 * @param array                $server     The server parameters ($_SERVER)
 * @param string|resource|null $content    The raw body data
 *
 * @throws BadRequestException When the URI is invalid
 */',
        'startLine' => 360,
        'endLine' => 484,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setFactory' => 
      array (
        'name' => 'setFactory',
        'parameters' => 
        array (
          'callable' => 
          array (
            'name' => 'callable',
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
                      'name' => 'callable',
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
            'startLine' => 493,
            'endLine' => 493,
            'startColumn' => 39,
            'endColumn' => 57,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets a callable able to create a Request instance.
 *
 * This is mainly useful when you need to override the Request class
 * to keep BC with an existing system. It should not be used for any
 * other purpose.
 */',
        'startLine' => 493,
        'endLine' => 496,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'duplicate' => 
      array (
        'name' => 'duplicate',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 508,
                'endLine' => 508,
                'startTokenPos' => 3085,
                'startFilePos' => 18458,
                'endTokenPos' => 3085,
                'endFilePos' => 18461,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 31,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 508,
                'endLine' => 508,
                'startTokenPos' => 3095,
                'startFilePos' => 18482,
                'endTokenPos' => 3095,
                'endFilePos' => 18485,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 53,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 508,
                'endLine' => 508,
                'startTokenPos' => 3105,
                'startFilePos' => 18509,
                'endTokenPos' => 3105,
                'endFilePos' => 18512,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 77,
            'endColumn' => 101,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cookies' => 
          array (
            'name' => 'cookies',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 508,
                'endLine' => 508,
                'startTokenPos' => 3115,
                'startFilePos' => 18533,
                'endTokenPos' => 3115,
                'endFilePos' => 18536,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 104,
            'endColumn' => 125,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 508,
                'endLine' => 508,
                'startTokenPos' => 3125,
                'startFilePos' => 18555,
                'endTokenPos' => 3125,
                'endFilePos' => 18558,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 128,
            'endColumn' => 147,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'server' => 
          array (
            'name' => 'server',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 508,
                'endLine' => 508,
                'startTokenPos' => 3135,
                'startFilePos' => 18578,
                'endTokenPos' => 3135,
                'endFilePos' => 18581,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 150,
            'endColumn' => 170,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clones a request and overrides some of its parameters.
 *
 * @param array|null $query      The GET parameters
 * @param array|null $request    The POST parameters
 * @param array|null $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
 * @param array|null $cookies    The COOKIE parameters
 * @param array|null $files      The FILES parameters
 * @param array|null $server     The SERVER parameters
 */',
        'startLine' => 508,
        'endLine' => 550,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      '__clone' => 
      array (
        'name' => '__clone',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clones the current request.
 *
 * Note that the session is not cloned as duplicated requests
 * are most of the time sub-requests of the main one.
 */',
        'startLine' => 558,
        'endLine' => 567,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      '__toString' => 
      array (
        'name' => '__toString',
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
        'startLine' => 569,
        'endLine' => 589,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'overrideGlobals' => 
      array (
        'name' => 'overrideGlobals',
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
 * Overrides the PHP global variables according to this request instance.
 *
 * It overrides $_GET, $_POST, $_REQUEST, $_SERVER, $_COOKIE.
 * $_FILES is never overridden, see rfc1867
 */',
        'startLine' => 597,
        'endLine' => 627,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setTrustedProxies' => 
      array (
        'name' => 'setTrustedProxies',
        'parameters' => 
        array (
          'proxies' => 
          array (
            'name' => 'proxies',
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
            'startLine' => 637,
            'endLine' => 637,
            'startColumn' => 46,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'trustedHeaderSet' => 
          array (
            'name' => 'trustedHeaderSet',
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
            'startLine' => 637,
            'endLine' => 637,
            'startColumn' => 62,
            'endColumn' => 82,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets a list of trusted proxies.
 *
 * You should only list the reverse proxies that you manage directly.
 *
 * @param array                          $proxies          A list of trusted proxies, the string \'REMOTE_ADDR\' will be replaced with $_SERVER[\'REMOTE_ADDR\'] and \'PRIVATE_SUBNETS\' by IpUtils::PRIVATE_SUBNETS
 * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet A bit field to set which headers to trust from your proxies
 */',
        'startLine' => 637,
        'endLine' => 655,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getTrustedProxies' => 
      array (
        'name' => 'getTrustedProxies',
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
 * Gets the list of trusted proxies.
 *
 * @return string[]
 */',
        'startLine' => 662,
        'endLine' => 665,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getTrustedHeaderSet' => 
      array (
        'name' => 'getTrustedHeaderSet',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the set of trusted headers from trusted proxies.
 *
 * @return int A bit field of Request::HEADER_* that defines which headers are trusted from your proxies
 */',
        'startLine' => 672,
        'endLine' => 675,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setTrustedHosts' => 
      array (
        'name' => 'setTrustedHosts',
        'parameters' => 
        array (
          'hostPatterns' => 
          array (
            'name' => 'hostPatterns',
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
            'startLine' => 684,
            'endLine' => 684,
            'startColumn' => 44,
            'endColumn' => 62,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets a list of trusted host patterns.
 *
 * You should only list the hosts you manage using regexs.
 *
 * @param array $hostPatterns A list of trusted host patterns
 */',
        'startLine' => 684,
        'endLine' => 689,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getTrustedHosts' => 
      array (
        'name' => 'getTrustedHosts',
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
 * Gets the list of trusted host patterns.
 *
 * @return string[]
 */',
        'startLine' => 696,
        'endLine' => 699,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'normalizeQueryString' => 
      array (
        'name' => 'normalizeQueryString',
        'parameters' => 
        array (
          'qs' => 
          array (
            'name' => 'qs',
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
            'startLine' => 707,
            'endLine' => 707,
            'startColumn' => 49,
            'endColumn' => 59,
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
 * Normalizes a query string.
 *
 * It builds a normalized query string, where keys/value pairs are alphabetized,
 * have consistent escaping and unneeded delimiters are removed.
 */',
        'startLine' => 707,
        'endLine' => 717,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'enableHttpMethodParameterOverride' => 
      array (
        'name' => 'enableHttpMethodParameterOverride',
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
 * Enables support for the _method request parameter to determine the intended HTTP method.
 *
 * Be warned that enabling this feature might lead to CSRF issues in your code.
 * Check that you are using CSRF tokens when required.
 * If the HTTP method parameter override is enabled, an html-form with method "POST" can be altered
 * and used to send a "PUT" or "DELETE" request via the _method request parameter.
 * If these methods are not protected against CSRF, this presents a possible vulnerability.
 *
 * The HTTP method can only be overridden when the real HTTP method is POST.
 */',
        'startLine' => 730,
        'endLine' => 733,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getHttpMethodParameterOverride' => 
      array (
        'name' => 'getHttpMethodParameterOverride',
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
 * Checks whether support for the _method request parameter is enabled.
 */',
        'startLine' => 738,
        'endLine' => 741,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setAllowedHttpMethodOverride' => 
      array (
        'name' => 'setAllowedHttpMethodOverride',
        'parameters' => 
        array (
          'methods' => 
          array (
            'name' => 'methods',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 752,
            'endLine' => 752,
            'startColumn' => 57,
            'endColumn' => 71,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the list of HTTP methods that can be overridden.
 *
 * Set to null to allow all methods to be overridden (default). Set to an
 * empty array to disallow overrides entirely. Otherwise, provide the list
 * of uppercased method names that are allowed.
 *
 * @param uppercase-string[]|null $methods
 */',
        'startLine' => 752,
        'endLine' => 759,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getAllowedHttpMethodOverride' => 
      array (
        'name' => 'getAllowedHttpMethodOverride',
        'parameters' => 
        array (
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
 * Gets the list of HTTP methods that can be overridden.
 *
 * @return uppercase-string[]|null
 */',
        'startLine' => 766,
        'endLine' => 769,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getSession' => 
      array (
        'name' => 'getSession',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\Session\\SessionInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the Session.
 *
 * @throws SessionNotFoundException When session is not set properly
 */',
        'startLine' => 776,
        'endLine' => 788,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'hasPreviousSession' => 
      array (
        'name' => 'hasPreviousSession',
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
 * Whether the request contains a Session which was started in one of the
 * previous requests.
 */',
        'startLine' => 794,
        'endLine' => 798,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'hasSession' => 
      array (
        'name' => 'hasSession',
        'parameters' => 
        array (
          'skipIfUninitialized' => 
          array (
            'name' => 'skipIfUninitialized',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 809,
                'endLine' => 809,
                'startTokenPos' => 4934,
                'startFilePos' => 29028,
                'endTokenPos' => 4934,
                'endFilePos' => 29032,
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
            'startLine' => 809,
            'endLine' => 809,
            'startColumn' => 32,
            'endColumn' => 64,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Whether the request contains a Session object.
 *
 * This method does not give any information about the state of the session object,
 * like whether the session is started or not. It is just a way to check if this Request
 * is associated with a Session instance.
 *
 * @param bool $skipIfUninitialized When true, ignores factories injected by `setSessionFactory`
 */',
        'startLine' => 809,
        'endLine' => 812,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setSession' => 
      array (
        'name' => 'setSession',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\HttpFoundation\\Session\\SessionInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 814,
            'endLine' => 814,
            'startColumn' => 32,
            'endColumn' => 56,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 814,
        'endLine' => 817,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setSessionFactory' => 
      array (
        'name' => 'setSessionFactory',
        'parameters' => 
        array (
          'factory' => 
          array (
            'name' => 'factory',
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
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 39,
            'endColumn' => 55,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @internal
 *
 * @param callable(): SessionInterface $factory
 */',
        'startLine' => 824,
        'endLine' => 827,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getClientIps' => 
      array (
        'name' => 'getClientIps',
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
 * Returns the client IP addresses.
 *
 * In the returned array the most trusted IP address is first, and the
 * least trusted one last. The "real" client IP address is the last one,
 * but this is also the least trusted one. Trusted proxies are stripped.
 *
 * Use this method carefully; you should use getClientIp() instead.
 *
 * @see getClientIp()
 */',
        'startLine' => 840,
        'endLine' => 849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getClientIp' => 
      array (
        'name' => 'getClientIp',
        'parameters' => 
        array (
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
 * Returns the client IP address.
 *
 * This method can read the client IP address from the "X-Forwarded-For" header
 * when trusted proxies were set via "setTrustedProxies()". The "X-Forwarded-For"
 * header value is a comma+space separated list of IP addresses, the left-most
 * being the original client, and each successive proxy that passed the request
 * adding the IP address where it received the request from.
 *
 * @see getClientIps()
 * @see https://wikipedia.org/wiki/X-Forwarded-For
 */',
        'startLine' => 863,
        'endLine' => 866,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getScriptName' => 
      array (
        'name' => 'getScriptName',
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
 * Returns current script name.
 */',
        'startLine' => 871,
        'endLine' => 874,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getPathInfo' => 
      array (
        'name' => 'getPathInfo',
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
 * Returns the path being requested relative to the executed script.
 *
 * The path info always starts with a /.
 *
 * Suppose this request is instantiated from /mysite on localhost:
 *
 *  * http://localhost/mysite              returns \'/\'
 *  * http://localhost/mysite/about        returns \'/about\'
 *  * http://localhost/mysite/enco%20ded   returns \'/enco%20ded\'
 *  * http://localhost/mysite/about?var=1  returns \'/about\'
 *
 * @return string The raw path (i.e. not urldecoded)
 */',
        'startLine' => 890,
        'endLine' => 893,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getBasePath' => 
      array (
        'name' => 'getBasePath',
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
 * Returns the root path from which this request is executed.
 *
 * Suppose that an index.php file instantiates this request object:
 *
 *  * http://localhost/index.php         returns an empty string
 *  * http://localhost/index.php/page    returns an empty string
 *  * http://localhost/web/index.php     returns \'/web\'
 *  * http://localhost/we%20b/index.php  returns \'/we%20b\'
 *
 * @return string The raw path (i.e. not urldecoded)
 */',
        'startLine' => 907,
        'endLine' => 910,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getBaseUrl' => 
      array (
        'name' => 'getBaseUrl',
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
 * Returns the root URL from which this request is executed.
 *
 * The base URL never ends with a /.
 *
 * This is similar to getBasePath(), except that it also includes the
 * script filename (e.g. index.php) if one exists.
 *
 * @return string The raw URL (i.e. not urldecoded)
 */',
        'startLine' => 922,
        'endLine' => 932,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getBaseUrlReal' => 
      array (
        'name' => 'getBaseUrlReal',
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
 * Returns the real base URL received by the webserver from which this request is executed.
 * The URL does not include trusted reverse proxy prefix.
 *
 * @return string The raw URL (i.e. not urldecoded)
 */',
        'startLine' => 940,
        'endLine' => 943,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getScheme' => 
      array (
        'name' => 'getScheme',
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
 * Gets the request\'s scheme.
 */',
        'startLine' => 948,
        'endLine' => 951,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getPort' => 
      array (
        'name' => 'getPort',
        'parameters' => 
        array (
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
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              2 => 
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
 * Returns the port on which the request is made.
 *
 * This method can read the client port from the "X-Forwarded-Port" header
 * when trusted proxies were set via "setTrustedProxies()".
 *
 * The "X-Forwarded-Port" header must contain the client port.
 *
 * @return int|string|null Can be a string if fetched from the server bag
 */',
        'startLine' => 963,
        'endLine' => 984,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getUser' => 
      array (
        'name' => 'getUser',
        'parameters' => 
        array (
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
 * Returns the user.
 */',
        'startLine' => 989,
        'endLine' => 992,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getPassword' => 
      array (
        'name' => 'getPassword',
        'parameters' => 
        array (
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
 * Returns the password.
 */',
        'startLine' => 997,
        'endLine' => 1000,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getUserInfo' => 
      array (
        'name' => 'getUserInfo',
        'parameters' => 
        array (
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
 * Gets the user info.
 *
 * @return string|null A user name if any and, optionally, scheme-specific information about how to gain authorization to access the server
 */',
        'startLine' => 1007,
        'endLine' => 1017,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getHttpHost' => 
      array (
        'name' => 'getHttpHost',
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
 * Returns the HTTP host being requested.
 *
 * The port name will be appended to the host if it\'s non-standard.
 */',
        'startLine' => 1024,
        'endLine' => 1034,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getRequestUri' => 
      array (
        'name' => 'getRequestUri',
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
 * Returns the requested URI (path and query string).
 *
 * @return string The raw URI (i.e. not URI decoded)
 */',
        'startLine' => 1041,
        'endLine' => 1044,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getSchemeAndHttpHost' => 
      array (
        'name' => 'getSchemeAndHttpHost',
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
 * Gets the scheme and HTTP host.
 *
 * If the URL was called with basic authentication, the user
 * and the password are not added to the generated string.
 */',
        'startLine' => 1052,
        'endLine' => 1055,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getUri' => 
      array (
        'name' => 'getUri',
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
 * Generates a normalized URI (URL) for the Request.
 *
 * @see getQueryString()
 */',
        'startLine' => 1062,
        'endLine' => 1069,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getUriForPath' => 
      array (
        'name' => 'getUriForPath',
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
            'startLine' => 1076,
            'endLine' => 1076,
            'startColumn' => 35,
            'endColumn' => 46,
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
 * Generates a normalized URI for the given path.
 *
 * @param string $path A path to use instead of the current one
 */',
        'startLine' => 1076,
        'endLine' => 1079,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getRelativeUriForPath' => 
      array (
        'name' => 'getRelativeUriForPath',
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
            'startLine' => 1096,
            'endLine' => 1096,
            'startColumn' => 43,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the path as relative reference from the current Request path.
 *
 * Only the URIs path component (no schema, host etc.) is relevant and must be given.
 * Both paths must be absolute and not contain relative parts.
 * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
 * Furthermore, they can be used to reduce the link size in documents.
 *
 * Example target paths, given a base path of "/a/b/c/d":
 * - "/a/b/c/d"     -> ""
 * - "/a/b/c/"      -> "./"
 * - "/a/b/"        -> "../"
 * - "/a/b/c/other" -> "other"
 * - "/a/x/y"       -> "../../x/y"
 */',
        'startLine' => 1096,
        'endLine' => 1130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getQueryString' => 
      array (
        'name' => 'getQueryString',
        'parameters' => 
        array (
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
 * Generates the normalized query string for the Request.
 *
 * It builds a normalized query string, where keys/value pairs are alphabetized
 * and have consistent escaping.
 */',
        'startLine' => 1138,
        'endLine' => 1143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isSecure' => 
      array (
        'name' => 'isSecure',
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
 * Checks whether the request is secure or not.
 *
 * This method can read the client protocol from the "X-Forwarded-Proto" header
 * when trusted proxies were set via "setTrustedProxies()".
 *
 * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
 */',
        'startLine' => 1153,
        'endLine' => 1162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getHost' => 
      array (
        'name' => 'getHost',
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
 * Returns the host name.
 *
 * This method can read the client host name from the "X-Forwarded-Host" header
 * when trusted proxies were set via "setTrustedProxies()".
 *
 * The "X-Forwarded-Host" header must contain the client host name.
 *
 * @throws SuspiciousOperationException when the host name is invalid or not trusted
 */',
        'startLine' => 1174,
        'endLine' => 1220,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setMethod' => 
      array (
        'name' => 'setMethod',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
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
            'startLine' => 1225,
            'endLine' => 1225,
            'startColumn' => 31,
            'endColumn' => 44,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the request method.
 */',
        'startLine' => 1225,
        'endLine' => 1229,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getMethod' => 
      array (
        'name' => 'getMethod',
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
 * Gets the request "intended" method.
 *
 * If the X-HTTP-Method-Override header is set, and if the method is a POST,
 * then it is used to determine the "real" intended HTTP method.
 *
 * The _method request parameter can also be used to determine the HTTP method,
 * but only if enableHttpMethodParameterOverride() has been called.
 *
 * The method is always an uppercased string.
 *
 * @see getRealMethod()
 */',
        'startLine' => 1244,
        'endLine' => 1281,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getRealMethod' => 
      array (
        'name' => 'getRealMethod',
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
 * Gets the "real" request method.
 *
 * @see getMethod()
 */',
        'startLine' => 1288,
        'endLine' => 1291,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getMimeType' => 
      array (
        'name' => 'getMimeType',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
            'startLine' => 1296,
            'endLine' => 1296,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Gets the mime type associated with the format.
 */',
        'startLine' => 1296,
        'endLine' => 1303,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getMimeTypes' => 
      array (
        'name' => 'getMimeTypes',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
            'startLine' => 1310,
            'endLine' => 1310,
            'startColumn' => 41,
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
 * Gets the mime types associated with the format.
 *
 * @return string[]
 */',
        'startLine' => 1310,
        'endLine' => 1317,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getFormat' => 
      array (
        'name' => 'getFormat',
        'parameters' => 
        array (
          'mimeType' => 
          array (
            'name' => 'mimeType',
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
            'startLine' => 1334,
            'endLine' => 1334,
            'startColumn' => 31,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'subtypeFallback' => 
          array (
            'name' => 'subtypeFallback',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1334,
                'endLine' => 1334,
                'startTokenPos' => 7443,
                'startFilePos' => 46091,
                'endTokenPos' => 7443,
                'endFilePos' => 46095,
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
            'startLine' => 1334,
            'endLine' => 1334,
            'startColumn' => 50,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => true,
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
 * Gets the format associated with the mime type.
 *
 *  Resolution order:
 *   1) Exact match on the full MIME type (e.g. "application/json").
 *   2) Match on the canonical MIME type (i.e. before the first ";" parameter).
 *   3) If the type is "application/*+suffix", use the structured syntax suffix
 *      mapping (e.g. "application/foo+json" → "json"), when available.
 *   4) If $subtypeFallback is true and no match was found:
 *      - return the MIME subtype (without "x-" prefix), provided it does not
 *        contain a "+" (e.g. "application/x-yaml" → "yaml", "text/csv" → "csv").
 *
 * @param string|null $mimeType        The mime type to check
 * @param bool        $subtypeFallback Whether to fall back to the subtype if no exact match is found
 */',
        'startLine' => 1334,
        'endLine' => 1383,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setFormat' => 
      array (
        'name' => 'setFormat',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
            'startLine' => 1390,
            'endLine' => 1390,
            'startColumn' => 31,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mimeTypes' => 
          array (
            'name' => 'mimeTypes',
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
                      'name' => 'array',
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
            'startLine' => 1390,
            'endLine' => 1390,
            'startColumn' => 47,
            'endColumn' => 69,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Associates a format with mime types.
 *
 * @param string|string[] $mimeTypes The associated mime types (the preferred one must be the first as it will be used as the content type)
 */',
        'startLine' => 1390,
        'endLine' => 1397,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getRequestFormat' => 
      array (
        'name' => 'getRequestFormat',
        'parameters' => 
        array (
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => '\'html\'',
              'attributes' => 
              array (
                'startLine' => 1410,
                'endLine' => 1410,
                'startTokenPos' => 7916,
                'startFilePos' => 48465,
                'endTokenPos' => 7916,
                'endFilePos' => 48470,
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
            'startLine' => 1410,
            'endLine' => 1410,
            'startColumn' => 38,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * Gets the request format.
 *
 * Here is the process to determine the format:
 *
 *  * format defined by the user (with setRequestFormat())
 *  * _format request attribute
 *  * $default
 *
 * @see getPreferredFormat
 */',
        'startLine' => 1410,
        'endLine' => 1415,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setRequestFormat' => 
      array (
        'name' => 'setRequestFormat',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
            'startLine' => 1420,
            'endLine' => 1420,
            'startColumn' => 38,
            'endColumn' => 52,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the request format.
 */',
        'startLine' => 1420,
        'endLine' => 1423,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getContentTypeFormat' => 
      array (
        'name' => 'getContentTypeFormat',
        'parameters' => 
        array (
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
 * Gets the usual name of the format associated with the request\'s media type (provided in the Content-Type header).
 *
 * @see Request::$formats
 */',
        'startLine' => 1430,
        'endLine' => 1433,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setDefaultLocale' => 
      array (
        'name' => 'setDefaultLocale',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
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
            'startLine' => 1438,
            'endLine' => 1438,
            'startColumn' => 38,
            'endColumn' => 51,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the default locale.
 */',
        'startLine' => 1438,
        'endLine' => 1445,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getDefaultLocale' => 
      array (
        'name' => 'getDefaultLocale',
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
 * Get the default locale.
 */',
        'startLine' => 1450,
        'endLine' => 1453,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setLocale' => 
      array (
        'name' => 'setLocale',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
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
            'startLine' => 1458,
            'endLine' => 1458,
            'startColumn' => 31,
            'endColumn' => 44,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the locale.
 */',
        'startLine' => 1458,
        'endLine' => 1461,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getLocale' => 
      array (
        'name' => 'getLocale',
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
 * Get the locale.
 */',
        'startLine' => 1466,
        'endLine' => 1469,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isMethod' => 
      array (
        'name' => 'isMethod',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
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
            'startLine' => 1476,
            'endLine' => 1476,
            'startColumn' => 30,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Checks if the request method is of specified type.
 *
 * @param string $method Uppercase request method (GET, POST etc)
 */',
        'startLine' => 1476,
        'endLine' => 1479,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isMethodSafe' => 
      array (
        'name' => 'isMethodSafe',
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
 * Checks whether or not the method is safe.
 *
 * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
 */',
        'startLine' => 1486,
        'endLine' => 1489,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isMethodIdempotent' => 
      array (
        'name' => 'isMethodIdempotent',
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
 * Checks whether or not the method is idempotent.
 */',
        'startLine' => 1494,
        'endLine' => 1497,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isMethodCacheable' => 
      array (
        'name' => 'isMethodCacheable',
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
 * Checks whether the method is cacheable or not.
 *
 * @see https://tools.ietf.org/html/rfc7231#section-4.2.3
 */',
        'startLine' => 1504,
        'endLine' => 1507,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getProtocolVersion' => 
      array (
        'name' => 'getProtocolVersion',
        'parameters' => 
        array (
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
 * Returns the protocol version.
 *
 * If the application is behind a proxy, the protocol version used in the
 * requests between the client and the proxy and between the proxy and the
 * server might be different. This returns the former (from the "Via" header)
 * if the proxy is trusted (see "setTrustedProxies()"), otherwise it returns
 * the latter (from the "SERVER_PROTOCOL" server parameter).
 */',
        'startLine' => 1518,
        'endLine' => 1529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getContent' => 
      array (
        'name' => 'getContent',
        'parameters' => 
        array (
          'asResource' => 
          array (
            'name' => 'asResource',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1540,
                'endLine' => 1540,
                'startTokenPos' => 8450,
                'startFilePos' => 51963,
                'endTokenPos' => 8450,
                'endFilePos' => 51967,
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
            'startLine' => 1540,
            'endLine' => 1540,
            'startColumn' => 32,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the request body content.
 *
 * @param bool $asResource If true, a resource will be returned
 *
 * @return string|resource
 *
 * @psalm-return ($asResource is true ? resource : string)
 */',
        'startLine' => 1540,
        'endLine' => 1574,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getPayload' => 
      array (
        'name' => 'getPayload',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\HttpFoundation\\InputBag',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the decoded form or json request body.
 *
 * @throws JsonException When the body cannot be decoded to an array
 */',
        'startLine' => 1581,
        'endLine' => 1602,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'toArray' => 
      array (
        'name' => 'toArray',
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
 * Gets the request body decoded as array, typically from a JSON payload.
 *
 * @see getPayload() for portability between content types
 *
 * @throws JsonException When the body cannot be decoded to an array
 */',
        'startLine' => 1611,
        'endLine' => 1628,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getETags' => 
      array (
        'name' => 'getETags',
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
 * Gets the Etags.
 */',
        'startLine' => 1633,
        'endLine' => 1636,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isNoCache' => 
      array (
        'name' => 'isNoCache',
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
        'docComment' => NULL,
        'startLine' => 1638,
        'endLine' => 1641,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getPreferredFormat' => 
      array (
        'name' => 'getPreferredFormat',
        'parameters' => 
        array (
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => '\'html\'',
              'attributes' => 
              array (
                'startLine' => 1651,
                'endLine' => 1651,
                'startTokenPos' => 9076,
                'startFilePos' => 55423,
                'endTokenPos' => 9076,
                'endFilePos' => 55428,
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
            'startLine' => 1651,
            'endLine' => 1651,
            'startColumn' => 40,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * Gets the preferred format for the response by inspecting, in the following order:
 *   * the request format set using setRequestFormat;
 *   * the values of the Accept HTTP header.
 *
 * Note that if you use this method, you should send the "Vary: Accept" header
 * in the response to prevent any issues with intermediary HTTP caches.
 */',
        'startLine' => 1651,
        'endLine' => 1668,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getPreferredLanguage' => 
      array (
        'name' => 'getPreferredLanguage',
        'parameters' => 
        array (
          'locales' => 
          array (
            'name' => 'locales',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1675,
                'endLine' => 1675,
                'startTokenPos' => 9218,
                'startFilePos' => 56154,
                'endTokenPos' => 9218,
                'endFilePos' => 56157,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1675,
            'endLine' => 1675,
            'startColumn' => 42,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * Returns the preferred language.
 *
 * @param string[] $locales An array of ordered available locales
 */',
        'startLine' => 1675,
        'endLine' => 1698,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getLanguages' => 
      array (
        'name' => 'getLanguages',
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
 * Gets a list of languages acceptable by the client browser ordered in the user browser preferences.
 *
 * @return string[]
 */',
        'startLine' => 1705,
        'endLine' => 1720,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'formatLocale' => 
      array (
        'name' => 'formatLocale',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
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
            'startLine' => 1736,
            'endLine' => 1736,
            'startColumn' => 42,
            'endColumn' => 55,
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
 * Strips the locale to only keep the canonicalized language value.
 *
 * Depending on the $locale value, this method can return values like :
 * - language_Script_REGION: "fr_Latn_FR", "zh_Hans_TW"
 * - language_Script: "fr_Latn", "zh_Hans"
 * - language_REGION: "fr_FR", "zh_TW"
 * - language: "fr", "zh"
 *
 * Invalid locale values are returned as is.
 *
 * @see https://wikipedia.org/wiki/IETF_language_tag
 * @see https://datatracker.ietf.org/doc/html/rfc5646
 */',
        'startLine' => 1736,
        'endLine' => 1741,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getLanguageCombinations' => 
      array (
        'name' => 'getLanguageCombinations',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
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
            'startLine' => 1754,
            'endLine' => 1754,
            'startColumn' => 53,
            'endColumn' => 66,
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
 * Returns an array of all possible combinations of the language components.
 *
 * For instance, if the locale is "fr_Latn_FR", this method will return:
 * - "fr_Latn_FR"
 * - "fr_Latn"
 * - "fr_FR"
 * - "fr"
 *
 * @return string[]
 */',
        'startLine' => 1754,
        'endLine' => 1764,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getLanguageComponents' => 
      array (
        'name' => 'getLanguageComponents',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
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
            'startLine' => 1779,
            'endLine' => 1779,
            'startColumn' => 51,
            'endColumn' => 64,
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
 * Returns an array with the language components of the locale.
 *
 * For example:
 * - If the locale is "fr_Latn_FR", this method will return "fr", "Latn", "FR"
 * - If the locale is "fr_FR", this method will return "fr", null, "FR"
 * - If the locale is "zh_Hans", this method will return "zh", "Hans", null
 *
 * @see https://wikipedia.org/wiki/IETF_language_tag
 * @see https://datatracker.ietf.org/doc/html/rfc5646
 *
 * @return array{string, string|null, string|null}
 */',
        'startLine' => 1779,
        'endLine' => 1798,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getCharsets' => 
      array (
        'name' => 'getCharsets',
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
 * Gets a list of charsets acceptable by the client browser in preferable order.
 *
 * @return string[]
 */',
        'startLine' => 1805,
        'endLine' => 1808,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getEncodings' => 
      array (
        'name' => 'getEncodings',
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
 * Gets a list of encodings acceptable by the client browser in preferable order.
 *
 * @return string[]
 */',
        'startLine' => 1815,
        'endLine' => 1818,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getAcceptableContentTypes' => 
      array (
        'name' => 'getAcceptableContentTypes',
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
 * Gets a list of content types acceptable by the client browser in preferable order.
 *
 * @return string[]
 */',
        'startLine' => 1825,
        'endLine' => 1828,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isXmlHttpRequest' => 
      array (
        'name' => 'isXmlHttpRequest',
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
 * Returns true if the request is an XMLHttpRequest.
 *
 * It works if your JavaScript library sets an X-Requested-With HTTP header.
 * It is known to work with common JavaScript frameworks:
 *
 * @see https://wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
 */',
        'startLine' => 1838,
        'endLine' => 1841,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'preferSafeContent' => 
      array (
        'name' => 'preferSafeContent',
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
 * Checks whether the client browser prefers safe content or not according to RFC8674.
 *
 * @see https://tools.ietf.org/html/rfc8674
 */',
        'startLine' => 1848,
        'endLine' => 1860,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'prepareRequestUri' => 
      array (
        'name' => 'prepareRequestUri',
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
        'startLine' => 1870,
        'endLine' => 1912,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'prepareBaseUrl' => 
      array (
        'name' => 'prepareBaseUrl',
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
 * Prepares the base URL.
 */',
        'startLine' => 1917,
        'endLine' => 1979,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'prepareBasePath' => 
      array (
        'name' => 'prepareBasePath',
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
 * Prepares the base path.
 */',
        'startLine' => 1984,
        'endLine' => 2003,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'preparePathInfo' => 
      array (
        'name' => 'preparePathInfo',
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
 * Prepares the path info.
 */',
        'startLine' => 2008,
        'endLine' => 2032,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'initializeFormats' => 
      array (
        'name' => 'initializeFormats',
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
 * Initializes HTTP request formats.
 */',
        'startLine' => 2037,
        'endLine' => 2060,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setPhpDefaultLocale' => 
      array (
        'name' => 'setPhpDefaultLocale',
        'parameters' => 
        array (
          'locale' => 
          array (
            'name' => 'locale',
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
            'startLine' => 2062,
            'endLine' => 2062,
            'startColumn' => 42,
            'endColumn' => 55,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 2062,
        'endLine' => 2073,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getUrlencodedPrefix' => 
      array (
        'name' => 'getUrlencodedPrefix',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
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
            'startLine' => 2079,
            'endLine' => 2079,
            'startColumn' => 42,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prefix' => 
          array (
            'name' => 'prefix',
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
            'startLine' => 2079,
            'endLine' => 2079,
            'startColumn' => 58,
            'endColumn' => 71,
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
 * Returns the prefix as encoded in the string when the string starts with
 * the given prefix, null otherwise.
 */',
        'startLine' => 2079,
        'endLine' => 2098,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'createRequestFromFactory' => 
      array (
        'name' => 'createRequestFromFactory',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 11965,
                'startFilePos' => 71293,
                'endTokenPos' => 11966,
                'endFilePos' => 71294,
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
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 54,
            'endColumn' => 70,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'request' => 
          array (
            'name' => 'request',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 11975,
                'startFilePos' => 71314,
                'endTokenPos' => 11976,
                'endFilePos' => 71315,
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
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 73,
            'endColumn' => 91,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 11985,
                'startFilePos' => 71338,
                'endTokenPos' => 11986,
                'endFilePos' => 71339,
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
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 94,
            'endColumn' => 115,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cookies' => 
          array (
            'name' => 'cookies',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 11995,
                'startFilePos' => 71359,
                'endTokenPos' => 11996,
                'endFilePos' => 71360,
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
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 118,
            'endColumn' => 136,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'files' => 
          array (
            'name' => 'files',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 12005,
                'startFilePos' => 71378,
                'endTokenPos' => 12006,
                'endFilePos' => 71379,
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
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 139,
            'endColumn' => 155,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'server' => 
          array (
            'name' => 'server',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 12015,
                'startFilePos' => 71398,
                'endTokenPos' => 12016,
                'endFilePos' => 71399,
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
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 158,
            'endColumn' => 175,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'content' => 
          array (
            'name' => 'content',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2100,
                'endLine' => 2100,
                'startTokenPos' => 12023,
                'startFilePos' => 71413,
                'endTokenPos' => 12023,
                'endFilePos' => 71416,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2100,
            'endLine' => 2100,
            'startColumn' => 178,
            'endColumn' => 192,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 2100,
        'endLine' => 2113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isFromTrustedProxy' => 
      array (
        'name' => 'isFromTrustedProxy',
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
 * Indicates whether this request originated from a trusted proxy.
 *
 * This can be useful to determine whether or not to trust the
 * contents of a proxy-specific header.
 */',
        'startLine' => 2121,
        'endLine' => 2124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'getTrustedValues' => 
      array (
        'name' => 'getTrustedValues',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
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
            'startLine' => 2131,
            'endLine' => 2131,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ip' => 
          array (
            'name' => 'ip',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2131,
                'endLine' => 2131,
                'startTokenPos' => 12203,
                'startFilePos' => 72645,
                'endTokenPos' => 12203,
                'endFilePos' => 72648,
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
            'startLine' => 2131,
            'endLine' => 2131,
            'startColumn' => 50,
            'endColumn' => 67,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * This method is rather heavy because it splits and merges headers, and it\'s called by many other methods such as
 * getPort(), isSecure(), getHost(), getClientIps(), getBaseUrl() etc. Thus, we try to cache the results for
 * best performance.
 */',
        'startLine' => 2131,
        'endLine' => 2186,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'normalizeAndFilterClientIps' => 
      array (
        'name' => 'normalizeAndFilterClientIps',
        'parameters' => 
        array (
          'clientIps' => 
          array (
            'name' => 'clientIps',
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
            'startLine' => 2188,
            'endLine' => 2188,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ip' => 
          array (
            'name' => 'ip',
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
            'startLine' => 2188,
            'endLine' => 2188,
            'startColumn' => 68,
            'endColumn' => 77,
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
        'docComment' => NULL,
        'startLine' => 2188,
        'endLine' => 2226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isIisRewrite' => 
      array (
        'name' => 'isIisRewrite',
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
 * Is this IIS with UrlRewriteModule?
 *
 * This method consumes, caches and removed the IIS_WasUrlRewritten env var,
 * so we don\'t inherit it to sub-requests.
 */',
        'startLine' => 2234,
        'endLine' => 2242,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'isHostValid' => 
      array (
        'name' => 'isHostValid',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
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
            'startLine' => 2247,
            'endLine' => 2247,
            'startColumn' => 41,
            'endColumn' => 52,
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
 * See https://url.spec.whatwg.org/.
 */',
        'startLine' => 2247,
        'endLine' => 2259,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'aliasName' => NULL,
      ),
      'setProperty' => 
      array (
        'name' => 'setProperty',
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
                'name' => 'self',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2261,
            'endLine' => 2261,
            'startColumn' => 41,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 2261,
            'endLine' => 2261,
            'startColumn' => 56,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2261,
            'endLine' => 2261,
            'startColumn' => 70,
            'endColumn' => 81,
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
        'docComment' => NULL,
        'startLine' => 2261,
        'endLine' => 2268,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Symfony\\Component\\HttpFoundation',
        'declaringClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'implementingClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
        'currentClassName' => 'Symfony\\Component\\HttpFoundation\\Request',
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