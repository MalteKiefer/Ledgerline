<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../symfony/mime/Address.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Symfony\Component\Mime\Address
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-05d605bdfda48b7b456dc8d2bcf8bc3e61cd71a15f6cbcaf6e60fc2eb2cfadb4-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Symfony\\Component\\Mime\\Address',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../symfony/mime/Address.php',
      ),
    ),
    'namespace' => 'Symfony\\Component\\Mime',
    'name' => 'Symfony\\Component\\Mime\\Address',
    'shortName' => 'Address',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @author Fabien Potencier <fabien@symfony.com>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 144,
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
      'FROM_STRING_PATTERN' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'name' => 'FROM_STRING_PATTERN',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~\'',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 64,
            'startFilePos' => 1163,
            'endTokenPos' => 64,
            'endFilePos' => 1209,
          ),
        ),
        'docComment' => '/**
 * A regex that matches a structure like \'Name <email@address.com>\'.
 * It matches anything between the first < and last > as email address.
 * This allows to use a single string to construct an Address, which can be convenient to use in
 * config, and allows to have more readable config.
 * This does not try to cover all edge cases for address.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 88,
      ),
    ),
    'immediateProperties' => 
    array (
      'validator' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'name' => 'validator',
        'modifiers' => 20,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Egulias\\EmailValidator\\EmailValidator',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encoder' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'name' => 'encoder',
        'modifiers' => 20,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Symfony\\Component\\Mime\\Encoder\\IdnAddressEncoder',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 46,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'address' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'name' => 'address',
        'modifiers' => 4,
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
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'name' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'name' => 'name',
        'modifiers' => 4,
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
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 25,
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
          'address' => 
          array (
            'name' => 'address',
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
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 42,
                'endLine' => 42,
                'startTokenPos' => 116,
                'startFilePos' => 1427,
                'endTokenPos' => 116,
                'endFilePos' => 1428,
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
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 50,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 42,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'getAddress' => 
      array (
        'name' => 'getAddress',
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
        'startLine' => 62,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'getName' => 
      array (
        'name' => 'getName',
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
        'startLine' => 67,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'getEncodedAddress' => 
      array (
        'name' => 'getEncodedAddress',
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
        'startLine' => 72,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'toString' => 
      array (
        'name' => 'toString',
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
        'startLine' => 79,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'getEncodedName' => 
      array (
        'name' => 'getEncodedName',
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
        'startLine' => 84,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'create' => 
      array (
        'name' => 'create',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
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
                      'name' => 'self',
                      'isIdentifier' => false,
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
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 35,
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
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 93,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'createArray' => 
      array (
        'name' => 'createArray',
        'parameters' => 
        array (
          'addresses' => 
          array (
            'name' => 'addresses',
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
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 40,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array<Address|string> $addresses
 *
 * @return Address[]
 */',
        'startLine' => 115,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
        'aliasName' => NULL,
      ),
      'hasUnicodeLocalpart' => 
      array (
        'name' => 'hasUnicodeLocalpart',
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
 * Returns true if this address\' localpart contains at least one
 * non-ASCII character, and false if it is only ASCII (or empty).
 *
 * This is a helper for Envelope, which has to decide whether to
 * the SMTPUTF8 extensions (RFC 6530 and following) for any given
 * message.
 *
 * The SMTPUTF8 extension is strictly required if any address
 * contains a non-ASCII character in its localpart. If non-ASCII
 * is only used in domains (e.g. horst@freiherr-von-mühlhausen.de)
 * then it is possible to send the message using IDN encoding
 * instead of SMTPUTF8. The most common software will display the
 * message as intended.
 */',
        'startLine' => 140,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Mime',
        'declaringClassName' => 'Symfony\\Component\\Mime\\Address',
        'implementingClassName' => 'Symfony\\Component\\Mime\\Address',
        'currentClassName' => 'Symfony\\Component\\Mime\\Address',
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