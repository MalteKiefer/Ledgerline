<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/AwsClientInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Aws\AwsClientInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-b0c28d1b6cde70dffc2f06fd15d28410591e55c3f95b5408036279da540f9c02-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Aws\\AwsClientInterface',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../aws/aws-sdk-php/src/AwsClientInterface.php',
      ),
    ),
    'namespace' => 'Aws',
    'name' => 'Aws\\AwsClientInterface',
    'shortName' => 'AwsClientInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Represents an AWS client.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 169,
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
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 28,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'arguments' => 
          array (
            'name' => 'arguments',
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
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 35,
            'endColumn' => 50,
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
 * Creates and executes a command for an operation by name.
 *
 * Suffixing an operation name with "Async" will return a
 * promise that can be used to execute commands asynchronously.
 *
 * @param string $name      Name of the command to execute.
 * @param array  $arguments Arguments to pass to the getCommand method.
 *
 * @return ResultInterface
 * @throws \\Exception
 */',
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getCommand' => 
      array (
        'name' => 'getCommand',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startColumn' => 32,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 43,
                'endLine' => 43,
                'startTokenPos' => 58,
                'startFilePos' => 1378,
                'endTokenPos' => 59,
                'endFilePos' => 1379,
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
            'startLine' => 43,
            'endLine' => 43,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a command for an operation name.
 *
 * Special keys may be set on the command to control how it behaves,
 * including:
 *
 * - @http: Associative array of transfer specific options to apply to the
 *   request that is serialized for this command. Available keys include
 *   "proxy", "verify", "timeout", "connect_timeout", "debug", "delay", and
 *   "headers".
 *
 * @param string $name Name of the operation to use in the command
 * @param array  $args Arguments to pass to the command
 *
 * @return CommandInterface
 * @throws \\InvalidArgumentException if no command can be found by name
 */',
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'execute' => 
      array (
        'name' => 'execute',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\CommandInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 29,
            'endColumn' => 53,
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
 * Execute a single command.
 *
 * @param CommandInterface $command Command to execute
 *
 * @return ResultInterface
 * @throws \\Exception
 */',
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'executeAsync' => 
      array (
        'name' => 'executeAsync',
        'parameters' => 
        array (
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Aws\\CommandInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 62,
            'endLine' => 62,
            'startColumn' => 34,
            'endColumn' => 58,
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
 * Execute a command asynchronously.
 *
 * @param CommandInterface $command Command to execute
 *
 * @return \\GuzzleHttp\\Promise\\PromiseInterface
 */',
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 60,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getCredentials' => 
      array (
        'name' => 'getCredentials',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a promise that is fulfilled with an
 * {@see \\Aws\\Credentials\\CredentialsInterface} object.
 *
 * If you need the credentials synchronously, then call the wait() method
 * on the returned promise.
 *
 * @return PromiseInterface
 */',
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 37,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getRegion' => 
      array (
        'name' => 'getRegion',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the region to which the client is configured to send requests.
 *
 * @return string
 */',
        'startLine' => 80,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 32,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getEndpoint' => 
      array (
        'name' => 'getEndpoint',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the default endpoint, or base URL, used by the client.
 *
 * @return UriInterface
 */',
        'startLine' => 87,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getApi' => 
      array (
        'name' => 'getApi',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the service description associated with the client.
 *
 * @return \\Aws\\Api\\Service
 */',
        'startLine' => 94,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 29,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getConfig' => 
      array (
        'name' => 'getConfig',
        'parameters' => 
        array (
          'option' => 
          array (
            'name' => 'option',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 103,
                'endLine' => 103,
                'startTokenPos' => 147,
                'startFilePos' => 2899,
                'endTokenPos' => 147,
                'endFilePos' => 2902,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 103,
            'endLine' => 103,
            'startColumn' => 31,
            'endColumn' => 44,
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
 * Get a client configuration value.
 *
 * @param string|null $option The option to retrieve. Pass null to retrieve
 *                            all options.
 * @return mixed|null
 */',
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getHandlerList' => 
      array (
        'name' => 'getHandlerList',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the handler list used to transfer commands.
 *
 * This list can be modified to add middleware or to change the underlying
 * handler used to send HTTP requests.
 *
 * @return HandlerList
 */',
        'startLine' => 113,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 37,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getIterator' => 
      array (
        'name' => 'getIterator',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 124,
            'endLine' => 124,
            'startColumn' => 33,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 124,
                'endLine' => 124,
                'startTokenPos' => 179,
                'startFilePos' => 3556,
                'endTokenPos' => 180,
                'endFilePos' => 3557,
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
            'startLine' => 124,
            'endLine' => 124,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a resource iterator for the specified operation.
 *
 * @param string $name Name of the iterator to retrieve.
 * @param array  $args Command arguments to use with each command.
 *
 * @return \\Iterator
 * @throws \\UnexpectedValueException if the iterator config is invalid.
 */',
        'startLine' => 124,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 57,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getPaginator' => 
      array (
        'name' => 'getPaginator',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 34,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 135,
                'endLine' => 135,
                'startTokenPos' => 201,
                'startFilePos' => 3957,
                'endTokenPos' => 202,
                'endFilePos' => 3958,
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a result paginator for the specified operation.
 *
 * @param string $name   Name of the operation used for iterator
 * @param array  $args   Command args to be used with each command
 *
 * @return \\Aws\\ResultPaginator
 * @throws \\UnexpectedValueException if the iterator config is invalid.
 */',
        'startLine' => 135,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 58,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'waitUntil' => 
      array (
        'name' => 'waitUntil',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 31,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 149,
                'endLine' => 149,
                'startTokenPos' => 223,
                'startFilePos' => 4613,
                'endTokenPos' => 224,
                'endFilePos' => 4614,
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
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 38,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Wait until a resource is in a particular state.
 *
 * @param string|callable $name Name of the waiter that defines the wait
 *                              configuration and conditions.
 * @param array  $args          Args to be used with each command executed
 *                              by the waiter. Waiter configuration options
 *                              can be provided in an associative array in
 *                              the @waiter key.
 * @return void
 * @throws \\UnexpectedValueException if the waiter is invalid.
 */',
        'startLine' => 149,
        'endLine' => 149,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
        'aliasName' => NULL,
      ),
      'getWaiter' => 
      array (
        'name' => 'getWaiter',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 31,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 168,
                'endLine' => 168,
                'startTokenPos' => 245,
                'startFilePos' => 5522,
                'endTokenPos' => 246,
                'endFilePos' => 5523,
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
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 38,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a waiter that waits until a resource is in a particular state.
 *
 * Retrieving a waiter can be useful when you wish to wait asynchronously:
 *
 *     $waiter = $client->getWaiter(\'foo\', [\'bar\' => \'baz\']);
 *     $waiter->promise()->then(function () { echo \'Done!\'; });
 *
 * @param string|callable $name Name of the waiter that defines the wait
 *                              configuration and conditions.
 * @param array  $args          Args to be used with each command executed
 *                              by the waiter. Waiter configuration options
 *                              can be provided in an associative array in
 *                              the @waiter key.
 * @return \\Aws\\Waiter
 * @throws \\UnexpectedValueException if the waiter is invalid.
 */',
        'startLine' => 168,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Aws',
        'declaringClassName' => 'Aws\\AwsClientInterface',
        'implementingClassName' => 'Aws\\AwsClientInterface',
        'currentClassName' => 'Aws\\AwsClientInterface',
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