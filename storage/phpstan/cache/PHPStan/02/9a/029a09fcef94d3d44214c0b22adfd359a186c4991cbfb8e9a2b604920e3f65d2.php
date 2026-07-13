<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../socialiteproviders/manager/src/OAuth2/AbstractProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-SocialiteProviders\Manager\OAuth2\AbstractProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-11cb9a7e15671025f9a9338f98d8fa430baabadee9e803c84052924e1cffa2c8-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../socialiteproviders/manager/src/OAuth2/AbstractProvider.php',
      ),
    ),
    'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
    'name' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
    'shortName' => 'AbstractProvider',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 122,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laravel\\Socialite\\Two\\AbstractProvider',
    'implementsClassNames' => 
    array (
      0 => 'SocialiteProviders\\Manager\\Contracts\\OAuth2\\ProviderInterface',
    ),
    'traitClassNames' => 
    array (
      0 => 'SocialiteProviders\\Manager\\ConfigTrait',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'credentialsResponseBody' => 
      array (
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'name' => 'credentialsResponseBody',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'user' => 
      array (
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'name' => 'user',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The cached user instance.
 *
 * @var \\SocialiteProviders\\Manager\\OAuth2\\User|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 20,
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
      'serviceContainerKey' => 
      array (
        'name' => 'serviceContainerKey',
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
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 48,
            'endColumn' => 60,
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
 * @param  string  $providerName
 * @return string
 */',
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'currentClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'aliasName' => NULL,
      ),
      'user' => 
      array (
        'name' => 'user',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return \\SocialiteProviders\\Manager\\OAuth2\\User
 *
 * @throws \\Laravel\\Socialite\\Two\\InvalidStateException
 */',
        'startLine' => 42,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'currentClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'aliasName' => NULL,
      ),
      'parseAccessToken' => 
      array (
        'name' => 'parseAccessToken',
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
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 41,
            'endColumn' => 45,
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
 * Get the access token from the token response body.
 *
 * @param  array  $body
 * @return string
 */',
        'startLine' => 75,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'currentClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'aliasName' => NULL,
      ),
      'parseRefreshToken' => 
      array (
        'name' => 'parseRefreshToken',
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
            'startLine' => 86,
            'endLine' => 86,
            'startColumn' => 42,
            'endColumn' => 46,
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
 * Get the refresh token from the token response body.
 *
 * @param  array  $body
 * @return string
 */',
        'startLine' => 86,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'currentClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'aliasName' => NULL,
      ),
      'parseExpiresIn' => 
      array (
        'name' => 'parseExpiresIn',
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
            'startLine' => 97,
            'endLine' => 97,
            'startColumn' => 39,
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
 * Get the expires in from the token response body.
 *
 * @param  array  $body
 * @return string
 */',
        'startLine' => 97,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'currentClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'aliasName' => NULL,
      ),
      'parseApprovedScopes' => 
      array (
        'name' => 'parseApprovedScopes',
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
            'startLine' => 108,
            'endLine' => 108,
            'startColumn' => 44,
            'endColumn' => 48,
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
 * Get the approved scopes from the token response body.
 *
 * @param  array  $body
 * @return array
 */',
        'startLine' => 108,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'SocialiteProviders\\Manager\\OAuth2',
        'declaringClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'implementingClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
        'currentClassName' => 'SocialiteProviders\\Manager\\OAuth2\\AbstractProvider',
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