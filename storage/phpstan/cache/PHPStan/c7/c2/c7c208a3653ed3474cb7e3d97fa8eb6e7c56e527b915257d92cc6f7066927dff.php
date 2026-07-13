<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Database/Query/Builder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Query\Builder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-0babf26233c521db311a7e16848870b7d1c575ad9e50bf3f3998bf811df5e48b-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Query\\Builder',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Database/Query/Builder.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Query',
    'name' => 'Illuminate\\Database\\Query\\Builder',
    'shortName' => 'Builder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 37,
    'endLine' => 4950,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Database\\Query\\Builder',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Database\\Concerns\\BuildsWhereDateClauses',
      1 => 'Illuminate\\Database\\Concerns\\BuildsQueries',
      2 => 'Illuminate\\Database\\Concerns\\ExplainsQueries',
      3 => 'Illuminate\\Support\\Traits\\ForwardsCalls',
      4 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'connection' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'connection',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database connection instance.
 *
 * @var \\Illuminate\\Database\\ConnectionInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'grammar' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'grammar',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database query grammar instance.
 *
 * @var \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'processor' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'processor',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The database query post processor instance.
 *
 * @var \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bindings' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'bindings',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'select\' => [], \'from\' => [], \'join\' => [], \'where\' => [], \'groupBy\' => [], \'having\' => [], \'order\' => [], \'union\' => [], \'unionOrder\' => []]',
          'attributes' => 
          array (
            'startLine' => 80,
            'endLine' => 90,
            'startTokenPos' => 239,
            'startFilePos' => 2309,
            'endTokenPos' => 313,
            'endFilePos' => 2529,
          ),
        ),
        'docComment' => '/**
 * The current query value bindings.
 *
 * @var array{
 *     select: list<mixed>,
 *     from: list<mixed>,
 *     join: list<mixed>,
 *     where: list<mixed>,
 *     groupBy: list<mixed>,
 *     having: list<mixed>,
 *     order: list<mixed>,
 *     union: list<mixed>,
 *     unionOrder: list<mixed>,
 * }
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 80,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'aggregate' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'aggregate',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * An aggregate function and column to be run.
 *
 * @var array{
 *     function: string,
 *     columns: array<\\Illuminate\\Contracts\\Database\\Query\\Expression|string>
 * }|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 100,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'columns' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'columns',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The columns that should be returned.
 *
 * @var array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 107,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'distinct' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'distinct',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 116,
            'endLine' => 116,
            'startTokenPos' => 338,
            'startFilePos' => 3141,
            'endTokenPos' => 338,
            'endFilePos' => 3145,
          ),
        ),
        'docComment' => '/**
 * Indicates if the query returns distinct results.
 *
 * Occasionally contains the columns that should be distinct.
 *
 * @var bool|array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 116,
        'endLine' => 116,
        'startColumn' => 5,
        'endColumn' => 29,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'from' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'from',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The table which the query is targeting.
 *
 * @var \\Illuminate\\Database\\Query\\Expression|string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 123,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'indexHint' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'indexHint',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The index hint for the query.
 *
 * @var \\Illuminate\\Database\\Query\\IndexHint|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 130,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'joins' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'joins',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The table joins for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 137,
        'endLine' => 137,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'wheres' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'wheres',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 144,
            'endLine' => 144,
            'startTokenPos' => 370,
            'startFilePos' => 3643,
            'endTokenPos' => 371,
            'endFilePos' => 3644,
          ),
        ),
        'docComment' => '/**
 * The where constraints for the query.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 144,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'groups' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'groups',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The groupings for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 151,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'havings' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'havings',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The having constraints for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 158,
        'endLine' => 158,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'orders' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'orders',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The orderings for the query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 165,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'limit' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'limit',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The maximum number of records to return.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 172,
        'endLine' => 172,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'groupLimit' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'groupLimit',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The maximum number of records to return per group.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 179,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'offset' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'offset',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of records to skip.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 186,
        'endLine' => 186,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unions' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unions',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The query union statements.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 193,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unionLimit' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unionLimit',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The maximum number of union records to return.
 *
 * @var int|null
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
      'unionOffset' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unionOffset',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The number of union records to skip.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 207,
        'endLine' => 207,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'unionOrders' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'unionOrders',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The orderings for the union query.
 *
 * @var array|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 214,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lock' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'lock',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Indicates whether row locking is being used.
 *
 * @var string|bool|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 221,
        'endLine' => 221,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timeout' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'timeout',
        'modifiers' => 1,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The query execution timeout in seconds.
 *
 * @var int|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 228,
        'endLine' => 228,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'beforeQueryCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'beforeQueryCallbacks',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 235,
            'endLine' => 235,
            'startTokenPos' => 466,
            'startFilePos' => 5150,
            'endTokenPos' => 467,
            'endFilePos' => 5151,
          ),
        ),
        'docComment' => '/**
 * The callbacks that should be invoked before the query is executed.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 235,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'afterQueryCallbacks' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'afterQueryCallbacks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 242,
            'endLine' => 242,
            'startTokenPos' => 478,
            'startFilePos' => 5318,
            'endTokenPos' => 479,
            'endFilePos' => 5319,
          ),
        ),
        'docComment' => '/**
 * The callbacks that should be invoked after retrieving data from the database.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 242,
        'endLine' => 242,
        'startColumn' => 5,
        'endColumn' => 40,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'operators' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'operators',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'=\', \'<\', \'>\', \'<=\', \'>=\', \'<>\', \'!=\', \'<=>\', \'like\', \'like binary\', \'not like\', \'ilike\', \'&\', \'|\', \'^\', \'<<\', \'>>\', \'&~\', \'is\', \'is not\', \'rlike\', \'not rlike\', \'regexp\', \'not regexp\', \'~\', \'~*\', \'!~\', \'!~*\', \'similar to\', \'not similar to\', \'not ilike\', \'~~*\', \'!~~*\']',
          'attributes' => 
          array (
            'startLine' => 249,
            'endLine' => 256,
            'startTokenPos' => 490,
            'startFilePos' => 5437,
            'endTokenPos' => 591,
            'endFilePos' => 5760,
          ),
        ),
        'docComment' => '/**
 * All of the available clause operators.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 249,
        'endLine' => 256,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'bitwiseOperators' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'bitwiseOperators',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'&\', \'|\', \'^\', \'<<\', \'>>\', \'&~\']',
          'attributes' => 
          array (
            'startLine' => 263,
            'endLine' => 265,
            'startTokenPos' => 602,
            'startFilePos' => 5886,
            'endTokenPos' => 622,
            'endFilePos' => 5933,
          ),
        ),
        'docComment' => '/**
 * All of the available bitwise operators.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 263,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'useWritePdo' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'useWritePdo',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 272,
            'endLine' => 272,
            'startTokenPos' => 633,
            'startFilePos' => 6051,
            'endTokenPos' => 633,
            'endFilePos' => 6055,
          ),
        ),
        'docComment' => '/**
 * Whether to use write pdo for the select.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 272,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fetchUsing' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'name' => 'fetchUsing',
        'modifiers' => 1,
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
            'startLine' => 279,
            'endLine' => 279,
            'startTokenPos' => 646,
            'startFilePos' => 6209,
            'endTokenPos' => 647,
            'endFilePos' => 6210,
          ),
        ),
        'docComment' => '/**
 * The custom arguments for the PDOStatement::fetchAll / fetch functions.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 279,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 34,
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
          'connection' => 
          array (
            'name' => 'connection',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\ConnectionInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 285,
            'endLine' => 285,
            'startColumn' => 9,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'grammar' => 
          array (
            'name' => 'grammar',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 286,
                'endLine' => 286,
                'startTokenPos' => 671,
                'startFilePos' => 6376,
                'endTokenPos' => 671,
                'endFilePos' => 6379,
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
                      'name' => 'Illuminate\\Database\\Query\\Grammars\\Grammar',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 286,
            'endLine' => 286,
            'startColumn' => 9,
            'endColumn' => 32,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'processor' => 
          array (
            'name' => 'processor',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 287,
                'endLine' => 287,
                'startTokenPos' => 681,
                'startFilePos' => 6414,
                'endTokenPos' => 681,
                'endFilePos' => 6417,
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
                      'name' => 'Illuminate\\Database\\Query\\Processors\\Processor',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 287,
            'endLine' => 287,
            'startColumn' => 9,
            'endColumn' => 36,
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
 * Create a new query builder instance.
 */',
        'startLine' => 284,
        'endLine' => 292,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'select' => 
      array (
        'name' => 'select',
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
                'startLine' => 300,
                'endLine' => 300,
                'startTokenPos' => 747,
                'startFilePos' => 6771,
                'endTokenPos' => 749,
                'endFilePos' => 6775,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 300,
            'endLine' => 300,
            'startColumn' => 28,
            'endColumn' => 43,
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
 * Set the columns to be selected.
 *
 * @param  mixed  $columns
 * @return $this
 */',
        'startLine' => 300,
        'endLine' => 316,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectSub' => 
      array (
        'name' => 'selectSub',
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
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 39,
            'endColumn' => 41,
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
 * Add a subselect expression to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 327,
        'endLine' => 334,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectExpression' => 
      array (
        'name' => 'selectExpression',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 343,
            'endLine' => 343,
            'startColumn' => 38,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 343,
            'endLine' => 343,
            'startColumn' => 51,
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
 * Add a select expression to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|literal-string  $expression
 * @param  string  $as
 * @return $this
 */',
        'startLine' => 343,
        'endLine' => 348,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectRaw' => 
      array (
        'name' => 'selectRaw',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 356,
            'endLine' => 356,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 356,
                'endLine' => 356,
                'startTokenPos' => 1002,
                'startFilePos' => 8319,
                'endTokenPos' => 1003,
                'endFilePos' => 8320,
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
            'startLine' => 356,
            'endLine' => 356,
            'startColumn' => 44,
            'endColumn' => 63,
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
 * Add a new "raw" select expression to the query.
 *
 * @param  literal-string  $expression
 * @return $this
 */',
        'startLine' => 356,
        'endLine' => 365,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'fromSub' => 
      array (
        'name' => 'fromSub',
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
            'startLine' => 376,
            'endLine' => 376,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 376,
            'endLine' => 376,
            'startColumn' => 37,
            'endColumn' => 39,
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
 * Makes "from" fetch from a subquery.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 376,
        'endLine' => 381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'fromRaw' => 
      array (
        'name' => 'fromRaw',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 29,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 390,
                'endLine' => 390,
                'startTokenPos' => 1125,
                'startFilePos' => 9200,
                'endTokenPos' => 1126,
                'endFilePos' => 9201,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 42,
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
 * Add a raw "from" clause to the query.
 *
 * @param  literal-string  $expression
 * @param  mixed  $bindings
 * @return $this
 */',
        'startLine' => 390,
        'endLine' => 397,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'createSub' => 
      array (
        'name' => 'createSub',
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
            'startLine' => 405,
            'endLine' => 405,
            'startColumn' => 34,
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
 * Creates a subquery and parse it.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return array
 */',
        'startLine' => 405,
        'endLine' => 417,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'parseSub' => 
      array (
        'name' => 'parseSub',
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
            'startLine' => 427,
            'endLine' => 427,
            'startColumn' => 33,
            'endColumn' => 38,
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
 * Parse the subquery into SQL and bindings.
 *
 * @param  mixed  $query
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 427,
        'endLine' => 440,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'prependDatabaseNameIfCrossDatabaseQuery' => 
      array (
        'name' => 'prependDatabaseNameIfCrossDatabaseQuery',
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
            'startLine' => 448,
            'endLine' => 448,
            'startColumn' => 64,
            'endColumn' => 69,
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
 * Prepend the database name if the given query is on another database.
 *
 * @param  mixed  $query
 * @return mixed
 */',
        'startLine' => 448,
        'endLine' => 460,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addSelect' => 
      array (
        'name' => 'addSelect',
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
            'startLine' => 468,
            'endLine' => 468,
            'startColumn' => 31,
            'endColumn' => 37,
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
 * Add a new select column to the query.
 *
 * @param  mixed  $column
 * @return $this
 */',
        'startLine' => 468,
        'endLine' => 489,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'selectVectorDistance' => 
      array (
        'name' => 'selectVectorDistance',
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
            'startLine' => 501,
            'endLine' => 501,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 501,
            'endLine' => 501,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 501,
                'endLine' => 501,
                'startTokenPos' => 1645,
                'startFilePos' => 12584,
                'endTokenPos' => 1645,
                'endFilePos' => 12587,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 501,
            'endLine' => 501,
            'startColumn' => 60,
            'endColumn' => 69,
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
 * Add a vector-similarity selection to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  string|null  $as
 * @return $this
 *
 * @throws \\JsonException
 */',
        'startLine' => 501,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'distinct' => 
      array (
        'name' => 'distinct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Force the query to only return distinct results.
 *
 * @return $this
 */',
        'startLine' => 531,
        'endLine' => 542,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'from' => 
      array (
        'name' => 'from',
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
            'startLine' => 551,
            'endLine' => 551,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 551,
                'endLine' => 551,
                'startTokenPos' => 1899,
                'startFilePos' => 13954,
                'endTokenPos' => 1899,
                'endFilePos' => 13957,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 551,
            'endLine' => 551,
            'startColumn' => 34,
            'endColumn' => 43,
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
 * Set the table which the query is targeting.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  string|null  $as
 * @return $this
 */',
        'startLine' => 551,
        'endLine' => 560,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'useIndex' => 
      array (
        'name' => 'useIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 568,
            'endLine' => 568,
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
 * Add an index hint to suggest a query index.
 *
 * @param  string  $index
 * @return $this
 */',
        'startLine' => 568,
        'endLine' => 573,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forceIndex' => 
      array (
        'name' => 'forceIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 581,
            'endLine' => 581,
            'startColumn' => 32,
            'endColumn' => 37,
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
 * Add an index hint to force a query index.
 *
 * @param  string  $index
 * @return $this
 */',
        'startLine' => 581,
        'endLine' => 586,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ignoreIndex' => 
      array (
        'name' => 'ignoreIndex',
        'parameters' => 
        array (
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 594,
            'endLine' => 594,
            'startColumn' => 33,
            'endColumn' => 38,
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
 * Add an index hint to ignore a query index.
 *
 * @param  string  $index
 * @return $this
 */',
        'startLine' => 594,
        'endLine' => 599,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'join' => 
      array (
        'name' => 'join',
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
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 26,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 612,
                'endLine' => 612,
                'startTokenPos' => 2093,
                'startFilePos' => 15410,
                'endTokenPos' => 2093,
                'endFilePos' => 15413,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 612,
                'endLine' => 612,
                'startTokenPos' => 2100,
                'startFilePos' => 15426,
                'endTokenPos' => 2100,
                'endFilePos' => 15429,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 60,
            'endColumn' => 73,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 612,
                'endLine' => 612,
                'startTokenPos' => 2107,
                'startFilePos' => 15440,
                'endTokenPos' => 2107,
                'endFilePos' => 15446,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 76,
            'endColumn' => 90,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'where' => 
          array (
            'name' => 'where',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 612,
                'endLine' => 612,
                'startTokenPos' => 2114,
                'startFilePos' => 15458,
                'endTokenPos' => 2114,
                'endFilePos' => 15462,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 93,
            'endColumn' => 106,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a "join" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @param  string  $type
 * @param  bool  $where
 * @return $this
 */',
        'startLine' => 612,
        'endLine' => 639,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'joinWhere' => 
      array (
        'name' => 'joinWhere',
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
            'startLine' => 651,
            'endLine' => 651,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 651,
            'endLine' => 651,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 651,
            'endLine' => 651,
            'startColumn' => 47,
            'endColumn' => 55,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 651,
            'endLine' => 651,
            'startColumn' => 58,
            'endColumn' => 64,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 651,
                'endLine' => 651,
                'startTokenPos' => 2284,
                'startFilePos' => 16928,
                'endTokenPos' => 2284,
                'endFilePos' => 16934,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 651,
            'endLine' => 651,
            'startColumn' => 67,
            'endColumn' => 81,
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
 * Add a "join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $second
 * @param  string  $type
 * @return $this
 */',
        'startLine' => 651,
        'endLine' => 654,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'joinSub' => 
      array (
        'name' => 'joinSub',
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
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 37,
            'endColumn' => 39,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 670,
                'endLine' => 670,
                'startTokenPos' => 2337,
                'startFilePos' => 17638,
                'endTokenPos' => 2337,
                'endFilePos' => 17641,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 670,
                'endLine' => 670,
                'startTokenPos' => 2344,
                'startFilePos' => 17654,
                'endTokenPos' => 2344,
                'endFilePos' => 17657,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 68,
            'endColumn' => 81,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 670,
                'endLine' => 670,
                'startTokenPos' => 2351,
                'startFilePos' => 17668,
                'endTokenPos' => 2351,
                'endFilePos' => 17674,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 84,
            'endColumn' => 98,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'where' => 
          array (
            'name' => 'where',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 670,
                'endLine' => 670,
                'startTokenPos' => 2358,
                'startFilePos' => 17686,
                'endTokenPos' => 2358,
                'endFilePos' => 17690,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 670,
            'endLine' => 670,
            'startColumn' => 101,
            'endColumn' => 114,
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
 * Add a "subquery join" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @param  string  $type
 * @param  bool  $where
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 670,
        'endLine' => 679,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'joinLateral' => 
      array (
        'name' => 'joinLateral',
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
            'startLine' => 687,
            'endLine' => 687,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
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
            'startLine' => 687,
            'endLine' => 687,
            'startColumn' => 41,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'inner\'',
              'attributes' => 
              array (
                'startLine' => 687,
                'endLine' => 687,
                'startTokenPos' => 2465,
                'startFilePos' => 18259,
                'endTokenPos' => 2465,
                'endFilePos' => 18265,
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
            'startLine' => 687,
            'endLine' => 687,
            'startColumn' => 53,
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
 * Add a "lateral join" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return $this
 */',
        'startLine' => 687,
        'endLine' => 698,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoinLateral' => 
      array (
        'name' => 'leftJoinLateral',
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
            'startLine' => 706,
            'endLine' => 706,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
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
            'startLine' => 706,
            'endLine' => 706,
            'startColumn' => 45,
            'endColumn' => 54,
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
 * Add a lateral left join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return $this
 */',
        'startLine' => 706,
        'endLine' => 709,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoin' => 
      array (
        'name' => 'leftJoin',
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
            'startLine' => 720,
            'endLine' => 720,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 720,
            'endLine' => 720,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 720,
                'endLine' => 720,
                'startTokenPos' => 2606,
                'startFilePos' => 19333,
                'endTokenPos' => 2606,
                'endFilePos' => 19336,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 720,
            'endLine' => 720,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 720,
                'endLine' => 720,
                'startTokenPos' => 2613,
                'startFilePos' => 19349,
                'endTokenPos' => 2613,
                'endFilePos' => 19352,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 720,
            'endLine' => 720,
            'startColumn' => 64,
            'endColumn' => 77,
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
 * Add a left join to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 720,
        'endLine' => 723,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoinWhere' => 
      array (
        'name' => 'leftJoinWhere',
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
            'startLine' => 734,
            'endLine' => 734,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 734,
            'endLine' => 734,
            'startColumn' => 43,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 734,
            'endLine' => 734,
            'startColumn' => 51,
            'endColumn' => 59,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 734,
            'endLine' => 734,
            'startColumn' => 62,
            'endColumn' => 68,
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
 * Add a "join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 734,
        'endLine' => 737,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'leftJoinSub' => 
      array (
        'name' => 'leftJoinSub',
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
            'startLine' => 749,
            'endLine' => 749,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 749,
            'endLine' => 749,
            'startColumn' => 41,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 749,
            'endLine' => 749,
            'startColumn' => 46,
            'endColumn' => 51,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 749,
                'endLine' => 749,
                'startTokenPos' => 2709,
                'startFilePos' => 20479,
                'endTokenPos' => 2709,
                'endFilePos' => 20482,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 749,
            'endLine' => 749,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 749,
                'endLine' => 749,
                'startTokenPos' => 2716,
                'startFilePos' => 20495,
                'endTokenPos' => 2716,
                'endFilePos' => 20498,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 749,
            'endLine' => 749,
            'startColumn' => 72,
            'endColumn' => 85,
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
 * Add a subquery left join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 749,
        'endLine' => 752,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rightJoin' => 
      array (
        'name' => 'rightJoin',
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
            'startLine' => 763,
            'endLine' => 763,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 763,
            'endLine' => 763,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 763,
                'endLine' => 763,
                'startTokenPos' => 2766,
                'startFilePos' => 20973,
                'endTokenPos' => 2766,
                'endFilePos' => 20976,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 763,
            'endLine' => 763,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 763,
                'endLine' => 763,
                'startTokenPos' => 2773,
                'startFilePos' => 20989,
                'endTokenPos' => 2773,
                'endFilePos' => 20992,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 763,
            'endLine' => 763,
            'startColumn' => 65,
            'endColumn' => 78,
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
 * Add a right join to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 763,
        'endLine' => 766,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rightJoinWhere' => 
      array (
        'name' => 'rightJoinWhere',
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
            'startLine' => 777,
            'endLine' => 777,
            'startColumn' => 36,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 777,
            'endLine' => 777,
            'startColumn' => 44,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 777,
            'endLine' => 777,
            'startColumn' => 52,
            'endColumn' => 60,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 777,
            'endLine' => 777,
            'startColumn' => 63,
            'endColumn' => 69,
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
 * Add a "right join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $second
 * @return $this
 */',
        'startLine' => 777,
        'endLine' => 780,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rightJoinSub' => 
      array (
        'name' => 'rightJoinSub',
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
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 42,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 792,
                'endLine' => 792,
                'startTokenPos' => 2869,
                'startFilePos' => 22125,
                'endTokenPos' => 2869,
                'endFilePos' => 22128,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 55,
            'endColumn' => 70,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 792,
                'endLine' => 792,
                'startTokenPos' => 2876,
                'startFilePos' => 22141,
                'endTokenPos' => 2876,
                'endFilePos' => 22144,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 792,
            'endLine' => 792,
            'startColumn' => 73,
            'endColumn' => 86,
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
 * Add a subquery right join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 792,
        'endLine' => 795,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'crossJoin' => 
      array (
        'name' => 'crossJoin',
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
            'startLine' => 806,
            'endLine' => 806,
            'startColumn' => 31,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 806,
                'endLine' => 806,
                'startTokenPos' => 2923,
                'startFilePos' => 22671,
                'endTokenPos' => 2923,
                'endFilePos' => 22674,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 806,
            'endLine' => 806,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 806,
                'endLine' => 806,
                'startTokenPos' => 2930,
                'startFilePos' => 22689,
                'endTokenPos' => 2930,
                'endFilePos' => 22692,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 806,
            'endLine' => 806,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 806,
                'endLine' => 806,
                'startTokenPos' => 2937,
                'startFilePos' => 22705,
                'endTokenPos' => 2937,
                'endFilePos' => 22708,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 806,
            'endLine' => 806,
            'startColumn' => 72,
            'endColumn' => 85,
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
 * Add a "cross join" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 806,
        'endLine' => 815,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'crossJoinSub' => 
      array (
        'name' => 'crossJoinSub',
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
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 824,
            'endLine' => 824,
            'startColumn' => 42,
            'endColumn' => 44,
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
 * Add a subquery cross join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @return $this
 */',
        'startLine' => 824,
        'endLine' => 835,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'straightJoin' => 
      array (
        'name' => 'straightJoin',
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
            'startLine' => 846,
            'endLine' => 846,
            'startColumn' => 34,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 846,
            'endLine' => 846,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 846,
                'endLine' => 846,
                'startTokenPos' => 3119,
                'startFilePos' => 23898,
                'endTokenPos' => 3119,
                'endFilePos' => 23901,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 846,
            'endLine' => 846,
            'startColumn' => 50,
            'endColumn' => 65,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 846,
                'endLine' => 846,
                'startTokenPos' => 3126,
                'startFilePos' => 23914,
                'endTokenPos' => 3126,
                'endFilePos' => 23917,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 846,
            'endLine' => 846,
            'startColumn' => 68,
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
 * Add a straight join to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 846,
        'endLine' => 849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'straightJoinWhere' => 
      array (
        'name' => 'straightJoinWhere',
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
            'startLine' => 860,
            'endLine' => 860,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 860,
            'endLine' => 860,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 860,
            'endLine' => 860,
            'startColumn' => 55,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 860,
            'endLine' => 860,
            'startColumn' => 66,
            'endColumn' => 72,
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
 * Add a "straight join where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $second
 * @return $this
 */',
        'startLine' => 860,
        'endLine' => 863,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'straightJoinSub' => 
      array (
        'name' => 'straightJoinSub',
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
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'as' => 
          array (
            'name' => 'as',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 45,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 50,
            'endColumn' => 55,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 875,
                'endLine' => 875,
                'startTokenPos' => 3222,
                'startFilePos' => 25078,
                'endTokenPos' => 3222,
                'endFilePos' => 25081,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 58,
            'endColumn' => 73,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 875,
                'endLine' => 875,
                'startTokenPos' => 3229,
                'startFilePos' => 25094,
                'endTokenPos' => 3229,
                'endFilePos' => 25097,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 875,
            'endLine' => 875,
            'startColumn' => 76,
            'endColumn' => 89,
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
 * Add a subquery straight join to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @param  string  $as
 * @param  \\Closure|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $first
 * @param  string|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $second
 * @return $this
 */',
        'startLine' => 875,
        'endLine' => 878,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'newJoinClause' => 
      array (
        'name' => 'newJoinClause',
        'parameters' => 
        array (
          'parentQuery' => 
          array (
            'name' => 'parentQuery',
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
            'startLine' => 887,
            'endLine' => 887,
            'startColumn' => 38,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 887,
            'endLine' => 887,
            'startColumn' => 57,
            'endColumn' => 61,
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
            'startLine' => 887,
            'endLine' => 887,
            'startColumn' => 64,
            'endColumn' => 69,
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
 * Get a new "join" clause.
 *
 * @param  string  $type
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @return \\Illuminate\\Database\\Query\\JoinClause
 */',
        'startLine' => 887,
        'endLine' => 890,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'newJoinLateralClause' => 
      array (
        'name' => 'newJoinLateralClause',
        'parameters' => 
        array (
          'parentQuery' => 
          array (
            'name' => 'parentQuery',
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
            'startLine' => 899,
            'endLine' => 899,
            'startColumn' => 45,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 899,
            'endLine' => 899,
            'startColumn' => 64,
            'endColumn' => 68,
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
            'startLine' => 899,
            'endLine' => 899,
            'startColumn' => 71,
            'endColumn' => 76,
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
 * Get a new "join lateral" clause.
 *
 * @param  string  $type
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $table
 * @return \\Illuminate\\Database\\Query\\JoinLateralClause
 */',
        'startLine' => 899,
        'endLine' => 902,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'mergeWheres' => 
      array (
        'name' => 'mergeWheres',
        'parameters' => 
        array (
          'wheres' => 
          array (
            'name' => 'wheres',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 911,
            'endLine' => 911,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 911,
            'endLine' => 911,
            'startColumn' => 42,
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
 * Merge an array of "where" clauses and bindings.
 *
 * @param  array  $wheres
 * @param  array  $bindings
 * @return $this
 */',
        'startLine' => 911,
        'endLine' => 920,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'where' => 
      array (
        'name' => 'where',
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
            'startLine' => 931,
            'endLine' => 931,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 931,
                'endLine' => 931,
                'startTokenPos' => 3426,
                'startFilePos' => 26723,
                'endTokenPos' => 3426,
                'endFilePos' => 26726,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 931,
            'endLine' => 931,
            'startColumn' => 36,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 931,
                'endLine' => 931,
                'startTokenPos' => 3433,
                'startFilePos' => 26738,
                'endTokenPos' => 3433,
                'endFilePos' => 26741,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 931,
            'endLine' => 931,
            'startColumn' => 54,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 931,
                'endLine' => 931,
                'startTokenPos' => 3440,
                'startFilePos' => 26755,
                'endTokenPos' => 3440,
                'endFilePos' => 26759,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 931,
            'endLine' => 931,
            'startColumn' => 69,
            'endColumn' => 84,
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
 * Add a basic "where" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 931,
        'endLine' => 1030,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addArrayOfWheres' => 
      array (
        'name' => 'addArrayOfWheres',
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
            'startLine' => 1040,
            'endLine' => 1040,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1040,
            'endLine' => 1040,
            'startColumn' => 50,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'method' => 
          array (
            'name' => 'method',
            'default' => 
            array (
              'code' => '\'where\'',
              'attributes' => 
              array (
                'startLine' => 1040,
                'endLine' => 1040,
                'startTokenPos' => 4082,
                'startFilePos' => 31398,
                'endTokenPos' => 4082,
                'endFilePos' => 31404,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1040,
            'endLine' => 1040,
            'startColumn' => 60,
            'endColumn' => 76,
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
 * Add an array of "where" clauses to the query.
 *
 * @param  array  $column
 * @param  string  $boolean
 * @param  string  $method
 * @return $this
 */',
        'startLine' => 1040,
        'endLine' => 1051,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'prepareValueAndOperator' => 
      array (
        'name' => 'prepareValueAndOperator',
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
            'startLine' => 1063,
            'endLine' => 1063,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1063,
            'endLine' => 1063,
            'startColumn' => 53,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'useDefault' => 
          array (
            'name' => 'useDefault',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1063,
                'endLine' => 1063,
                'startTokenPos' => 4223,
                'startFilePos' => 32170,
                'endTokenPos' => 4223,
                'endFilePos' => 32174,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1063,
            'endLine' => 1063,
            'startColumn' => 64,
            'endColumn' => 82,
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
 * Prepare the value and operator for a where clause.
 *
 * @param  string  $value
 * @param  string  $operator
 * @param  bool  $useDefault
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 1063,
        'endLine' => 1072,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'invalidOperatorAndValue' => 
      array (
        'name' => 'invalidOperatorAndValue',
        'parameters' => 
        array (
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1083,
            'endLine' => 1083,
            'startColumn' => 48,
            'endColumn' => 56,
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
            'startLine' => 1083,
            'endLine' => 1083,
            'startColumn' => 59,
            'endColumn' => 64,
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
 * Determine if the given operator and value combination is legal.
 *
 * Prevents using Null values with invalid operators.
 *
 * @param  string  $operator
 * @param  mixed  $value
 * @return bool
 */',
        'startLine' => 1083,
        'endLine' => 1087,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'invalidOperator' => 
      array (
        'name' => 'invalidOperator',
        'parameters' => 
        array (
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1095,
            'endLine' => 1095,
            'startColumn' => 40,
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
 * Determine if the given operator is supported.
 *
 * @param  string  $operator
 * @return bool
 */',
        'startLine' => 1095,
        'endLine' => 1099,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'isBitwiseOperator' => 
      array (
        'name' => 'isBitwiseOperator',
        'parameters' => 
        array (
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1107,
            'endLine' => 1107,
            'startColumn' => 42,
            'endColumn' => 50,
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
 * Determine if the operator is a bitwise operator.
 *
 * @param  string  $operator
 * @return bool
 */',
        'startLine' => 1107,
        'endLine' => 1111,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhere' => 
      array (
        'name' => 'orWhere',
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
            'startLine' => 1121,
            'endLine' => 1121,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1121,
                'endLine' => 1121,
                'startTokenPos' => 4491,
                'startFilePos' => 33969,
                'endTokenPos' => 4491,
                'endFilePos' => 33972,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1121,
            'endLine' => 1121,
            'startColumn' => 38,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1121,
                'endLine' => 1121,
                'startTokenPos' => 4498,
                'startFilePos' => 33984,
                'endTokenPos' => 4498,
                'endFilePos' => 33987,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1121,
            'endLine' => 1121,
            'startColumn' => 56,
            'endColumn' => 68,
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
 * Add an "or where" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1121,
        'endLine' => 1128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNot' => 
      array (
        'name' => 'whereNot',
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
            'startLine' => 1139,
            'endLine' => 1139,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1139,
                'endLine' => 1139,
                'startTokenPos' => 4570,
                'startFilePos' => 34526,
                'endTokenPos' => 4570,
                'endFilePos' => 34529,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1139,
            'endLine' => 1139,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1139,
                'endLine' => 1139,
                'startTokenPos' => 4577,
                'startFilePos' => 34541,
                'endTokenPos' => 4577,
                'endFilePos' => 34544,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1139,
            'endLine' => 1139,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1139,
                'endLine' => 1139,
                'startTokenPos' => 4584,
                'startFilePos' => 34558,
                'endTokenPos' => 4584,
                'endFilePos' => 34562,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1139,
            'endLine' => 1139,
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
 * Add a basic "where not" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1139,
        'endLine' => 1148,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNot' => 
      array (
        'name' => 'orWhereNot',
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
            'startLine' => 1158,
            'endLine' => 1158,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1158,
                'endLine' => 1158,
                'startTokenPos' => 4695,
                'startFilePos' => 35200,
                'endTokenPos' => 4695,
                'endFilePos' => 35203,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1158,
            'endLine' => 1158,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1158,
                'endLine' => 1158,
                'startTokenPos' => 4702,
                'startFilePos' => 35215,
                'endTokenPos' => 4702,
                'endFilePos' => 35218,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1158,
            'endLine' => 1158,
            'startColumn' => 59,
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
 * Add an "or where not" clause to the query.
 *
 * @param  \\Closure|string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1158,
        'endLine' => 1161,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereColumn' => 
      array (
        'name' => 'whereColumn',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1172,
            'endLine' => 1172,
            'startColumn' => 33,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1172,
                'endLine' => 1172,
                'startTokenPos' => 4743,
                'startFilePos' => 35655,
                'endTokenPos' => 4743,
                'endFilePos' => 35658,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1172,
            'endLine' => 1172,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1172,
                'endLine' => 1172,
                'startTokenPos' => 4750,
                'startFilePos' => 35671,
                'endTokenPos' => 4750,
                'endFilePos' => 35674,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1172,
            'endLine' => 1172,
            'startColumn' => 59,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1172,
                'endLine' => 1172,
                'startTokenPos' => 4757,
                'startFilePos' => 35688,
                'endTokenPos' => 4757,
                'endFilePos' => 35692,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1172,
            'endLine' => 1172,
            'startColumn' => 75,
            'endColumn' => 90,
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
 * Add a "where" clause comparing two columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|array  $first
 * @param  string|null  $operator
 * @param  string|null  $second
 * @param  string|null  $boolean
 * @return $this
 */',
        'startLine' => 1172,
        'endLine' => 1198,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereColumn' => 
      array (
        'name' => 'orWhereColumn',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1208,
            'endLine' => 1208,
            'startColumn' => 35,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1208,
                'endLine' => 1208,
                'startTokenPos' => 4918,
                'startFilePos' => 37215,
                'endTokenPos' => 4918,
                'endFilePos' => 37218,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1208,
            'endLine' => 1208,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1208,
                'endLine' => 1208,
                'startTokenPos' => 4925,
                'startFilePos' => 37231,
                'endTokenPos' => 4925,
                'endFilePos' => 37234,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1208,
            'endLine' => 1208,
            'startColumn' => 61,
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
 * Add an "or where" clause comparing two columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string|array  $first
 * @param  string|null  $operator
 * @param  string|null  $second
 * @return $this
 */',
        'startLine' => 1208,
        'endLine' => 1211,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereVectorSimilarTo' => 
      array (
        'name' => 'whereVectorSimilarTo',
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
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
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
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'minSimilarity' => 
          array (
            'name' => 'minSimilarity',
            'default' => 
            array (
              'code' => '0.6',
              'attributes' => 
              array (
                'startLine' => 1222,
                'endLine' => 1222,
                'startTokenPos' => 4969,
                'startFilePos' => 37881,
                'endTokenPos' => 4969,
                'endFilePos' => 37883,
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
            'startColumn' => 60,
            'endColumn' => 79,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'order' => 
          array (
            'name' => 'order',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 1222,
                'endLine' => 1222,
                'startTokenPos' => 4976,
                'startFilePos' => 37895,
                'endTokenPos' => 4976,
                'endFilePos' => 37898,
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
            'startColumn' => 82,
            'endColumn' => 94,
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
 * Add a vector similarity clause to the query, filtering by minimum similarity and ordering by similarity.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  float  $minSimilarity  A value between 0.0 and 1.0, where 1.0 is identical.
 * @param  bool  $order
 * @return $this
 */',
        'startLine' => 1222,
        'endLine' => 1235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereVectorDistanceLessThan' => 
      array (
        'name' => 'whereVectorDistanceLessThan',
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
            'startLine' => 1248,
            'endLine' => 1248,
            'startColumn' => 49,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1248,
            'endLine' => 1248,
            'startColumn' => 58,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'maxDistance' => 
          array (
            'name' => 'maxDistance',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1248,
            'endLine' => 1248,
            'startColumn' => 67,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1248,
                'endLine' => 1248,
                'startTokenPos' => 5081,
                'startFilePos' => 38732,
                'endTokenPos' => 5081,
                'endFilePos' => 38736,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1248,
            'endLine' => 1248,
            'startColumn' => 81,
            'endColumn' => 96,
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
 * Add a vector distance "where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  float  $maxDistance
 * @param  string  $boolean
 * @return $this
 *
 * @throws \\JsonException
 */',
        'startLine' => 1248,
        'endLine' => 1269,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereVectorDistanceLessThan' => 
      array (
        'name' => 'orWhereVectorDistanceLessThan',
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
            'startLine' => 1279,
            'endLine' => 1279,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1279,
            'endLine' => 1279,
            'startColumn' => 60,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'maxDistance' => 
          array (
            'name' => 'maxDistance',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1279,
            'endLine' => 1279,
            'startColumn' => 69,
            'endColumn' => 80,
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
 * Add a vector distance "or where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>|string  $vector
 * @param  float  $maxDistance
 * @return $this
 */',
        'startLine' => 1279,
        'endLine' => 1282,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereRaw' => 
      array (
        'name' => 'whereRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1292,
            'endLine' => 1292,
            'startColumn' => 30,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1292,
                'endLine' => 1292,
                'startTokenPos' => 5251,
                'startFilePos' => 40147,
                'endTokenPos' => 5252,
                'endFilePos' => 40148,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1292,
            'endLine' => 1292,
            'startColumn' => 36,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1292,
                'endLine' => 1292,
                'startTokenPos' => 5259,
                'startFilePos' => 40162,
                'endTokenPos' => 5259,
                'endFilePos' => 40166,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1292,
            'endLine' => 1292,
            'startColumn' => 52,
            'endColumn' => 67,
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
 * Add a raw "where" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|literal-string  $sql
 * @param  mixed  $bindings
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1292,
        'endLine' => 1299,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereRaw' => 
      array (
        'name' => 'orWhereRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1308,
            'endLine' => 1308,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 1308,
                'endLine' => 1308,
                'startTokenPos' => 5330,
                'startFilePos' => 40554,
                'endTokenPos' => 5331,
                'endFilePos' => 40555,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1308,
            'endLine' => 1308,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Add a raw "or where" clause to the query.
 *
 * @param  literal-string  $sql
 * @param  mixed  $bindings
 * @return $this
 */',
        'startLine' => 1308,
        'endLine' => 1311,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereLike' => 
      array (
        'name' => 'whereLike',
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
            'startLine' => 1323,
            'endLine' => 1323,
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
            'startLine' => 1323,
            'endLine' => 1323,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1323,
                'endLine' => 1323,
                'startTokenPos' => 5372,
                'startFilePos' => 40984,
                'endTokenPos' => 5372,
                'endFilePos' => 40988,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1323,
            'endLine' => 1323,
            'startColumn' => 48,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1323,
                'endLine' => 1323,
                'startTokenPos' => 5379,
                'startFilePos' => 41002,
                'endTokenPos' => 5379,
                'endFilePos' => 41006,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1323,
            'endLine' => 1323,
            'startColumn' => 72,
            'endColumn' => 87,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1323,
                'endLine' => 1323,
                'startTokenPos' => 5386,
                'startFilePos' => 41016,
                'endTokenPos' => 5386,
                'endFilePos' => 41020,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1323,
            'endLine' => 1323,
            'startColumn' => 90,
            'endColumn' => 101,
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
 * Add a "where like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1323,
        'endLine' => 1336,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereLike' => 
      array (
        'name' => 'orWhereLike',
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
            'startLine' => 1346,
            'endLine' => 1346,
            'startColumn' => 33,
            'endColumn' => 39,
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
            'startLine' => 1346,
            'endLine' => 1346,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1346,
                'endLine' => 1346,
                'startTokenPos' => 5518,
                'startFilePos' => 41753,
                'endTokenPos' => 5518,
                'endFilePos' => 41757,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1346,
            'endLine' => 1346,
            'startColumn' => 50,
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
 * Add an "or where like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @return $this
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
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotLike' => 
      array (
        'name' => 'whereNotLike',
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
            'startLine' => 1360,
            'endLine' => 1360,
            'startColumn' => 34,
            'endColumn' => 40,
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
            'startLine' => 1360,
            'endLine' => 1360,
            'startColumn' => 43,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1360,
                'endLine' => 1360,
                'startTokenPos' => 5565,
                'startFilePos' => 42191,
                'endTokenPos' => 5565,
                'endFilePos' => 42195,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1360,
            'endLine' => 1360,
            'startColumn' => 51,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1360,
                'endLine' => 1360,
                'startTokenPos' => 5572,
                'startFilePos' => 42209,
                'endTokenPos' => 5572,
                'endFilePos' => 42213,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1360,
            'endLine' => 1360,
            'startColumn' => 75,
            'endColumn' => 90,
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
 * Add a "where not like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1360,
        'endLine' => 1363,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotLike' => 
      array (
        'name' => 'orWhereNotLike',
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
            'startLine' => 1373,
            'endLine' => 1373,
            'startColumn' => 36,
            'endColumn' => 42,
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
            'startLine' => 1373,
            'endLine' => 1373,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'caseSensitive' => 
          array (
            'name' => 'caseSensitive',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1373,
                'endLine' => 1373,
                'startTokenPos' => 5619,
                'startFilePos' => 42624,
                'endTokenPos' => 5619,
                'endFilePos' => 42628,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1373,
            'endLine' => 1373,
            'startColumn' => 53,
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
 * Add an "or where not like" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $value
 * @param  bool  $caseSensitive
 * @return $this
 */',
        'startLine' => 1373,
        'endLine' => 1376,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNullSafeEquals' => 
      array (
        'name' => 'whereNullSafeEquals',
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
            'startLine' => 1386,
            'endLine' => 1386,
            'startColumn' => 41,
            'endColumn' => 47,
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
            'startLine' => 1386,
            'endLine' => 1386,
            'startColumn' => 50,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1386,
                'endLine' => 1386,
                'startTokenPos' => 5663,
                'startFilePos' => 43030,
                'endTokenPos' => 5663,
                'endFilePos' => 43034,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1386,
            'endLine' => 1386,
            'startColumn' => 58,
            'endColumn' => 73,
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
 * Add a "where null safe equals" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1386,
        'endLine' => 1397,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNullSafeEquals' => 
      array (
        'name' => 'orWhereNullSafeEquals',
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
            'startLine' => 1406,
            'endLine' => 1406,
            'startColumn' => 43,
            'endColumn' => 49,
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
            'startLine' => 1406,
            'endLine' => 1406,
            'startColumn' => 52,
            'endColumn' => 57,
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
 * Add an "or where null safe equals" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 1406,
        'endLine' => 1409,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereIn' => 
      array (
        'name' => 'whereIn',
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
            'startLine' => 1422,
            'endLine' => 1422,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1422,
            'endLine' => 1422,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1422,
                'endLine' => 1422,
                'startTokenPos' => 5804,
                'startFilePos' => 44064,
                'endTokenPos' => 5804,
                'endFilePos' => 44068,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1422,
            'endLine' => 1422,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1422,
                'endLine' => 1422,
                'startTokenPos' => 5811,
                'startFilePos' => 44078,
                'endTokenPos' => 5811,
                'endFilePos' => 44082,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1422,
            'endLine' => 1422,
            'startColumn' => 65,
            'endColumn' => 76,
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
 * Add a "where in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 1422,
        'endLine' => 1456,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereIn' => 
      array (
        'name' => 'orWhereIn',
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
            'startLine' => 1465,
            'endLine' => 1465,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1465,
            'endLine' => 1465,
            'startColumn' => 40,
            'endColumn' => 46,
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
 * Add an "or where in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @return $this
 */',
        'startLine' => 1465,
        'endLine' => 1468,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotIn' => 
      array (
        'name' => 'whereNotIn',
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
            'startLine' => 1478,
            'endLine' => 1478,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1478,
            'endLine' => 1478,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1478,
                'endLine' => 1478,
                'startTokenPos' => 6082,
                'startFilePos' => 46229,
                'endTokenPos' => 6082,
                'endFilePos' => 46233,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1478,
            'endLine' => 1478,
            'startColumn' => 50,
            'endColumn' => 65,
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
 * Add a "where not in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1478,
        'endLine' => 1481,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotIn' => 
      array (
        'name' => 'orWhereNotIn',
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
            'startLine' => 1490,
            'endLine' => 1490,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1490,
            'endLine' => 1490,
            'startColumn' => 43,
            'endColumn' => 49,
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
 * Add an "or where not in" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  mixed  $values
 * @return $this
 */',
        'startLine' => 1490,
        'endLine' => 1493,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereIntegerInRaw' => 
      array (
        'name' => 'whereIntegerInRaw',
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
            'startLine' => 1504,
            'endLine' => 1504,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1504,
            'endLine' => 1504,
            'startColumn' => 48,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1504,
                'endLine' => 1504,
                'startTokenPos' => 6160,
                'startFilePos' => 46980,
                'endTokenPos' => 6160,
                'endFilePos' => 46984,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1504,
            'endLine' => 1504,
            'startColumn' => 57,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1504,
                'endLine' => 1504,
                'startTokenPos' => 6167,
                'startFilePos' => 46994,
                'endTokenPos' => 6167,
                'endFilePos' => 46998,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1504,
            'endLine' => 1504,
            'startColumn' => 75,
            'endColumn' => 86,
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
 * Add a "where in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1504,
        'endLine' => 1521,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereIntegerInRaw' => 
      array (
        'name' => 'orWhereIntegerInRaw',
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
            'startLine' => 1530,
            'endLine' => 1530,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1530,
            'endLine' => 1530,
            'startColumn' => 50,
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
 * Add an "or where in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @return $this
 */',
        'startLine' => 1530,
        'endLine' => 1533,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereIntegerNotInRaw' => 
      array (
        'name' => 'whereIntegerNotInRaw',
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
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 51,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1543,
                'endLine' => 1543,
                'startTokenPos' => 6361,
                'startFilePos' => 48138,
                'endTokenPos' => 6361,
                'endFilePos' => 48142,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1543,
            'endLine' => 1543,
            'startColumn' => 60,
            'endColumn' => 75,
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
 * Add a "where not in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1543,
        'endLine' => 1546,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereIntegerNotInRaw' => 
      array (
        'name' => 'orWhereIntegerNotInRaw',
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
            'startLine' => 1555,
            'endLine' => 1555,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1555,
            'endLine' => 1555,
            'startColumn' => 53,
            'endColumn' => 59,
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
 * Add an "or where not in raw" clause for integer values to the query.
 *
 * @param  string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @return $this
 */',
        'startLine' => 1555,
        'endLine' => 1558,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNull' => 
      array (
        'name' => 'whereNull',
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
            'startLine' => 1568,
            'endLine' => 1568,
            'startColumn' => 31,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1568,
                'endLine' => 1568,
                'startTokenPos' => 6436,
                'startFilePos' => 48882,
                'endTokenPos' => 6436,
                'endFilePos' => 48886,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1568,
            'endLine' => 1568,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1568,
                'endLine' => 1568,
                'startTokenPos' => 6443,
                'startFilePos' => 48896,
                'endTokenPos' => 6443,
                'endFilePos' => 48900,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1568,
            'endLine' => 1568,
            'startColumn' => 59,
            'endColumn' => 70,
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
 * Add a "where null" clause to the query.
 *
 * @param  string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $columns
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1568,
        'endLine' => 1577,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNull' => 
      array (
        'name' => 'orWhereNull',
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
            'startLine' => 1585,
            'endLine' => 1585,
            'startColumn' => 33,
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
 * Add an "or where null" clause to the query.
 *
 * @param  string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $column
 * @return $this
 */',
        'startLine' => 1585,
        'endLine' => 1588,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotNull' => 
      array (
        'name' => 'whereNotNull',
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
            'startLine' => 1597,
            'endLine' => 1597,
            'startColumn' => 34,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1597,
                'endLine' => 1597,
                'startTokenPos' => 6563,
                'startFilePos' => 49686,
                'endTokenPos' => 6563,
                'endFilePos' => 49690,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1597,
            'endLine' => 1597,
            'startColumn' => 44,
            'endColumn' => 59,
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
 * Add a "where not null" clause to the query.
 *
 * @param  string|array|\\Illuminate\\Contracts\\Database\\Query\\Expression  $columns
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1597,
        'endLine' => 1600,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereBetween' => 
      array (
        'name' => 'whereBetween',
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
            'startLine' => 1610,
            'endLine' => 1610,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1610,
            'endLine' => 1610,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1610,
                'endLine' => 1610,
                'startTokenPos' => 6606,
                'startFilePos' => 50146,
                'endTokenPos' => 6606,
                'endFilePos' => 50150,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1610,
            'endLine' => 1610,
            'startColumn' => 61,
            'endColumn' => 76,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1610,
                'endLine' => 1610,
                'startTokenPos' => 6613,
                'startFilePos' => 50160,
                'endTokenPos' => 6613,
                'endFilePos' => 50164,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1610,
            'endLine' => 1610,
            'startColumn' => 79,
            'endColumn' => 90,
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
 * Add a "where between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1610,
        'endLine' => 1630,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereBetweenColumns' => 
      array (
        'name' => 'whereBetweenColumns',
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
            'startLine' => 1640,
            'endLine' => 1640,
            'startColumn' => 41,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 1640,
            'endLine' => 1640,
            'startColumn' => 50,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1640,
                'endLine' => 1640,
                'startTokenPos' => 6822,
                'startFilePos' => 51157,
                'endTokenPos' => 6822,
                'endFilePos' => 51161,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1640,
            'endLine' => 1640,
            'startColumn' => 65,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1640,
                'endLine' => 1640,
                'startTokenPos' => 6829,
                'startFilePos' => 51171,
                'endTokenPos' => 6829,
                'endFilePos' => 51175,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1640,
            'endLine' => 1640,
            'startColumn' => 83,
            'endColumn' => 94,
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
 * Add a "where between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1640,
        'endLine' => 1654,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereBetween' => 
      array (
        'name' => 'orWhereBetween',
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
            'startLine' => 1662,
            'endLine' => 1662,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1662,
            'endLine' => 1662,
            'startColumn' => 45,
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
 * Add an "or where between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1662,
        'endLine' => 1665,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereBetweenColumns' => 
      array (
        'name' => 'orWhereBetweenColumns',
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
            'startLine' => 1673,
            'endLine' => 1673,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 1673,
            'endLine' => 1673,
            'startColumn' => 52,
            'endColumn' => 64,
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
 * Add an "or where between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1673,
        'endLine' => 1676,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotBetween' => 
      array (
        'name' => 'whereNotBetween',
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
            'startLine' => 1685,
            'endLine' => 1685,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1685,
            'endLine' => 1685,
            'startColumn' => 46,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1685,
                'endLine' => 1685,
                'startTokenPos' => 7054,
                'startFilePos' => 52732,
                'endTokenPos' => 7054,
                'endFilePos' => 52736,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1685,
            'endLine' => 1685,
            'startColumn' => 64,
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
 * Add a "where not between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1685,
        'endLine' => 1688,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotBetweenColumns' => 
      array (
        'name' => 'whereNotBetweenColumns',
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
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 44,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1697,
                'endLine' => 1697,
                'startTokenPos' => 7100,
                'startFilePos' => 53126,
                'endTokenPos' => 7100,
                'endFilePos' => 53130,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1697,
            'endLine' => 1697,
            'startColumn' => 68,
            'endColumn' => 83,
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
 * Add a "where not between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1697,
        'endLine' => 1700,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotBetween' => 
      array (
        'name' => 'orWhereNotBetween',
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
            'startLine' => 1708,
            'endLine' => 1708,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1708,
            'endLine' => 1708,
            'startColumn' => 48,
            'endColumn' => 63,
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
 * Add an "or where not between" statement to the query.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1708,
        'endLine' => 1711,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotBetweenColumns' => 
      array (
        'name' => 'orWhereNotBetweenColumns',
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
            'startLine' => 1719,
            'endLine' => 1719,
            'startColumn' => 46,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 1719,
            'endLine' => 1719,
            'startColumn' => 55,
            'endColumn' => 67,
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
 * Add an "or where not between" statement using columns to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1719,
        'endLine' => 1722,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereValueBetween' => 
      array (
        'name' => 'whereValueBetween',
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
            'startLine' => 1733,
            'endLine' => 1733,
            'startColumn' => 39,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 1733,
            'endLine' => 1733,
            'startColumn' => 47,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1733,
                'endLine' => 1733,
                'startTokenPos' => 7218,
                'startFilePos' => 54396,
                'endTokenPos' => 7218,
                'endFilePos' => 54400,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1733,
            'endLine' => 1733,
            'startColumn' => 63,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1733,
                'endLine' => 1733,
                'startTokenPos' => 7225,
                'startFilePos' => 54410,
                'endTokenPos' => 7225,
                'endFilePos' => 54414,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1733,
            'endLine' => 1733,
            'startColumn' => 81,
            'endColumn' => 92,
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
 * Add a "where between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 1733,
        'endLine' => 1742,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereValueBetween' => 
      array (
        'name' => 'orWhereValueBetween',
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
            'startLine' => 1751,
            'endLine' => 1751,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 1751,
            'endLine' => 1751,
            'startColumn' => 49,
            'endColumn' => 62,
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
 * Add an "or where between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @return $this
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
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereValueNotBetween' => 
      array (
        'name' => 'whereValueNotBetween',
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
            'startLine' => 1764,
            'endLine' => 1764,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 1764,
            'endLine' => 1764,
            'startColumn' => 50,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1764,
                'endLine' => 1764,
                'startTokenPos' => 7356,
                'startFilePos' => 55496,
                'endTokenPos' => 7356,
                'endFilePos' => 55500,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1764,
            'endLine' => 1764,
            'startColumn' => 66,
            'endColumn' => 81,
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
 * Add a "where not between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1764,
        'endLine' => 1767,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereValueNotBetween' => 
      array (
        'name' => 'orWhereValueNotBetween',
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
            'startLine' => 1776,
            'endLine' => 1776,
            'startColumn' => 44,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 1776,
            'endLine' => 1776,
            'startColumn' => 52,
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
 * Add an "or where not between columns" statement using a value to the query.
 *
 * @param  mixed  $value
 * @param  array{\\Illuminate\\Contracts\\Database\\Query\\Expression|string, \\Illuminate\\Contracts\\Database\\Query\\Expression|string}  $columns
 * @return $this
 */',
        'startLine' => 1776,
        'endLine' => 1779,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotNull' => 
      array (
        'name' => 'orWhereNotNull',
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
            'startLine' => 1787,
            'endLine' => 1787,
            'startColumn' => 36,
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
 * Add an "or where not null" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 1787,
        'endLine' => 1790,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereDate' => 
      array (
        'name' => 'whereDate',
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
            'startLine' => 1801,
            'endLine' => 1801,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1801,
            'endLine' => 1801,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1801,
                'endLine' => 1801,
                'startTokenPos' => 7464,
                'startFilePos' => 56699,
                'endTokenPos' => 7464,
                'endFilePos' => 56702,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1801,
            'endLine' => 1801,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1801,
                'endLine' => 1801,
                'startTokenPos' => 7471,
                'startFilePos' => 56716,
                'endTokenPos' => 7471,
                'endFilePos' => 56720,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1801,
            'endLine' => 1801,
            'startColumn' => 66,
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
 * Add a "where date" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1801,
        'endLine' => 1821,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereDate' => 
      array (
        'name' => 'orWhereDate',
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
            'startLine' => 1831,
            'endLine' => 1831,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1831,
            'endLine' => 1831,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1831,
                'endLine' => 1831,
                'startTokenPos' => 7625,
                'startFilePos' => 57810,
                'endTokenPos' => 7625,
                'endFilePos' => 57813,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1831,
            'endLine' => 1831,
            'startColumn' => 53,
            'endColumn' => 65,
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
 * Add an "or where date" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @return $this
 */',
        'startLine' => 1831,
        'endLine' => 1838,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereTime' => 
      array (
        'name' => 'whereTime',
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
            'startLine' => 1849,
            'endLine' => 1849,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1849,
            'endLine' => 1849,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1849,
                'endLine' => 1849,
                'startTokenPos' => 7700,
                'startFilePos' => 58398,
                'endTokenPos' => 7700,
                'endFilePos' => 58401,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1849,
            'endLine' => 1849,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1849,
                'endLine' => 1849,
                'startTokenPos' => 7707,
                'startFilePos' => 58415,
                'endTokenPos' => 7707,
                'endFilePos' => 58419,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1849,
            'endLine' => 1849,
            'startColumn' => 66,
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
 * Add a "where time" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1849,
        'endLine' => 1869,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereTime' => 
      array (
        'name' => 'orWhereTime',
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
            'startLine' => 1879,
            'endLine' => 1879,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1879,
            'endLine' => 1879,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1879,
                'endLine' => 1879,
                'startTokenPos' => 7861,
                'startFilePos' => 59509,
                'endTokenPos' => 7861,
                'endFilePos' => 59512,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1879,
            'endLine' => 1879,
            'startColumn' => 53,
            'endColumn' => 65,
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
 * Add an "or where time" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|null  $operator
 * @param  \\DateTimeInterface|string|null  $value
 * @return $this
 */',
        'startLine' => 1879,
        'endLine' => 1886,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereDay' => 
      array (
        'name' => 'whereDay',
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
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1897,
                'endLine' => 1897,
                'startTokenPos' => 7936,
                'startFilePos' => 60103,
                'endTokenPos' => 7936,
                'endFilePos' => 60106,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 50,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1897,
                'endLine' => 1897,
                'startTokenPos' => 7943,
                'startFilePos' => 60120,
                'endTokenPos' => 7943,
                'endFilePos' => 60124,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1897,
            'endLine' => 1897,
            'startColumn' => 65,
            'endColumn' => 80,
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
 * Add a "where day" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1897,
        'endLine' => 1921,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereDay' => 
      array (
        'name' => 'orWhereDay',
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
            'startLine' => 1931,
            'endLine' => 1931,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1931,
            'endLine' => 1931,
            'startColumn' => 41,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1931,
                'endLine' => 1931,
                'startTokenPos' => 8126,
                'startFilePos' => 61326,
                'endTokenPos' => 8126,
                'endFilePos' => 61329,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1931,
            'endLine' => 1931,
            'startColumn' => 52,
            'endColumn' => 64,
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
 * Add an "or where day" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @return $this
 */',
        'startLine' => 1931,
        'endLine' => 1938,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereMonth' => 
      array (
        'name' => 'whereMonth',
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
            'startLine' => 1949,
            'endLine' => 1949,
            'startColumn' => 32,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1949,
            'endLine' => 1949,
            'startColumn' => 41,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1949,
                'endLine' => 1949,
                'startTokenPos' => 8201,
                'startFilePos' => 61923,
                'endTokenPos' => 8201,
                'endFilePos' => 61926,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1949,
            'endLine' => 1949,
            'startColumn' => 52,
            'endColumn' => 64,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 1949,
                'endLine' => 1949,
                'startTokenPos' => 8208,
                'startFilePos' => 61940,
                'endTokenPos' => 8208,
                'endFilePos' => 61944,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1949,
            'endLine' => 1949,
            'startColumn' => 67,
            'endColumn' => 82,
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
 * Add a "where month" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 1949,
        'endLine' => 1973,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereMonth' => 
      array (
        'name' => 'orWhereMonth',
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
            'startLine' => 1983,
            'endLine' => 1983,
            'startColumn' => 34,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1983,
            'endLine' => 1983,
            'startColumn' => 43,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1983,
                'endLine' => 1983,
                'startTokenPos' => 8391,
                'startFilePos' => 63152,
                'endTokenPos' => 8391,
                'endFilePos' => 63155,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1983,
            'endLine' => 1983,
            'startColumn' => 54,
            'endColumn' => 66,
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
 * Add an "or where month" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @return $this
 */',
        'startLine' => 1983,
        'endLine' => 1990,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereYear' => 
      array (
        'name' => 'whereYear',
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
            'startLine' => 2001,
            'endLine' => 2001,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2001,
            'endLine' => 2001,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2001,
                'endLine' => 2001,
                'startTokenPos' => 8466,
                'startFilePos' => 63749,
                'endTokenPos' => 8466,
                'endFilePos' => 63752,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2001,
            'endLine' => 2001,
            'startColumn' => 51,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2001,
                'endLine' => 2001,
                'startTokenPos' => 8473,
                'startFilePos' => 63766,
                'endTokenPos' => 8473,
                'endFilePos' => 63770,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2001,
            'endLine' => 2001,
            'startColumn' => 66,
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
 * Add a "where year" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2001,
        'endLine' => 2021,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereYear' => 
      array (
        'name' => 'orWhereYear',
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
            'startLine' => 2031,
            'endLine' => 2031,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2031,
            'endLine' => 2031,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2031,
                'endLine' => 2031,
                'startTokenPos' => 8627,
                'startFilePos' => 64864,
                'endTokenPos' => 8627,
                'endFilePos' => 64867,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2031,
            'endLine' => 2031,
            'startColumn' => 53,
            'endColumn' => 65,
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
 * Add an "or where year" statement to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\DateTimeInterface|string|int|null  $operator
 * @param  \\DateTimeInterface|string|int|null  $value
 * @return $this
 */',
        'startLine' => 2031,
        'endLine' => 2038,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addDateBasedWhere' => 
      array (
        'name' => 'addDateBasedWhere',
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
            'startLine' => 2050,
            'endLine' => 2050,
            'startColumn' => 42,
            'endColumn' => 46,
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
            'startLine' => 2050,
            'endLine' => 2050,
            'startColumn' => 49,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2050,
            'endLine' => 2050,
            'startColumn' => 58,
            'endColumn' => 66,
            'parameterIndex' => 2,
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
            'startLine' => 2050,
            'endLine' => 2050,
            'startColumn' => 69,
            'endColumn' => 74,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2050,
                'endLine' => 2050,
                'startTokenPos' => 8708,
                'startFilePos' => 65483,
                'endTokenPos' => 8708,
                'endFilePos' => 65487,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2050,
            'endLine' => 2050,
            'startColumn' => 77,
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
 * Add a date based (year, month, day, time) statement to the query.
 *
 * @param  string  $type
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2050,
        'endLine' => 2059,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNested' => 
      array (
        'name' => 'whereNested',
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
            'startLine' => 2067,
            'endLine' => 2067,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2067,
                'endLine' => 2067,
                'startTokenPos' => 8809,
                'startFilePos' => 65962,
                'endTokenPos' => 8809,
                'endFilePos' => 65966,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2067,
            'endLine' => 2067,
            'startColumn' => 52,
            'endColumn' => 67,
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
 * Add a nested "where" statement to the query.
 *
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2067,
        'endLine' => 2072,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forNestedWhere' => 
      array (
        'name' => 'forNestedWhere',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new query instance for nested where condition.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 2079,
        'endLine' => 2082,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addNestedWhereQuery' => 
      array (
        'name' => 'addNestedWhereQuery',
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
            'startLine' => 2091,
            'endLine' => 2091,
            'startColumn' => 41,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2091,
                'endLine' => 2091,
                'startTokenPos' => 8888,
                'startFilePos' => 66602,
                'endTokenPos' => 8888,
                'endFilePos' => 66606,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2091,
            'endLine' => 2091,
            'startColumn' => 49,
            'endColumn' => 64,
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
 * Add another query builder as a nested where to the query builder.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder  $query
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2091,
        'endLine' => 2102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereSub' => 
      array (
        'name' => 'whereSub',
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
            'startLine' => 2113,
            'endLine' => 2113,
            'startColumn' => 33,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2113,
            'endLine' => 2113,
            'startColumn' => 42,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2113,
            'endLine' => 2113,
            'startColumn' => 53,
            'endColumn' => 61,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2113,
            'endLine' => 2113,
            'startColumn' => 64,
            'endColumn' => 71,
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
 * Add a full sub-select to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string  $operator
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2113,
        'endLine' => 2133,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereExists' => 
      array (
        'name' => 'whereExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2143,
            'endLine' => 2143,
            'startColumn' => 33,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2143,
                'endLine' => 2143,
                'startTokenPos' => 9148,
                'startFilePos' => 68370,
                'endTokenPos' => 9148,
                'endFilePos' => 68374,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2143,
            'endLine' => 2143,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2143,
                'endLine' => 2143,
                'startTokenPos' => 9155,
                'startFilePos' => 68384,
                'endTokenPos' => 9155,
                'endFilePos' => 68388,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2143,
            'endLine' => 2143,
            'startColumn' => 62,
            'endColumn' => 73,
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
 * Add an "exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2143,
        'endLine' => 2157,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereExists' => 
      array (
        'name' => 'orWhereExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2166,
            'endLine' => 2166,
            'startColumn' => 35,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2166,
                'endLine' => 2166,
                'startTokenPos' => 9259,
                'startFilePos' => 69252,
                'endTokenPos' => 9259,
                'endFilePos' => 69256,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2166,
            'endLine' => 2166,
            'startColumn' => 46,
            'endColumn' => 57,
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
 * Add an "or where exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2166,
        'endLine' => 2169,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNotExists' => 
      array (
        'name' => 'whereNotExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2178,
            'endLine' => 2178,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2178,
                'endLine' => 2178,
                'startTokenPos' => 9297,
                'startFilePos' => 69627,
                'endTokenPos' => 9297,
                'endFilePos' => 69631,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2178,
            'endLine' => 2178,
            'startColumn' => 47,
            'endColumn' => 62,
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
 * Add a "where not exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2178,
        'endLine' => 2181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNotExists' => 
      array (
        'name' => 'orWhereNotExists',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2189,
            'endLine' => 2189,
            'startColumn' => 38,
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
 * Add an "or where not exists" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $callback
 * @return $this
 */',
        'startLine' => 2189,
        'endLine' => 2192,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addWhereExistsQuery' => 
      array (
        'name' => 'addWhereExistsQuery',
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
            'startLine' => 2201,
            'endLine' => 2201,
            'startColumn' => 41,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2201,
                'endLine' => 2201,
                'startTokenPos' => 9365,
                'startFilePos' => 70246,
                'endTokenPos' => 9365,
                'endFilePos' => 70250,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2201,
            'endLine' => 2201,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2201,
                'endLine' => 2201,
                'startTokenPos' => 9372,
                'startFilePos' => 70260,
                'endTokenPos' => 9372,
                'endFilePos' => 70264,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2201,
            'endLine' => 2201,
            'startColumn' => 72,
            'endColumn' => 83,
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
 * Add an "exists" clause to the query.
 *
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2201,
        'endLine' => 2210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereRowValues' => 
      array (
        'name' => 'whereRowValues',
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
            'startLine' => 2223,
            'endLine' => 2223,
            'startColumn' => 36,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2223,
            'endLine' => 2223,
            'startColumn' => 46,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2223,
            'endLine' => 2223,
            'startColumn' => 57,
            'endColumn' => 63,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2223,
                'endLine' => 2223,
                'startTokenPos' => 9466,
                'startFilePos' => 70840,
                'endTokenPos' => 9466,
                'endFilePos' => 70844,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2223,
            'endLine' => 2223,
            'startColumn' => 66,
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
 * Adds a where condition using row values.
 *
 * @param  array  $columns
 * @param  string  $operator
 * @param  array  $values
 * @param  string  $boolean
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 2223,
        'endLine' => 2236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereRowValues' => 
      array (
        'name' => 'orWhereRowValues',
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
            'startLine' => 2246,
            'endLine' => 2246,
            'startColumn' => 38,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2246,
            'endLine' => 2246,
            'startColumn' => 48,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2246,
            'endLine' => 2246,
            'startColumn' => 59,
            'endColumn' => 65,
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
 * Adds an or where condition using row values.
 *
 * @param  array  $columns
 * @param  string  $operator
 * @param  array  $values
 * @return $this
 */',
        'startLine' => 2246,
        'endLine' => 2249,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonContains' => 
      array (
        'name' => 'whereJsonContains',
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
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 39,
            'endColumn' => 45,
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
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 48,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2260,
                'endLine' => 2260,
                'startTokenPos' => 9631,
                'startFilePos' => 71903,
                'endTokenPos' => 9631,
                'endFilePos' => 71907,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 56,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2260,
                'endLine' => 2260,
                'startTokenPos' => 9638,
                'startFilePos' => 71917,
                'endTokenPos' => 9638,
                'endFilePos' => 71921,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2260,
            'endLine' => 2260,
            'startColumn' => 74,
            'endColumn' => 85,
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
 * Add a "where JSON contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2260,
        'endLine' => 2271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonContains' => 
      array (
        'name' => 'orWhereJsonContains',
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
            'startLine' => 2280,
            'endLine' => 2280,
            'startColumn' => 41,
            'endColumn' => 47,
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
            'startLine' => 2280,
            'endLine' => 2280,
            'startColumn' => 50,
            'endColumn' => 55,
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
 * Add an "or where JSON contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2280,
        'endLine' => 2283,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonDoesntContain' => 
      array (
        'name' => 'whereJsonDoesntContain',
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
            'startLine' => 2293,
            'endLine' => 2293,
            'startColumn' => 44,
            'endColumn' => 50,
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
            'startLine' => 2293,
            'endLine' => 2293,
            'startColumn' => 53,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2293,
                'endLine' => 2293,
                'startTokenPos' => 9785,
                'startFilePos' => 72832,
                'endTokenPos' => 9785,
                'endFilePos' => 72836,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2293,
            'endLine' => 2293,
            'startColumn' => 61,
            'endColumn' => 76,
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
 * Add a "where JSON not contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2293,
        'endLine' => 2296,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonDoesntContain' => 
      array (
        'name' => 'orWhereJsonDoesntContain',
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
            'startLine' => 2305,
            'endLine' => 2305,
            'startColumn' => 46,
            'endColumn' => 52,
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
            'startLine' => 2305,
            'endLine' => 2305,
            'startColumn' => 55,
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
 * Add an "or where JSON not contains" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
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
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonOverlaps' => 
      array (
        'name' => 'whereJsonOverlaps',
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
            'startLine' => 2319,
            'endLine' => 2319,
            'startColumn' => 39,
            'endColumn' => 45,
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
            'startLine' => 2319,
            'endLine' => 2319,
            'startColumn' => 48,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2319,
                'endLine' => 2319,
                'startTokenPos' => 9863,
                'startFilePos' => 73522,
                'endTokenPos' => 9863,
                'endFilePos' => 73526,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2319,
            'endLine' => 2319,
            'startColumn' => 56,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2319,
                'endLine' => 2319,
                'startTokenPos' => 9870,
                'startFilePos' => 73536,
                'endTokenPos' => 9870,
                'endFilePos' => 73540,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2319,
            'endLine' => 2319,
            'startColumn' => 74,
            'endColumn' => 85,
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
 * Add a "where JSON overlaps" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2319,
        'endLine' => 2330,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonOverlaps' => 
      array (
        'name' => 'orWhereJsonOverlaps',
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
            'startLine' => 2339,
            'endLine' => 2339,
            'startColumn' => 41,
            'endColumn' => 47,
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
            'startLine' => 2339,
            'endLine' => 2339,
            'startColumn' => 50,
            'endColumn' => 55,
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
 * Add an "or where JSON overlaps" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2339,
        'endLine' => 2342,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonDoesntOverlap' => 
      array (
        'name' => 'whereJsonDoesntOverlap',
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
            'startLine' => 2352,
            'endLine' => 2352,
            'startColumn' => 44,
            'endColumn' => 50,
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
            'startLine' => 2352,
            'endLine' => 2352,
            'startColumn' => 53,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2352,
                'endLine' => 2352,
                'startTokenPos' => 10017,
                'startFilePos' => 74450,
                'endTokenPos' => 10017,
                'endFilePos' => 74454,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2352,
            'endLine' => 2352,
            'startColumn' => 61,
            'endColumn' => 76,
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
 * Add a "where JSON not overlap" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2352,
        'endLine' => 2355,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonDoesntOverlap' => 
      array (
        'name' => 'orWhereJsonDoesntOverlap',
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
            'startLine' => 2364,
            'endLine' => 2364,
            'startColumn' => 46,
            'endColumn' => 52,
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
            'startLine' => 2364,
            'endLine' => 2364,
            'startColumn' => 55,
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
 * Add an "or where JSON not overlap" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2364,
        'endLine' => 2367,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonContainsKey' => 
      array (
        'name' => 'whereJsonContainsKey',
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
            'startLine' => 2377,
            'endLine' => 2377,
            'startColumn' => 42,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2377,
                'endLine' => 2377,
                'startTokenPos' => 10092,
                'startFilePos' => 75121,
                'endTokenPos' => 10092,
                'endFilePos' => 75125,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2377,
            'endLine' => 2377,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2377,
                'endLine' => 2377,
                'startTokenPos' => 10099,
                'startFilePos' => 75135,
                'endTokenPos' => 10099,
                'endFilePos' => 75139,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2377,
            'endLine' => 2377,
            'startColumn' => 69,
            'endColumn' => 80,
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
 * Add a clause that determines if a JSON path exists to the query.
 *
 * @param  string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2377,
        'endLine' => 2384,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonContainsKey' => 
      array (
        'name' => 'orWhereJsonContainsKey',
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
            'startLine' => 2392,
            'endLine' => 2392,
            'startColumn' => 44,
            'endColumn' => 50,
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
 * Add an "or" clause that determines if a JSON path exists to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2392,
        'endLine' => 2395,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonDoesntContainKey' => 
      array (
        'name' => 'whereJsonDoesntContainKey',
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
            'startLine' => 2404,
            'endLine' => 2404,
            'startColumn' => 47,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2404,
                'endLine' => 2404,
                'startTokenPos' => 10199,
                'startFilePos' => 75848,
                'endTokenPos' => 10199,
                'endFilePos' => 75852,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2404,
            'endLine' => 2404,
            'startColumn' => 56,
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
 * Add a clause that determines if a JSON path does not exist to the query.
 *
 * @param  string  $column
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2404,
        'endLine' => 2407,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonDoesntContainKey' => 
      array (
        'name' => 'orWhereJsonDoesntContainKey',
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
            'startLine' => 2415,
            'endLine' => 2415,
            'startColumn' => 49,
            'endColumn' => 55,
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
 * Add an "or" clause that determines if a JSON path does not exist to the query.
 *
 * @param  string  $column
 * @return $this
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
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereJsonLength' => 
      array (
        'name' => 'whereJsonLength',
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
            'startLine' => 2429,
            'endLine' => 2429,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2429,
            'endLine' => 2429,
            'startColumn' => 46,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2429,
                'endLine' => 2429,
                'startTokenPos' => 10268,
                'startFilePos' => 76519,
                'endTokenPos' => 10268,
                'endFilePos' => 76522,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2429,
            'endLine' => 2429,
            'startColumn' => 57,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2429,
                'endLine' => 2429,
                'startTokenPos' => 10275,
                'startFilePos' => 76536,
                'endTokenPos' => 10275,
                'endFilePos' => 76540,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2429,
            'endLine' => 2429,
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
 * Add a "where JSON length" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2429,
        'endLine' => 2451,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereJsonLength' => 
      array (
        'name' => 'orWhereJsonLength',
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
            'startLine' => 2461,
            'endLine' => 2461,
            'startColumn' => 39,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2461,
            'endLine' => 2461,
            'startColumn' => 48,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2461,
                'endLine' => 2461,
                'startTokenPos' => 10457,
                'startFilePos' => 77618,
                'endTokenPos' => 10457,
                'endFilePos' => 77621,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2461,
            'endLine' => 2461,
            'startColumn' => 59,
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
 * Add an "or where JSON length" clause to the query.
 *
 * @param  string  $column
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2461,
        'endLine' => 2468,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dynamicWhere' => 
      array (
        'name' => 'dynamicWhere',
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
            'startLine' => 2477,
            'endLine' => 2477,
            'startColumn' => 34,
            'endColumn' => 40,
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
            'startLine' => 2477,
            'endLine' => 2477,
            'startColumn' => 43,
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
 * Handles dynamic "where" clauses to the query.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return $this
 */',
        'startLine' => 2477,
        'endLine' => 2511,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addDynamic' => 
      array (
        'name' => 'addDynamic',
        'parameters' => 
        array (
          'segment' => 
          array (
            'name' => 'segment',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2522,
            'endLine' => 2522,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'connector' => 
          array (
            'name' => 'connector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2522,
            'endLine' => 2522,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 1,
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
            'startLine' => 2522,
            'endLine' => 2522,
            'startColumn' => 57,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'index' => 
          array (
            'name' => 'index',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2522,
            'endLine' => 2522,
            'startColumn' => 70,
            'endColumn' => 75,
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
 * Add a single dynamic "where" clause statement to the query.
 *
 * @param  string  $segment
 * @param  string  $connector
 * @param  array  $parameters
 * @param  int  $index
 * @return void
 */',
        'startLine' => 2522,
        'endLine' => 2530,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereFullText' => 
      array (
        'name' => 'whereFullText',
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
            'startLine' => 2540,
            'endLine' => 2540,
            'startColumn' => 35,
            'endColumn' => 42,
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
            'startLine' => 2540,
            'endLine' => 2540,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2540,
                'endLine' => 2540,
                'startTokenPos' => 10759,
                'startFilePos' => 80359,
                'endTokenPos' => 10760,
                'endFilePos' => 80360,
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
            'startLine' => 2540,
            'endLine' => 2540,
            'startColumn' => 53,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2540,
                'endLine' => 2540,
                'startTokenPos' => 10767,
                'startFilePos' => 80374,
                'endTokenPos' => 10767,
                'endFilePos' => 80378,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2540,
            'endLine' => 2540,
            'startColumn' => 74,
            'endColumn' => 89,
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
 * Add a "where fulltext" clause to the query.
 *
 * @param  string|string[]  $columns
 * @param  string  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2540,
        'endLine' => 2551,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereFullText' => 
      array (
        'name' => 'orWhereFullText',
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
            'startLine' => 2560,
            'endLine' => 2560,
            'startColumn' => 37,
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
            'startLine' => 2560,
            'endLine' => 2560,
            'startColumn' => 47,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2560,
                'endLine' => 2560,
                'startTokenPos' => 10868,
                'startFilePos' => 80894,
                'endTokenPos' => 10869,
                'endFilePos' => 80895,
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
            'startLine' => 2560,
            'endLine' => 2560,
            'startColumn' => 55,
            'endColumn' => 73,
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
 * Add an "or where fulltext" clause to the query.
 *
 * @param  string|string[]  $columns
 * @param  string  $value
 * @return $this
 */',
        'startLine' => 2560,
        'endLine' => 2563,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereAll' => 
      array (
        'name' => 'whereAll',
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
            'startLine' => 2574,
            'endLine' => 2574,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2574,
                'endLine' => 2574,
                'startTokenPos' => 10910,
                'startFilePos' => 81363,
                'endTokenPos' => 10910,
                'endFilePos' => 81366,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2574,
            'endLine' => 2574,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2574,
                'endLine' => 2574,
                'startTokenPos' => 10917,
                'startFilePos' => 81378,
                'endTokenPos' => 10917,
                'endFilePos' => 81381,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2574,
            'endLine' => 2574,
            'startColumn' => 58,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2574,
                'endLine' => 2574,
                'startTokenPos' => 10924,
                'startFilePos' => 81395,
                'endTokenPos' => 10924,
                'endFilePos' => 81399,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2574,
            'endLine' => 2574,
            'startColumn' => 73,
            'endColumn' => 88,
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
 * Add a "where" clause to the query for multiple columns with "and" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2574,
        'endLine' => 2587,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereAll' => 
      array (
        'name' => 'orWhereAll',
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
            'startLine' => 2597,
            'endLine' => 2597,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2597,
                'endLine' => 2597,
                'startTokenPos' => 11044,
                'startFilePos' => 82147,
                'endTokenPos' => 11044,
                'endFilePos' => 82150,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2597,
            'endLine' => 2597,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2597,
                'endLine' => 2597,
                'startTokenPos' => 11051,
                'startFilePos' => 82162,
                'endTokenPos' => 11051,
                'endFilePos' => 82165,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2597,
            'endLine' => 2597,
            'startColumn' => 60,
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
 * Add an "or where" clause to the query for multiple columns with "and" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2597,
        'endLine' => 2600,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereAny' => 
      array (
        'name' => 'whereAny',
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
            'startLine' => 2611,
            'endLine' => 2611,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2611,
                'endLine' => 2611,
                'startTokenPos' => 11092,
                'startFilePos' => 82628,
                'endTokenPos' => 11092,
                'endFilePos' => 82631,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2611,
            'endLine' => 2611,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2611,
                'endLine' => 2611,
                'startTokenPos' => 11099,
                'startFilePos' => 82643,
                'endTokenPos' => 11099,
                'endFilePos' => 82646,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2611,
            'endLine' => 2611,
            'startColumn' => 58,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2611,
                'endLine' => 2611,
                'startTokenPos' => 11106,
                'startFilePos' => 82660,
                'endTokenPos' => 11106,
                'endFilePos' => 82664,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2611,
            'endLine' => 2611,
            'startColumn' => 73,
            'endColumn' => 88,
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
 * Add a "where" clause to the query for multiple columns with "or" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2611,
        'endLine' => 2624,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereAny' => 
      array (
        'name' => 'orWhereAny',
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
            'startLine' => 2634,
            'endLine' => 2634,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2634,
                'endLine' => 2634,
                'startTokenPos' => 11226,
                'startFilePos' => 83410,
                'endTokenPos' => 11226,
                'endFilePos' => 83413,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2634,
            'endLine' => 2634,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2634,
                'endLine' => 2634,
                'startTokenPos' => 11233,
                'startFilePos' => 83425,
                'endTokenPos' => 11233,
                'endFilePos' => 83428,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2634,
            'endLine' => 2634,
            'startColumn' => 60,
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
 * Add an "or where" clause to the query for multiple columns with "or" conditions between them.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2634,
        'endLine' => 2637,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'whereNone' => 
      array (
        'name' => 'whereNone',
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
            'startLine' => 2648,
            'endLine' => 2648,
            'startColumn' => 31,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2648,
                'endLine' => 2648,
                'startTokenPos' => 11274,
                'startFilePos' => 83906,
                'endTokenPos' => 11274,
                'endFilePos' => 83909,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2648,
            'endLine' => 2648,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2648,
                'endLine' => 2648,
                'startTokenPos' => 11281,
                'startFilePos' => 83921,
                'endTokenPos' => 11281,
                'endFilePos' => 83924,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2648,
            'endLine' => 2648,
            'startColumn' => 59,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2648,
                'endLine' => 2648,
                'startTokenPos' => 11288,
                'startFilePos' => 83938,
                'endTokenPos' => 11288,
                'endFilePos' => 83942,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2648,
            'endLine' => 2648,
            'startColumn' => 74,
            'endColumn' => 89,
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
 * Add a "where not" clause to the query for multiple columns where none of the conditions should be true.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2648,
        'endLine' => 2651,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orWhereNone' => 
      array (
        'name' => 'orWhereNone',
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
            'startLine' => 2661,
            'endLine' => 2661,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2661,
                'endLine' => 2661,
                'startTokenPos' => 11331,
                'startFilePos' => 84405,
                'endTokenPos' => 11331,
                'endFilePos' => 84408,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2661,
            'endLine' => 2661,
            'startColumn' => 43,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2661,
                'endLine' => 2661,
                'startTokenPos' => 11338,
                'startFilePos' => 84420,
                'endTokenPos' => 11338,
                'endFilePos' => 84423,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2661,
            'endLine' => 2661,
            'startColumn' => 61,
            'endColumn' => 73,
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
 * Add an "or where not" clause to the query for multiple columns where none of the conditions should be true.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression[]|\\Closure[]|string[]  $columns
 * @param  mixed  $operator
 * @param  mixed  $value
 * @return $this
 */',
        'startLine' => 2661,
        'endLine' => 2664,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'groupBy' => 
      array (
        'name' => 'groupBy',
        'parameters' => 
        array (
          'groups' => 
          array (
            'name' => 'groups',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2672,
            'endLine' => 2672,
            'startColumn' => 29,
            'endColumn' => 38,
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
 * Add a "group by" clause to the query.
 *
 * @param  array|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  ...$groups
 * @return $this
 */',
        'startLine' => 2672,
        'endLine' => 2682,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'groupByRaw' => 
      array (
        'name' => 'groupByRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
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
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2690,
                'endLine' => 2690,
                'startTokenPos' => 11442,
                'startFilePos' => 85120,
                'endTokenPos' => 11443,
                'endFilePos' => 85121,
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
            'startLine' => 2690,
            'endLine' => 2690,
            'startColumn' => 38,
            'endColumn' => 57,
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
 * Add a raw "groupBy" clause to the query.
 *
 * @param  literal-string  $sql
 * @return $this
 */',
        'startLine' => 2690,
        'endLine' => 2697,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'having' => 
      array (
        'name' => 'having',
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
            'startLine' => 2708,
            'endLine' => 2708,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2708,
                'endLine' => 2708,
                'startTokenPos' => 11497,
                'startFilePos' => 85692,
                'endTokenPos' => 11497,
                'endFilePos' => 85695,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2708,
            'endLine' => 2708,
            'startColumn' => 37,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2708,
                'endLine' => 2708,
                'startTokenPos' => 11504,
                'startFilePos' => 85707,
                'endTokenPos' => 11504,
                'endFilePos' => 85710,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2708,
            'endLine' => 2708,
            'startColumn' => 55,
            'endColumn' => 67,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2708,
                'endLine' => 2708,
                'startTokenPos' => 11511,
                'startFilePos' => 85724,
                'endTokenPos' => 11511,
                'endFilePos' => 85728,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2708,
            'endLine' => 2708,
            'startColumn' => 70,
            'endColumn' => 85,
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
 * Add a "having" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\Closure|string  $column
 * @param  \\DateTimeInterface|string|int|float|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\DateTimeInterface|string|int|float|null  $value
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2708,
        'endLine' => 2749,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHaving' => 
      array (
        'name' => 'orHaving',
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
            'startLine' => 2759,
            'endLine' => 2759,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'operator' => 
          array (
            'name' => 'operator',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2759,
                'endLine' => 2759,
                'startTokenPos' => 11810,
                'startFilePos' => 87654,
                'endTokenPos' => 11810,
                'endFilePos' => 87657,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2759,
            'endLine' => 2759,
            'startColumn' => 39,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 2759,
                'endLine' => 2759,
                'startTokenPos' => 11817,
                'startFilePos' => 87669,
                'endTokenPos' => 11817,
                'endFilePos' => 87672,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2759,
            'endLine' => 2759,
            'startColumn' => 57,
            'endColumn' => 69,
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
 * Add an "or having" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\Closure|string  $column
 * @param  \\DateTimeInterface|string|int|float|null  $operator
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|\\DateTimeInterface|string|int|float|null  $value
 * @return $this
 */',
        'startLine' => 2759,
        'endLine' => 2766,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNested' => 
      array (
        'name' => 'havingNested',
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
            'startLine' => 2774,
            'endLine' => 2774,
            'startColumn' => 34,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2774,
                'endLine' => 2774,
                'startTokenPos' => 11891,
                'startFilePos' => 88071,
                'endTokenPos' => 11891,
                'endFilePos' => 88075,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2774,
            'endLine' => 2774,
            'startColumn' => 53,
            'endColumn' => 68,
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
 * Add a nested "having" statement to the query.
 *
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2774,
        'endLine' => 2779,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addNestedHavingQuery' => 
      array (
        'name' => 'addNestedHavingQuery',
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
            'startLine' => 2788,
            'endLine' => 2788,
            'startColumn' => 42,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2788,
                'endLine' => 2788,
                'startTokenPos' => 11940,
                'startFilePos' => 88475,
                'endTokenPos' => 11940,
                'endFilePos' => 88479,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2788,
            'endLine' => 2788,
            'startColumn' => 50,
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
 * Add another query builder as a nested having to the query builder.
 *
 * @param  \\Illuminate\\Database\\Query\\Builder  $query
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2788,
        'endLine' => 2799,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNull' => 
      array (
        'name' => 'havingNull',
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
            'startLine' => 2809,
            'endLine' => 2809,
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2809,
                'endLine' => 2809,
                'startTokenPos' => 12038,
                'startFilePos' => 89007,
                'endTokenPos' => 12038,
                'endFilePos' => 89011,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2809,
            'endLine' => 2809,
            'startColumn' => 42,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2809,
                'endLine' => 2809,
                'startTokenPos' => 12045,
                'startFilePos' => 89021,
                'endTokenPos' => 12045,
                'endFilePos' => 89025,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2809,
            'endLine' => 2809,
            'startColumn' => 60,
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
 * Add a "having null" clause to the query.
 *
 * @param  array|string  $columns
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2809,
        'endLine' => 2818,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingNull' => 
      array (
        'name' => 'orHavingNull',
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
            'startLine' => 2826,
            'endLine' => 2826,
            'startColumn' => 34,
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
 * Add an "or having null" clause to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2826,
        'endLine' => 2829,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNotNull' => 
      array (
        'name' => 'havingNotNull',
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
            'startLine' => 2838,
            'endLine' => 2838,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2838,
                'endLine' => 2838,
                'startTokenPos' => 12165,
                'startFilePos' => 89715,
                'endTokenPos' => 12165,
                'endFilePos' => 89719,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2838,
            'endLine' => 2838,
            'startColumn' => 45,
            'endColumn' => 60,
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
 * Add a "having not null" clause to the query.
 *
 * @param  array|string  $columns
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2838,
        'endLine' => 2841,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingNotNull' => 
      array (
        'name' => 'orHavingNotNull',
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
            'startLine' => 2849,
            'endLine' => 2849,
            'startColumn' => 37,
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
 * Add an "or having not null" clause to the query.
 *
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 2849,
        'endLine' => 2852,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingBetween' => 
      array (
        'name' => 'havingBetween',
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
            'startLine' => 2862,
            'endLine' => 2862,
            'startColumn' => 35,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2862,
            'endLine' => 2862,
            'startColumn' => 44,
            'endColumn' => 59,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2862,
                'endLine' => 2862,
                'startTokenPos' => 12236,
                'startFilePos' => 90292,
                'endTokenPos' => 12236,
                'endFilePos' => 90296,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2862,
            'endLine' => 2862,
            'startColumn' => 62,
            'endColumn' => 77,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'not' => 
          array (
            'name' => 'not',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 2862,
                'endLine' => 2862,
                'startTokenPos' => 12243,
                'startFilePos' => 90306,
                'endTokenPos' => 12243,
                'endFilePos' => 90310,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2862,
            'endLine' => 2862,
            'startColumn' => 80,
            'endColumn' => 91,
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
 * Add a "having between" clause to the query.
 *
 * @param  string  $column
 * @param  string  $boolean
 * @param  bool  $not
 * @return $this
 */',
        'startLine' => 2862,
        'endLine' => 2875,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingNotBetween' => 
      array (
        'name' => 'havingNotBetween',
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
            'startLine' => 2885,
            'endLine' => 2885,
            'startColumn' => 38,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2885,
            'endLine' => 2885,
            'startColumn' => 47,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2885,
                'endLine' => 2885,
                'startTokenPos' => 12383,
                'startFilePos' => 90994,
                'endTokenPos' => 12383,
                'endFilePos' => 90998,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2885,
            'endLine' => 2885,
            'startColumn' => 65,
            'endColumn' => 80,
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
 * Add a "having not between" clause to the query.
 *
 * @param  string  $column
 * @param  iterable  $values
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2885,
        'endLine' => 2888,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingBetween' => 
      array (
        'name' => 'orHavingBetween',
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
            'startLine' => 2897,
            'endLine' => 2897,
            'startColumn' => 37,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2897,
            'endLine' => 2897,
            'startColumn' => 46,
            'endColumn' => 61,
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
 * Add an "or having between" clause to the query.
 *
 * @param  string  $column
 * @param  iterable  $values
 * @return $this
 */',
        'startLine' => 2897,
        'endLine' => 2900,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingNotBetween' => 
      array (
        'name' => 'orHavingNotBetween',
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
            'startLine' => 2909,
            'endLine' => 2909,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'iterable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2909,
            'endLine' => 2909,
            'startColumn' => 49,
            'endColumn' => 64,
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
 * Add an "or having not between" clause to the query.
 *
 * @param  string  $column
 * @param  iterable  $values
 * @return $this
 */',
        'startLine' => 2909,
        'endLine' => 2912,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'resolveDatePeriodBounds' => 
      array (
        'name' => 'resolveDatePeriodBounds',
        'parameters' => 
        array (
          'period' => 
          array (
            'name' => 'period',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DatePeriod',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2920,
            'endLine' => 2920,
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
 * Resolve the start and end dates from a DatePeriod.
 *
 * @param  \\DatePeriod  $period
 * @return array{\\DateTimeInterface, \\DateTimeInterface}
 */',
        'startLine' => 2920,
        'endLine' => 2935,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'havingRaw' => 
      array (
        'name' => 'havingRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2944,
            'endLine' => 2944,
            'startColumn' => 31,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2944,
                'endLine' => 2944,
                'startTokenPos' => 12628,
                'startFilePos' => 92533,
                'endTokenPos' => 12629,
                'endFilePos' => 92534,
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
            'startLine' => 2944,
            'endLine' => 2944,
            'startColumn' => 37,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'boolean' => 
          array (
            'name' => 'boolean',
            'default' => 
            array (
              'code' => '\'and\'',
              'attributes' => 
              array (
                'startLine' => 2944,
                'endLine' => 2944,
                'startTokenPos' => 12636,
                'startFilePos' => 92548,
                'endTokenPos' => 12636,
                'endFilePos' => 92552,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2944,
            'endLine' => 2944,
            'startColumn' => 59,
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
 * Add a raw "having" clause to the query.
 *
 * @param  literal-string  $sql
 * @param  string  $boolean
 * @return $this
 */',
        'startLine' => 2944,
        'endLine' => 2953,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orHavingRaw' => 
      array (
        'name' => 'orHavingRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2961,
            'endLine' => 2961,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 2961,
                'endLine' => 2961,
                'startTokenPos' => 12714,
                'startFilePos' => 92934,
                'endTokenPos' => 12715,
                'endFilePos' => 92935,
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
            'startLine' => 2961,
            'endLine' => 2961,
            'startColumn' => 39,
            'endColumn' => 58,
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
 * Add a raw "or having" clause to the query.
 *
 * @param  literal-string  $sql
 * @return $this
 */',
        'startLine' => 2961,
        'endLine' => 2964,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderBy' => 
      array (
        'name' => 'orderBy',
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
            'startLine' => 2975,
            'endLine' => 2975,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'direction' => 
          array (
            'name' => 'direction',
            'default' => 
            array (
              'code' => '\\SortDirection::Ascending',
              'attributes' => 
              array (
                'startLine' => 2975,
                'endLine' => 2975,
                'startTokenPos' => 12753,
                'startFilePos' => 93413,
                'endTokenPos' => 12755,
                'endFilePos' => 93436,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 2975,
            'endLine' => 2975,
            'startColumn' => 38,
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
 * Add an "order by" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  SortDirection|\'asc\'|\'desc\'  $direction
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 2975,
        'endLine' => 3001,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderByDesc' => 
      array (
        'name' => 'orderByDesc',
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
            'startLine' => 3009,
            'endLine' => 3009,
            'startColumn' => 33,
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
 * Add a descending "order by" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 3009,
        'endLine' => 3012,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'latest' => 
      array (
        'name' => 'latest',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'created_at\'',
              'attributes' => 
              array (
                'startLine' => 3020,
                'endLine' => 3020,
                'startTokenPos' => 13011,
                'startFilePos' => 94997,
                'endTokenPos' => 13011,
                'endFilePos' => 95008,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3020,
            'endLine' => 3020,
            'startColumn' => 28,
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
 * Add an "order by" clause for a timestamp to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 3020,
        'endLine' => 3023,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'oldest' => 
      array (
        'name' => 'oldest',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'created_at\'',
              'attributes' => 
              array (
                'startLine' => 3031,
                'endLine' => 3031,
                'startTokenPos' => 13045,
                'startFilePos' => 95357,
                'endTokenPos' => 13045,
                'endFilePos' => 95368,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3031,
            'endLine' => 3031,
            'startColumn' => 28,
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
 * Add an "order by" clause for a timestamp to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return $this
 */',
        'startLine' => 3031,
        'endLine' => 3034,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderByVectorDistance' => 
      array (
        'name' => 'orderByVectorDistance',
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
            'startLine' => 3045,
            'endLine' => 3045,
            'startColumn' => 43,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'vector' => 
          array (
            'name' => 'vector',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3045,
            'endLine' => 3045,
            'startColumn' => 52,
            'endColumn' => 58,
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
 * Add a vector-distance "order by" clause to the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Support\\Collection<int, float>|\\Illuminate\\Contracts\\Support\\Arrayable|array<int, float>  $vector
 * @return $this
 *
 * @throws \\JsonException
 */',
        'startLine' => 3045,
        'endLine' => 3069,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'inRandomOrder' => 
      array (
        'name' => 'inRandomOrder',
        'parameters' => 
        array (
          'seed' => 
          array (
            'name' => 'seed',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 3077,
                'endLine' => 3077,
                'startTokenPos' => 13251,
                'startFilePos' => 96709,
                'endTokenPos' => 13251,
                'endFilePos' => 96710,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3077,
            'endLine' => 3077,
            'startColumn' => 35,
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
 * Put the query\'s results in random order.
 *
 * @param  string|int  $seed
 * @return $this
 */',
        'startLine' => 3077,
        'endLine' => 3080,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'inOrderOf' => 
      array (
        'name' => 'inOrderOf',
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
            'startLine' => 3089,
            'endLine' => 3089,
            'startColumn' => 31,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3089,
            'endLine' => 3089,
            'startColumn' => 40,
            'endColumn' => 46,
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
 * Add an "order by" clause to order results by a given sequence of values.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $values
 * @return $this
 */',
        'startLine' => 3089,
        'endLine' => 3110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'orderByRaw' => 
      array (
        'name' => 'orderByRaw',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3119,
            'endLine' => 3119,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 3119,
                'endLine' => 3119,
                'startTokenPos' => 13438,
                'startFilePos' => 97846,
                'endTokenPos' => 13439,
                'endFilePos' => 97847,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3119,
            'endLine' => 3119,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Add a raw "order by" clause to the query.
 *
 * @param  literal-string  $sql
 * @param  array  $bindings
 * @return $this
 */',
        'startLine' => 3119,
        'endLine' => 3128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'skip' => 
      array (
        'name' => 'skip',
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
            'startLine' => 3136,
            'endLine' => 3136,
            'startColumn' => 26,
            'endColumn' => 31,
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
 * Alias to set the "offset" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 3136,
        'endLine' => 3139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'offset' => 
      array (
        'name' => 'offset',
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
            'startLine' => 3147,
            'endLine' => 3147,
            'startColumn' => 28,
            'endColumn' => 33,
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
 * Set the "offset" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 3147,
        'endLine' => 3154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'take' => 
      array (
        'name' => 'take',
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
            'startLine' => 3162,
            'endLine' => 3162,
            'startColumn' => 26,
            'endColumn' => 31,
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
 * Alias to set the "limit" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 3162,
        'endLine' => 3165,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'limit' => 
      array (
        'name' => 'limit',
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
            'startLine' => 3173,
            'endLine' => 3173,
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
 * Set the "limit" value of the query.
 *
 * @param  int  $value
 * @return $this
 */',
        'startLine' => 3173,
        'endLine' => 3182,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'groupLimit' => 
      array (
        'name' => 'groupLimit',
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
            'startLine' => 3191,
            'endLine' => 3191,
            'startColumn' => 32,
            'endColumn' => 37,
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
            'startLine' => 3191,
            'endLine' => 3191,
            'startColumn' => 40,
            'endColumn' => 46,
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
 * Add a "group limit" clause to the query.
 *
 * @param  int  $value
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 3191,
        'endLine' => 3198,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forPage' => 
      array (
        'name' => 'forPage',
        'parameters' => 
        array (
          'page' => 
          array (
            'name' => 'page',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3207,
            'endLine' => 3207,
            'startColumn' => 29,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3207,
                'endLine' => 3207,
                'startTokenPos' => 13768,
                'startFilePos' => 99691,
                'endTokenPos' => 13768,
                'endFilePos' => 99692,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3207,
            'endLine' => 3207,
            'startColumn' => 36,
            'endColumn' => 48,
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
 * Set the limit and offset for a given page.
 *
 * @param  int  $page
 * @param  int  $perPage
 * @return $this
 */',
        'startLine' => 3207,
        'endLine' => 3210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forPageBeforeId' => 
      array (
        'name' => 'forPageBeforeId',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3220,
                'endLine' => 3220,
                'startTokenPos' => 13812,
                'startFilePos' => 100043,
                'endTokenPos' => 13812,
                'endFilePos' => 100044,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3220,
            'endLine' => 3220,
            'startColumn' => 37,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'lastId' => 
          array (
            'name' => 'lastId',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 3220,
                'endLine' => 3220,
                'startTokenPos' => 13819,
                'startFilePos' => 100057,
                'endTokenPos' => 13819,
                'endFilePos' => 100057,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3220,
            'endLine' => 3220,
            'startColumn' => 52,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'id\'',
              'attributes' => 
              array (
                'startLine' => 3220,
                'endLine' => 3220,
                'startTokenPos' => 13826,
                'startFilePos' => 100070,
                'endTokenPos' => 13826,
                'endFilePos' => 100073,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3220,
            'endLine' => 3220,
            'startColumn' => 65,
            'endColumn' => 78,
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
 * Constrain the query to the previous "page" of results before a given ID.
 *
 * @param  int  $perPage
 * @param  int|null  $lastId
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 3220,
        'endLine' => 3232,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forPageAfterId' => 
      array (
        'name' => 'forPageAfterId',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3242,
                'endLine' => 3242,
                'startTokenPos' => 13921,
                'startFilePos' => 100661,
                'endTokenPos' => 13921,
                'endFilePos' => 100662,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3242,
            'endLine' => 3242,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'lastId' => 
          array (
            'name' => 'lastId',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 3242,
                'endLine' => 3242,
                'startTokenPos' => 13928,
                'startFilePos' => 100675,
                'endTokenPos' => 13928,
                'endFilePos' => 100675,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3242,
            'endLine' => 3242,
            'startColumn' => 51,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => '\'id\'',
              'attributes' => 
              array (
                'startLine' => 3242,
                'endLine' => 3242,
                'startTokenPos' => 13935,
                'startFilePos' => 100688,
                'endTokenPos' => 13935,
                'endFilePos' => 100691,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3242,
            'endLine' => 3242,
            'startColumn' => 64,
            'endColumn' => 77,
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
 * Constrain the query to the next "page" of results after a given ID.
 *
 * @param  int  $perPage
 * @param  int|null  $lastId
 * @param  string  $column
 * @return $this
 */',
        'startLine' => 3242,
        'endLine' => 3254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'reorder' => 
      array (
        'name' => 'reorder',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3263,
                'endLine' => 3263,
                'startTokenPos' => 14030,
                'startFilePos' => 101350,
                'endTokenPos' => 14030,
                'endFilePos' => 101353,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3263,
            'endLine' => 3263,
            'startColumn' => 29,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'direction' => 
          array (
            'name' => 'direction',
            'default' => 
            array (
              'code' => '\\SortDirection::Ascending',
              'attributes' => 
              array (
                'startLine' => 3263,
                'endLine' => 3263,
                'startTokenPos' => 14037,
                'startFilePos' => 101369,
                'endTokenPos' => 14039,
                'endFilePos' => 101392,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3263,
            'endLine' => 3263,
            'startColumn' => 45,
            'endColumn' => 81,
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
 * Remove all existing orders and optionally add a new order.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $column
 * @param  SortDirection|\'asc\'|\'desc\'  $direction
 * @return $this
 */',
        'startLine' => 3263,
        'endLine' => 3275,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'reorderDesc' => 
      array (
        'name' => 'reorderDesc',
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
            'startLine' => 3283,
            'endLine' => 3283,
            'startColumn' => 33,
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
 * Add descending "reorder" clause to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Contracts\\Database\\Query\\Expression|string|null  $column
 * @return $this
 */',
        'startLine' => 3283,
        'endLine' => 3286,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'removeExistingOrdersFor' => 
      array (
        'name' => 'removeExistingOrdersFor',
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
            'startLine' => 3294,
            'endLine' => 3294,
            'startColumn' => 48,
            'endColumn' => 54,
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
 * Get an array with all orders with a given column removed.
 *
 * @param  string  $column
 * @return array
 */',
        'startLine' => 3294,
        'endLine' => 3300,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'union' => 
      array (
        'name' => 'union',
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
            'startLine' => 3309,
            'endLine' => 3309,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'all' => 
          array (
            'name' => 'all',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 3309,
                'endLine' => 3309,
                'startTokenPos' => 14233,
                'startFilePos' => 102672,
                'endTokenPos' => 14233,
                'endFilePos' => 102676,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3309,
            'endLine' => 3309,
            'startColumn' => 35,
            'endColumn' => 46,
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
 * Add a "union" statement to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $query
 * @param  bool  $all
 * @return $this
 */',
        'startLine' => 3309,
        'endLine' => 3320,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'unionAll' => 
      array (
        'name' => 'unionAll',
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
            'startLine' => 3328,
            'endLine' => 3328,
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
 * Add a "union all" statement to the query.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>  $query
 * @return $this
 */',
        'startLine' => 3328,
        'endLine' => 3331,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'lock' => 
      array (
        'name' => 'lock',
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
                'startLine' => 3339,
                'endLine' => 3339,
                'startTokenPos' => 14352,
                'startFilePos' => 103388,
                'endTokenPos' => 14352,
                'endFilePos' => 103391,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3339,
            'endLine' => 3339,
            'startColumn' => 26,
            'endColumn' => 38,
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
 * Lock the selected rows in the table.
 *
 * @param  string|bool  $value
 * @return $this
 */',
        'startLine' => 3339,
        'endLine' => 3348,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'lockForUpdate' => 
      array (
        'name' => 'lockForUpdate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Lock the selected rows in the table for updating.
 *
 * @return $this
 */',
        'startLine' => 3355,
        'endLine' => 3358,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'sharedLock' => 
      array (
        'name' => 'sharedLock',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Share lock the selected rows in the table.
 *
 * @return $this
 */',
        'startLine' => 3365,
        'endLine' => 3368,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'timeout' => 
      array (
        'name' => 'timeout',
        'parameters' => 
        array (
          'seconds' => 
          array (
            'name' => 'seconds',
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
                      'name' => 'int',
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
            'startLine' => 3378,
            'endLine' => 3378,
            'startColumn' => 29,
            'endColumn' => 41,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set a query execution timeout in seconds.
 *
 * @param  int|null  $seconds
 * @return $this
 *
 * @throws InvalidArgumentException
 */',
        'startLine' => 3378,
        'endLine' => 3387,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'beforeQuery' => 
      array (
        'name' => 'beforeQuery',
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
            'startLine' => 3394,
            'endLine' => 3394,
            'startColumn' => 33,
            'endColumn' => 50,
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
 * Register a closure to be invoked before the query is executed.
 *
 * @return $this
 */',
        'startLine' => 3394,
        'endLine' => 3399,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'applyBeforeQueryCallbacks' => 
      array (
        'name' => 'applyBeforeQueryCallbacks',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Invoke the "before query" modification callbacks.
 *
 * @return void
 */',
        'startLine' => 3406,
        'endLine' => 3413,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'afterQuery' => 
      array (
        'name' => 'afterQuery',
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
            'startLine' => 3420,
            'endLine' => 3420,
            'startColumn' => 32,
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
 * Register a closure to be invoked after the query is executed.
 *
 * @return $this
 */',
        'startLine' => 3420,
        'endLine' => 3425,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'applyAfterQueryCallbacks' => 
      array (
        'name' => 'applyAfterQueryCallbacks',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3433,
            'endLine' => 3433,
            'startColumn' => 46,
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
 * Invoke the "after query" modification callbacks.
 *
 * @param  mixed  $result
 * @return mixed
 */',
        'startLine' => 3433,
        'endLine' => 3440,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'toSql' => 
      array (
        'name' => 'toSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL representation of the query.
 *
 * @return string
 */',
        'startLine' => 3447,
        'endLine' => 3452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'toRawSql' => 
      array (
        'name' => 'toRawSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the raw SQL representation of the query with embedded bindings.
 *
 * @return string
 */',
        'startLine' => 3459,
        'endLine' => 3464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'find' => 
      array (
        'name' => 'find',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3473,
            'endLine' => 3473,
            'startColumn' => 26,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3473,
                'endLine' => 3473,
                'startTokenPos' => 14769,
                'startFilePos' => 106387,
                'endTokenPos' => 14771,
                'endFilePos' => 106391,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3473,
            'endLine' => 3473,
            'startColumn' => 31,
            'endColumn' => 46,
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
 * Execute a query for a single record by ID.
 *
 * @param  int|string  $id
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return \\stdClass|null
 */',
        'startLine' => 3473,
        'endLine' => 3476,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'findOr' => 
      array (
        'name' => 'findOr',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3488,
            'endLine' => 3488,
            'startColumn' => 28,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3488,
                'endLine' => 3488,
                'startTokenPos' => 14814,
                'startFilePos' => 106908,
                'endTokenPos' => 14816,
                'endFilePos' => 106912,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3488,
            'endLine' => 3488,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3488,
                'endLine' => 3488,
                'startTokenPos' => 14826,
                'startFilePos' => 106936,
                'endTokenPos' => 14826,
                'endFilePos' => 106939,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3488,
            'endLine' => 3488,
            'startColumn' => 51,
            'endColumn' => 75,
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
 * Execute a query for a single record by ID or call a callback.
 *
 * @template TValue
 *
 * @param  mixed  $id
 * @param  (\\Closure(): TValue)|string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  (\\Closure(): TValue)|null  $callback
 * @return \\stdClass|TValue
 */',
        'startLine' => 3488,
        'endLine' => 3501,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'value' => 
      array (
        'name' => 'value',
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
            'startLine' => 3509,
            'endLine' => 3509,
            'startColumn' => 27,
            'endColumn' => 33,
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
 * Get a single column\'s value from the first result of a query.
 *
 * @param  string  $column
 * @return mixed
 */',
        'startLine' => 3509,
        'endLine' => 3514,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'rawValue' => 
      array (
        'name' => 'rawValue',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
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
            'startLine' => 3522,
            'endLine' => 3522,
            'startColumn' => 30,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bindings' => 
          array (
            'name' => 'bindings',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 3522,
                'endLine' => 3522,
                'startTokenPos' => 14973,
                'startFilePos' => 107730,
                'endTokenPos' => 14974,
                'endFilePos' => 107731,
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
            'startLine' => 3522,
            'endLine' => 3522,
            'startColumn' => 50,
            'endColumn' => 69,
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
 * Get a single expression value from the first result of a query.
 *
 * @param  literal-string  $expression
 * @return mixed
 */',
        'startLine' => 3522,
        'endLine' => 3527,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'soleValue' => 
      array (
        'name' => 'soleValue',
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
            'startLine' => 3538,
            'endLine' => 3538,
            'startColumn' => 31,
            'endColumn' => 37,
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
 * Get a single column\'s value from the first result of a query if it\'s the sole matching record.
 *
 * @param  string  $column
 * @return mixed
 *
 * @throws \\Illuminate\\Database\\RecordsNotFoundException
 * @throws \\Illuminate\\Database\\MultipleRecordsFoundException
 */',
        'startLine' => 3538,
        'endLine' => 3543,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
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
                'startLine' => 3551,
                'endLine' => 3551,
                'startTokenPos' => 15074,
                'startFilePos' => 108649,
                'endTokenPos' => 15076,
                'endFilePos' => 108653,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3551,
            'endLine' => 3551,
            'startColumn' => 25,
            'endColumn' => 40,
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
 * Execute the query as a "select" statement.
 *
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return \\Illuminate\\Support\\Collection<int, \\stdClass>
 */',
        'startLine' => 3551,
        'endLine' => 3564,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'runSelect' => 
      array (
        'name' => 'runSelect',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the query as a "select" statement against the connection.
 *
 * @return array
 */',
        'startLine' => 3571,
        'endLine' => 3576,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'withoutGroupLimitKeys' => 
      array (
        'name' => 'withoutGroupLimitKeys',
        'parameters' => 
        array (
          'items' => 
          array (
            'name' => 'items',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3584,
            'endLine' => 3584,
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
 * Remove the group limit keys from the results in the collection.
 *
 * @param  \\Illuminate\\Support\\Collection  $items
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3584,
        'endLine' => 3602,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'paginate' => 
      array (
        'name' => 'paginate',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3614,
                'endLine' => 3614,
                'startTokenPos' => 15384,
                'startFilePos' => 110602,
                'endTokenPos' => 15384,
                'endFilePos' => 110603,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3614,
            'endLine' => 3614,
            'startColumn' => 30,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3614,
                'endLine' => 3614,
                'startTokenPos' => 15391,
                'startFilePos' => 110617,
                'endTokenPos' => 15393,
                'endFilePos' => 110621,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3614,
            'endLine' => 3614,
            'startColumn' => 45,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'pageName' => 
          array (
            'name' => 'pageName',
            'default' => 
            array (
              'code' => '\'page\'',
              'attributes' => 
              array (
                'startLine' => 3614,
                'endLine' => 3614,
                'startTokenPos' => 15400,
                'startFilePos' => 110636,
                'endTokenPos' => 15400,
                'endFilePos' => 110641,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3614,
            'endLine' => 3614,
            'startColumn' => 63,
            'endColumn' => 80,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'page' => 
          array (
            'name' => 'page',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3614,
                'endLine' => 3614,
                'startTokenPos' => 15407,
                'startFilePos' => 110652,
                'endTokenPos' => 15407,
                'endFilePos' => 110655,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3614,
            'endLine' => 3614,
            'startColumn' => 83,
            'endColumn' => 94,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'total' => 
          array (
            'name' => 'total',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3614,
                'endLine' => 3614,
                'startTokenPos' => 15414,
                'startFilePos' => 110667,
                'endTokenPos' => 15414,
                'endFilePos' => 110670,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3614,
            'endLine' => 3614,
            'startColumn' => 97,
            'endColumn' => 109,
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
 * Paginate the given query into a simple paginator.
 *
 * @param  int|\\Closure  $perPage
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  string  $pageName
 * @param  int|null  $page
 * @param  \\Closure|int|null  $total
 * @return \\Illuminate\\Pagination\\LengthAwarePaginator
 */',
        'startLine' => 3614,
        'endLine' => 3628,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'simplePaginate' => 
      array (
        'name' => 'simplePaginate',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3641,
                'endLine' => 3641,
                'startTokenPos' => 15553,
                'startFilePos' => 111629,
                'endTokenPos' => 15553,
                'endFilePos' => 111630,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3641,
            'endLine' => 3641,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3641,
                'endLine' => 3641,
                'startTokenPos' => 15560,
                'startFilePos' => 111644,
                'endTokenPos' => 15562,
                'endFilePos' => 111648,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3641,
            'endLine' => 3641,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'pageName' => 
          array (
            'name' => 'pageName',
            'default' => 
            array (
              'code' => '\'page\'',
              'attributes' => 
              array (
                'startLine' => 3641,
                'endLine' => 3641,
                'startTokenPos' => 15569,
                'startFilePos' => 111663,
                'endTokenPos' => 15569,
                'endFilePos' => 111668,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3641,
            'endLine' => 3641,
            'startColumn' => 69,
            'endColumn' => 86,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'page' => 
          array (
            'name' => 'page',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3641,
                'endLine' => 3641,
                'startTokenPos' => 15576,
                'startFilePos' => 111679,
                'endTokenPos' => 15576,
                'endFilePos' => 111682,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3641,
            'endLine' => 3641,
            'startColumn' => 89,
            'endColumn' => 100,
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
 * Get a paginator only supporting simple next and previous links.
 *
 * This is more efficient on larger data-sets, etc.
 *
 * @param  int  $perPage
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  string  $pageName
 * @param  int|null  $page
 * @return \\Illuminate\\Contracts\\Pagination\\Paginator
 */',
        'startLine' => 3641,
        'endLine' => 3651,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cursorPaginate' => 
      array (
        'name' => 'cursorPaginate',
        'parameters' => 
        array (
          'perPage' => 
          array (
            'name' => 'perPage',
            'default' => 
            array (
              'code' => '15',
              'attributes' => 
              array (
                'startLine' => 3664,
                'endLine' => 3664,
                'startTokenPos' => 15683,
                'startFilePos' => 112562,
                'endTokenPos' => 15683,
                'endFilePos' => 112563,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3664,
            'endLine' => 3664,
            'startColumn' => 36,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 3664,
                'endLine' => 3664,
                'startTokenPos' => 15690,
                'startFilePos' => 112577,
                'endTokenPos' => 15692,
                'endFilePos' => 112581,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3664,
            'endLine' => 3664,
            'startColumn' => 51,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'cursorName' => 
          array (
            'name' => 'cursorName',
            'default' => 
            array (
              'code' => '\'cursor\'',
              'attributes' => 
              array (
                'startLine' => 3664,
                'endLine' => 3664,
                'startTokenPos' => 15699,
                'startFilePos' => 112598,
                'endTokenPos' => 15699,
                'endFilePos' => 112605,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3664,
            'endLine' => 3664,
            'startColumn' => 69,
            'endColumn' => 90,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'cursor' => 
          array (
            'name' => 'cursor',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3664,
                'endLine' => 3664,
                'startTokenPos' => 15706,
                'startFilePos' => 112618,
                'endTokenPos' => 15706,
                'endFilePos' => 112621,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3664,
            'endLine' => 3664,
            'startColumn' => 93,
            'endColumn' => 106,
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
 * Get a paginator only supporting simple next and previous links.
 *
 * This is more efficient on larger data-sets, etc.
 *
 * @param  int|null  $perPage
 * @param  string|\\Illuminate\\Contracts\\Database\\Query\\Expression|array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @param  string  $cursorName
 * @param  \\Illuminate\\Pagination\\Cursor|string|null  $cursor
 * @return \\Illuminate\\Contracts\\Pagination\\CursorPaginator
 */',
        'startLine' => 3664,
        'endLine' => 3667,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ensureOrderForCursorPagination' => 
      array (
        'name' => 'ensureOrderForCursorPagination',
        'parameters' => 
        array (
          'shouldReverse' => 
          array (
            'name' => 'shouldReverse',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 3675,
                'endLine' => 3675,
                'startTokenPos' => 15744,
                'startFilePos' => 112964,
                'endTokenPos' => 15744,
                'endFilePos' => 112968,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3675,
            'endLine' => 3675,
            'startColumn' => 55,
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
 * Ensure the proper order by required for cursor pagination.
 *
 * @param  bool  $shouldReverse
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3675,
        'endLine' => 3701,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getCountForPagination' => 
      array (
        'name' => 'getCountForPagination',
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
                'startLine' => 3709,
                'endLine' => 3709,
                'startTokenPos' => 15991,
                'startFilePos' => 114072,
                'endTokenPos' => 15993,
                'endFilePos' => 114076,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3709,
            'endLine' => 3709,
            'startColumn' => 43,
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
 * Get the count of the total records for the paginator.
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return int<0, max>
 */',
        'startLine' => 3709,
        'endLine' => 3723,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'runPaginationCountQuery' => 
      array (
        'name' => 'runPaginationCountQuery',
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
                'startLine' => 3731,
                'endLine' => 3731,
                'startTokenPos' => 16099,
                'startFilePos' => 114895,
                'endTokenPos' => 16101,
                'endFilePos' => 114899,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3731,
            'endLine' => 3731,
            'startColumn' => 48,
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
 * Run a pagination count query.
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return array<mixed>
 */',
        'startLine' => 3731,
        'endLine' => 3753,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cloneForPaginationCount' => 
      array (
        'name' => 'cloneForPaginationCount',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clone the existing query instance for usage in a pagination subquery.
 *
 * @return self
 */',
        'startLine' => 3760,
        'endLine' => 3764,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'withoutSelectAliases' => 
      array (
        'name' => 'withoutSelectAliases',
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
            'startLine' => 3772,
            'endLine' => 3772,
            'startColumn' => 45,
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
 * Remove the column aliases since they will break count queries.
 *
 * @param  array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>  $columns
 * @return array<string|\\Illuminate\\Contracts\\Database\\Query\\Expression>
 */',
        'startLine' => 3772,
        'endLine' => 3779,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cursor' => 
      array (
        'name' => 'cursor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a lazy collection for the given query.
 *
 * @return \\Illuminate\\Support\\LazyCollection<int, \\stdClass>
 */',
        'startLine' => 3786,
        'endLine' => 3799,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => true,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'enforceOrderBy' => 
      array (
        'name' => 'enforceOrderBy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Throw an exception if the query doesn\'t have an orderBy clause.
 *
 * @return void
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 3808,
        'endLine' => 3813,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'pluck' => 
      array (
        'name' => 'pluck',
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
            'startLine' => 3822,
            'endLine' => 3822,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 3822,
                'endLine' => 3822,
                'startTokenPos' => 16663,
                'startFilePos' => 118037,
                'endTokenPos' => 16663,
                'endFilePos' => 118040,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3822,
            'endLine' => 3822,
            'startColumn' => 36,
            'endColumn' => 46,
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
 * Get a collection instance containing the values of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @param  string|null  $key
 * @return \\Illuminate\\Support\\Collection<array-key, mixed>
 */',
        'startLine' => 3822,
        'endLine' => 3853,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'stripTableForPluck' => 
      array (
        'name' => 'stripTableForPluck',
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
            'startLine' => 3861,
            'endLine' => 3861,
            'startColumn' => 43,
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
 * Strip off the table name or alias from a column identifier.
 *
 * @param  string  $column
 * @return string|null
 */',
        'startLine' => 3861,
        'endLine' => 3874,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'pluckFromObjectColumn' => 
      array (
        'name' => 'pluckFromObjectColumn',
        'parameters' => 
        array (
          'queryResult' => 
          array (
            'name' => 'queryResult',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3884,
            'endLine' => 3884,
            'startColumn' => 46,
            'endColumn' => 57,
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
            'startLine' => 3884,
            'endLine' => 3884,
            'startColumn' => 60,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'startLine' => 3884,
            'endLine' => 3884,
            'startColumn' => 69,
            'endColumn' => 72,
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
 * Retrieve column values from rows represented as objects.
 *
 * @param  array  $queryResult
 * @param  string  $column
 * @param  string  $key
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3884,
        'endLine' => 3899,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'pluckFromArrayColumn' => 
      array (
        'name' => 'pluckFromArrayColumn',
        'parameters' => 
        array (
          'queryResult' => 
          array (
            'name' => 'queryResult',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3909,
            'endLine' => 3909,
            'startColumn' => 45,
            'endColumn' => 56,
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
            'startLine' => 3909,
            'endLine' => 3909,
            'startColumn' => 59,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'startLine' => 3909,
            'endLine' => 3909,
            'startColumn' => 68,
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
 * Retrieve column values from rows represented as arrays.
 *
 * @param  array  $queryResult
 * @param  string  $column
 * @param  string  $key
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 3909,
        'endLine' => 3924,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'implode' => 
      array (
        'name' => 'implode',
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
            'startLine' => 3933,
            'endLine' => 3933,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'glue' => 
          array (
            'name' => 'glue',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 3933,
                'endLine' => 3933,
                'startTokenPos' => 17189,
                'startFilePos' => 121340,
                'endTokenPos' => 17189,
                'endFilePos' => 121341,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3933,
            'endLine' => 3933,
            'startColumn' => 38,
            'endColumn' => 47,
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
 * Concatenate values of a given column as a string.
 *
 * @param  string  $column
 * @param  string  $glue
 * @return string
 */',
        'startLine' => 3933,
        'endLine' => 3936,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'exists' => 
      array (
        'name' => 'exists',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if any rows exist for the current query.
 *
 * @return bool
 */',
        'startLine' => 3943,
        'endLine' => 3961,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'doesntExist' => 
      array (
        'name' => 'doesntExist',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if no rows exist for the current query.
 *
 * @return bool
 */',
        'startLine' => 3968,
        'endLine' => 3971,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'existsOr' => 
      array (
        'name' => 'existsOr',
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
            'startLine' => 3978,
            'endLine' => 3978,
            'startColumn' => 30,
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
 * Execute the given callback if no rows exist for the current query.
 *
 * @return mixed
 */',
        'startLine' => 3978,
        'endLine' => 3981,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'doesntExistOr' => 
      array (
        'name' => 'doesntExistOr',
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
            'startLine' => 3988,
            'endLine' => 3988,
            'startColumn' => 35,
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
 * Execute the given callback if rows exist for the current query.
 *
 * @return mixed
 */',
        'startLine' => 3988,
        'endLine' => 3991,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'count' => 
      array (
        'name' => 'count',
        'parameters' => 
        array (
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '\'*\'',
              'attributes' => 
              array (
                'startLine' => 3999,
                'endLine' => 3999,
                'startTokenPos' => 17427,
                'startFilePos' => 123041,
                'endTokenPos' => 17427,
                'endFilePos' => 123043,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 3999,
            'endLine' => 3999,
            'startColumn' => 27,
            'endColumn' => 40,
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
 * Retrieve the "count" result of the query.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $columns
 * @return int<0, max>
 */',
        'startLine' => 3999,
        'endLine' => 4002,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'min' => 
      array (
        'name' => 'min',
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
            'startLine' => 4010,
            'endLine' => 4010,
            'startColumn' => 25,
            'endColumn' => 31,
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
 * Retrieve the minimum value of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 4010,
        'endLine' => 4013,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'max' => 
      array (
        'name' => 'max',
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
            'startLine' => 4021,
            'endLine' => 4021,
            'startColumn' => 25,
            'endColumn' => 31,
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
 * Retrieve the maximum value of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 4021,
        'endLine' => 4024,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'sum' => 
      array (
        'name' => 'sum',
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
            'startLine' => 4032,
            'endLine' => 4032,
            'startColumn' => 25,
            'endColumn' => 31,
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
 * Retrieve the sum of the values of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 4032,
        'endLine' => 4037,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'avg' => 
      array (
        'name' => 'avg',
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
            'startLine' => 4045,
            'endLine' => 4045,
            'startColumn' => 25,
            'endColumn' => 31,
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
 * Retrieve the average of the values of a given column.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 4045,
        'endLine' => 4048,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'average' => 
      array (
        'name' => 'average',
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
            'startLine' => 4056,
            'endLine' => 4056,
            'startColumn' => 29,
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
 * Alias for the "avg" method.
 *
 * @param  \\Illuminate\\Contracts\\Database\\Query\\Expression|string  $column
 * @return mixed
 */',
        'startLine' => 4056,
        'endLine' => 4059,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'aggregate' => 
      array (
        'name' => 'aggregate',
        'parameters' => 
        array (
          'function' => 
          array (
            'name' => 'function',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4068,
            'endLine' => 4068,
            'startColumn' => 31,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 4068,
                'endLine' => 4068,
                'startTokenPos' => 17626,
                'startFilePos' => 124756,
                'endTokenPos' => 17628,
                'endFilePos' => 124760,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4068,
            'endLine' => 4068,
            'startColumn' => 42,
            'endColumn' => 57,
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
 * Execute an aggregate function on the database.
 *
 * @param  string  $function
 * @param  array  $columns
 * @return mixed
 */',
        'startLine' => 4068,
        'endLine' => 4078,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'numericAggregate' => 
      array (
        'name' => 'numericAggregate',
        'parameters' => 
        array (
          'function' => 
          array (
            'name' => 'function',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4087,
            'endLine' => 4087,
            'startColumn' => 38,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'columns' => 
          array (
            'name' => 'columns',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 4087,
                'endLine' => 4087,
                'startTokenPos' => 17753,
                'startFilePos' => 125386,
                'endTokenPos' => 17755,
                'endFilePos' => 125390,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4087,
            'endLine' => 4087,
            'startColumn' => 49,
            'endColumn' => 64,
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
 * Execute a numeric aggregate function on the database.
 *
 * @param  string  $function
 * @param  array  $columns
 * @return float|int
 */',
        'startLine' => 4087,
        'endLine' => 4108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'setAggregate' => 
      array (
        'name' => 'setAggregate',
        'parameters' => 
        array (
          'function' => 
          array (
            'name' => 'function',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4117,
            'endLine' => 4117,
            'startColumn' => 37,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 4117,
            'endLine' => 4117,
            'startColumn' => 48,
            'endColumn' => 55,
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
 * Set the aggregate property without running the query.
 *
 * @param  string  $function
 * @param  array<\\Illuminate\\Contracts\\Database\\Query\\Expression|string>  $columns
 * @return $this
 */',
        'startLine' => 4117,
        'endLine' => 4128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insert' => 
      array (
        'name' => 'insert',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4135,
            'endLine' => 4135,
            'startColumn' => 28,
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
 * Insert new records into the database.
 *
 * @return bool
 */',
        'startLine' => 4135,
        'endLine' => 4168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertOrIgnore' => 
      array (
        'name' => 'insertOrIgnore',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4175,
            'endLine' => 4175,
            'startColumn' => 36,
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
 * Insert new records into the database while ignoring errors.
 *
 * @return int<0, max>
 */',
        'startLine' => 4175,
        'endLine' => 4197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertOrIgnoreReturning' => 
      array (
        'name' => 'insertOrIgnoreReturning',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4206,
            'endLine' => 4206,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'returning' => 
          array (
            'name' => 'returning',
            'default' => 
            array (
              'code' => '[\'*\']',
              'attributes' => 
              array (
                'startLine' => 4206,
                'endLine' => 4206,
                'startTokenPos' => 18275,
                'startFilePos' => 129337,
                'endTokenPos' => 18277,
                'endFilePos' => 129341,
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
            'startLine' => 4206,
            'endLine' => 4206,
            'startColumn' => 60,
            'endColumn' => 83,
            'parameterIndex' => 1,
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
                'startLine' => 4206,
                'endLine' => 4206,
                'startTokenPos' => 18290,
                'startFilePos' => 129374,
                'endTokenPos' => 18290,
                'endFilePos' => 129377,
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
            'startLine' => 4206,
            'endLine' => 4206,
            'startColumn' => 86,
            'endColumn' => 119,
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
 * Insert new records into the database and returning specified columns with optional ignoring specific conflicts.
 *
 * @param  non-empty-array<non-empty-string>  $returning
 * @param  non-empty-string|non-empty-array<non-empty-string>|null  $uniqueBy
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 4206,
        'endLine' => 4241,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertGetId' => 
      array (
        'name' => 'insertGetId',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4249,
            'endLine' => 4249,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sequence' => 
          array (
            'name' => 'sequence',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4249,
                'endLine' => 4249,
                'startTokenPos' => 18562,
                'startFilePos' => 130650,
                'endTokenPos' => 18562,
                'endFilePos' => 130653,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4249,
            'endLine' => 4249,
            'startColumn' => 48,
            'endColumn' => 63,
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
 * Insert a new record and get the value of the primary key.
 *
 * @param  string|null  $sequence
 * @return int
 */',
        'startLine' => 4249,
        'endLine' => 4258,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertUsing' => 
      array (
        'name' => 'insertUsing',
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
            'startLine' => 4266,
            'endLine' => 4266,
            'startColumn' => 33,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 4266,
            'endLine' => 4266,
            'startColumn' => 49,
            'endColumn' => 54,
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
 * Insert new records into the table using a subquery.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return int
 */',
        'startLine' => 4266,
        'endLine' => 4276,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'insertOrIgnoreUsing' => 
      array (
        'name' => 'insertOrIgnoreUsing',
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
            'startLine' => 4284,
            'endLine' => 4284,
            'startColumn' => 41,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 4284,
            'endLine' => 4284,
            'startColumn' => 57,
            'endColumn' => 62,
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
 * Insert new records into the table using a subquery while ignoring errors.
 *
 * @param  \\Closure|\\Illuminate\\Database\\Query\\Builder|\\Illuminate\\Database\\Eloquent\\Builder<*>|string  $query
 * @return int
 */',
        'startLine' => 4284,
        'endLine' => 4294,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'update' => 
      array (
        'name' => 'update',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4301,
            'endLine' => 4301,
            'startColumn' => 28,
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
 * Update records in the database.
 *
 * @return int<0, max>
 */',
        'startLine' => 4301,
        'endLine' => 4324,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'updateFrom' => 
      array (
        'name' => 'updateFrom',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4333,
            'endLine' => 4333,
            'startColumn' => 32,
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
 * Update records in a PostgreSQL database using the update from syntax.
 *
 * @return int
 *
 * @throws \\LogicException
 */',
        'startLine' => 4333,
        'endLine' => 4346,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'updateOrInsert' => 
      array (
        'name' => 'updateOrInsert',
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
            'startLine' => 4353,
            'endLine' => 4353,
            'startColumn' => 36,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 4353,
                'endLine' => 4353,
                'startTokenPos' => 19206,
                'startFilePos' => 134114,
                'endTokenPos' => 19207,
                'endFilePos' => 134115,
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
                      'name' => 'callable',
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
            'startLine' => 4353,
            'endLine' => 4353,
            'startColumn' => 55,
            'endColumn' => 81,
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
 * Insert or update a record matching the attributes, and fill it with values.
 *
 * @return bool
 */',
        'startLine' => 4353,
        'endLine' => 4370,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'upsert' => 
      array (
        'name' => 'upsert',
        'parameters' => 
        array (
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 4378,
            'endLine' => 4378,
            'startColumn' => 28,
            'endColumn' => 40,
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
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4378,
            'endLine' => 4378,
            'startColumn' => 43,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'update' => 
          array (
            'name' => 'update',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4378,
                'endLine' => 4378,
                'startTokenPos' => 19344,
                'startFilePos' => 134769,
                'endTokenPos' => 19344,
                'endFilePos' => 134772,
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
            'startLine' => 4378,
            'endLine' => 4378,
            'startColumn' => 67,
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
 * Insert new records or update the existing ones.
 *
 * @param  non-empty-string|non-empty-array<int, non-empty-string>  $uniqueBy
 * @return int
 */',
        'startLine' => 4378,
        'endLine' => 4417,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
            'startLine' => 4428,
            'endLine' => 4428,
            'startColumn' => 31,
            'endColumn' => 37,
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
                'startLine' => 4428,
                'endLine' => 4428,
                'startTokenPos' => 19639,
                'startFilePos' => 136143,
                'endTokenPos' => 19639,
                'endFilePos' => 136143,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4428,
            'endLine' => 4428,
            'startColumn' => 40,
            'endColumn' => 50,
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
                'startLine' => 4428,
                'endLine' => 4428,
                'startTokenPos' => 19648,
                'startFilePos' => 136161,
                'endTokenPos' => 19649,
                'endFilePos' => 136162,
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
            'startLine' => 4428,
            'endLine' => 4428,
            'startColumn' => 53,
            'endColumn' => 69,
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
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4428,
        'endLine' => 4435,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
            'startLine' => 4446,
            'endLine' => 4446,
            'startColumn' => 35,
            'endColumn' => 48,
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
                'startLine' => 4446,
                'endLine' => 4446,
                'startTokenPos' => 19719,
                'startFilePos' => 136727,
                'endTokenPos' => 19720,
                'endFilePos' => 136728,
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
            'startLine' => 4446,
            'endLine' => 4446,
            'startColumn' => 51,
            'endColumn' => 67,
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
 * Increment the given column\'s values by the given amounts.
 *
 * @param  array<string, float|int|numeric-string>  $columns
 * @param  array<string, mixed>  $extra
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4446,
        'endLine' => 4459,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
            'startLine' => 4470,
            'endLine' => 4470,
            'startColumn' => 31,
            'endColumn' => 37,
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
                'startLine' => 4470,
                'endLine' => 4470,
                'startTokenPos' => 19858,
                'startFilePos' => 137555,
                'endTokenPos' => 19858,
                'endFilePos' => 137555,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4470,
            'endLine' => 4470,
            'startColumn' => 40,
            'endColumn' => 50,
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
                'startLine' => 4470,
                'endLine' => 4470,
                'startTokenPos' => 19867,
                'startFilePos' => 137573,
                'endTokenPos' => 19868,
                'endFilePos' => 137574,
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
            'startLine' => 4470,
            'endLine' => 4470,
            'startColumn' => 53,
            'endColumn' => 69,
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
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4470,
        'endLine' => 4477,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
            'startLine' => 4488,
            'endLine' => 4488,
            'startColumn' => 35,
            'endColumn' => 48,
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
                'startLine' => 4488,
                'endLine' => 4488,
                'startTokenPos' => 19938,
                'startFilePos' => 138139,
                'endTokenPos' => 19939,
                'endFilePos' => 138140,
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
            'startLine' => 4488,
            'endLine' => 4488,
            'startColumn' => 51,
            'endColumn' => 67,
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
 * Decrement the given column\'s values by the given amounts.
 *
 * @param  array<string, float|int|numeric-string>  $columns
 * @param  array<string, mixed>  $extra
 * @return int<0, max>
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4488,
        'endLine' => 4501,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 4509,
                'endLine' => 4509,
                'startTokenPos' => 20074,
                'startFilePos' => 138844,
                'endTokenPos' => 20074,
                'endFilePos' => 138847,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4509,
            'endLine' => 4509,
            'startColumn' => 28,
            'endColumn' => 37,
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
 * Delete records from the database.
 *
 * @param  mixed  $id
 * @return int
 */',
        'startLine' => 4509,
        'endLine' => 4525,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'truncate' => 
      array (
        'name' => 'truncate',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run a "truncate" statement on the table.
 *
 * @return void
 */',
        'startLine' => 4532,
        'endLine' => 4539,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
 * Get a new instance of the query builder.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 4546,
        'endLine' => 4549,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'forSubQuery' => 
      array (
        'name' => 'forSubQuery',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new query instance for a sub-query.
 *
 * @return \\Illuminate\\Database\\Query\\Builder
 */',
        'startLine' => 4556,
        'endLine' => 4559,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getColumns' => 
      array (
        'name' => 'getColumns',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the query builder\'s columns in a text-only array with all expressions evaluated.
 *
 * @return list<string>
 */',
        'startLine' => 4566,
        'endLine' => 4571,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'raw' => 
      array (
        'name' => 'raw',
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
            'startLine' => 4579,
            'endLine' => 4579,
            'startColumn' => 25,
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
 * Create a raw database expression.
 *
 * @param  literal-string|int|float  $value
 * @return \\Illuminate\\Contracts\\Database\\Query\\Expression
 */',
        'startLine' => 4579,
        'endLine' => 4582,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getUnionBuilders' => 
      array (
        'name' => 'getUnionBuilders',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the query builder instances that are used in the union of the query.
 *
 * @return \\Illuminate\\Support\\Collection
 */',
        'startLine' => 4589,
        'endLine' => 4594,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getLimit' => 
      array (
        'name' => 'getLimit',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the "limit" value for the query or null if it\'s not set.
 *
 * @return mixed
 */',
        'startLine' => 4601,
        'endLine' => 4606,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getOffset' => 
      array (
        'name' => 'getOffset',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the "offset" value for the query or null if it\'s not set.
 *
 * @return mixed
 */',
        'startLine' => 4613,
        'endLine' => 4618,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getBindings' => 
      array (
        'name' => 'getBindings',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current query value bindings in a flattened array.
 *
 * @return list<mixed>
 */',
        'startLine' => 4625,
        'endLine' => 4628,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getRawBindings' => 
      array (
        'name' => 'getRawBindings',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the raw array of bindings.
 *
 * @return array{
 *      select: list<mixed>,
 *      from: list<mixed>,
 *      join: list<mixed>,
 *      where: list<mixed>,
 *      groupBy: list<mixed>,
 *      having: list<mixed>,
 *      order: list<mixed>,
 *      union: list<mixed>,
 *      unionOrder: list<mixed>,
 * }
 */',
        'startLine' => 4645,
        'endLine' => 4648,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'setBindings' => 
      array (
        'name' => 'setBindings',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
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
            'startLine' => 4659,
            'endLine' => 4659,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'where\'',
              'attributes' => 
              array (
                'startLine' => 4659,
                'endLine' => 4659,
                'startTokenPos' => 20592,
                'startFilePos' => 142774,
                'endTokenPos' => 20592,
                'endFilePos' => 142780,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4659,
            'endLine' => 4659,
            'startColumn' => 50,
            'endColumn' => 64,
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
 * Set the bindings on the query builder.
 *
 * @param  list<mixed>  $bindings
 * @param  "select"|"from"|"join"|"where"|"groupBy"|"having"|"order"|"union"|"unionOrder"  $type
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4659,
        'endLine' => 4668,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'addBinding' => 
      array (
        'name' => 'addBinding',
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
            'startLine' => 4679,
            'endLine' => 4679,
            'startColumn' => 32,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => 
            array (
              'code' => '\'where\'',
              'attributes' => 
              array (
                'startLine' => 4679,
                'endLine' => 4679,
                'startTokenPos' => 20667,
                'startFilePos' => 143318,
                'endTokenPos' => 20667,
                'endFilePos' => 143324,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4679,
            'endLine' => 4679,
            'startColumn' => 40,
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
 * Add a binding to the query.
 *
 * @param  mixed  $value
 * @param  "select"|"from"|"join"|"where"|"groupBy"|"having"|"order"|"union"|"unionOrder"  $type
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 4679,
        'endLine' => 4695,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'castBinding' => 
      array (
        'name' => 'castBinding',
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
            'startLine' => 4703,
            'endLine' => 4703,
            'startColumn' => 33,
            'endColumn' => 38,
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
 * Cast the given binding value.
 *
 * @param  mixed  $value
 * @return mixed
 */',
        'startLine' => 4703,
        'endLine' => 4706,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'mergeBindings' => 
      array (
        'name' => 'mergeBindings',
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
            'startLine' => 4714,
            'endLine' => 4714,
            'startColumn' => 35,
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
 * Merge an array of bindings into our bindings.
 *
 * @param  self  $query
 * @return $this
 */',
        'startLine' => 4714,
        'endLine' => 4719,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cleanBindings' => 
      array (
        'name' => 'cleanBindings',
        'parameters' => 
        array (
          'bindings' => 
          array (
            'name' => 'bindings',
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
            'startLine' => 4727,
            'endLine' => 4727,
            'startColumn' => 35,
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
 * Remove all of the expressions from a list of bindings.
 *
 * @param  array<mixed>  $bindings
 * @return list<mixed>
 */',
        'startLine' => 4727,
        'endLine' => 4736,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'flattenValue' => 
      array (
        'name' => 'flattenValue',
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
            'startLine' => 4744,
            'endLine' => 4744,
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
 * Get a scalar type value from an unknown type of input.
 *
 * @param  mixed  $value
 * @return mixed
 */',
        'startLine' => 4744,
        'endLine' => 4747,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'defaultKeyName' => 
      array (
        'name' => 'defaultKeyName',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default key name of the table.
 *
 * @return string
 */',
        'startLine' => 4754,
        'endLine' => 4757,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
 * Get the database connection instance.
 *
 * @return \\Illuminate\\Database\\ConnectionInterface
 */',
        'startLine' => 4764,
        'endLine' => 4767,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ensureConnectionSupportsVectors' => 
      array (
        'name' => 'ensureConnectionSupportsVectors',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the database connection supports vector queries.
 *
 * @return void
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 4776,
        'endLine' => 4781,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getProcessor' => 
      array (
        'name' => 'getProcessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the database query processor instance.
 *
 * @return \\Illuminate\\Database\\Query\\Processors\\Processor
 */',
        'startLine' => 4788,
        'endLine' => 4791,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'getGrammar' => 
      array (
        'name' => 'getGrammar',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the query grammar instance.
 *
 * @return \\Illuminate\\Database\\Query\\Grammars\\Grammar
 */',
        'startLine' => 4798,
        'endLine' => 4801,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'useWritePdo' => 
      array (
        'name' => 'useWritePdo',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Use the "write" PDO connection when executing the query.
 *
 * @return $this
 */',
        'startLine' => 4808,
        'endLine' => 4813,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'fetchUsing' => 
      array (
        'name' => 'fetchUsing',
        'parameters' => 
        array (
          'fetchUsing' => 
          array (
            'name' => 'fetchUsing',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4821,
            'endLine' => 4821,
            'startColumn' => 32,
            'endColumn' => 45,
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
 * Specify arguments for the PDOStatement::fetchAll / fetch functions.
 *
 * @param  mixed  ...$fetchUsing
 * @return $this
 */',
        'startLine' => 4821,
        'endLine' => 4826,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'isQueryable' => 
      array (
        'name' => 'isQueryable',
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
            'startLine' => 4834,
            'endLine' => 4834,
            'startColumn' => 36,
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
 * Determine if the value is a query builder instance or a Closure.
 *
 * @param  mixed  $value
 * @return bool
 */',
        'startLine' => 4834,
        'endLine' => 4840,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'clone' => 
      array (
        'name' => 'clone',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Clone the query.
 *
 * @return static
 */',
        'startLine' => 4847,
        'endLine' => 4850,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cloneWithout' => 
      array (
        'name' => 'cloneWithout',
        'parameters' => 
        array (
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
            'startLine' => 4857,
            'endLine' => 4857,
            'startColumn' => 34,
            'endColumn' => 50,
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
 * Clone the query without the given properties.
 *
 * @return static
 */',
        'startLine' => 4857,
        'endLine' => 4864,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'cloneWithoutBindings' => 
      array (
        'name' => 'cloneWithoutBindings',
        'parameters' => 
        array (
          'except' => 
          array (
            'name' => 'except',
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
            'startLine' => 4871,
            'endLine' => 4871,
            'startColumn' => 42,
            'endColumn' => 54,
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
 * Clone the query without the given bindings.
 *
 * @return static
 */',
        'startLine' => 4871,
        'endLine' => 4878,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dump' => 
      array (
        'name' => 'dump',
        'parameters' => 
        array (
          'args' => 
          array (
            'name' => 'args',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 4886,
            'endLine' => 4886,
            'startColumn' => 26,
            'endColumn' => 33,
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
 * Dump the current SQL and bindings.
 *
 * @param  mixed  ...$args
 * @return $this
 */',
        'startLine' => 4886,
        'endLine' => 4895,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dumpRawSql' => 
      array (
        'name' => 'dumpRawSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dump the raw current SQL with embedded bindings.
 *
 * @return $this
 */',
        'startLine' => 4902,
        'endLine' => 4907,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'dd' => 
      array (
        'name' => 'dd',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Die and dump the current SQL and bindings.
 *
 * @return never
 */',
        'startLine' => 4914,
        'endLine' => 4917,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
      'ddRawSql' => 
      array (
        'name' => 'ddRawSql',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Die and dump the current SQL with embedded bindings.
 *
 * @return never
 */',
        'startLine' => 4924,
        'endLine' => 4927,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
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
            'startLine' => 4938,
            'endLine' => 4938,
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
            'startLine' => 4938,
            'endLine' => 4938,
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
 * Handle dynamic method calls into the method.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 *
 * @throws \\BadMethodCallException
 */',
        'startLine' => 4938,
        'endLine' => 4949,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Query',
        'declaringClassName' => 'Illuminate\\Database\\Query\\Builder',
        'implementingClassName' => 'Illuminate\\Database\\Query\\Builder',
        'currentClassName' => 'Illuminate\\Database\\Query\\Builder',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
        'Illuminate\\Database\\Concerns\\BuildsWhereDateClauses' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\database\\concerns\\buildswheredateclauses::__call',
          ),
        ),
        'Illuminate\\Database\\Concerns\\BuildsQueries' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\database\\concerns\\buildsqueries::__call',
          ),
        ),
        'Illuminate\\Database\\Concerns\\ExplainsQueries' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\database\\concerns\\explainsqueries::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\ForwardsCalls' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\forwardscalls::__call',
          ),
        ),
        'Illuminate\\Support\\Traits\\Macroable' => 
        array (
          0 => 
          array (
            'alias' => 'macroCall',
            'method' => '__call',
            'hash' => 'illuminate\\support\\traits\\macroable::__call',
          ),
        ),
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
        'illuminate\\database\\concerns\\buildswheredateclauses::__call' => 'Illuminate\\Database\\Concerns\\BuildsWhereDateClauses::__call',
        'illuminate\\database\\concerns\\buildsqueries::__call' => 'Illuminate\\Database\\Concerns\\BuildsQueries::__call',
        'illuminate\\database\\concerns\\explainsqueries::__call' => 'Illuminate\\Database\\Concerns\\ExplainsQueries::__call',
        'illuminate\\support\\traits\\forwardscalls::__call' => 'Illuminate\\Support\\Traits\\ForwardsCalls::__call',
        'illuminate\\support\\traits\\macroable::__call' => 'Illuminate\\Support\\Traits\\Macroable::__call',
      ),
    ),
  ),
));