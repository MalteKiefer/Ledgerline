<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Support/Facades/Event.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Support\Facades\Event
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-fceacae933098a1bba734c7cb18e27a3d1faef87245bb56e05ec6f9ad301ea52-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Support\\Facades\\Event',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Support/Facades/Event.php',
      ),
    ),
    'namespace' => 'Illuminate\\Support\\Facades',
    'name' => 'Illuminate\\Support\\Facades\\Event',
    'shortName' => 'Event',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @method static void listen(\\Illuminate\\Events\\QueuedClosure|callable|array|string $events, \\Illuminate\\Events\\QueuedClosure|callable|array|string|null $listener = null)
 * @method static bool hasListeners(string $eventName)
 * @method static bool hasWildcardListeners(string $eventName)
 * @method static void push(string $event, object|array $payload = [])
 * @method static void flush(string $event)
 * @method static void subscribe(object|string $subscriber)
 * @method static array|null until(string|object $event, mixed $payload = [])
 * @method static array|null dispatch(string|object $event, mixed $payload = [], bool $halt = false)
 * @method static array getListeners(string $eventName)
 * @method static \\Closure makeListener(\\Closure|string|array $listener, bool $wildcard = false)
 * @method static \\Closure createClassListener(string $listener, bool $wildcard = false)
 * @method static void forget(string $event)
 * @method static void forgetPushed()
 * @method static \\Illuminate\\Events\\Dispatcher setQueueResolver(callable $resolver)
 * @method static \\Illuminate\\Events\\Dispatcher setTransactionManagerResolver(callable $resolver)
 * @method static mixed defer(callable $callback, string[]|null $events = null)
 * @method static array getRawListeners()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 * @method static string|null resolveConnectionFromQueueRoute(object $queueable)
 * @method static string|null resolveQueueFromQueueRoute(object $queueable)
 * @method static \\Illuminate\\Support\\Testing\\Fakes\\EventFake except(array|string $eventsToDispatch)
 * @method static void assertListening(string $expectedEvent, string|array $expectedListener)
 * @method static void assertDispatched(string|\\Closure $event, callable|int|null $callback = null)
 * @method static void assertDispatchedOnce(string $event)
 * @method static void assertDispatchedTimes(string $event, int $times = 1)
 * @method static void assertNotDispatched(string|\\Closure $event, callable|null $callback = null)
 * @method static void assertNothingDispatched()
 * @method static \\Illuminate\\Support\\Collection dispatched(string $event, callable|null $callback = null)
 * @method static bool hasDispatched(string $event)
 * @method static array dispatchedEvents()
 *
 * @see \\Illuminate\\Events\\Dispatcher
 * @see \\Illuminate\\Support\\Testing\\Fakes\\EventFake
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 46,
    'endLine' => 138,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Support\\Facades\\Facade',
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
      'fake' => 
      array (
        'name' => 'fake',
        'parameters' => 
        array (
          'eventsToFake' => 
          array (
            'name' => 'eventsToFake',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 54,
                'endLine' => 54,
                'startTokenPos' => 43,
                'startFilePos' => 2912,
                'endTokenPos' => 44,
                'endFilePos' => 2913,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 33,
            'endColumn' => 50,
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
 * Replace the bound instance with a fake.
 *
 * @param  array|string  $eventsToFake
 * @return \\Illuminate\\Support\\Testing\\Fakes\\EventFake
 */',
        'startLine' => 54,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Event',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Event',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Event',
        'aliasName' => NULL,
      ),
      'fakeExcept' => 
      array (
        'name' => 'fakeExcept',
        'parameters' => 
        array (
          'eventsToAllow' => 
          array (
            'name' => 'eventsToAllow',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Replace the bound instance with a fake that fakes all events except the given events.
 *
 * @param  string[]|string  $eventsToAllow
 * @return \\Illuminate\\Support\\Testing\\Fakes\\EventFake
 */',
        'startLine' => 74,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Event',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Event',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Event',
        'aliasName' => NULL,
      ),
      'fakeFor' => 
      array (
        'name' => 'fakeFor',
        'parameters' => 
        array (
          'callable' => 
          array (
            'name' => 'callable',
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 36,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'eventsToFake' => 
          array (
            'name' => 'eventsToFake',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 90,
                'endLine' => 90,
                'startTokenPos' => 212,
                'startFilePos' => 4039,
                'endTokenPos' => 213,
                'endFilePos' => 4040,
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 56,
            'endColumn' => 79,
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
 * Replace the bound instance with a fake during the given callable\'s execution.
 *
 * @param  callable  $callable
 * @param  array  $eventsToFake
 * @return mixed
 */',
        'startLine' => 90,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Event',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Event',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Event',
        'aliasName' => NULL,
      ),
      'fakeExceptFor' => 
      array (
        'name' => 'fakeExceptFor',
        'parameters' => 
        array (
          'callable' => 
          array (
            'name' => 'callable',
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
            'startLine' => 113,
            'endLine' => 113,
            'startColumn' => 42,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'eventsToAllow' => 
          array (
            'name' => 'eventsToAllow',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 113,
                'endLine' => 113,
                'startTokenPos' => 302,
                'startFilePos' => 4664,
                'endTokenPos' => 303,
                'endFilePos' => 4665,
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
            'startLine' => 113,
            'endLine' => 113,
            'startColumn' => 62,
            'endColumn' => 86,
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
 * Replace the bound instance with a fake during the given callable\'s execution.
 *
 * @param  callable  $callable
 * @param  array  $eventsToAllow
 * @return mixed
 */',
        'startLine' => 113,
        'endLine' => 127,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Event',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Event',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Event',
        'aliasName' => NULL,
      ),
      'getFacadeAccessor' => 
      array (
        'name' => 'getFacadeAccessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the registered name of the component.
 *
 * @return string
 */',
        'startLine' => 134,
        'endLine' => 137,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Event',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Event',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Event',
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