<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../socialiteproviders/manager/src/SocialiteWasCalled.php-PHPStan\BetterReflection\Reflection\ReflectionClass-SocialiteProviders\Manager\SocialiteWasCalled
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-106a0486fa302af3a00cc4e865b5193d0ed2c208d1921ba0c9c997daac9addee-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../socialiteproviders/manager/src/SocialiteWasCalled.php',
      ),
    ),
    'namespace' => 'SocialiteProviders\\Manager',
    'name' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
    'shortName' => 'SocialiteWasCalled',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 181,
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
      'SERVICE_CONTAINER_PREFIX' => 
      array (
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'name' => 'SERVICE_CONTAINER_PREFIX',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'SocialiteProviders.config.\'',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 81,
            'startFilePos' => 636,
            'endTokenPos' => 81,
            'endFilePos' => 663,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 73,
      ),
    ),
    'immediateProperties' => 
    array (
      'app' => 
      array (
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'name' => 'app',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var \\Illuminate\\Contracts\\Container\\Container
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'configRetriever' => 
      array (
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'name' => 'configRetriever',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'SocialiteProviders\\Manager\\Contracts\\Helpers\\ConfigRetrieverInterface',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 54,
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
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'configRetriever' => 
          array (
            'name' => 'configRetriever',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'SocialiteProviders\\Manager\\Contracts\\Helpers\\ConfigRetrieverInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 51,
            'endColumn' => 91,
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
 * @param  \\Illuminate\\Contracts\\Container\\Container  $app
 * @param  \\SocialiteProviders\\Manager\\Contracts\\Helpers\\ConfigRetrieverInterface  $configRetriever
 */',
        'startLine' => 29,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'extendSocialite' => 
      array (
        'name' => 'extendSocialite',
        'parameters' => 
        array (
          'providerName' => 
          array (
            'name' => 'providerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 43,
            'endLine' => 43,
            'startColumn' => 37,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'providerClass' => 
          array (
            'name' => 'providerClass',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 43,
            'endLine' => 43,
            'startColumn' => 52,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'oauth1Server' => 
          array (
            'name' => 'oauth1Server',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 43,
                'endLine' => 43,
                'startTokenPos' => 156,
                'startFilePos' => 1719,
                'endTokenPos' => 156,
                'endFilePos' => 1722,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 43,
            'endLine' => 43,
            'startColumn' => 68,
            'endColumn' => 87,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  string  $providerName  \'meetup\'
 * @param  string  $providerClass  \'Your\\Name\\Space\\ClassNameProvider\' must extend
 *                                 either Laravel\\Socialite\\Two\\AbstractProvider or
 *                                 Laravel\\Socialite\\One\\AbstractProvider
 * @param  string  $oauth1Server  \'Your\\Name\\Space\\ClassNameServer\' must extend League\\OAuth1\\Client\\Server\\Server
 * @return void
 */',
        'startLine' => 43,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'buildProvider' => 
      array (
        'name' => 'buildProvider',
        'parameters' => 
        array (
          'socialite' => 
          array (
            'name' => 'socialite',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Socialite\\SocialiteManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 35,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'providerName' => 
          array (
            'name' => 'providerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 64,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'providerClass' => 
          array (
            'name' => 'providerClass',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 79,
            'endColumn' => 92,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'oauth1Server' => 
          array (
            'name' => 'oauth1Server',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 95,
            'endColumn' => 107,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  \\Laravel\\Socialite\\SocialiteManager  $socialite
 * @param  string  $providerName
 * @param  string  $providerClass
 * @param  null|string  $oauth1Server
 * @return \\Laravel\\Socialite\\One\\AbstractProvider|\\Laravel\\Socialite\\Two\\AbstractProvider
 */',
        'startLine' => 76,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'buildOAuth1Provider' => 
      array (
        'name' => 'buildOAuth1Provider',
        'parameters' => 
        array (
          'socialite' => 
          array (
            'name' => 'socialite',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Socialite\\SocialiteManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 44,
            'endColumn' => 70,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'providerClass' => 
          array (
            'name' => 'providerClass',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 73,
            'endColumn' => 86,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'providerName' => 
          array (
            'name' => 'providerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 89,
            'endColumn' => 101,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'oauth1Server' => 
          array (
            'name' => 'oauth1Server',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 94,
            'endLine' => 94,
            'startColumn' => 104,
            'endColumn' => 116,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build an OAuth 1 provider instance.
 *
 * @param  \\Laravel\\Socialite\\SocialiteManager  $socialite
 * @param  string  $providerClass  must extend Laravel\\Socialite\\One\\AbstractProvider
 * @param  string  $providerName
 * @param  string  $oauth1Server  must extend League\\OAuth1\\Client\\Server\\Server
 * @return \\Laravel\\Socialite\\One\\AbstractProvider
 */',
        'startLine' => 94,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'buildOAuth2Provider' => 
      array (
        'name' => 'buildOAuth2Provider',
        'parameters' => 
        array (
          'socialite' => 
          array (
            'name' => 'socialite',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Laravel\\Socialite\\SocialiteManager',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 44,
            'endColumn' => 70,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'providerClass' => 
          array (
            'name' => 'providerClass',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 73,
            'endColumn' => 86,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'providerName' => 
          array (
            'name' => 'providerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 119,
            'endLine' => 119,
            'startColumn' => 89,
            'endColumn' => 101,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build an OAuth 2 provider instance.
 *
 * @param  SocialiteManager  $socialite
 * @param  string  $providerClass  must extend Laravel\\Socialite\\Two\\AbstractProvider
 * @param  string  $providerName
 * @return \\Laravel\\Socialite\\Two\\AbstractProvider
 */',
        'startLine' => 119,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'getConfig' => 
      array (
        'name' => 'getConfig',
        'parameters' => 
        array (
          'providerClass' => 
          array (
            'name' => 'providerClass',
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
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 34,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'providerName' => 
          array (
            'name' => 'providerName',
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
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 57,
            'endColumn' => 76,
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
 * @param  string  $providerClass
 * @param  string  $providerName
 * @return \\SocialiteProviders\\Manager\\Contracts\\ConfigInterface
 */',
        'startLine' => 137,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'isOAuth1' => 
      array (
        'name' => 'isOAuth1',
        'parameters' => 
        array (
          'oauth1Server' => 
          array (
            'name' => 'oauth1Server',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 31,
            'endColumn' => 43,
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
 * Check if a server is given, which indicates that OAuth1 is used.
 *
 * @param  string  $oauth1Server
 * @return bool
 */',
        'startLine' => 150,
        'endLine' => 153,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'classExtends' => 
      array (
        'name' => 'classExtends',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'baseClass' => 
          array (
            'name' => 'baseClass',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 43,
            'endColumn' => 52,
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
 * @param  string  $class
 * @param  string  $baseClass
 * @return void
 *
 * @throws \\SocialiteProviders\\Manager\\Exception\\InvalidArgumentException
 */',
        'startLine' => 162,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'aliasName' => NULL,
      ),
      'classExists' => 
      array (
        'name' => 'classExists',
        'parameters' => 
        array (
          'providerClass' => 
          array (
            'name' => 'providerClass',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 175,
            'endLine' => 175,
            'startColumn' => 34,
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
 * @param  string  $providerClass
 * @return void
 *
 * @throws \\SocialiteProviders\\Manager\\Exception\\InvalidArgumentException
 */',
        'startLine' => 175,
        'endLine' => 180,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'SocialiteProviders\\Manager',
        'declaringClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'implementingClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
        'currentClassName' => 'SocialiteProviders\\Manager\\SocialiteWasCalled',
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