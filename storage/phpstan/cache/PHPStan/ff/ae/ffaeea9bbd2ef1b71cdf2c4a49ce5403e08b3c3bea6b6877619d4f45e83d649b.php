<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../sabre/http/lib/Client.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Sabre\HTTP\Client
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-e35e7b5655f15c08493e0cccccc666772a502d513d27a25e8e67bb3449da0d15-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Sabre\\HTTP\\Client',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../sabre/http/lib/Client.php',
      ),
    ),
    'namespace' => 'Sabre\\HTTP',
    'name' => 'Sabre\\HTTP\\Client',
    'shortName' => 'Client',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * A rudimentary HTTP client.
 *
 * This object wraps PHP\'s curl extension and provides an easy way to send it a
 * Request object, and return a Response object.
 *
 * This is by no means intended as the next best HTTP client, but it does the
 * job and provides a simple integration with the rest of sabre/http.
 *
 * This client emits the following events:
 *   beforeRequest(RequestInterface $request)
 *   afterRequest(RequestInterface $request, ResponseInterface $response)
 *   error(RequestInterface $request, ResponseInterface $response, bool &$retry, int $retryCount)
 *   exception(RequestInterface $request, ClientException $e, bool &$retry, int $retryCount)
 *
 * The beforeRequest event allows you to do some last minute changes to the
 * request before it\'s done, such as adding authentication headers.
 *
 * The afterRequest event will be emitted after the request is completed
 * successfully.
 *
 * If a HTTP error is returned (status code higher than 399) the error event is
 * triggered. It\'s possible using this event to retry the request, by setting
 * retry to true.
 *
 * The amount of times a request has retried is passed as $retryCount, which
 * can be used to avoid retrying indefinitely. The first time the event is
 * called, this will be 0.
 *
 * It\'s also possible to intercept specific http errors, by subscribing to for
 * example \'error:401\'.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 46,
    'endLine' => 620,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Sabre\\Event\\EventEmitter',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'STATUS_SUCCESS' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'STATUS_SUCCESS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 420,
            'endLine' => 420,
            'startTokenPos' => 1928,
            'startFilePos' => 13887,
            'endTokenPos' => 1928,
            'endFilePos' => 13887,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 420,
        'endLine' => 420,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'STATUS_CURLERROR' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'STATUS_CURLERROR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 421,
            'endLine' => 421,
            'startTokenPos' => 1939,
            'startFilePos' => 13926,
            'endTokenPos' => 1939,
            'endFilePos' => 13926,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 421,
        'endLine' => 421,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'STATUS_HTTPERROR' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'STATUS_HTTPERROR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 422,
            'endLine' => 422,
            'startTokenPos' => 1950,
            'startFilePos' => 13965,
            'endTokenPos' => 1950,
            'endFilePos' => 13965,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 422,
        'endLine' => 422,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
    ),
    'immediateProperties' => 
    array (
      'curlSettings' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'curlSettings',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 53,
            'endLine' => 53,
            'startTokenPos' => 45,
            'startFilePos' => 1789,
            'endTokenPos' => 46,
            'endFilePos' => 1790,
          ),
        ),
        'docComment' => '/**
 * List of curl settings.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'throwExceptions' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'throwExceptions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 60,
            'endLine' => 60,
            'startTokenPos' => 57,
            'startFilePos' => 1941,
            'endTokenPos' => 57,
            'endFilePos' => 1945,
          ),
        ),
        'docComment' => '/**
 * Whether exceptions should be thrown when a HTTP error is returned.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 60,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'maxRedirects' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'maxRedirects',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 67,
            'endLine' => 67,
            'startTokenPos' => 68,
            'startFilePos' => 2078,
            'endTokenPos' => 68,
            'endFilePos' => 2078,
          ),
        ),
        'docComment' => '/**
 * The maximum number of times we\'ll follow a redirect.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 67,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'headerLinesMap' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'headerLinesMap',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 69,
            'endLine' => 69,
            'startTokenPos' => 77,
            'startFilePos' => 2114,
            'endTokenPos' => 78,
            'endFilePos' => 2115,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'curlHandle' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'curlHandle',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Cached curl handle.
 *
 * By keeping this resource around for the lifetime of this object, things
 * like persistent connections are possible.
 *
 * @var resource
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 339,
        'endLine' => 339,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'curlMultiHandle' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'curlMultiHandle',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Handler for curl_multi requests.
 *
 * The first time sendAsync is used, this will be created.
 *
 * @var resource
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 348,
        'endLine' => 348,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'curlMultiMap' => 
      array (
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'name' => 'curlMultiMap',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 356,
            'endLine' => 356,
            'startTokenPos' => 1527,
            'startFilePos' => 11423,
            'endTokenPos' => 1528,
            'endFilePos' => 11424,
          ),
        ),
        'docComment' => '/**
 * Has a list of curl handles, as well as their associated success and
 * error callbacks.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 356,
        'endLine' => 356,
        'startColumn' => 5,
        'endColumn' => 31,
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
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Initializes the client.
 */',
        'startLine' => 74,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'receiveCurlHeader' => 
      array (
        'name' => 'receiveCurlHeader',
        'parameters' => 
        array (
          'curlHandle' => 
          array (
            'name' => 'curlHandle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 42,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'headerLine' => 
          array (
            'name' => 'headerLine',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 55,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 92,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'send' => 
      array (
        'name' => 'send',
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
                'name' => 'Sabre\\HTTP\\RequestInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 26,
            'endColumn' => 50,
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
            'name' => 'Sabre\\HTTP\\ResponseInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sends a request to a HTTP server, and returns a response.
 */',
        'startLine' => 102,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'sendAsync' => 
      array (
        'name' => 'sendAsync',
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
                'name' => 'Sabre\\HTTP\\RequestInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 178,
            'endLine' => 178,
            'startColumn' => 31,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'success' => 
          array (
            'name' => 'success',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 178,
                'endLine' => 178,
                'startTokenPos' => 652,
                'startFilePos' => 5723,
                'endTokenPos' => 652,
                'endFilePos' => 5726,
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
            'startLine' => 178,
            'endLine' => 178,
            'startColumn' => 58,
            'endColumn' => 82,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'error' => 
          array (
            'name' => 'error',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 178,
                'endLine' => 178,
                'startTokenPos' => 662,
                'startFilePos' => 5748,
                'endTokenPos' => 662,
                'endFilePos' => 5751,
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
            'startLine' => 178,
            'endLine' => 178,
            'startColumn' => 85,
            'endColumn' => 107,
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
 * Sends a HTTP request asynchronously.
 *
 * Due to the nature of PHP, you must from time to time poll to see if any
 * new responses came in.
 *
 * After calling sendAsync, you must therefore occasionally call the poll()
 * method, or wait().
 */',
        'startLine' => 178,
        'endLine' => 183,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'poll' => 
      array (
        'name' => 'poll',
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
 * This method checks if any http requests have gotten results, and if so,
 * call the appropriate success or error handlers.
 *
 * This method will return true if there are still requests waiting to
 * return, and false if all the work is done.
 */',
        'startLine' => 192,
        'endLine' => 269,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'wait' => 
      array (
        'name' => 'wait',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Processes every HTTP request in the queue, and waits till they are all
 * completed.
 */',
        'startLine' => 275,
        'endLine' => 281,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'setThrowExceptions' => 
      array (
        'name' => 'setThrowExceptions',
        'parameters' => 
        array (
          'throwExceptions' => 
          array (
            'name' => 'throwExceptions',
            'default' => NULL,
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
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 40,
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
 * If this is set to true, the Client will automatically throw exceptions
 * upon HTTP errors.
 *
 * This means that if a response came back with a status code greater than
 * or equal to 400, we will throw a ClientHttpException.
 *
 * This only works for the send() method. Throwing exceptions for
 * sendAsync() is not supported.
 */',
        'startLine' => 293,
        'endLine' => 296,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'addCurlSetting' => 
      array (
        'name' => 'addCurlSetting',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 303,
            'endLine' => 303,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 303,
            'endLine' => 303,
            'startColumn' => 47,
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
 * Adds a CURL setting.
 *
 * These settings will be included in every HTTP request.
 */',
        'startLine' => 303,
        'endLine' => 306,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'doRequest' => 
      array (
        'name' => 'doRequest',
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
                'name' => 'Sabre\\HTTP\\RequestInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 311,
            'endLine' => 311,
            'startColumn' => 34,
            'endColumn' => 58,
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
            'name' => 'Sabre\\HTTP\\ResponseInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * This method is responsible for performing a single request.
 */',
        'startLine' => 311,
        'endLine' => 329,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'createCurlSettingsArray' => 
      array (
        'name' => 'createCurlSettingsArray',
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
                'name' => 'Sabre\\HTTP\\RequestInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 362,
            'endLine' => 362,
            'startColumn' => 48,
            'endColumn' => 72,
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
 * Turns a RequestInterface object into an array with settings that can be
 * fed to curl_setopt.
 */',
        'startLine' => 362,
        'endLine' => 418,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'parseResponse' => 
      array (
        'name' => 'parseResponse',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
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
            'startLine' => 424,
            'endLine' => 424,
            'startColumn' => 36,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'curlHandle' => 
          array (
            'name' => 'curlHandle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 424,
            'endLine' => 424,
            'startColumn' => 54,
            'endColumn' => 64,
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
        'startLine' => 424,
        'endLine' => 442,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'parseCurlResponse' => 
      array (
        'name' => 'parseCurlResponse',
        'parameters' => 
        array (
          'headerLines' => 
          array (
            'name' => 'headerLines',
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
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 42,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'body' => 
          array (
            'name' => 'body',
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
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 62,
            'endColumn' => 73,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'curlHandle' => 
          array (
            'name' => 'curlHandle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 76,
            'endColumn' => 86,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parses the result of a curl call in a format that\'s a bit more
 * convenient to work with.
 *
 * The method returns an array with the following elements:
 *   * status - one of the 3 STATUS constants.
 *   * curl_errno - A curl error number. Only set if status is
 *                  STATUS_CURLERROR.
 *   * curl_errmsg - A current error message. Only set if status is
 *                   STATUS_CURLERROR.
 *   * response - Response object. Only set if status is STATUS_SUCCESS, or
 *                STATUS_HTTPERROR.
 *   * http_code - HTTP status code, as an int. Only set if Only set if
 *                 status is STATUS_SUCCESS, or STATUS_HTTPERROR
 *
 * @param resource $curlHandle
 */',
        'startLine' => 461,
        'endLine' => 495,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'parseCurlResult' => 
      array (
        'name' => 'parseCurlResult',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
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
            'startLine' => 516,
            'endLine' => 516,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'curlHandle' => 
          array (
            'name' => 'curlHandle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 516,
            'endLine' => 516,
            'startColumn' => 58,
            'endColumn' => 68,
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
 * Parses the result of a curl call in a format that\'s a bit more
 * convenient to work with.
 *
 * The method returns an array with the following elements:
 *   * status - one of the 3 STATUS constants.
 *   * curl_errno - A curl error number. Only set if status is
 *                  STATUS_CURLERROR.
 *   * curl_errmsg - A current error message. Only set if status is
 *                   STATUS_CURLERROR.
 *   * response - Response object. Only set if status is STATUS_SUCCESS, or
 *                STATUS_HTTPERROR.
 *   * http_code - HTTP status code, as an int. Only set if Only set if
 *                 status is STATUS_SUCCESS, or STATUS_HTTPERROR
 *
 * @deprecated Use parseCurlResponse instead
 *
 * @param resource $curlHandle
 */',
        'startLine' => 516,
        'endLine' => 552,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'sendAsyncInternal' => 
      array (
        'name' => 'sendAsyncInternal',
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
                'name' => 'Sabre\\HTTP\\RequestInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 560,
            'endLine' => 560,
            'startColumn' => 42,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'success' => 
          array (
            'name' => 'success',
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
            'startLine' => 560,
            'endLine' => 560,
            'startColumn' => 69,
            'endColumn' => 85,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'error' => 
          array (
            'name' => 'error',
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
            'startLine' => 560,
            'endLine' => 560,
            'startColumn' => 88,
            'endColumn' => 102,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'retryCount' => 
          array (
            'name' => 'retryCount',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 560,
                'endLine' => 560,
                'startTokenPos' => 2600,
                'startFilePos' => 19079,
                'endTokenPos' => 2600,
                'endFilePos' => 19079,
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
            'startLine' => 560,
            'endLine' => 560,
            'startColumn' => 105,
            'endColumn' => 123,
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
 * Sends an asynchronous HTTP request.
 *
 * We keep this in a separate method, so we can call it without triggering
 * the beforeRequest event and don\'t do the poll().
 */',
        'startLine' => 560,
        'endLine' => 580,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'curlExec' => 
      array (
        'name' => 'curlExec',
        'parameters' => 
        array (
          'curlHandle' => 
          array (
            'name' => 'curlHandle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 591,
            'endLine' => 591,
            'startColumn' => 33,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Calls curl_exec.
 *
 * This method exists so it can easily be overridden and mocked.
 *
 * @param resource $curlHandle
 */',
        'startLine' => 591,
        'endLine' => 601,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
        'aliasName' => NULL,
      ),
      'curlStuff' => 
      array (
        'name' => 'curlStuff',
        'parameters' => 
        array (
          'curlHandle' => 
          array (
            'name' => 'curlHandle',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 610,
            'endLine' => 610,
            'startColumn' => 34,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns a bunch of information about a curl request.
 *
 * This method exists so it can easily be overridden and mocked.
 *
 * @param resource $curlHandle
 */',
        'startLine' => 610,
        'endLine' => 617,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Sabre\\HTTP',
        'declaringClassName' => 'Sabre\\HTTP\\Client',
        'implementingClassName' => 'Sabre\\HTTP\\Client',
        'currentClassName' => 'Sabre\\HTTP\\Client',
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