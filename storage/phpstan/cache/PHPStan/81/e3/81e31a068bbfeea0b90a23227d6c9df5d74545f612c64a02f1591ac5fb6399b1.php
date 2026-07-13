<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Database/Eloquent/Model.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Eloquent\Model
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-db33da0c4dcb414628bcce316f68c6eb39ccb59bce85abcf16db800981bd05d1-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Eloquent\\Model',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Database/Eloquent/Model.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Eloquent',
    'name' => 'Illuminate\\Database\\Eloquent\\Model',
    'shortName' => 'Model',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 42,
    'endLine' => 2895,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Support\\Arrayable',
      1 => 'ArrayAccess',
      2 => 'Illuminate\\Contracts\\Support\\CanBeEscapedWhenCastToString',
      3 => 'Illuminate\\Contracts\\Broadcasting\\HasBroadcastChannel',
      4 => 'Illuminate\\Contracts\\Support\\Jsonable',
      5 => 'JsonSerializable',
      6 => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
      7 => 'Stringable',
      8 => 'Illuminate\\Contracts\\Routing\\UrlRoutable',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasAttributes',
      1 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasEvents',
      2 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasGlobalScopes',
      3 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasRelationships',
      4 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasTimestamps',
      5 => 'Illuminate\\Database\\Eloquent\\Concerns\\HasUniqueIds',
      6 => 'Illuminate\\Database\\Eloquent\\Concerns\\HidesAttributes',
      7 => 'Illuminate\\Database\\Eloquent\\Concerns\\GuardsAttributes',
      8 => 'Illuminate\\Database\\Eloquent\\Concerns\\PreventsCircularRecursion',
      9 => 'Illuminate\\Database\\Eloquent\\Concerns\\TransformsToResource',
      10 => 'Illuminate\\Support\\Traits\\ForwardsCalls',
      11 => 'Illuminate\\Database\\Eloquent\\HasCollection',
    ),
    'immediateConstants' => 
    array (
      'CREATED_AT' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'CREATED_AT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'created_at\'',
          'attributes' => 
          array (
            'startLine' => 301,
            'endLine' => 301,
            'startTokenPos' => 693,
            'startFilePos' => 7804,
            'endTokenPos' => 693,
            'endFilePos' => 7815,
          ),
        ),
        'docComment' => '/**
 * The name of the "created at" column.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 301,
        'endLine' => 301,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'UPDATED_AT' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'UPDATED_AT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'updated_at\'',
          'attributes' => 
          array (
            'startLine' => 308,
            'endLine' => 308,
            'startTokenPos' => 704,
            'startFilePos' => 7933,
            'endTokenPos' => 704,
            'endFilePos' => 7944,
          ),
        ),
        'docComment' => '/**
 * The name of the "updated at" column.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 308,
        'endLine' => 308,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
    ),
    'immediateProperties' => 
    array (
      'connection' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'connection',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The connection name for the model.
 *
 * @var \\UnitEnum|string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'table' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The table associated with the model.
 *
 * @var string|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 70,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'primaryKey' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'primaryKey',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'id\'',
          'attributes' => 
          array (
            'startLine' => 77,
            'endLine' => 77,
            'startTokenPos' => 304,
            'startFilePos' => 2607,
            'endTokenPos' => 304,
            'endFilePos' => 2610,
          ),
        ),
        'docComment' => '/**
 * The primary key for the model.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 77,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'keyType' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'keyType',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'int\'',
          'attributes' => 
          array (
            'startLine' => 84,
            'endLine' => 84,
            'startTokenPos' => 315,
            'startFilePos' => 2722,
            'endTokenPos' => 315,
            'endFilePos' => 2726,
          ),
        ),
        'docComment' => '/**
 * The "type" of the primary key ID.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 84,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'incrementing' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'incrementing',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 91,
            'endLine' => 91,
            'startTokenPos' => 326,
            'startFilePos' => 2848,
            'endTokenPos' => 326,
            'endFilePos' => 2851,
          ),
        ),
        'docComment' => '/**
 * Indicates if the IDs are auto-incrementing.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 91,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'with' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'with',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 98,
            'endLine' => 98,
            'startTokenPos' => 337,
            'startFilePos' => 2969,
            'endTokenPos' => 338,
            'endFilePos' => 2970,
          ),
        ),
        'docComment' => '/**
 * The relations to eager load on every query.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 98,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'withCount' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'withCount',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 105,
            'endLine' => 105,
            'startTokenPos' => 349,
            'startFilePos' => 3117,
            'endTokenPos' => 350,
            'endFilePos' => 3118,
          ),
        ),
        'docComment' => '/**
 * The relationship counts that should be eager loaded on every query.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 105,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preventsLazyLoading' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'preventsLazyLoading',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 112,
            'endLine' => 112,
            'startTokenPos' => 361,
            'startFilePos' => 3267,
            'endTokenPos' => 361,
            'endFilePos' => 3271,
          ),
        ),
        'docComment' => '/**
 * Indicates whether lazy loading will be prevented on this model.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 112,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'perPage' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'perPage',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '15',
          'attributes' => 
          array (
            'startLine' => 119,
            'endLine' => 119,
            'startTokenPos' => 372,
            'startFilePos' => 3393,
            'endTokenPos' => 372,
            'endFilePos' => 3394,
          ),
        ),
        'docComment' => '/**
 * The number of models to return for pagination.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 119,
        'endLine' => 119,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exists' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'exists',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 126,
            'endLine' => 126,
            'startTokenPos' => 383,
            'startFilePos' => 3497,
            'endTokenPos' => 383,
            'endFilePos' => 3501,
          ),
        ),
        'docComment' => '/**
 * Indicates if the model exists.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 126,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'wasRecentlyCreated' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'wasRecentlyCreated',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 133,
            'endLine' => 133,
            'startTokenPos' => 394,
            'startFilePos' => 3652,
            'endTokenPos' => 394,
            'endFilePos' => 3656,
          ),
        ),
        'docComment' => '/**
 * Indicates if the model was inserted during the object\'s lifecycle.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 133,
        'endLine' => 133,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'escapeWhenCastingToString' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'escapeWhenCastingToString',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 140,
            'endLine' => 140,
            'startTokenPos' => 405,
            'startFilePos' => 3846,
            'endTokenPos' => 405,
            'endFilePos' => 3850,
          ),
        ),
        'docComment' => '/**
 * Indicates that the object\'s string representation should be escaped when __toString is invoked.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 140,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'resolver' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'resolver',
        'modifiers' => 18,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The connection resolver instance.
 *
 * @var \\Illuminate\\Database\\ConnectionResolverInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 147,
        'endLine' => 147,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'dispatcher' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'dispatcher',
        'modifiers' => 18,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher instance.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 154,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'booting' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'booting',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 161,
            'endLine' => 161,
            'startTokenPos' => 436,
            'startFilePos' => 4289,
            'endTokenPos' => 437,
            'endFilePos' => 4290,
          ),
        ),
        'docComment' => '/**
 * The models that are currently being booted.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 161,
        'endLine' => 161,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'booted' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'booted',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 168,
            'endLine' => 168,
            'startTokenPos' => 450,
            'startFilePos' => 4401,
            'endTokenPos' => 451,
            'endFilePos' => 4402,
          ),
        ),
        'docComment' => '/**
 * The array of booted models.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 168,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bootedCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'bootedCallbacks',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 175,
            'endLine' => 175,
            'startTokenPos' => 464,
            'startFilePos' => 4560,
            'endTokenPos' => 465,
            'endFilePos' => 4561,
          ),
        ),
        'docComment' => '/**
 * The callbacks that should be executed after the model has booted.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 175,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'traitInitializers' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'traitInitializers',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 182,
            'endLine' => 182,
            'startTokenPos' => 478,
            'startFilePos' => 4729,
            'endTokenPos' => 479,
            'endFilePos' => 4730,
          ),
        ),
        'docComment' => '/**
 * The array of trait initializers that will be called on each new instance.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 182,
        'endLine' => 182,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'globalScopes' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'globalScopes',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 189,
            'endLine' => 189,
            'startTokenPos' => 492,
            'startFilePos' => 4860,
            'endTokenPos' => 493,
            'endFilePos' => 4861,
          ),
        ),
        'docComment' => '/**
 * The array of global scopes on the model.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 189,
        'endLine' => 189,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'ignoreOnTouch' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'ignoreOnTouch',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 196,
            'endLine' => 196,
            'startTokenPos' => 506,
            'startFilePos' => 5018,
            'endTokenPos' => 507,
            'endFilePos' => 5019,
          ),
        ),
        'docComment' => '/**
 * The list of models classes that should not be affected with touch.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 196,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'modelsShouldPreventLazyLoading' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'modelsShouldPreventLazyLoading',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 203,
            'endLine' => 203,
            'startTokenPos' => 520,
            'startFilePos' => 5192,
            'endTokenPos' => 520,
            'endFilePos' => 5196,
          ),
        ),
        'docComment' => '/**
 * Indicates whether lazy loading should be restricted on all models.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 203,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'modelsShouldAutomaticallyEagerLoadRelationships' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'modelsShouldAutomaticallyEagerLoadRelationships',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 210,
            'endLine' => 210,
            'startTokenPos' => 533,
            'startFilePos' => 5416,
            'endTokenPos' => 533,
            'endFilePos' => 5420,
          ),
        ),
        'docComment' => '/**
 * Indicates whether relations should be automatically loaded on all models when they are accessed.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 210,
        'endLine' => 210,
        'startColumn' => 5,
        'endColumn' => 78,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lazyLoadingViolationCallback' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'lazyLoadingViolationCallback',
        'modifiers' => 18,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The callback that is responsible for handling lazy loading violations.
 *
 * @var (callable(self, string))|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 217,
        'endLine' => 217,
        'startColumn' => 5,
        'endColumn' => 51,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'modelsShouldPreventSilentlyDiscardingAttributes' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'modelsShouldPreventSilentlyDiscardingAttributes',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 224,
            'endLine' => 224,
            'startTokenPos' => 555,
            'startFilePos' => 5838,
            'endTokenPos' => 555,
            'endFilePos' => 5842,
          ),
        ),
        'docComment' => '/**
 * Indicates if an exception should be thrown instead of silently discarding non-fillable attributes.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 224,
        'endLine' => 224,
        'startColumn' => 5,
        'endColumn' => 78,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'discardedAttributeViolationCallback' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'discardedAttributeViolationCallback',
        'modifiers' => 18,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The callback that is responsible for handling discarded attribute violations.
 *
 * @var (callable(self, array))|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 231,
        'endLine' => 231,
        'startColumn' => 5,
        'endColumn' => 58,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'modelsShouldPreventAccessingMissingAttributes' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'modelsShouldPreventAccessingMissingAttributes',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 238,
            'endLine' => 238,
            'startTokenPos' => 577,
            'startFilePos' => 6279,
            'endTokenPos' => 577,
            'endFilePos' => 6283,
          ),
        ),
        'docComment' => '/**
 * Indicates if an exception should be thrown when trying to access a missing attribute on a retrieved model.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 238,
        'endLine' => 238,
        'startColumn' => 5,
        'endColumn' => 76,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'missingAttributeViolationCallback' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'missingAttributeViolationCallback',
        'modifiers' => 18,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The callback that is responsible for handling missing attribute violations.
 *
 * @var (callable(self, string))|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 245,
        'endLine' => 245,
        'startColumn' => 5,
        'endColumn' => 56,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isBroadcasting' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'isBroadcasting',
        'modifiers' => 18,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 252,
            'endLine' => 252,
            'startTokenPos' => 599,
            'startFilePos' => 6627,
            'endTokenPos' => 599,
            'endFilePos' => 6630,
          ),
        ),
        'docComment' => '/**
 * Indicates if broadcasting is currently enabled.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 252,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'builder' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'builder',
        'modifiers' => 18,
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
          'code' => '\\Illuminate\\Database\\Eloquent\\Builder::class',
          'attributes' => 
          array (
            'startLine' => 259,
            'endLine' => 259,
            'startTokenPos' => 614,
            'startFilePos' => 6825,
            'endTokenPos' => 616,
            'endFilePos' => 6838,
          ),
        ),
        'docComment' => '/**
 * The Eloquent query builder class to use for the model.
 *
 * @var class-string<\\Illuminate\\Database\\Eloquent\\Builder<*>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 259,
        'endLine' => 259,
        'startColumn' => 5,
        'endColumn' => 54,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'collectionClass' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'collectionClass',
        'modifiers' => 18,
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
          'code' => '\\Illuminate\\Database\\Eloquent\\Collection::class',
          'attributes' => 
          array (
            'startLine' => 266,
            'endLine' => 266,
            'startTokenPos' => 631,
            'startFilePos' => 7044,
            'endTokenPos' => 633,
            'endFilePos' => 7060,
          ),
        ),
        'docComment' => '/**
 * The Eloquent collection class to use for the model.
 *
 * @var class-string<\\Illuminate\\Database\\Eloquent\\Collection<*, *>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 266,
        'endLine' => 266,
        'startColumn' => 5,
        'endColumn' => 65,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isSoftDeletable' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'isSoftDeletable',
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
        'default' => NULL,
        'docComment' => '/**
 * Cache of soft deletable models.
 *
 * @var array<class-string<self>, bool>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 273,
        'endLine' => 273,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isPrunable' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'isPrunable',
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
        'default' => NULL,
        'docComment' => '/**
 * Cache of prunable models.
 *
 * @var array<class-string<self>, bool>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 280,
        'endLine' => 280,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'isMassPrunable' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'isMassPrunable',
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
        'default' => NULL,
        'docComment' => '/**
 * Cache of mass prunable models.
 *
 * @var array<class-string<self>, bool>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 287,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'classAttributes' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'name' => 'classAttributes',
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
            'startLine' => 294,
            'endLine' => 294,
            'startTokenPos' => 681,
            'startFilePos' => 7685,
            'endTokenPos' => 682,
            'endFilePos' => 7686,
          ),
        ),
        'docComment' => '/**
 * Cache of resolved class attributes.
 *
 * @var array<class-string<self>, array<class-string, mixed>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 294,
        'endLine' => 294,
        'startColumn' => 5,
        'endColumn' => 49,
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
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 315,
                'endLine' => 315,
                'startTokenPos' => 721,
                'startFilePos' => 8117,
                'endTokenPos' => 722,
                'endFilePos' => 8118,
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
            'startLine' => 315,
            'endLine' => 315,
            'startColumn' => 33,
            'endColumn' => 54,
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
 * Create a new Eloquent model instance.
 *
 * @param  array<string, mixed>  $attributes
 */',
        'startLine' => 315,
        'endLine' => 322,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'bootIfNotBooted' => 
      array (
        'name' => 'bootIfNotBooted',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if the model needs to be booted and if so, do it.
 *
 * @return void
 *
 * @throws \\LogicException
 */',
        'startLine' => 331,
        'endLine' => 358,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'booting' => 
      array (
        'name' => 'booting',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Perform any actions required before the model boots.
 *
 * @return void
 */',
        'startLine' => 365,
        'endLine' => 368,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'boot' => 
      array (
        'name' => 'boot',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Bootstrap the model and its traits.
 *
 * @return void
 */',
        'startLine' => 375,
        'endLine' => 378,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'bootTraits' => 
      array (
        'name' => 'bootTraits',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Boot all of the bootable traits on the model.
 *
 * @return void
 */',
        'startLine' => 385,
        'endLine' => 415,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'initializeTraits' => 
      array (
        'name' => 'initializeTraits',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Initialize any initializable traits on the model.
 *
 * @return void
 */',
        'startLine' => 422,
        'endLine' => 427,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'initializeModelAttributes' => 
      array (
        'name' => 'initializeModelAttributes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Initialize the model attributes from class attributes.
 *
 * @return void
 */',
        'startLine' => 434,
        'endLine' => 464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'booted' => 
      array (
        'name' => 'booted',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Perform any actions required after the model boots.
 *
 * @return void
 */',
        'startLine' => 471,
        'endLine' => 474,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'whenBooted' => 
      array (
        'name' => 'whenBooted',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 482,
            'endLine' => 482,
            'startColumn' => 42,
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
 * Register a closure to be executed after the model has booted.
 *
 * @param  \\Closure  $callback
 * @return void
 */',
        'startLine' => 482,
        'endLine' => 487,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'clearBootedModels' => 
      array (
        'name' => 'clearBootedModels',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clear the list of booted models so they will be re-booted.
 *
 * @return void
 */',
        'startLine' => 494,
        'endLine' => 500,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'withoutTouching' => 
      array (
        'name' => 'withoutTouching',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 508,
            'endLine' => 508,
            'startColumn' => 44,
            'endColumn' => 61,
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
 * Disables relationship model touching for the current class during given callback scope.
 *
 * @param  callable  $callback
 * @return void
 */',
        'startLine' => 508,
        'endLine' => 511,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'withoutTouchingOn' => 
      array (
        'name' => 'withoutTouchingOn',
        'parameters' => 
        array (
          'models' => 
          array (
            'name' => 'models',
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
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 46,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 61,
            'endColumn' => 78,
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
 * Disables relationship model touching for the given model classes during given callback scope.
 *
 * @param  array  $models
 * @param  callable  $callback
 * @return void
 */',
        'startLine' => 520,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isIgnoringTouch' => 
      array (
        'name' => 'isIgnoringTouch',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 537,
                'endLine' => 537,
                'startTokenPos' => 1901,
                'startFilePos' => 14351,
                'endTokenPos' => 1901,
                'endFilePos' => 14354,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 537,
            'endLine' => 537,
            'startColumn' => 44,
            'endColumn' => 56,
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
 * Determine if the given model is ignoring touches.
 *
 * @param  string|null  $class
 * @return bool
 */',
        'startLine' => 537,
        'endLine' => 559,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'shouldBeStrict' => 
      array (
        'name' => 'shouldBeStrict',
        'parameters' => 
        array (
          'shouldBeStrict' => 
          array (
            'name' => 'shouldBeStrict',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 567,
                'endLine' => 567,
                'startTokenPos' => 2054,
                'startFilePos' => 15181,
                'endTokenPos' => 2054,
                'endFilePos' => 15184,
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
            'startLine' => 567,
            'endLine' => 567,
            'startColumn' => 43,
            'endColumn' => 69,
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
 * Indicate that models should prevent lazy loading, silently discarding attributes, and accessing missing attributes.
 *
 * @param  bool  $shouldBeStrict
 * @return void
 */',
        'startLine' => 567,
        'endLine' => 572,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'preventLazyLoading' => 
      array (
        'name' => 'preventLazyLoading',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 580,
                'endLine' => 580,
                'startTokenPos' => 2099,
                'startFilePos' => 15576,
                'endTokenPos' => 2099,
                'endFilePos' => 15579,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 580,
            'endLine' => 580,
            'startColumn' => 47,
            'endColumn' => 59,
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
 * Prevent model relationships from being lazy loaded.
 *
 * @param  bool  $value
 * @return void
 */',
        'startLine' => 580,
        'endLine' => 583,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'automaticallyEagerLoadRelationships' => 
      array (
        'name' => 'automaticallyEagerLoadRelationships',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 591,
                'endLine' => 591,
                'startTokenPos' => 2129,
                'startFilePos' => 15888,
                'endTokenPos' => 2129,
                'endFilePos' => 15891,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 591,
            'endLine' => 591,
            'startColumn' => 64,
            'endColumn' => 76,
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
 * Determine if model relationships should be automatically eager loaded when accessed.
 *
 * @param  bool  $value
 * @return void
 */',
        'startLine' => 591,
        'endLine' => 594,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'handleLazyLoadingViolationUsing' => 
      array (
        'name' => 'handleLazyLoadingViolationUsing',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 602,
            'endLine' => 602,
            'startColumn' => 60,
            'endColumn' => 78,
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
 * Register a callback that is responsible for handling lazy loading violations.
 *
 * @param  (callable(self, string))|null  $callback
 * @return void
 */',
        'startLine' => 602,
        'endLine' => 605,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'preventSilentlyDiscardingAttributes' => 
      array (
        'name' => 'preventSilentlyDiscardingAttributes',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 613,
                'endLine' => 613,
                'startTokenPos' => 2188,
                'startFilePos' => 16531,
                'endTokenPos' => 2188,
                'endFilePos' => 16534,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 613,
            'endLine' => 613,
            'startColumn' => 64,
            'endColumn' => 76,
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
 * Prevent non-fillable attributes from being silently discarded.
 *
 * @param  bool  $value
 * @return void
 */',
        'startLine' => 613,
        'endLine' => 616,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'handleDiscardedAttributeViolationUsing' => 
      array (
        'name' => 'handleDiscardedAttributeViolationUsing',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 624,
            'endLine' => 624,
            'startColumn' => 67,
            'endColumn' => 85,
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
 * Register a callback that is responsible for handling discarded attribute violations.
 *
 * @param  (callable(self, array))|null  $callback
 * @return void
 */',
        'startLine' => 624,
        'endLine' => 627,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'preventAccessingMissingAttributes' => 
      array (
        'name' => 'preventAccessingMissingAttributes',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 635,
                'endLine' => 635,
                'startTokenPos' => 2247,
                'startFilePos' => 17187,
                'endTokenPos' => 2247,
                'endFilePos' => 17190,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 635,
            'endLine' => 635,
            'startColumn' => 62,
            'endColumn' => 74,
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
 * Prevent accessing missing attributes on retrieved models.
 *
 * @param  bool  $value
 * @return void
 */',
        'startLine' => 635,
        'endLine' => 638,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'handleMissingAttributeViolationUsing' => 
      array (
        'name' => 'handleMissingAttributeViolationUsing',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 646,
            'endLine' => 646,
            'startColumn' => 65,
            'endColumn' => 83,
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
 * Register a callback that is responsible for handling missing attribute violations.
 *
 * @param  (callable(self, string))|null  $callback
 * @return void
 */',
        'startLine' => 646,
        'endLine' => 649,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'withoutBroadcasting' => 
      array (
        'name' => 'withoutBroadcasting',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 659,
            'endLine' => 659,
            'startColumn' => 48,
            'endColumn' => 65,
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
 * Execute a callback without broadcasting any model events for all model types.
 *
 * @template TReturn
 *
 * @param  callable(): TReturn  $callback
 * @return TReturn
 */',
        'startLine' => 659,
        'endLine' => 670,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'fill' => 
      array (
        'name' => 'fill',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
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
            'startLine' => 680,
            'endLine' => 680,
            'startColumn' => 26,
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
 * Fill the model with an array of attributes.
 *
 * @param  array<string, mixed>  $attributes
 * @return $this
 *
 * @throws \\Illuminate\\Database\\Eloquent\\MassAssignmentException
 */',
        'startLine' => 680,
        'endLine' => 720,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'forceFill' => 
      array (
        'name' => 'forceFill',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
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
            'startLine' => 728,
            'endLine' => 728,
            'startColumn' => 31,
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
 * Fill the model with an array of attributes. Force mass assignment.
 *
 * @param  array<string, mixed>  $attributes
 * @return $this
 */',
        'startLine' => 728,
        'endLine' => 731,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'qualifyColumn' => 
      array (
        'name' => 'qualifyColumn',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 739,
            'endLine' => 739,
            'startColumn' => 35,
            'endColumn' => 41,
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
 * Qualify the given column name by the model\'s table.
 *
 * @param  string  $column
 * @return string
 */',
        'startLine' => 739,
        'endLine' => 746,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'qualifyColumns' => 
      array (
        'name' => 'qualifyColumns',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 754,
            'endLine' => 754,
            'startColumn' => 36,
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
 * Qualify the given columns with the model\'s table.
 *
 * @param  array  $columns
 * @return array
 */',
        'startLine' => 754,
        'endLine' => 759,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newInstance' => 
      array (
        'name' => 'newInstance',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 768,
                'endLine' => 768,
                'startTokenPos' => 2794,
                'startFilePos' => 21320,
                'endTokenPos' => 2795,
                'endFilePos' => 21321,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 768,
            'endLine' => 768,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'exists' => 
          array (
            'name' => 'exists',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 768,
                'endLine' => 768,
                'startTokenPos' => 2802,
                'startFilePos' => 21334,
                'endTokenPos' => 2802,
                'endFilePos' => 21338,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 768,
            'endLine' => 768,
            'startColumn' => 51,
            'endColumn' => 65,
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
 * Create a new instance of the given model.
 *
 * @param  array<string, mixed>  $attributes
 * @param  bool  $exists
 * @return static
 */',
        'startLine' => 768,
        'endLine' => 788,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newFromBuilder' => 
      array (
        'name' => 'newFromBuilder',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 797,
                'endLine' => 797,
                'startTokenPos' => 2896,
                'startFilePos' => 22147,
                'endTokenPos' => 2897,
                'endFilePos' => 22148,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 797,
            'endLine' => 797,
            'startColumn' => 36,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'connection' => 
          array (
            'name' => 'connection',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 797,
                'endLine' => 797,
                'startTokenPos' => 2904,
                'startFilePos' => 22165,
                'endTokenPos' => 2904,
                'endFilePos' => 22168,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 797,
            'endLine' => 797,
            'startColumn' => 54,
            'endColumn' => 71,
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
 * Create a new model instance that is existing.
 *
 * @param  array<string, mixed>  $attributes
 * @param  \\UnitEnum|string|null  $connection
 * @return static
 */',
        'startLine' => 797,
        'endLine' => 808,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'on' => 
      array (
        'name' => 'on',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 816,
                'endLine' => 816,
                'startTokenPos' => 2986,
                'startFilePos' => 22678,
                'endTokenPos' => 2986,
                'endFilePos' => 22681,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 816,
            'endLine' => 816,
            'startColumn' => 31,
            'endColumn' => 48,
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
 * Begin querying the model on a given connection.
 *
 * @param  \\UnitEnum|string|null  $connection
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 816,
        'endLine' => 822,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'onWriteConnection' => 
      array (
        'name' => 'onWriteConnection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin querying the model on the write connection.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 829,
        'endLine' => 832,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'all' => 
      array (
        'name' => 'all',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 840,
                'endLine' => 840,
                'startTokenPos' => 3060,
                'startFilePos' => 23505,
                'endTokenPos' => 3062,
                'endFilePos' => 23509,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 840,
            'endLine' => 840,
            'startColumn' => 32,
            'endColumn' => 47,
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
 * Get all of the models from the database.
 *
 * @param  array|string  $columns
 * @return \\Illuminate\\Database\\Eloquent\\Collection<int, static>
 */',
        'startLine' => 840,
        'endLine' => 845,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'with' => 
      array (
        'name' => 'with',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 853,
            'endLine' => 853,
            'startColumn' => 33,
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
 * Begin querying a model with eager loading.
 *
 * @param  array|string  $relations
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 853,
        'endLine' => 858,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'load' => 
      array (
        'name' => 'load',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 866,
            'endLine' => 866,
            'startColumn' => 26,
            'endColumn' => 35,
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
 * Eager load relations on the model.
 *
 * @param  array|string  $relations
 * @return $this
 */',
        'startLine' => 866,
        'endLine' => 875,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorph' => 
      array (
        'name' => 'loadMorph',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 884,
            'endLine' => 884,
            'startColumn' => 31,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 884,
            'endLine' => 884,
            'startColumn' => 42,
            'endColumn' => 51,
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
 * Eager load relationships on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @return $this
 */',
        'startLine' => 884,
        'endLine' => 895,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMissing' => 
      array (
        'name' => 'loadMissing',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 903,
            'endLine' => 903,
            'startColumn' => 33,
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
 * Eager load relations on the model if they are not already eager loaded.
 *
 * @param  array|string  $relations
 * @return $this
 */',
        'startLine' => 903,
        'endLine' => 910,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadAggregate' => 
      array (
        'name' => 'loadAggregate',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 920,
            'endLine' => 920,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 920,
            'endLine' => 920,
            'startColumn' => 47,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'function' => 
          array (
            'name' => 'function',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 920,
                'endLine' => 920,
                'startTokenPos' => 3356,
                'startFilePos' => 25488,
                'endTokenPos' => 3356,
                'endFilePos' => 25491,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 920,
            'endLine' => 920,
            'startColumn' => 56,
            'endColumn' => 71,
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
 * Eager load relation\'s column aggregations on the model.
 *
 * @param  array|string  $relations
 * @param  string  $column
 * @param  string|null  $function
 * @return $this
 */',
        'startLine' => 920,
        'endLine' => 925,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadCount' => 
      array (
        'name' => 'loadCount',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 933,
            'endLine' => 933,
            'startColumn' => 31,
            'endColumn' => 40,
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
 * Eager load relation counts on the model.
 *
 * @param  array|string  $relations
 * @return $this
 */',
        'startLine' => 933,
        'endLine' => 938,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMax' => 
      array (
        'name' => 'loadMax',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 947,
            'endLine' => 947,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 947,
            'endLine' => 947,
            'startColumn' => 41,
            'endColumn' => 47,
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
 * Eager load relation max column values on the model.
 *
 * @param  array|string  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 947,
        'endLine' => 950,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMin' => 
      array (
        'name' => 'loadMin',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 959,
            'endLine' => 959,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 959,
            'endLine' => 959,
            'startColumn' => 41,
            'endColumn' => 47,
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
 * Eager load relation min column values on the model.
 *
 * @param  array|string  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 959,
        'endLine' => 962,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadSum' => 
      array (
        'name' => 'loadSum',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 971,
            'endLine' => 971,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 971,
            'endLine' => 971,
            'startColumn' => 41,
            'endColumn' => 47,
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
 * Eager load relation\'s column summations on the model.
 *
 * @param  array|string  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 971,
        'endLine' => 974,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadAvg' => 
      array (
        'name' => 'loadAvg',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 983,
            'endLine' => 983,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 983,
            'endLine' => 983,
            'startColumn' => 41,
            'endColumn' => 47,
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
 * Eager load relation average column values on the model.
 *
 * @param  array|string  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 983,
        'endLine' => 986,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadExists' => 
      array (
        'name' => 'loadExists',
        'parameters' => 
        array (
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 994,
            'endLine' => 994,
            'startColumn' => 32,
            'endColumn' => 41,
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
 * Eager load related model existence values on the model.
 *
 * @param  array|string  $relations
 * @return $this
 */',
        'startLine' => 994,
        'endLine' => 997,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorphAggregate' => 
      array (
        'name' => 'loadMorphAggregate',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1008,
            'endLine' => 1008,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1008,
            'endLine' => 1008,
            'startColumn' => 51,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1008,
            'endLine' => 1008,
            'startColumn' => 63,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'function' => 
          array (
            'name' => 'function',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1008,
                'endLine' => 1008,
                'startTokenPos' => 3628,
                'startFilePos' => 27771,
                'endTokenPos' => 3628,
                'endFilePos' => 27774,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1008,
            'endLine' => 1008,
            'startColumn' => 72,
            'endColumn' => 87,
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
 * Eager load relationship column aggregation on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @param  string  $column
 * @param  string|null  $function
 * @return $this
 */',
        'startLine' => 1008,
        'endLine' => 1019,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorphCount' => 
      array (
        'name' => 'loadMorphCount',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1028,
            'endLine' => 1028,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1028,
            'endLine' => 1028,
            'startColumn' => 47,
            'endColumn' => 56,
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
 * Eager load relationship counts on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @return $this
 */',
        'startLine' => 1028,
        'endLine' => 1031,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorphMax' => 
      array (
        'name' => 'loadMorphMax',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1041,
            'endLine' => 1041,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1041,
            'endLine' => 1041,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1041,
            'endLine' => 1041,
            'startColumn' => 57,
            'endColumn' => 63,
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
 * Eager load relationship max column values on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @param  string  $column
 * @return $this
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
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorphMin' => 
      array (
        'name' => 'loadMorphMin',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1054,
            'endLine' => 1054,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1054,
            'endLine' => 1054,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1054,
            'endLine' => 1054,
            'startColumn' => 57,
            'endColumn' => 63,
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
 * Eager load relationship min column values on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 1054,
        'endLine' => 1057,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorphSum' => 
      array (
        'name' => 'loadMorphSum',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1067,
            'endLine' => 1067,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1067,
            'endLine' => 1067,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1067,
            'endLine' => 1067,
            'startColumn' => 57,
            'endColumn' => 63,
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
 * Eager load relationship column summations on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 1067,
        'endLine' => 1070,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'loadMorphAvg' => 
      array (
        'name' => 'loadMorphAvg',
        'parameters' => 
        array (
          'relation' => 
          array (
            'name' => 'relation',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1080,
            'endLine' => 1080,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'relations' => 
          array (
            'name' => 'relations',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1080,
            'endLine' => 1080,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1080,
            'endLine' => 1080,
            'startColumn' => 57,
            'endColumn' => 63,
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
 * Eager load relationship average column values on the polymorphic relation of a model.
 *
 * @param  string  $relation
 * @param  array  $relations
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 1080,
        'endLine' => 1083,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'increment' => 
      array (
        'name' => 'increment',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1093,
            'endLine' => 1093,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 1093,
                'endLine' => 1093,
                'startTokenPos' => 3913,
                'startFilePos' => 30171,
                'endTokenPos' => 3913,
                'endFilePos' => 30171,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1093,
            'endLine' => 1093,
            'startColumn' => 43,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1093,
                'endLine' => 1093,
                'startTokenPos' => 3922,
                'startFilePos' => 30189,
                'endTokenPos' => 3923,
                'endFilePos' => 30190,
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
            'startLine' => 1093,
            'endLine' => 1093,
            'startColumn' => 56,
            'endColumn' => 72,
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
 * Increment a column\'s value by a given amount.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @param  array  $extra
 * @return int
 */',
        'startLine' => 1093,
        'endLine' => 1096,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'decrement' => 
      array (
        'name' => 'decrement',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1106,
            'endLine' => 1106,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 1106,
                'endLine' => 1106,
                'startTokenPos' => 3964,
                'startFilePos' => 30530,
                'endTokenPos' => 3964,
                'endFilePos' => 30530,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1106,
            'endLine' => 1106,
            'startColumn' => 43,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1106,
                'endLine' => 1106,
                'startTokenPos' => 3973,
                'startFilePos' => 30548,
                'endTokenPos' => 3974,
                'endFilePos' => 30549,
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
            'startLine' => 1106,
            'endLine' => 1106,
            'startColumn' => 56,
            'endColumn' => 72,
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
 * Decrement a column\'s value by a given amount.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @param  array  $extra
 * @return int
 */',
        'startLine' => 1106,
        'endLine' => 1109,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'incrementOrDecrement' => 
      array (
        'name' => 'incrementOrDecrement',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1120,
            'endLine' => 1120,
            'startColumn' => 45,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1120,
            'endLine' => 1120,
            'startColumn' => 54,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1120,
            'endLine' => 1120,
            'startColumn' => 63,
            'endColumn' => 68,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
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
            'startLine' => 1120,
            'endLine' => 1120,
            'startColumn' => 71,
            'endColumn' => 77,
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
 * Run the increment or decrement method on the model.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @param  array  $extra
 * @param  string  $method
 * @return int
 */',
        'startLine' => 1120,
        'endLine' => 1147,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'update' => 
      array (
        'name' => 'update',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1156,
                'endLine' => 1156,
                'startTokenPos' => 4279,
                'startFilePos' => 32138,
                'endTokenPos' => 4280,
                'endFilePos' => 32139,
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
            'startLine' => 1156,
            'endLine' => 1156,
            'startColumn' => 28,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1156,
                'endLine' => 1156,
                'startTokenPos' => 4289,
                'startFilePos' => 32159,
                'endTokenPos' => 4290,
                'endFilePos' => 32160,
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
            'startLine' => 1156,
            'endLine' => 1156,
            'startColumn' => 52,
            'endColumn' => 70,
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
 * Update the model in the database.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $options
 * @return bool
 */',
        'startLine' => 1156,
        'endLine' => 1163,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'updateOrFail' => 
      array (
        'name' => 'updateOrFail',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1174,
                'endLine' => 1174,
                'startTokenPos' => 4345,
                'startFilePos' => 32587,
                'endTokenPos' => 4346,
                'endFilePos' => 32588,
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
            'startLine' => 1174,
            'endLine' => 1174,
            'startColumn' => 34,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1174,
                'endLine' => 1174,
                'startTokenPos' => 4355,
                'startFilePos' => 32608,
                'endTokenPos' => 4356,
                'endFilePos' => 32609,
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
            'startLine' => 1174,
            'endLine' => 1174,
            'startColumn' => 58,
            'endColumn' => 76,
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
 * Update the model in the database within a transaction.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $options
 * @return bool
 *
 * @throws \\Throwable
 */',
        'startLine' => 1174,
        'endLine' => 1181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'updateQuietly' => 
      array (
        'name' => 'updateQuietly',
        'parameters' => 
        array (
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1190,
                'endLine' => 1190,
                'startTokenPos' => 4411,
                'startFilePos' => 33016,
                'endTokenPos' => 4412,
                'endFilePos' => 33017,
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
            'startLine' => 1190,
            'endLine' => 1190,
            'startColumn' => 35,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1190,
                'endLine' => 1190,
                'startTokenPos' => 4421,
                'startFilePos' => 33037,
                'endTokenPos' => 4422,
                'endFilePos' => 33038,
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
            'startLine' => 1190,
            'endLine' => 1190,
            'startColumn' => 59,
            'endColumn' => 77,
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
 * Update the model in the database without raising any events.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $options
 * @return bool
 */',
        'startLine' => 1190,
        'endLine' => 1197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'incrementQuietly' => 
      array (
        'name' => 'incrementQuietly',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1207,
            'endLine' => 1207,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 1207,
                'endLine' => 1207,
                'startTokenPos' => 4478,
                'startFilePos' => 33461,
                'endTokenPos' => 4478,
                'endFilePos' => 33461,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1207,
            'endLine' => 1207,
            'startColumn' => 50,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1207,
                'endLine' => 1207,
                'startTokenPos' => 4487,
                'startFilePos' => 33479,
                'endTokenPos' => 4488,
                'endFilePos' => 33480,
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
            'startLine' => 1207,
            'endLine' => 1207,
            'startColumn' => 63,
            'endColumn' => 79,
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
 * Increment a column\'s value by a given amount without raising any events.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @param  array  $extra
 * @return int
 */',
        'startLine' => 1207,
        'endLine' => 1212,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'decrementQuietly' => 
      array (
        'name' => 'decrementQuietly',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1222,
            'endLine' => 1222,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'amount' => 
          array (
            'name' => 'amount',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 1222,
                'endLine' => 1222,
                'startTokenPos' => 4543,
                'startFilePos' => 33908,
                'endTokenPos' => 4543,
                'endFilePos' => 33908,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1222,
            'endLine' => 1222,
            'startColumn' => 50,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1222,
                'endLine' => 1222,
                'startTokenPos' => 4552,
                'startFilePos' => 33926,
                'endTokenPos' => 4553,
                'endFilePos' => 33927,
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
            'startLine' => 1222,
            'endLine' => 1222,
            'startColumn' => 63,
            'endColumn' => 79,
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
 * Decrement a column\'s value by a given amount without raising any events.
 *
 * @param  string  $column
 * @param  float|int  $amount
 * @param  array  $extra
 * @return int
 */',
        'startLine' => 1222,
        'endLine' => 1227,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'incrementEach' => 
      array (
        'name' => 'incrementEach',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
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
            'startLine' => 1236,
            'endLine' => 1236,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1236,
                'endLine' => 1236,
                'startTokenPos' => 4612,
                'startFilePos' => 34349,
                'endTokenPos' => 4613,
                'endFilePos' => 34350,
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
            'startLine' => 1236,
            'endLine' => 1236,
            'startColumn' => 54,
            'endColumn' => 70,
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
 * Increment each given column\'s value by the given amounts.
 *
 * @param  array<string, float|int>  $columns
 * @param  array<string, mixed>  $extra
 * @return int
 */',
        'startLine' => 1236,
        'endLine' => 1239,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'decrementEach' => 
      array (
        'name' => 'decrementEach',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
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
            'startLine' => 1248,
            'endLine' => 1248,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extra' => 
          array (
            'name' => 'extra',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1248,
                'endLine' => 1248,
                'startTokenPos' => 4655,
                'startFilePos' => 34718,
                'endTokenPos' => 4656,
                'endFilePos' => 34719,
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
            'startLine' => 1248,
            'endLine' => 1248,
            'startColumn' => 54,
            'endColumn' => 70,
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
 * Decrement each given column\'s value by the given amounts.
 *
 * @param  array<string, float|int>  $columns
 * @param  array<string, mixed>  $extra
 * @return int
 */',
        'startLine' => 1248,
        'endLine' => 1251,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'incrementOrDecrementEach' => 
      array (
        'name' => 'incrementOrDecrementEach',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
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
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 49,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extra' => 
          array (
            'name' => 'extra',
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
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 65,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'startLine' => 1261,
            'endLine' => 1261,
            'startColumn' => 79,
            'endColumn' => 92,
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
 * Run the incrementEach or decrementEach method on the model.
 *
 * @param  array<string, float|int>  $columns
 * @param  array<string, mixed>  $extra
 * @param  string  $method
 * @return int
 */',
        'startLine' => 1261,
        'endLine' => 1297,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'push' => 
      array (
        'name' => 'push',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Save the model and all of its relationships.
 *
 * @return bool
 */',
        'startLine' => 1304,
        'endLine' => 1328,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'pushQuietly' => 
      array (
        'name' => 'pushQuietly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Save the model and all of its relationships without raising any events to the parent model.
 *
 * @return bool
 */',
        'startLine' => 1335,
        'endLine' => 1338,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'saveQuietly' => 
      array (
        'name' => 'saveQuietly',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1346,
                'endLine' => 1346,
                'startTokenPos' => 5206,
                'startFilePos' => 37810,
                'endTokenPos' => 5207,
                'endFilePos' => 37811,
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
            'startLine' => 1346,
            'endLine' => 1346,
            'startColumn' => 33,
            'endColumn' => 51,
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
 * Save the model to the database without raising any events.
 *
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1346,
        'endLine' => 1349,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'save' => 
      array (
        'name' => 'save',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1357,
                'endLine' => 1357,
                'startTokenPos' => 5250,
                'startFilePos' => 38052,
                'endTokenPos' => 5251,
                'endFilePos' => 38053,
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
            'startLine' => 1357,
            'endLine' => 1357,
            'startColumn' => 26,
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
 * Save the model to the database.
 *
 * @param  array  $options
 * @return bool
 */',
        'startLine' => 1357,
        'endLine' => 1398,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'saveOrIgnore' => 
      array (
        'name' => 'saveOrIgnore',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1407,
                'endLine' => 1407,
                'startTokenPos' => 5455,
                'startFilePos' => 39980,
                'endTokenPos' => 5456,
                'endFilePos' => 39981,
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
            'startLine' => 1407,
            'endLine' => 1407,
            'startColumn' => 34,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'uniqueBy' => 
          array (
            'name' => 'uniqueBy',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1407,
                'endLine' => 1407,
                'startTokenPos' => 5469,
                'startFilePos' => 40014,
                'endTokenPos' => 5469,
                'endFilePos' => 40017,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1407,
            'endLine' => 1407,
            'startColumn' => 55,
            'endColumn' => 88,
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
 * Save the model to the database, ignoring specific unique constraint conflicts.
 *
 * @param  array  $options
 * @param  array|string|null  $uniqueBy
 * @return bool
 */',
        'startLine' => 1407,
        'endLine' => 1433,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'saveOrFail' => 
      array (
        'name' => 'saveOrFail',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1443,
                'endLine' => 1443,
                'startTokenPos' => 5632,
                'startFilePos' => 40891,
                'endTokenPos' => 5633,
                'endFilePos' => 40892,
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
            'startLine' => 1443,
            'endLine' => 1443,
            'startColumn' => 32,
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
 * Save the model to the database within a transaction.
 *
 * @param  array  $options
 * @return bool
 *
 * @throws \\Throwable
 */',
        'startLine' => 1443,
        'endLine' => 1446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'finishSave' => 
      array (
        'name' => 'finishSave',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
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
            'startLine' => 1454,
            'endLine' => 1454,
            'startColumn' => 35,
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
 * Perform any actions that are necessary after the model is saved.
 *
 * @param  array  $options
 * @return void
 */',
        'startLine' => 1454,
        'endLine' => 1463,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'performUpdate' => 
      array (
        'name' => 'performUpdate',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1471,
            'endLine' => 1471,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Perform a model update operation.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $query
 * @return bool
 */',
        'startLine' => 1471,
        'endLine' => 1501,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setKeysForSelectQuery' => 
      array (
        'name' => 'setKeysForSelectQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1509,
            'endLine' => 1509,
            'startColumn' => 46,
            'endColumn' => 51,
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
 * Set the keys for a select query.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $query
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1509,
        'endLine' => 1514,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getKeyForSelectQuery' => 
      array (
        'name' => 'getKeyForSelectQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the primary key value for a select query.
 *
 * @return mixed
 */',
        'startLine' => 1521,
        'endLine' => 1524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setKeysForSaveQuery' => 
      array (
        'name' => 'setKeysForSaveQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1532,
            'endLine' => 1532,
            'startColumn' => 44,
            'endColumn' => 49,
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
 * Set the keys for a save update query.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $query
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1532,
        'endLine' => 1537,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getKeyForSaveQuery' => 
      array (
        'name' => 'getKeyForSaveQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the primary key value for a save query.
 *
 * @return mixed
 */',
        'startLine' => 1544,
        'endLine' => 1547,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'performInsert' => 
      array (
        'name' => 'performInsert',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1555,
            'endLine' => 1555,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Perform a model insert operation.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $query
 * @return bool
 */',
        'startLine' => 1555,
        'endLine' => 1602,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'performInsertOrIgnore' => 
      array (
        'name' => 'performInsertOrIgnore',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1611,
            'endLine' => 1611,
            'startColumn' => 46,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'uniqueBy' => 
          array (
            'name' => 'uniqueBy',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1611,
            'endLine' => 1611,
            'startColumn' => 62,
            'endColumn' => 88,
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
 * Perform a model insert operation, ignoring specific unique constraint conflicts.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $query
 * @param  array|string|null  $uniqueBy
 * @return bool
 */',
        'startLine' => 1611,
        'endLine' => 1651,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'insertAndSetId' => 
      array (
        'name' => 'insertAndSetId',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Eloquent\\Builder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1660,
            'endLine' => 1660,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1660,
            'endLine' => 1660,
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
        'docComment' => '/**
 * Insert the given attributes and set the ID on the model.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $query
 * @param  array<string, mixed>  $attributes
 * @return void
 */',
        'startLine' => 1660,
        'endLine' => 1665,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'destroy' => 
      array (
        'name' => 'destroy',
        'parameters' => 
        array (
          'ids' => 
          array (
            'name' => 'ids',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1673,
            'endLine' => 1673,
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
 * Destroy the models for the given IDs.
 *
 * @param  \\Illuminate\\Support\\Collection|array|int|string  $ids
 * @return int
 */',
        'startLine' => 1673,
        'endLine' => 1703,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete the model from the database.
 *
 * @return bool|null
 *
 * @throws \\LogicException
 */',
        'startLine' => 1712,
        'endLine' => 1744,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'deleteQuietly' => 
      array (
        'name' => 'deleteQuietly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete the model from the database without raising any events.
 *
 * @return bool
 */',
        'startLine' => 1751,
        'endLine' => 1754,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'deleteOrFail' => 
      array (
        'name' => 'deleteOrFail',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete the model from the database within a transaction.
 *
 * @return bool|null
 *
 * @throws \\Throwable
 */',
        'startLine' => 1763,
        'endLine' => 1770,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'forceDelete' => 
      array (
        'name' => 'forceDelete',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Force a hard delete on a soft deleted model.
 *
 * This method protects developers from running forceDelete when the trait is missing.
 *
 * @return bool|null
 */',
        'startLine' => 1779,
        'endLine' => 1782,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'forceDestroy' => 
      array (
        'name' => 'forceDestroy',
        'parameters' => 
        array (
          'ids' => 
          array (
            'name' => 'ids',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1792,
            'endLine' => 1792,
            'startColumn' => 41,
            'endColumn' => 44,
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
 * Force a hard destroy on a soft deleted model.
 *
 * This method protects developers from running forceDestroy when the trait is missing.
 *
 * @param  \\Illuminate\\Support\\Collection|array|int|string  $ids
 * @return bool|null
 */',
        'startLine' => 1792,
        'endLine' => 1795,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'performDeleteOnModel' => 
      array (
        'name' => 'performDeleteOnModel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Perform the actual delete query on this model instance.
 *
 * @return void
 */',
        'startLine' => 1802,
        'endLine' => 1807,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'query' => 
      array (
        'name' => 'query',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Begin querying the model.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1814,
        'endLine' => 1817,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newQuery' => 
      array (
        'name' => 'newQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new query builder for the model\'s table.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1824,
        'endLine' => 1827,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newModelQuery' => 
      array (
        'name' => 'newModelQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new query builder that doesn\'t have any global scopes or eager loading.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1834,
        'endLine' => 1839,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newQueryWithoutRelationships' => 
      array (
        'name' => 'newQueryWithoutRelationships',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new query builder with no relationships loaded.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1846,
        'endLine' => 1849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'registerGlobalScopes' => 
      array (
        'name' => 'registerGlobalScopes',
        'parameters' => 
        array (
          'builder' => 
          array (
            'name' => 'builder',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1857,
            'endLine' => 1857,
            'startColumn' => 42,
            'endColumn' => 49,
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
 * Register the global scopes for this builder instance.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Builder<static>  $builder
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1857,
        'endLine' => 1864,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newQueryWithoutScopes' => 
      array (
        'name' => 'newQueryWithoutScopes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new query builder that doesn\'t have any global scopes.
 *
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1871,
        'endLine' => 1876,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newQueryWithoutScope' => 
      array (
        'name' => 'newQueryWithoutScope',
        'parameters' => 
        array (
          'scope' => 
          array (
            'name' => 'scope',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1884,
            'endLine' => 1884,
            'startColumn' => 42,
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
 * Get a new query instance without a given scope.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Scope|string  $scope
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1884,
        'endLine' => 1887,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newQueryForRestoration' => 
      array (
        'name' => 'newQueryForRestoration',
        'parameters' => 
        array (
          'ids' => 
          array (
            'name' => 'ids',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1895,
            'endLine' => 1895,
            'startColumn' => 44,
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
 * Get a new query to restore one or more models by their queueable IDs.
 *
 * @param  array|int  $ids
 * @return \\Illuminate\\Database\\Eloquent\\Builder<static>
 */',
        'startLine' => 1895,
        'endLine' => 1898,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newEloquentBuilder' => 
      array (
        'name' => 'newEloquentBuilder',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1906,
            'endLine' => 1906,
            'startColumn' => 40,
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
 * Create a new Eloquent query builder for the model.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder  $query
 * @return \\Illuminate\\Database\\Eloquent\\Builder<*>
 */',
        'startLine' => 1906,
        'endLine' => 1915,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveCustomBuilderClass' => 
      array (
        'name' => 'resolveCustomBuilderClass',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Resolve the custom Eloquent builder class from the model attributes.
 *
 * @return class-string<\\Illuminate\\Database\\Eloquent\\Builder>|false
 */',
        'startLine' => 1922,
        'endLine' => 1930,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newBaseQueryBuilder' => 
      array (
        'name' => 'newBaseQueryBuilder',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a new query builder instance for the connection.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 1937,
        'endLine' => 1940,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'newPivot' => 
      array (
        'name' => 'newPivot',
        'parameters' => 
        array (
          'parent' => 
          array (
            'name' => 'parent',
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
            'startLine' => 1952,
            'endLine' => 1952,
            'startColumn' => 30,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
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
            'startLine' => 1952,
            'endLine' => 1952,
            'startColumn' => 44,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1952,
            'endLine' => 1952,
            'startColumn' => 63,
            'endColumn' => 68,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'exists' => 
          array (
            'name' => 'exists',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1952,
            'endLine' => 1952,
            'startColumn' => 71,
            'endColumn' => 77,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'using' => 
          array (
            'name' => 'using',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1952,
                'endLine' => 1952,
                'startTokenPos' => 7504,
                'startFilePos' => 55631,
                'endTokenPos' => 7504,
                'endFilePos' => 55634,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1952,
            'endLine' => 1952,
            'startColumn' => 80,
            'endColumn' => 92,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new pivot model instance.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Model  $parent
 * @param  array<string, mixed>  $attributes
 * @param  string  $table
 * @param  bool  $exists
 * @param  string|null  $using
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\Pivot
 */',
        'startLine' => 1952,
        'endLine' => 1956,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'hasNamedScope' => 
      array (
        'name' => 'hasNamedScope',
        'parameters' => 
        array (
          'scope' => 
          array (
            'name' => 'scope',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1964,
            'endLine' => 1964,
            'startColumn' => 35,
            'endColumn' => 40,
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
 * Determine if the model has a given scope.
 *
 * @param  string  $scope
 * @return bool
 */',
        'startLine' => 1964,
        'endLine' => 1968,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'callNamedScope' => 
      array (
        'name' => 'callNamedScope',
        'parameters' => 
        array (
          'scope' => 
          array (
            'name' => 'scope',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1977,
            'endLine' => 1977,
            'startColumn' => 36,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1977,
                'endLine' => 1977,
                'startTokenPos' => 7609,
                'startFilePos' => 56331,
                'endTokenPos' => 7610,
                'endFilePos' => 56332,
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
            'startLine' => 1977,
            'endLine' => 1977,
            'startColumn' => 44,
            'endColumn' => 65,
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
 * Apply the given named scope if possible.
 *
 * @param  string  $scope
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 1977,
        'endLine' => 1984,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isScopeMethodWithAttribute' => 
      array (
        'name' => 'isScopeMethodWithAttribute',
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
            'startLine' => 1992,
            'endLine' => 1992,
            'startColumn' => 58,
            'endColumn' => 71,
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
 * Determine if the given method has a scope attribute.
 *
 * @param  string  $method
 * @return bool
 */',
        'startLine' => 1992,
        'endLine' => 2001,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'toArray' => 
      array (
        'name' => 'toArray',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Convert the model instance to an array.
 *
 * @return array
 */',
        'startLine' => 2008,
        'endLine' => 2014,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'toJson' => 
      array (
        'name' => 'toJson',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 2024,
                'endLine' => 2024,
                'startTokenPos' => 7823,
                'startFilePos' => 57582,
                'endTokenPos' => 7823,
                'endFilePos' => 57582,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2024,
            'endLine' => 2024,
            'startColumn' => 28,
            'endColumn' => 39,
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
 * Convert the model instance to JSON.
 *
 * @param  int  $options
 * @return string
 *
 * @throws \\Illuminate\\Database\\Eloquent\\JsonEncodingException
 */',
        'startLine' => 2024,
        'endLine' => 2033,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'toPrettyJson' => 
      array (
        'name' => 'toPrettyJson',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 2043,
                'endLine' => 2043,
                'startTokenPos' => 7905,
                'startFilePos' => 58109,
                'endTokenPos' => 7905,
                'endFilePos' => 58109,
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
            'startLine' => 2043,
            'endLine' => 2043,
            'startColumn' => 34,
            'endColumn' => 49,
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
 * Convert the model instance to pretty print formatted JSON.
 *
 * @param  int  $options
 * @return string
 *
 * @throws \\Illuminate\\Database\\Eloquent\\JsonEncodingException
 */',
        'startLine' => 2043,
        'endLine' => 2046,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'jsonSerialize' => 
      array (
        'name' => 'jsonSerialize',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Convert the object into something JSON serializable.
 *
 * @return mixed
 */',
        'startLine' => 2053,
        'endLine' => 2056,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'fresh' => 
      array (
        'name' => 'fresh',
        'parameters' => 
        array (
          'with' => 
          array (
            'name' => 'with',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2064,
                'endLine' => 2064,
                'startTokenPos' => 7964,
                'startFilePos' => 58553,
                'endTokenPos' => 7965,
                'endFilePos' => 58554,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2064,
            'endLine' => 2064,
            'startColumn' => 27,
            'endColumn' => 36,
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
 * Reload a fresh model instance from the database.
 *
 * @param  array|string  $with
 * @return static|null
 */',
        'startLine' => 2064,
        'endLine' => 2074,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'refresh' => 
      array (
        'name' => 'refresh',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reload the current model instance with fresh attributes from the database.
 *
 * @return $this
 */',
        'startLine' => 2081,
        'endLine' => 2102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'replicate' => 
      array (
        'name' => 'replicate',
        'parameters' => 
        array (
          'except' => 
          array (
            'name' => 'except',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2110,
                'endLine' => 2110,
                'startTokenPos' => 8192,
                'startFilePos' => 59762,
                'endTokenPos' => 8192,
                'endFilePos' => 59765,
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
            'startLine' => 2110,
            'endLine' => 2110,
            'startColumn' => 31,
            'endColumn' => 51,
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
 * Clone the model into a new, non-existing instance.
 *
 * @param  array|null  $except
 * @return static
 */',
        'startLine' => 2110,
        'endLine' => 2131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'replicateQuietly' => 
      array (
        'name' => 'replicateQuietly',
        'parameters' => 
        array (
          'except' => 
          array (
            'name' => 'except',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2139,
                'endLine' => 2139,
                'startTokenPos' => 8355,
                'startFilePos' => 60654,
                'endTokenPos' => 8355,
                'endFilePos' => 60657,
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
            'startLine' => 2139,
            'endLine' => 2139,
            'startColumn' => 38,
            'endColumn' => 58,
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
 * Clone the model into a new, non-existing instance without raising any events.
 *
 * @param  array|null  $except
 * @return static
 */',
        'startLine' => 2139,
        'endLine' => 2142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'is' => 
      array (
        'name' => 'is',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2150,
            'endLine' => 2150,
            'startColumn' => 24,
            'endColumn' => 29,
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
 * Determine if two models have the same ID and belong to the same table.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Model|null  $model
 * @return bool
 */',
        'startLine' => 2150,
        'endLine' => 2156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isNot' => 
      array (
        'name' => 'isNot',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2164,
            'endLine' => 2164,
            'startColumn' => 27,
            'endColumn' => 32,
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
 * Determine if two models are not the same.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Model|null  $model
 * @return bool
 */',
        'startLine' => 2164,
        'endLine' => 2167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getConnection' => 
      array (
        'name' => 'getConnection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database connection for the model.
 *
 * @return \\Illuminate\\Database\\Connection
 */',
        'startLine' => 2174,
        'endLine' => 2177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getConnectionName' => 
      array (
        'name' => 'getConnectionName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current connection name for the model.
 *
 * @return string|null
 */',
        'startLine' => 2184,
        'endLine' => 2187,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setConnection' => 
      array (
        'name' => 'setConnection',
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
            'startLine' => 2195,
            'endLine' => 2195,
            'startColumn' => 35,
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
 * Set the connection associated with the model.
 *
 * @param  \\UnitEnum|string|null  $name
 * @return $this
 */',
        'startLine' => 2195,
        'endLine' => 2200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveConnection' => 
      array (
        'name' => 'resolveConnection',
        'parameters' => 
        array (
          'connection' => 
          array (
            'name' => 'connection',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2208,
                'endLine' => 2208,
                'startTokenPos' => 8579,
                'startFilePos' => 62343,
                'endTokenPos' => 8579,
                'endFilePos' => 62346,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2208,
            'endLine' => 2208,
            'startColumn' => 46,
            'endColumn' => 63,
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
 * Resolve a connection instance.
 *
 * @param  \\UnitEnum|string|null  $connection
 * @return \\Illuminate\\Database\\Connection
 */',
        'startLine' => 2208,
        'endLine' => 2211,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getConnectionResolver' => 
      array (
        'name' => 'getConnectionResolver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the connection resolver instance.
 *
 * @return \\Illuminate\\Database\\ConnectionResolverInterface|null
 */',
        'startLine' => 2218,
        'endLine' => 2221,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setConnectionResolver' => 
      array (
        'name' => 'setConnectionResolver',
        'parameters' => 
        array (
          'resolver' => 
          array (
            'name' => 'resolver',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\ConnectionResolverInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2229,
            'endLine' => 2229,
            'startColumn' => 50,
            'endColumn' => 67,
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
 * Set the connection resolver instance.
 *
 * @param  \\Illuminate\\Database\\ConnectionResolverInterface  $resolver
 * @return void
 */',
        'startLine' => 2229,
        'endLine' => 2232,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'unsetConnectionResolver' => 
      array (
        'name' => 'unsetConnectionResolver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Unset the connection resolver for models.
 *
 * @return void
 */',
        'startLine' => 2239,
        'endLine' => 2242,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getTable' => 
      array (
        'name' => 'getTable',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the table associated with the model.
 *
 * @return string
 */',
        'startLine' => 2249,
        'endLine' => 2252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setTable' => 
      array (
        'name' => 'setTable',
        'parameters' => 
        array (
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 30,
            'endColumn' => 35,
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
 * Set the table associated with the model.
 *
 * @param  string  $table
 * @return $this
 */',
        'startLine' => 2260,
        'endLine' => 2265,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getKeyName' => 
      array (
        'name' => 'getKeyName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the primary key for the model.
 *
 * @return string
 */',
        'startLine' => 2272,
        'endLine' => 2275,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setKeyName' => 
      array (
        'name' => 'setKeyName',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2283,
            'endLine' => 2283,
            'startColumn' => 32,
            'endColumn' => 35,
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
 * Set the primary key for the model.
 *
 * @param  string  $key
 * @return $this
 */',
        'startLine' => 2283,
        'endLine' => 2288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getQualifiedKeyName' => 
      array (
        'name' => 'getQualifiedKeyName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the table qualified key name.
 *
 * @return string
 */',
        'startLine' => 2295,
        'endLine' => 2298,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getKeyType' => 
      array (
        'name' => 'getKeyType',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the auto-incrementing key type.
 *
 * @return string
 */',
        'startLine' => 2305,
        'endLine' => 2308,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setKeyType' => 
      array (
        'name' => 'setKeyType',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2316,
            'endLine' => 2316,
            'startColumn' => 32,
            'endColumn' => 36,
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
 * Set the data type for the primary key.
 *
 * @param  string  $type
 * @return $this
 */',
        'startLine' => 2316,
        'endLine' => 2321,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getIncrementing' => 
      array (
        'name' => 'getIncrementing',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value indicating whether the IDs are incrementing.
 *
 * @return bool
 */',
        'startLine' => 2328,
        'endLine' => 2331,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setIncrementing' => 
      array (
        'name' => 'setIncrementing',
        'parameters' => 
        array (
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
            'startLine' => 2339,
            'endLine' => 2339,
            'startColumn' => 37,
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
 * Set whether IDs are incrementing.
 *
 * @param  bool  $value
 * @return $this
 */',
        'startLine' => 2339,
        'endLine' => 2344,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getKey' => 
      array (
        'name' => 'getKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value of the model\'s primary key.
 *
 * @return mixed
 */',
        'startLine' => 2351,
        'endLine' => 2354,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getQueueableId' => 
      array (
        'name' => 'getQueueableId',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queueable identity for the entity.
 *
 * @return mixed
 */',
        'startLine' => 2361,
        'endLine' => 2364,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getQueueableRelations' => 
      array (
        'name' => 'getQueueableRelations',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queueable relationships for the entity.
 *
 * @return array
 */',
        'startLine' => 2371,
        'endLine' => 2398,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getQueueableConnection' => 
      array (
        'name' => 'getQueueableConnection',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the queueable connection for the entity.
 *
 * @return string|null
 */',
        'startLine' => 2405,
        'endLine' => 2408,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getRouteKey' => 
      array (
        'name' => 'getRouteKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value of the model\'s route key.
 *
 * @return mixed
 */',
        'startLine' => 2415,
        'endLine' => 2418,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getRouteKeyName' => 
      array (
        'name' => 'getRouteKeyName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the route key for the model.
 *
 * @return string
 */',
        'startLine' => 2425,
        'endLine' => 2428,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveRouteBinding' => 
      array (
        'name' => 'resolveRouteBinding',
        'parameters' => 
        array (
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
            'startLine' => 2437,
            'endLine' => 2437,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2437,
                'endLine' => 2437,
                'startTokenPos' => 9252,
                'startFilePos' => 67196,
                'endTokenPos' => 9252,
                'endFilePos' => 67199,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2437,
            'endLine' => 2437,
            'startColumn' => 49,
            'endColumn' => 61,
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
 * Retrieve the model for a bound value.
 *
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \\Illuminate\\Database\\Eloquent\\Model|null
 */',
        'startLine' => 2437,
        'endLine' => 2440,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveSoftDeletableRouteBinding' => 
      array (
        'name' => 'resolveSoftDeletableRouteBinding',
        'parameters' => 
        array (
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
            'startLine' => 2449,
            'endLine' => 2449,
            'startColumn' => 54,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2449,
                'endLine' => 2449,
                'startTokenPos' => 9294,
                'startFilePos' => 67553,
                'endTokenPos' => 9294,
                'endFilePos' => 67556,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2449,
            'endLine' => 2449,
            'startColumn' => 62,
            'endColumn' => 74,
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
 * Retrieve the model for a bound value.
 *
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \\Illuminate\\Database\\Eloquent\\Model|null
 */',
        'startLine' => 2449,
        'endLine' => 2452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveChildRouteBinding' => 
      array (
        'name' => 'resolveChildRouteBinding',
        'parameters' => 
        array (
          'childType' => 
          array (
            'name' => 'childType',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2462,
            'endLine' => 2462,
            'startColumn' => 46,
            'endColumn' => 55,
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
            'startLine' => 2462,
            'endLine' => 2462,
            'startColumn' => 58,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2462,
            'endLine' => 2462,
            'startColumn' => 66,
            'endColumn' => 71,
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
 * Retrieve the child model for a bound value.
 *
 * @param  string  $childType
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \\Illuminate\\Database\\Eloquent\\Model|null
 */',
        'startLine' => 2462,
        'endLine' => 2465,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveSoftDeletableChildRouteBinding' => 
      array (
        'name' => 'resolveSoftDeletableChildRouteBinding',
        'parameters' => 
        array (
          'childType' => 
          array (
            'name' => 'childType',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2475,
            'endLine' => 2475,
            'startColumn' => 59,
            'endColumn' => 68,
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
            'startLine' => 2475,
            'endLine' => 2475,
            'startColumn' => 71,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2475,
            'endLine' => 2475,
            'startColumn' => 79,
            'endColumn' => 84,
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
 * Retrieve the child model for a bound value.
 *
 * @param  string  $childType
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \\Illuminate\\Database\\Eloquent\\Model|null
 */',
        'startLine' => 2475,
        'endLine' => 2478,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveChildRouteBindingQuery' => 
      array (
        'name' => 'resolveChildRouteBindingQuery',
        'parameters' => 
        array (
          'childType' => 
          array (
            'name' => 'childType',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2488,
            'endLine' => 2488,
            'startColumn' => 54,
            'endColumn' => 63,
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
            'startLine' => 2488,
            'endLine' => 2488,
            'startColumn' => 66,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2488,
            'endLine' => 2488,
            'startColumn' => 74,
            'endColumn' => 79,
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
 * Retrieve the child model query for a bound value.
 *
 * @param  string  $childType
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \\Illuminate\\Database\\Eloquent\\Relations\\Relation<\\Illuminate\\Database\\Eloquent\\Model, $this, *>
 */',
        'startLine' => 2488,
        'endLine' => 2502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'childRouteBindingRelationshipName' => 
      array (
        'name' => 'childRouteBindingRelationshipName',
        'parameters' => 
        array (
          'childType' => 
          array (
            'name' => 'childType',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2510,
            'endLine' => 2510,
            'startColumn' => 58,
            'endColumn' => 67,
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
 * Retrieve the child route model binding relationship name for the given child type.
 *
 * @param  string  $childType
 * @return string
 */',
        'startLine' => 2510,
        'endLine' => 2513,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveRouteBindingQuery' => 
      array (
        'name' => 'resolveRouteBindingQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2523,
            'endLine' => 2523,
            'startColumn' => 46,
            'endColumn' => 51,
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
            'startLine' => 2523,
            'endLine' => 2523,
            'startColumn' => 54,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'field' => 
          array (
            'name' => 'field',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2523,
                'endLine' => 2523,
                'startTokenPos' => 9599,
                'startFilePos' => 70200,
                'endTokenPos' => 9599,
                'endFilePos' => 70203,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2523,
            'endLine' => 2523,
            'startColumn' => 62,
            'endColumn' => 74,
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
 * Retrieve the model for a bound value.
 *
 * @param  \\Illuminate\\Database\\Eloquent\\Model|\\Illuminate\\Contracts\\Database\\Eloquent\\Builder|\\Illuminate\\Database\\Eloquent\\Relations\\Relation  $query
 * @param  mixed  $value
 * @param  string|null  $field
 * @return \\Illuminate\\Contracts\\Database\\Eloquent\\Builder
 */',
        'startLine' => 2523,
        'endLine' => 2526,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getForeignKey' => 
      array (
        'name' => 'getForeignKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default foreign key name for the model.
 *
 * @return string
 */',
        'startLine' => 2533,
        'endLine' => 2536,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'getPerPage' => 
      array (
        'name' => 'getPerPage',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the number of models to return per page.
 *
 * @return int
 */',
        'startLine' => 2543,
        'endLine' => 2546,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'setPerPage' => 
      array (
        'name' => 'setPerPage',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2554,
            'endLine' => 2554,
            'startColumn' => 32,
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
 * Set the number of models to return per page.
 *
 * @param  int  $perPage
 * @return $this
 */',
        'startLine' => 2554,
        'endLine' => 2559,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isSoftDeletable' => 
      array (
        'name' => 'isSoftDeletable',
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
 * Determine if the model is soft deletable.
 */',
        'startLine' => 2564,
        'endLine' => 2567,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isPrunable' => 
      array (
        'name' => 'isPrunable',
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
 * Determine if the model is prunable.
 */',
        'startLine' => 2572,
        'endLine' => 2575,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isMassPrunable' => 
      array (
        'name' => 'isMassPrunable',
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
 * Determine if the model is mass prunable.
 */',
        'startLine' => 2580,
        'endLine' => 2583,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'preventsLazyLoading' => 
      array (
        'name' => 'preventsLazyLoading',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if lazy loading is disabled.
 *
 * @return bool
 */',
        'startLine' => 2590,
        'endLine' => 2593,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'isAutomaticallyEagerLoadingRelationships' => 
      array (
        'name' => 'isAutomaticallyEagerLoadingRelationships',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if relationships are being automatically eager loaded when accessed.
 *
 * @return bool
 */',
        'startLine' => 2600,
        'endLine' => 2603,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'preventsSilentlyDiscardingAttributes' => 
      array (
        'name' => 'preventsSilentlyDiscardingAttributes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if discarding guarded attribute fills is disabled.
 *
 * @return bool
 */',
        'startLine' => 2610,
        'endLine' => 2613,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'preventsAccessingMissingAttributes' => 
      array (
        'name' => 'preventsAccessingMissingAttributes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if accessing missing attributes is disabled.
 *
 * @return bool
 */',
        'startLine' => 2620,
        'endLine' => 2623,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'broadcastChannelRoute' => 
      array (
        'name' => 'broadcastChannelRoute',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the broadcast channel route definition that is associated with the given entity.
 *
 * @return string
 */',
        'startLine' => 2630,
        'endLine' => 2633,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'broadcastChannel' => 
      array (
        'name' => 'broadcastChannel',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the broadcast channel name that is associated with the given entity.
 *
 * @return string
 */',
        'startLine' => 2640,
        'endLine' => 2643,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'resolveClassAttribute' => 
      array (
        'name' => 'resolveClassAttribute',
        'parameters' => 
        array (
          'attributeClass' => 
          array (
            'name' => 'attributeClass',
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
            'startLine' => 2655,
            'endLine' => 2655,
            'startColumn' => 53,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'property' => 
          array (
            'name' => 'property',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2655,
                'endLine' => 2655,
                'startTokenPos' => 10058,
                'startFilePos' => 73601,
                'endTokenPos' => 10058,
                'endFilePos' => 73604,
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
            'startLine' => 2655,
            'endLine' => 2655,
            'startColumn' => 77,
            'endColumn' => 100,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'class' => 
          array (
            'name' => 'class',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2655,
                'endLine' => 2655,
                'startTokenPos' => 10068,
                'startFilePos' => 73624,
                'endTokenPos' => 10068,
                'endFilePos' => 73627,
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
            'startLine' => 2655,
            'endLine' => 2655,
            'startColumn' => 103,
            'endColumn' => 123,
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
 * Resolve a class attribute value from the model.
 *
 * @template TAttribute of object
 *
 * @param  class-string<TAttribute>  $attributeClass
 * @param  string|null  $property
 * @param  string|null  $class
 * @return mixed
 */',
        'startLine' => 2655,
        'endLine' => 2682,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__get' => 
      array (
        'name' => '__get',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2690,
            'endLine' => 2690,
            'startColumn' => 27,
            'endColumn' => 30,
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
 * Dynamically retrieve attributes on the model.
 *
 * @param  string  $key
 * @return mixed
 */',
        'startLine' => 2690,
        'endLine' => 2693,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__set' => 
      array (
        'name' => '__set',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2702,
            'endLine' => 2702,
            'startColumn' => 27,
            'endColumn' => 30,
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
            'startLine' => 2702,
            'endLine' => 2702,
            'startColumn' => 33,
            'endColumn' => 38,
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
 * Dynamically set attributes on the model.
 *
 * @param  string  $key
 * @param  mixed  $value
 * @return void
 */',
        'startLine' => 2702,
        'endLine' => 2705,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'offsetExists' => 
      array (
        'name' => 'offsetExists',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2713,
            'endLine' => 2713,
            'startColumn' => 34,
            'endColumn' => 40,
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
 * Determine if the given attribute exists.
 *
 * @param  mixed  $offset
 * @return bool
 */',
        'startLine' => 2713,
        'endLine' => 2724,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'offsetGet' => 
      array (
        'name' => 'offsetGet',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2732,
            'endLine' => 2732,
            'startColumn' => 31,
            'endColumn' => 37,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value for a given offset.
 *
 * @param  mixed  $offset
 * @return mixed
 */',
        'startLine' => 2732,
        'endLine' => 2735,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'offsetSet' => 
      array (
        'name' => 'offsetSet',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2744,
            'endLine' => 2744,
            'startColumn' => 31,
            'endColumn' => 37,
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
            'startLine' => 2744,
            'endLine' => 2744,
            'startColumn' => 40,
            'endColumn' => 45,
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
 * Set the value for a given offset.
 *
 * @param  mixed  $offset
 * @param  mixed  $value
 * @return void
 */',
        'startLine' => 2744,
        'endLine' => 2747,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'offsetUnset' => 
      array (
        'name' => 'offsetUnset',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2755,
            'endLine' => 2755,
            'startColumn' => 33,
            'endColumn' => 39,
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
 * Unset the value for a given offset.
 *
 * @param  mixed  $offset
 * @return void
 */',
        'startLine' => 2755,
        'endLine' => 2763,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__isset' => 
      array (
        'name' => '__isset',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2771,
            'endLine' => 2771,
            'startColumn' => 29,
            'endColumn' => 32,
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
 * Determine if an attribute or relation exists on the model.
 *
 * @param  string  $key
 * @return bool
 */',
        'startLine' => 2771,
        'endLine' => 2774,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__unset' => 
      array (
        'name' => '__unset',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2782,
            'endLine' => 2782,
            'startColumn' => 29,
            'endColumn' => 32,
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
 * Unset an attribute on the model.
 *
 * @param  string  $key
 * @return void
 */',
        'startLine' => 2782,
        'endLine' => 2785,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
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
            'startLine' => 2794,
            'endLine' => 2794,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2794,
            'endLine' => 2794,
            'startColumn' => 37,
            'endColumn' => 47,
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
 * Handle dynamic method calls into the model.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 2794,
        'endLine' => 2810,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__callStatic' => 
      array (
        'name' => '__callStatic',
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
            'startLine' => 2819,
            'endLine' => 2819,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2819,
            'endLine' => 2819,
            'startColumn' => 50,
            'endColumn' => 60,
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
 * Handle dynamic static method calls into the model.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 2819,
        'endLine' => 2826,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__toString' => 
      array (
        'name' => '__toString',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Convert the model to its string representation.
 *
 * @return string
 */',
        'startLine' => 2833,
        'endLine' => 2838,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      'escapeWhenCastingToString' => 
      array (
        'name' => 'escapeWhenCastingToString',
        'parameters' => 
        array (
          'escape' => 
          array (
            'name' => 'escape',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 2846,
                'endLine' => 2846,
                'startTokenPos' => 10839,
                'startFilePos' => 78474,
                'endTokenPos' => 10839,
                'endFilePos' => 78477,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2846,
            'endLine' => 2846,
            'startColumn' => 47,
            'endColumn' => 60,
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
 * Indicate that the object\'s string representation should be escaped when __toString is invoked.
 *
 * @param  bool  $escape
 * @return $this
 */',
        'startLine' => 2846,
        'endLine' => 2851,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__sleep' => 
      array (
        'name' => '__sleep',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Prepare the object for serialization.
 *
 * @return array
 */',
        'startLine' => 2858,
        'endLine' => 2878,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'aliasName' => NULL,
      ),
      '__wakeup' => 
      array (
        'name' => '__wakeup',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * When a model is being unserialized, check if it needs to be booted.
 *
 * @return void
 */',
        'startLine' => 2885,
        'endLine' => 2894,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Eloquent',
        'declaringClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'implementingClassName' => 'Illuminate\\Database\\Eloquent\\Model',
        'currentClassName' => 'Illuminate\\Database\\Eloquent\\Model',
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