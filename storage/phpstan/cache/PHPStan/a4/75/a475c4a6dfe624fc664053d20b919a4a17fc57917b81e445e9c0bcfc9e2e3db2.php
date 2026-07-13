<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../sabre/dav/lib/DAV/Client.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Sabre\DAV\Client
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-547109530c5318efff5759a573f165ea3c6901e96b99004cdc3bcdeed528c44c-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Sabre\\DAV\\Client',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../sabre/dav/lib/DAV/Client.php',
      ),
    ),
    'namespace' => 'Sabre\\DAV',
    'name' => 'Sabre\\DAV\\Client',
    'shortName' => 'Client',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * SabreDAV DAV client.
 *
 * This client wraps around Curl to provide a convenient API to a WebDAV
 * server.
 *
 * NOTE: This class is experimental, it\'s api will likely change in the future.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 485,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Sabre\\HTTP\\Client',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'AUTH_BASIC' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'AUTH_BASIC',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 57,
            'startTokenPos' => 71,
            'startFilePos' => 1138,
            'endTokenPos' => 71,
            'endFilePos' => 1138,
          ),
        ),
        'docComment' => '/**
 * Basic authentication.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 25,
      ),
      'AUTH_DIGEST' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'AUTH_DIGEST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 82,
            'startFilePos' => 1212,
            'endTokenPos' => 82,
            'endFilePos' => 1212,
          ),
        ),
        'docComment' => '/**
 * Digest authentication.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 26,
      ),
      'AUTH_NTLM' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'AUTH_NTLM',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 67,
            'startTokenPos' => 93,
            'startFilePos' => 1282,
            'endTokenPos' => 93,
            'endFilePos' => 1282,
          ),
        ),
        'docComment' => '/**
 * NTLM authentication.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 24,
      ),
      'ENCODING_IDENTITY' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'ENCODING_IDENTITY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 72,
            'endLine' => 72,
            'startTokenPos' => 104,
            'startFilePos' => 1392,
            'endTokenPos' => 104,
            'endFilePos' => 1392,
          ),
        ),
        'docComment' => '/**
 * Identity encoding, which basically does not nothing.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 72,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'ENCODING_DEFLATE' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'ENCODING_DEFLATE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 77,
            'endLine' => 77,
            'startTokenPos' => 115,
            'startFilePos' => 1466,
            'endTokenPos' => 115,
            'endFilePos' => 1466,
          ),
        ),
        'docComment' => '/**
 * Deflate encoding.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 77,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 31,
      ),
      'ENCODING_GZIP' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'ENCODING_GZIP',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 82,
            'endLine' => 82,
            'startTokenPos' => 126,
            'startFilePos' => 1534,
            'endTokenPos' => 126,
            'endFilePos' => 1534,
          ),
        ),
        'docComment' => '/**
 * Gzip encoding.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
      'ENCODING_ALL' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'ENCODING_ALL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '7',
          'attributes' => 
          array (
            'startLine' => 87,
            'endLine' => 87,
            'startTokenPos' => 137,
            'startFilePos' => 1614,
            'endTokenPos' => 137,
            'endFilePos' => 1614,
          ),
        ),
        'docComment' => '/**
 * Sends all encoding headers.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 87,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
    ),
    'immediateProperties' => 
    array (
      'xml' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'xml',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The xml service.
 *
 * Uset this service to configure the property and namespace maps.
 *
 * @var mixed
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 16,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'propertyMap' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'propertyMap',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 52,
            'startFilePos' => 919,
            'endTokenPos' => 53,
            'endFilePos' => 920,
          ),
        ),
        'docComment' => '/**
 * The elementMap.
 *
 * This property is linked via reference to $this->xml->elementMap.
 * It\'s deprecated as of version 3.0.0, and should no longer be used.
 *
 * @deprecated
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'baseUri' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'baseUri',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Base URI.
 *
 * This URI will be used to resolve relative urls.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encoding' => 
      array (
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'name' => 'encoding',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'self::ENCODING_IDENTITY',
          'attributes' => 
          array (
            'startLine' => 94,
            'endLine' => 94,
            'startTokenPos' => 148,
            'startFilePos' => 1708,
            'endTokenPos' => 150,
            'endFilePos' => 1730,
          ),
        ),
        'docComment' => '/**
 * Content-encoding.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 94,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 50,
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
          'settings' => 
          array (
            'name' => 'settings',
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
            'startLine' => 116,
            'endLine' => 116,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Constructor.
 *
 * Settings are provided through the \'settings\' argument. The following
 * settings are supported:
 *
 *   * baseUri
 *   * userName (optional)
 *   * password (optional)
 *   * proxy (optional)
 *   * authType (optional)
 *   * encoding (optional)
 *
 *  authType must be a bitmap, using self::AUTH_BASIC, self::AUTH_DIGEST
 *  and self::AUTH_NTLM. If you know which authentication method will be
 *  used, it\'s recommended to set it, as it will save a great deal of
 *  requests to \'discover\' this information.
 *
 *  Encoding is a bitmap with one of the ENCODING constants.
 */',
        'startLine' => 116,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'propFind' => 
      array (
        'name' => 'propFind',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 196,
            'endLine' => 196,
            'startColumn' => 30,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'properties' => 
          array (
            'name' => 'properties',
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
            'startLine' => 196,
            'endLine' => 196,
            'startColumn' => 36,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'depth' => 
          array (
            'name' => 'depth',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 196,
                'endLine' => 196,
                'startTokenPos' => 632,
                'startFilePos' => 5378,
                'endTokenPos' => 632,
                'endFilePos' => 5378,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 196,
            'endLine' => 196,
            'startColumn' => 55,
            'endColumn' => 64,
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
 * Does a PROPFIND request with filtered response returning only available properties.
 *
 * The list of requested properties must be specified as an array, in clark
 * notation.
 *
 * Depth should be either 0 or 1. A depth of 1 will cause a request to be
 * made to the server to also return all child resources.
 *
 * For depth 0, just the array of properties for the resource is returned.
 *
 * For depth 1, the returned array will contain a list of resource names as keys,
 * and an array of properties as values.
 *
 * The array of properties will contain the properties as keys with their values as the value.
 * Only properties that are actually returned from the server without error will be
 * returned, anything else is discarded.
 *
 * @param 1|0 $depth
 */',
        'startLine' => 196,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'propFindUnfiltered' => 
      array (
        'name' => 'propFindUnfiltered',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
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
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'properties' => 
          array (
            'name' => 'properties',
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
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 53,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'depth' => 
          array (
            'name' => 'depth',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 237,
                'endLine' => 237,
                'startTokenPos' => 798,
                'startFilePos' => 6830,
                'endTokenPos' => 798,
                'endFilePos' => 6830,
              ),
            ),
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
            'startLine' => 237,
            'endLine' => 237,
            'startColumn' => 72,
            'endColumn' => 85,
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
 * Does a PROPFIND request with unfiltered response.
 *
 * The list of requested properties must be specified as an array, in clark
 * notation.
 *
 * Depth should be either 0 or 1. A depth of 1 will cause a request to be
 * made to the server to also return all child resources.
 *
 * For depth 0, just the multi-level array of status and properties for the resource is returned.
 *
 * For depth 1, the returned array will contain a list of resources as keys and
 * a multi-level array containing status and properties as value.
 *
 * The multi-level array of status and properties is formatted the same as what is
 * documented for parseMultiStatus.
 *
 * All properties that are actually returned from the server are returned by this method.
 *
 * @param 1|0 $depth
 */',
        'startLine' => 237,
        'endLine' => 249,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'doPropFind' => 
      array (
        'name' => 'doPropFind',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'properties' => 
          array (
            'name' => 'properties',
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
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 39,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'depth' => 
          array (
            'name' => 'depth',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 268,
                'endLine' => 268,
                'startTokenPos' => 887,
                'startFilePos' => 7801,
                'endTokenPos' => 887,
                'endFilePos' => 7801,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 58,
            'endColumn' => 67,
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
 * Does a PROPFIND request.
 *
 * The list of requested properties must be specified as an array, in clark
 * notation.
 *
 * Depth should be either 0 or 1. A depth of 1 will cause a request to be
 * made to the server to also return all child resources.
 *
 * The returned array will contain a list of resources as keys and
 * a multi-level array containing status and properties as value.
 *
 * The multi-level array of status and properties is formatted the same as what is
 * documented for parseMultiStatus.
 *
 * @param 1|0 $depth
 */',
        'startLine' => 268,
        'endLine' => 307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'propPatch' => 
      array (
        'name' => 'propPatch',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 31,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'properties' => 
          array (
            'name' => 'properties',
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
            'startLine' => 320,
            'endLine' => 320,
            'startColumn' => 37,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Updates a list of properties on the server.
 *
 * The list of properties must have clark-notation properties for the keys,
 * and the actual (string) value for the value. If the value is null, an
 * attempt is made to delete the property.
 *
 * @param string $url
 *
 * @return bool
 */',
        'startLine' => 320,
        'endLine' => 360,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'options' => 
      array (
        'name' => 'options',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Performs an HTTP options request.
 *
 * This method returns all the features from the \'DAV:\' header as an array.
 * If there was no DAV header, or no contents this method will return an
 * empty array.
 *
 * @return array
 */',
        'startLine' => 371,
        'endLine' => 387,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'request' => 
      array (
        'name' => 'request',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 419,
            'endLine' => 419,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'url' => 
          array (
            'name' => 'url',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 419,
                'endLine' => 419,
                'startTokenPos' => 1606,
                'startFilePos' => 12643,
                'endTokenPos' => 1606,
                'endFilePos' => 12644,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 419,
            'endLine' => 419,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'body' => 
          array (
            'name' => 'body',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 419,
                'endLine' => 419,
                'startTokenPos' => 1613,
                'startFilePos' => 12655,
                'endTokenPos' => 1613,
                'endFilePos' => 12658,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 419,
            'endLine' => 419,
            'startColumn' => 49,
            'endColumn' => 60,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 419,
                'endLine' => 419,
                'startTokenPos' => 1622,
                'startFilePos' => 12678,
                'endTokenPos' => 1623,
                'endFilePos' => 12679,
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
            'startLine' => 419,
            'endLine' => 419,
            'startColumn' => 63,
            'endColumn' => 81,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Performs an actual HTTP request, and returns the result.
 *
 * If the specified url is relative, it will be expanded based on the base
 * url.
 *
 * The returned array contains 3 keys:
 *   * body - the response body
 *   * httpCode - a HTTP code (200, 404, etc)
 *   * headers - a list of response http headers. The header names have
 *     been lowercased.
 *
 * For large uploads, it\'s highly recommended to specify body as a stream
 * resource. You can easily do this by simply passing the result of
 * fopen(..., \'r\').
 *
 * This method will throw an exception if an HTTP error was received. Any
 * HTTP status code above 399 is considered an error.
 *
 * Note that it is no longer recommended to use this method, use the send()
 * method instead.
 *
 * @param string               $method
 * @param string               $url
 * @param string|resource|null $body
 *
 * @throws clientException, in case a curl error occurred
 *
 * @return array
 */',
        'startLine' => 419,
        'endLine' => 430,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'getAbsoluteUrl' => 
      array (
        'name' => 'getAbsoluteUrl',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 440,
            'endLine' => 440,
            'startColumn' => 36,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the full url based on the given url (which may be relative). All
 * urls are expanded based on the base url as given by the server.
 *
 * @param string $url
 *
 * @return string
 */',
        'startLine' => 440,
        'endLine' => 446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
        'aliasName' => NULL,
      ),
      'parseMultiStatus' => 
      array (
        'name' => 'parseMultiStatus',
        'parameters' => 
        array (
          'body' => 
          array (
            'name' => 'body',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 473,
            'endLine' => 473,
            'startColumn' => 38,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parses a WebDAV multistatus response body.
 *
 * This method returns an array with the following structure
 *
 * [
 *   \'url/to/resource\' => [
 *     \'200\' => [
 *        \'{DAV:}property1\' => \'value1\',
 *        \'{DAV:}property2\' => \'value2\',
 *     ],
 *     \'404\' => [
 *        \'{DAV:}property1\' => null,
 *        \'{DAV:}property2\' => null,
 *     ],
 *   ],
 *   \'url/to/resource2\' => [
 *      .. etc ..
 *   ]
 * ]
 *
 * @param string $body xml body
 *
 * @return array
 */',
        'startLine' => 473,
        'endLine' => 484,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\DAV',
        'declaringClassName' => 'Sabre\\DAV\\Client',
        'implementingClassName' => 'Sabre\\DAV\\Client',
        'currentClassName' => 'Sabre\\DAV\\Client',
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