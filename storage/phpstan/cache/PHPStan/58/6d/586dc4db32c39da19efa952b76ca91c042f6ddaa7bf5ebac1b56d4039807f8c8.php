<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../symfony/process/Process.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Symfony\Component\Process\Process
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-ca29681bc88fd143a30085fd3aec20236ef360cbaf0b5ac6101e3083712c8c26-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Symfony\\Component\\Process\\Process',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../symfony/process/Process.php',
      ),
    ),
    'namespace' => 'Symfony\\Component\\Process',
    'name' => 'Symfony\\Component\\Process\\Process',
    'shortName' => 'Process',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Process is a thin wrapper around proc_* functions to easily
 * start independent PHP processes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @implements \\IteratorAggregate<string, string>
 *
 * @psalm-type EnvArray = array<string, string|\\Stringable|false>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 35,
    'endLine' => 1760,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'IteratorAggregate',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'ERR' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'ERR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'err\'',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 74,
            'startFilePos' => 1214,
            'endTokenPos' => 74,
            'endFilePos' => 1218,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'OUT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'OUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'out\'',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 85,
            'startFilePos' => 1244,
            'endTokenPos' => 85,
            'endFilePos' => 1248,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 29,
      ),
      'STATUS_READY' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'STATUS_READY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ready\'',
          'attributes' => 
          array (
            'startLine' => 40,
            'endLine' => 40,
            'startTokenPos' => 96,
            'startFilePos' => 1284,
            'endTokenPos' => 96,
            'endFilePos' => 1290,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'STATUS_STARTED' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'STATUS_STARTED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'started\'',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 107,
            'startFilePos' => 1327,
            'endTokenPos' => 107,
            'endFilePos' => 1335,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'STATUS_TERMINATED' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'STATUS_TERMINATED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'terminated\'',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 42,
            'startTokenPos' => 118,
            'startFilePos' => 1375,
            'endTokenPos' => 118,
            'endFilePos' => 1386,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'STDIN' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'STDIN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 44,
            'startTokenPos' => 129,
            'startFilePos' => 1415,
            'endTokenPos' => 129,
            'endFilePos' => 1415,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 27,
      ),
      'STDOUT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'STDOUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 140,
            'startFilePos' => 1444,
            'endTokenPos' => 140,
            'endFilePos' => 1444,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
      'STDERR' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'STDERR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 151,
            'startFilePos' => 1473,
            'endTokenPos' => 151,
            'endFilePos' => 1473,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
      'TIMEOUT_PRECISION' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'TIMEOUT_PRECISION',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0.2',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 164,
            'startFilePos' => 1551,
            'endTokenPos' => 164,
            'endFilePos' => 1553,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'ITER_NON_BLOCKING' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'ITER_NON_BLOCKING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 51,
            'endLine' => 51,
            'startTokenPos' => 175,
            'startFilePos' => 1594,
            'endTokenPos' => 175,
            'endFilePos' => 1594,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'ITER_KEEP_OUTPUT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'ITER_KEEP_OUTPUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 188,
            'startFilePos' => 1729,
            'endTokenPos' => 188,
            'endFilePos' => 1729,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'ITER_SKIP_OUT' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'ITER_SKIP_OUT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 53,
            'endLine' => 53,
            'startTokenPos' => 201,
            'startFilePos' => 1855,
            'endTokenPos' => 201,
            'endFilePos' => 1855,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'ITER_SKIP_ERR' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'ITER_SKIP_ERR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 54,
            'endLine' => 54,
            'startTokenPos' => 214,
            'startFilePos' => 1943,
            'endTokenPos' => 214,
            'endFilePos' => 1943,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'WINDOWS_ENV_BLOCK_MAX_LENGTH' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'WINDOWS_ENV_BLOCK_MAX_LENGTH',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '32767',
          'attributes' => 
          array (
            'startLine' => 65,
            'endLine' => 65,
            'startTokenPos' => 229,
            'startFilePos' => 2488,
            'endTokenPos' => 229,
            'endFilePos' => 2492,
          ),
        ),
        'docComment' => '/**
 * Maximum number of UTF-16 code units allowed in the Windows environment block.
 *
 * The Win32 CreateProcess API encodes env vars as KEY=VALUE\\0 in UTF-16LE,
 * terminated by an extra \\0. Exceeding this limit causes proc_open() to hang
 * silently rather than returning false.
 *
 * @see https://learn.microsoft.com/en-us/windows/win32/api/processthreadsapi/nf-processthreadsapi-createprocessa
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 65,
        'endLine' => 65,
        'startColumn' => 5,
        'endColumn' => 55,
      ),
    ),
    'immediateProperties' => 
    array (
      'callback' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'callback',
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
            'startLine' => 68,
            'endLine' => 68,
            'startTokenPos' => 243,
            'startFilePos' => 2586,
            'endTokenPos' => 243,
            'endFilePos' => 2589,
          ),
        ),
        'docComment' => '/** @var \\Closure(\'out\'|\'err\', string):bool|null */',
        'attributes' => 
        array (
        ),
        'startLine' => 68,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'commandline' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'commandline',
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
        'default' => NULL,
        'docComment' => '/** @var string[]|string */',
        'attributes' => 
        array (
        ),
        'startLine' => 70,
        'endLine' => 70,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cwd' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'cwd',
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
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 71,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'env' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'env',
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
            'startLine' => 73,
            'endLine' => 73,
            'startTokenPos' => 275,
            'startFilePos' => 2739,
            'endTokenPos' => 276,
            'endFilePos' => 2740,
          ),
        ),
        'docComment' => '/** @var EnvArray */',
        'attributes' => 
        array (
        ),
        'startLine' => 73,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'input' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'input',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var resource|string|\\Iterator|null */',
        'attributes' => 
        array (
        ),
        'startLine' => 75,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'starttime' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'starttime',
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
                  'name' => 'float',
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
            'startLine' => 76,
            'endLine' => 76,
            'startTokenPos' => 295,
            'startFilePos' => 2842,
            'endTokenPos' => 295,
            'endFilePos' => 2845,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 76,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'lastOutputTime' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'lastOutputTime',
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
                  'name' => 'float',
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
            'startLine' => 77,
            'endLine' => 77,
            'startTokenPos' => 307,
            'startFilePos' => 2885,
            'endTokenPos' => 307,
            'endFilePos' => 2888,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 77,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timeout' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'timeout',
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
                  'name' => 'float',
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
            'startLine' => 78,
            'endLine' => 78,
            'startTokenPos' => 319,
            'startFilePos' => 2921,
            'endTokenPos' => 319,
            'endFilePos' => 2924,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 78,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'idleTimeout' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'idleTimeout',
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
                  'name' => 'float',
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
            'startLine' => 79,
            'endLine' => 79,
            'startTokenPos' => 331,
            'startFilePos' => 2961,
            'endTokenPos' => 331,
            'endFilePos' => 2964,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 79,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exitcode' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'exitcode',
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 80,
            'endLine' => 80,
            'startTokenPos' => 343,
            'startFilePos' => 2996,
            'endTokenPos' => 343,
            'endFilePos' => 2999,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 80,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fallbackStatus' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'fallbackStatus',
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
            'startLine' => 81,
            'endLine' => 81,
            'startTokenPos' => 354,
            'startFilePos' => 3038,
            'endTokenPos' => 355,
            'endFilePos' => 3039,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 81,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'processInformation' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'processInformation',
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
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'outputDisabled' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'outputDisabled',
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
            'startLine' => 83,
            'endLine' => 83,
            'startTokenPos' => 373,
            'startFilePos' => 3116,
            'endTokenPos' => 373,
            'endFilePos' => 3120,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 83,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stdout' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'stdout',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var resource */',
        'attributes' => 
        array (
        ),
        'startLine' => 85,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'stderr' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'stderr',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var resource */',
        'attributes' => 
        array (
        ),
        'startLine' => 87,
        'endLine' => 87,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'process' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'process',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var resource|null */',
        'attributes' => 
        array (
        ),
        'startLine' => 89,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'status' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'status',
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
        'default' => 
        array (
          'code' => 'self::STATUS_READY',
          'attributes' => 
          array (
            'startLine' => 90,
            'endLine' => 90,
            'startTokenPos' => 405,
            'startFilePos' => 3296,
            'endTokenPos' => 407,
            'endFilePos' => 3313,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 90,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'incrementalOutputOffset' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'incrementalOutputOffset',
        'modifiers' => 4,
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
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 91,
            'endLine' => 91,
            'startTokenPos' => 418,
            'startFilePos' => 3359,
            'endTokenPos' => 418,
            'endFilePos' => 3359,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 91,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 45,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'incrementalErrorOutputOffset' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'incrementalErrorOutputOffset',
        'modifiers' => 4,
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
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 92,
            'endLine' => 92,
            'startTokenPos' => 429,
            'startFilePos' => 3410,
            'endTokenPos' => 429,
            'endFilePos' => 3410,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 92,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 50,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tty' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'tty',
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
            'startLine' => 93,
            'endLine' => 93,
            'startTokenPos' => 440,
            'startFilePos' => 3437,
            'endTokenPos' => 440,
            'endFilePos' => 3441,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 93,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pty' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'pty',
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
        'startLine' => 94,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'options' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'options',
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
          'code' => '[\'suppress_errors\' => true, \'bypass_shell\' => true]',
          'attributes' => 
          array (
            'startLine' => 95,
            'endLine' => 95,
            'startTokenPos' => 458,
            'startFilePos' => 3496,
            'endTokenPos' => 471,
            'endFilePos' => 3546,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 95,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 81,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'ignoredSignals' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'ignoredSignals',
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
            'startLine' => 96,
            'endLine' => 96,
            'startTokenPos' => 482,
            'startFilePos' => 3585,
            'endTokenPos' => 483,
            'endFilePos' => 3586,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 96,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 39,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'processPipes' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'processPipes',
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
                  'name' => 'Symfony\\Component\\Process\\Pipes\\WindowsPipes',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'Symfony\\Component\\Process\\Pipes\\UnixPipes',
                  'isIdentifier' => false,
                ),
              ),
            ),
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 98,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'latestSignal' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'latestSignal',
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 100,
            'endLine' => 100,
            'startTokenPos' => 504,
            'startFilePos' => 3674,
            'endTokenPos' => 504,
            'endFilePos' => 3677,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 100,
        'endLine' => 100,
        'startColumn' => 5,
        'endColumn' => 38,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'sigchild' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'sigchild',
        'modifiers' => 20,
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
                  'name' => 'bool',
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
            'startLine' => 102,
            'endLine' => 102,
            'startTokenPos' => 518,
            'startFilePos' => 3718,
            'endTokenPos' => 518,
            'endFilePos' => 3721,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 102,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'executables' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'executables',
        'modifiers' => 20,
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
            'startLine' => 103,
            'endLine' => 103,
            'startTokenPos' => 531,
            'startFilePos' => 3764,
            'endTokenPos' => 532,
            'endFilePos' => 3765,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 103,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'exitCodes' => 
      array (
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'name' => 'exitCodes',
        'modifiers' => 17,
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
          'code' => '[
    0 => \'OK\',
    1 => \'General error\',
    2 => \'Misuse of shell builtins\',
    126 => \'Invoked command cannot execute\',
    127 => \'Command not found\',
    128 => \'Invalid exit argument\',
    // signals
    129 => \'Hangup\',
    130 => \'Interrupt\',
    131 => \'Quit and dump core\',
    132 => \'Illegal instruction\',
    133 => \'Trace/breakpoint trap\',
    134 => \'Process aborted\',
    135 => \'Bus error: "access to undefined portion of memory object"\',
    136 => \'Floating point exception: "erroneous arithmetic operation"\',
    137 => \'Kill (terminate immediately)\',
    138 => \'User-defined 1\',
    139 => \'Segmentation violation\',
    140 => \'User-defined 2\',
    141 => \'Write to pipe with no one reading\',
    142 => \'Signal raised by alarm\',
    143 => \'Termination (request to terminate)\',
    // 144 - not defined
    145 => \'Child process terminated, stopped (or continued*)\',
    146 => \'Continue if stopped\',
    147 => \'Stop executing temporarily\',
    148 => \'Terminal stop signal\',
    149 => \'Background process attempting to read from tty ("in")\',
    150 => \'Background process attempting to write to tty ("out")\',
    151 => \'Urgent data available on socket\',
    152 => \'CPU time limit exceeded\',
    153 => \'File size limit exceeded\',
    154 => \'Signal raised by timer counting virtual time: "virtual timer expired"\',
    155 => \'Profiling timer expired\',
    // 156 - not defined
    157 => \'Pollable event\',
    // 158 - not defined
    159 => \'Bad syscall\',
]',
          'attributes' => 
          array (
            'startLine' => 110,
            'endLine' => 151,
            'startTokenPos' => 547,
            'startFilePos' => 3934,
            'endTokenPos' => 795,
            'endFilePos' => 5580,
          ),
        ),
        'docComment' => '/**
 * Exit codes translation table.
 *
 * User-defined errors must use exit codes in the 64-113 range.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 110,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 6,
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
          'command' => 
          array (
            'name' => 'command',
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 33,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cwd' => 
          array (
            'name' => 'cwd',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 162,
                'endLine' => 162,
                'startTokenPos' => 818,
                'startFilePos' => 6272,
                'endTokenPos' => 818,
                'endFilePos' => 6275,
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 49,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'env' => 
          array (
            'name' => 'env',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 162,
                'endLine' => 162,
                'startTokenPos' => 828,
                'startFilePos' => 6292,
                'endTokenPos' => 828,
                'endFilePos' => 6295,
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 70,
            'endColumn' => 87,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'input' => 
          array (
            'name' => 'input',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 162,
                'endLine' => 162,
                'startTokenPos' => 837,
                'startFilePos' => 6313,
                'endTokenPos' => 837,
                'endFilePos' => 6316,
              ),
            ),
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 90,
            'endColumn' => 108,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'timeout' => 
          array (
            'name' => 'timeout',
            'default' => 
            array (
              'code' => '60',
              'attributes' => 
              array (
                'startLine' => 162,
                'endLine' => 162,
                'startTokenPos' => 847,
                'startFilePos' => 6337,
                'endTokenPos' => 847,
                'endFilePos' => 6338,
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
                      'name' => 'float',
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
            'startLine' => 162,
            'endLine' => 162,
            'startColumn' => 111,
            'endColumn' => 130,
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
 * @param string[]       $command The command to run and its arguments listed as separate entries
 * @param string|null    $cwd     The working directory or null to use the working dir of the current PHP process
 * @param EnvArray|null  $env     The environment variables or null to use the same environment as the current PHP process
 * @param mixed          $input   The input as stream resource, scalar or \\Traversable, or null for no input
 * @param int|float|null $timeout The timeout in seconds or null to disable
 *
 * @throws LogicException When proc_open is not installed
 */',
        'startLine' => 162,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'fromShellCommandline' => 
      array (
        'name' => 'fromShellCommandline',
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 49,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cwd' => 
          array (
            'name' => 'cwd',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 208,
                'endLine' => 208,
                'startTokenPos' => 1017,
                'startFilePos' => 8567,
                'endTokenPos' => 1017,
                'endFilePos' => 8570,
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 66,
            'endColumn' => 84,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'env' => 
          array (
            'name' => 'env',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 208,
                'endLine' => 208,
                'startTokenPos' => 1027,
                'startFilePos' => 8587,
                'endTokenPos' => 1027,
                'endFilePos' => 8590,
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 87,
            'endColumn' => 104,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'input' => 
          array (
            'name' => 'input',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 208,
                'endLine' => 208,
                'startTokenPos' => 1036,
                'startFilePos' => 8608,
                'endTokenPos' => 1036,
                'endFilePos' => 8611,
              ),
            ),
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 107,
            'endColumn' => 125,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'timeout' => 
          array (
            'name' => 'timeout',
            'default' => 
            array (
              'code' => '60',
              'attributes' => 
              array (
                'startLine' => 208,
                'endLine' => 208,
                'startTokenPos' => 1046,
                'startFilePos' => 8632,
                'endTokenPos' => 1046,
                'endFilePos' => 8633,
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
                      'name' => 'float',
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 128,
            'endColumn' => 147,
            'parameterIndex' => 4,
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
 * Creates a Process instance as a command-line to be run in a shell wrapper.
 *
 * Command-lines are parsed by the shell of your OS (/bin/sh on Unix-like, cmd.exe on Windows.)
 * This allows using e.g. pipes or conditional execution. In this mode, signals are sent to the
 * shell wrapper and not to your commands.
 *
 * In order to inject dynamic values into command-lines, we strongly recommend using placeholders.
 * This will save escaping values, which is not portable nor secure anyway:
 *
 *   $process = Process::fromShellCommandline(\'my_command "${:MY_VAR}"\');
 *   $process->run(null, [\'MY_VAR\' => $theValue]);
 *
 * @param string         $command The command line to pass to the shell of the OS
 * @param string|null    $cwd     The working directory or null to use the working dir of the current PHP process
 * @param EnvArray|null  $env     The environment variables or null to use the same environment as the current PHP process
 * @param mixed          $input   The input as stream resource, scalar or \\Traversable, or null for no input
 * @param int|float|null $timeout The timeout in seconds or null to disable
 *
 * @throws LogicException When proc_open is not installed
 */',
        'startLine' => 208,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      '__serialize' => 
      array (
        'name' => '__serialize',
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
        'docComment' => NULL,
        'startLine' => 216,
        'endLine' => 219,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      '__unserialize' => 
      array (
        'name' => '__unserialize',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 221,
            'endLine' => 221,
            'startColumn' => 35,
            'endColumn' => 45,
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
        'startLine' => 221,
        'endLine' => 224,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      '__destruct' => 
      array (
        'name' => '__destruct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 226,
        'endLine' => 233,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
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
        'docComment' => NULL,
        'startLine' => 235,
        'endLine' => 238,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'run' => 
      array (
        'name' => 'run',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 264,
                'endLine' => 264,
                'startTokenPos' => 1240,
                'startFilePos' => 10534,
                'endTokenPos' => 1240,
                'endFilePos' => 10537,
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
            'startLine' => 264,
            'endLine' => 264,
            'startColumn' => 25,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'env' => 
          array (
            'name' => 'env',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 264,
                'endLine' => 264,
                'startTokenPos' => 1249,
                'startFilePos' => 10553,
                'endTokenPos' => 1250,
                'endFilePos' => 10554,
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
            'startLine' => 264,
            'endLine' => 264,
            'startColumn' => 53,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Runs the process.
 *
 * The callback receives the type of output (out or err) and
 * some bytes from the output in real-time. It allows to have feedback
 * from the independent process during execution.
 *
 * The STDOUT and STDERR are also available after the process is finished
 * via the getOutput() and getErrorOutput() methods.
 *
 * @param (callable(\'out\'|\'err\', string):void)|null $callback A PHP callback to run whenever there is some
 *                                                            output available on STDOUT or STDERR
 * @param EnvArray                                  $env
 *
 * @return int The exit status code
 *
 * @throws ProcessStartFailedException When process can\'t be launched
 * @throws RuntimeException            When process is already running
 * @throws ProcessTimedOutException    When process timed out
 * @throws ProcessSignaledException    When process stopped after receiving signal
 * @throws LogicException              In case a callback is provided and output has been disabled
 *
 * @final
 */',
        'startLine' => 264,
        'endLine' => 269,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'mustRun' => 
      array (
        'name' => 'mustRun',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 292,
                'endLine' => 292,
                'startTokenPos' => 1295,
                'startFilePos' => 11678,
                'endTokenPos' => 1295,
                'endFilePos' => 11681,
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
            'startLine' => 292,
            'endLine' => 292,
            'startColumn' => 29,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'env' => 
          array (
            'name' => 'env',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 292,
                'endLine' => 292,
                'startTokenPos' => 1304,
                'startFilePos' => 11697,
                'endTokenPos' => 1305,
                'endFilePos' => 11698,
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
            'startLine' => 292,
            'endLine' => 292,
            'startColumn' => 57,
            'endColumn' => 71,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Runs the process.
 *
 * This is identical to run() except that an exception is thrown if the process
 * exits with a non-zero exit code.
 *
 * @param (callable(\'out\'|\'err\', string):void)|null $callback A PHP callback to run whenever there is some
 *                                                            output available on STDOUT or STDERR
 * @param EnvArray                                  $env
 *
 * @return $this
 *
 * @throws ProcessFailedException   When process didn\'t terminate successfully
 * @throws RuntimeException         When process can\'t be launched
 * @throws RuntimeException         When process is already running
 * @throws ProcessTimedOutException When process timed out
 * @throws ProcessSignaledException When process stopped after receiving signal
 * @throws LogicException           In case a callback is provided and output has been disabled
 *
 * @final
 */',
        'startLine' => 292,
        'endLine' => 299,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'start' => 
      array (
        'name' => 'start',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 321,
                'endLine' => 321,
                'startTokenPos' => 1367,
                'startFilePos' => 12998,
                'endTokenPos' => 1367,
                'endFilePos' => 13001,
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 27,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'env' => 
          array (
            'name' => 'env',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 321,
                'endLine' => 321,
                'startTokenPos' => 1376,
                'startFilePos' => 13017,
                'endTokenPos' => 1377,
                'endFilePos' => 13018,
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
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 55,
            'endColumn' => 69,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Starts the process and returns after writing the input to STDIN.
 *
 * This method blocks until all STDIN data is sent to the process then it
 * returns while the process runs in the background.
 *
 * The termination of the process can be awaited with wait().
 *
 * The callback receives the type of output (out or err) and some bytes from
 * the output in real-time while writing the standard input to the process.
 * It allows to have feedback from the independent process during execution.
 *
 * @param (callable(\'out\'|\'err\', string):void)|null $callback A PHP callback to run whenever there is some
 *                                                            output available on STDOUT or STDERR
 * @param EnvArray                                  $env
 *
 * @throws ProcessStartFailedException When process can\'t be launched
 * @throws RuntimeException            When process is already running
 * @throws LogicException              In case a callback is provided and output has been disabled
 */',
        'startLine' => 321,
        'endLine' => 426,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'restart' => 
      array (
        'name' => 'restart',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 444,
                'endLine' => 444,
                'startTokenPos' => 2270,
                'startFilePos' => 17827,
                'endTokenPos' => 2270,
                'endFilePos' => 17830,
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
            'startLine' => 444,
            'endLine' => 444,
            'startColumn' => 29,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'env' => 
          array (
            'name' => 'env',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 444,
                'endLine' => 444,
                'startTokenPos' => 2279,
                'startFilePos' => 17846,
                'endTokenPos' => 2280,
                'endFilePos' => 17847,
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
            'startLine' => 444,
            'endLine' => 444,
            'startColumn' => 57,
            'endColumn' => 71,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Restarts the process.
 *
 * Be warned that the process is cloned before being started.
 *
 * @param (callable(\'out\'|\'err\', string):void)|null $callback A PHP callback to run whenever there is some
 *                                                            output available on STDOUT or STDERR
 * @param EnvArray                                  $env
 *
 * @throws ProcessStartFailedException When process can\'t be launched
 * @throws RuntimeException            When process is already running
 *
 * @see start()
 *
 * @final
 */',
        'startLine' => 444,
        'endLine' => 454,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'wait' => 
      array (
        'name' => 'wait',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 472,
                'endLine' => 472,
                'startTokenPos' => 2354,
                'startFilePos' => 18943,
                'endTokenPos' => 2354,
                'endFilePos' => 18946,
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
            'startLine' => 472,
            'endLine' => 472,
            'startColumn' => 26,
            'endColumn' => 51,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Waits for the process to terminate.
 *
 * The callback receives the type of output (out or err) and some bytes
 * from the output in real-time while writing the standard input to the process.
 * It allows to have feedback from the independent process during execution.
 *
 * @param (callable(\'out\'|\'err\', string):void)|null $callback A PHP callback to run whenever there is some
 *                                                            output available on STDOUT or STDERR
 *
 * @return int The exitcode of the process
 *
 * @throws ProcessTimedOutException When process timed out
 * @throws ProcessSignaledException When process stopped after receiving signal
 * @throws LogicException           When process is not yet started
 */',
        'startLine' => 472,
        'endLine' => 502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'waitUntil' => 
      array (
        'name' => 'waitUntil',
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
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 31,
            'endColumn' => 48,
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
 * Waits until the callback returns true.
 *
 * The callback receives the type of output (out or err) and some bytes
 * from the output in real-time while writing the standard input to the process.
 * It allows to have feedback from the independent process during execution.
 *
 * @param-immediately-invoked-callable $callback
 *
 * @param (callable(\'out\'|\'err\', string):bool)|null $callback A PHP callback to run whenever there is some
 *                                                            output available on STDOUT or STDERR
 *
 * @throws RuntimeException         When process timed out
 * @throws LogicException           When process is not yet started
 * @throws ProcessTimedOutException In case the timeout was reached
 */',
        'startLine' => 520,
        'endLine' => 553,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getPid' => 
      array (
        'name' => 'getPid',
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
 * Returns the Pid (process identifier), if applicable.
 *
 * @return int|null The process id if running, null otherwise
 */',
        'startLine' => 560,
        'endLine' => 563,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'signal' => 
      array (
        'name' => 'signal',
        'parameters' => 
        array (
          'signal' => 
          array (
            'name' => 'signal',
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
            'startLine' => 576,
            'endLine' => 576,
            'startColumn' => 28,
            'endColumn' => 38,
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
 * Sends a POSIX signal to the process.
 *
 * @param int $signal A valid POSIX signal (see https://php.net/pcntl.constants)
 *
 * @return $this
 *
 * @throws LogicException   In case the process is not running
 * @throws RuntimeException In case --enable-sigchild is activated and the process can\'t be killed
 * @throws RuntimeException In case of failure
 */',
        'startLine' => 576,
        'endLine' => 581,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'disableOutput' => 
      array (
        'name' => 'disableOutput',
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
 * Disables fetching output and error output from the underlying process.
 *
 * @return $this
 *
 * @throws RuntimeException In case the process is already running
 * @throws LogicException   if an idle timeout is set
 */',
        'startLine' => 591,
        'endLine' => 603,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'enableOutput' => 
      array (
        'name' => 'enableOutput',
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
 * Enables fetching output and error output from the underlying process.
 *
 * @return $this
 *
 * @throws RuntimeException In case the process is already running
 */',
        'startLine' => 612,
        'endLine' => 621,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isOutputDisabled' => 
      array (
        'name' => 'isOutputDisabled',
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
 * Returns true in case the output is disabled, false otherwise.
 */',
        'startLine' => 626,
        'endLine' => 629,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getOutput' => 
      array (
        'name' => 'getOutput',
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
 * Returns the current output of the process (STDOUT).
 *
 * @throws LogicException in case the output has been disabled
 * @throws LogicException In case the process is not started
 */',
        'startLine' => 637,
        'endLine' => 646,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getIncrementalOutput' => 
      array (
        'name' => 'getIncrementalOutput',
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
 * Returns the output incrementally.
 *
 * In comparison with the getOutput method which always return the whole
 * output, this one returns the new output since the last call.
 *
 * @throws LogicException in case the output has been disabled
 * @throws LogicException In case the process is not started
 */',
        'startLine' => 657,
        'endLine' => 669,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getIterator' => 
      array (
        'name' => 'getIterator',
        'parameters' => 
        array (
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 681,
                'endLine' => 681,
                'startTokenPos' => 3286,
                'startFilePos' => 25844,
                'endTokenPos' => 3286,
                'endFilePos' => 25844,
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
            'startLine' => 681,
            'endLine' => 681,
            'startColumn' => 33,
            'endColumn' => 46,
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
            'name' => 'Generator',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns an iterator to the output of the process, with the output type as keys (Process::OUT/ERR).
 *
 * @param int $flags A bit field of Process::ITER_* flags
 *
 * @return \\Generator<string, string>
 *
 * @throws LogicException in case the output has been disabled
 * @throws LogicException In case the process is not started
 */',
        'startLine' => 681,
        'endLine' => 726,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => true,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'clearOutput' => 
      array (
        'name' => 'clearOutput',
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
 * Clears the process output.
 *
 * @return $this
 */',
        'startLine' => 733,
        'endLine' => 740,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getErrorOutput' => 
      array (
        'name' => 'getErrorOutput',
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
 * Returns the current error output of the process (STDERR).
 *
 * @throws LogicException in case the output has been disabled
 * @throws LogicException In case the process is not started
 */',
        'startLine' => 748,
        'endLine' => 757,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getIncrementalErrorOutput' => 
      array (
        'name' => 'getIncrementalErrorOutput',
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
 * Returns the errorOutput incrementally.
 *
 * In comparison with the getErrorOutput method which always return the
 * whole error output, this one returns the new error output since the last
 * call.
 *
 * @throws LogicException in case the output has been disabled
 * @throws LogicException In case the process is not started
 */',
        'startLine' => 769,
        'endLine' => 781,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'clearErrorOutput' => 
      array (
        'name' => 'clearErrorOutput',
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
 * Clears the process output.
 *
 * @return $this
 */',
        'startLine' => 788,
        'endLine' => 795,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getExitCode' => 
      array (
        'name' => 'getExitCode',
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
 * Returns the exit code returned by the process.
 *
 * @return int|null The exit status code, null if the Process is not terminated
 */',
        'startLine' => 802,
        'endLine' => 807,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getExitCodeText' => 
      array (
        'name' => 'getExitCodeText',
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
 * Returns a string representation for the exit code returned by the process.
 *
 * This method relies on the Unix exit code status standardization
 * and might not be relevant for other operating systems.
 *
 * @return string|null A string representation for the exit status code, null if the Process is not terminated
 *
 * @see http://tldp.org/LDP/abs/html/exitcodes.html
 * @see http://en.wikipedia.org/wiki/Unix_signal
 */',
        'startLine' => 820,
        'endLine' => 827,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isSuccessful' => 
      array (
        'name' => 'isSuccessful',
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
 * Checks if the process ended successfully.
 */',
        'startLine' => 832,
        'endLine' => 835,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'hasBeenSignaled' => 
      array (
        'name' => 'hasBeenSignaled',
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
 * Returns true if the child process has been terminated by an uncaught signal.
 *
 * It always returns false on Windows.
 *
 * @throws LogicException In case the process is not terminated
 */',
        'startLine' => 844,
        'endLine' => 849,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getTermSignal' => 
      array (
        'name' => 'getTermSignal',
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
 * Returns the number of the signal that caused the child process to terminate its execution.
 *
 * It is only meaningful if hasBeenSignaled() returns true.
 *
 * @throws RuntimeException In case --enable-sigchild is activated
 * @throws LogicException   In case the process is not terminated
 */',
        'startLine' => 859,
        'endLine' => 868,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'hasBeenStopped' => 
      array (
        'name' => 'hasBeenStopped',
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
 * Returns true if the child process has been stopped by a signal.
 *
 * It always returns false on Windows.
 *
 * @throws LogicException In case the process is not terminated
 */',
        'startLine' => 877,
        'endLine' => 882,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getStopSignal' => 
      array (
        'name' => 'getStopSignal',
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
 * Returns the number of the signal that caused the child process to stop its execution.
 *
 * It is only meaningful if hasBeenStopped() returns true.
 *
 * @throws LogicException In case the process is not terminated
 */',
        'startLine' => 891,
        'endLine' => 896,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isRunning' => 
      array (
        'name' => 'isRunning',
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
 * Checks if the process is currently running.
 */',
        'startLine' => 901,
        'endLine' => 910,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isStarted' => 
      array (
        'name' => 'isStarted',
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
 * Checks if the process has been started with no regard to the current state.
 */',
        'startLine' => 915,
        'endLine' => 918,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isTerminated' => 
      array (
        'name' => 'isTerminated',
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
 * Checks if the process is terminated.
 */',
        'startLine' => 923,
        'endLine' => 928,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getStatus' => 
      array (
        'name' => 'getStatus',
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
 * Gets the process status.
 *
 * The status is one of: ready, started, terminated.
 */',
        'startLine' => 935,
        'endLine' => 940,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'stop' => 
      array (
        'name' => 'stop',
        'parameters' => 
        array (
          'timeout' => 
          array (
            'name' => 'timeout',
            'default' => 
            array (
              'code' => '10',
              'attributes' => 
              array (
                'startLine' => 950,
                'endLine' => 950,
                'startTokenPos' => 4398,
                'startFilePos' => 33547,
                'endTokenPos' => 4398,
                'endFilePos' => 33548,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 950,
            'endLine' => 950,
            'startColumn' => 26,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'signal' => 
          array (
            'name' => 'signal',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 950,
                'endLine' => 950,
                'startTokenPos' => 4408,
                'startFilePos' => 33566,
                'endTokenPos' => 4408,
                'endFilePos' => 33569,
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
            'startLine' => 950,
            'endLine' => 950,
            'startColumn' => 47,
            'endColumn' => 65,
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Stops the process.
 *
 * @param int|float $timeout The timeout in seconds
 * @param int|null  $signal  A POSIX signal to send in case the process has not stop at timeout, default is SIGKILL (9)
 *
 * @return int|null The exit-code of the process or null if it\'s not running
 */',
        'startLine' => 950,
        'endLine' => 977,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'addOutput' => 
      array (
        'name' => 'addOutput',
        'parameters' => 
        array (
          'line' => 
          array (
            'name' => 'line',
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
            'startLine' => 984,
            'endLine' => 984,
            'startColumn' => 31,
            'endColumn' => 42,
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
 * Adds a line to the STDOUT stream.
 *
 * @internal
 */',
        'startLine' => 984,
        'endLine' => 991,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'addErrorOutput' => 
      array (
        'name' => 'addErrorOutput',
        'parameters' => 
        array (
          'line' => 
          array (
            'name' => 'line',
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
            'startLine' => 998,
            'endLine' => 998,
            'startColumn' => 36,
            'endColumn' => 47,
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
 * Adds a line to the STDERR stream.
 *
 * @internal
 */',
        'startLine' => 998,
        'endLine' => 1005,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getLastOutputTime' => 
      array (
        'name' => 'getLastOutputTime',
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
                  'name' => 'float',
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
 * Gets the last output time in seconds.
 */',
        'startLine' => 1010,
        'endLine' => 1013,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getCommandLine' => 
      array (
        'name' => 'getCommandLine',
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
 * Gets the command line to be executed.
 */',
        'startLine' => 1018,
        'endLine' => 1021,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getTimeout' => 
      array (
        'name' => 'getTimeout',
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
                  'name' => 'float',
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
 * Gets the process timeout in seconds (max. runtime).
 */',
        'startLine' => 1026,
        'endLine' => 1029,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getIdleTimeout' => 
      array (
        'name' => 'getIdleTimeout',
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
                  'name' => 'float',
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
 * Gets the process idle timeout in seconds (max. time since last output).
 */',
        'startLine' => 1034,
        'endLine' => 1037,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setTimeout' => 
      array (
        'name' => 'setTimeout',
        'parameters' => 
        array (
          'timeout' => 
          array (
            'name' => 'timeout',
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
                      'name' => 'float',
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
            'startLine' => 1048,
            'endLine' => 1048,
            'startColumn' => 32,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the process timeout (max. runtime) in seconds.
 *
 * To disable the timeout, set this value to null.
 *
 * @return $this
 *
 * @throws InvalidArgumentException if the timeout is negative
 */',
        'startLine' => 1048,
        'endLine' => 1053,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setIdleTimeout' => 
      array (
        'name' => 'setIdleTimeout',
        'parameters' => 
        array (
          'timeout' => 
          array (
            'name' => 'timeout',
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
                      'name' => 'float',
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
            'startLine' => 1065,
            'endLine' => 1065,
            'startColumn' => 36,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the process idle timeout (max. time since last output) in seconds.
 *
 * To disable the timeout, set this value to null.
 *
 * @return $this
 *
 * @throws LogicException           if the output is disabled
 * @throws InvalidArgumentException if the timeout is negative
 */',
        'startLine' => 1065,
        'endLine' => 1074,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setTty' => 
      array (
        'name' => 'setTty',
        'parameters' => 
        array (
          'tty' => 
          array (
            'name' => 'tty',
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
            'startLine' => 1083,
            'endLine' => 1083,
            'startColumn' => 28,
            'endColumn' => 36,
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
 * Enables or disables the TTY mode.
 *
 * @return $this
 *
 * @throws RuntimeException In case the TTY mode is not supported
 */',
        'startLine' => 1083,
        'endLine' => 1096,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isTty' => 
      array (
        'name' => 'isTty',
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
 * Checks if the TTY mode is enabled.
 */',
        'startLine' => 1101,
        'endLine' => 1104,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setPty' => 
      array (
        'name' => 'setPty',
        'parameters' => 
        array (
          'bool' => 
          array (
            'name' => 'bool',
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
            'startLine' => 1111,
            'endLine' => 1111,
            'startColumn' => 28,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets PTY mode.
 *
 * @return $this
 */',
        'startLine' => 1111,
        'endLine' => 1116,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isPty' => 
      array (
        'name' => 'isPty',
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
 * Returns PTY state.
 */',
        'startLine' => 1121,
        'endLine' => 1124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getWorkingDirectory' => 
      array (
        'name' => 'getWorkingDirectory',
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
 * Gets the working directory.
 */',
        'startLine' => 1129,
        'endLine' => 1138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setWorkingDirectory' => 
      array (
        'name' => 'setWorkingDirectory',
        'parameters' => 
        array (
          'cwd' => 
          array (
            'name' => 'cwd',
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
            'startLine' => 1145,
            'endLine' => 1145,
            'startColumn' => 41,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the current working directory.
 *
 * @return $this
 */',
        'startLine' => 1145,
        'endLine' => 1150,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getEnv' => 
      array (
        'name' => 'getEnv',
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
 * Gets the environment variables.
 *
 * @psalm-return EnvArray
 */',
        'startLine' => 1157,
        'endLine' => 1160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setEnv' => 
      array (
        'name' => 'setEnv',
        'parameters' => 
        array (
          'env' => 
          array (
            'name' => 'env',
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
            'startLine' => 1169,
            'endLine' => 1169,
            'startColumn' => 28,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the environment variables.
 *
 * @param EnvArray $env The new environment variables
 *
 * @return $this
 */',
        'startLine' => 1169,
        'endLine' => 1174,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getInput' => 
      array (
        'name' => 'getInput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the Process input.
 *
 * @return resource|string|\\Iterator|null
 */',
        'startLine' => 1181,
        'endLine' => 1184,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setInput' => 
      array (
        'name' => 'setInput',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 1197,
            'endLine' => 1197,
            'startColumn' => 30,
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
 * Sets the input.
 *
 * This content will be passed to the underlying process standard input.
 *
 * @param string|resource|\\Traversable|self|null $input The content
 *
 * @return $this
 *
 * @throws LogicException In case the process is running
 */',
        'startLine' => 1197,
        'endLine' => 1206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'checkTimeout' => 
      array (
        'name' => 'checkTimeout',
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
 * Performs a check between the timeout definition and the time the process started.
 *
 * In case you run a background process (with the start method), you should
 * trigger this method regularly to ensure the process timeout
 *
 * @throws ProcessTimedOutException In case the timeout was reached
 */',
        'startLine' => 1216,
        'endLine' => 1233,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getStartTime' => 
      array (
        'name' => 'getStartTime',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @throws LogicException in case process is not started
 */',
        'startLine' => 1238,
        'endLine' => 1245,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setOptions' => 
      array (
        'name' => 'setOptions',
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
            'startLine' => 1255,
            'endLine' => 1255,
            'startColumn' => 32,
            'endColumn' => 45,
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
 * Defines options to pass to the underlying proc_open().
 *
 * @see https://php.net/proc_open for the options supported by PHP.
 *
 * Enabling the "create_new_console" option allows a subprocess to continue
 * to run after the main process exited, on both Windows and *nix
 */',
        'startLine' => 1255,
        'endLine' => 1271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'setIgnoredSignals' => 
      array (
        'name' => 'setIgnoredSignals',
        'parameters' => 
        array (
          'signals' => 
          array (
            'name' => 'signals',
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
            'startLine' => 1278,
            'endLine' => 1278,
            'startColumn' => 39,
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
 * Defines a list of posix signals that will not be propagated to the process.
 *
 * @param list<\\SIG*> $signals
 */',
        'startLine' => 1278,
        'endLine' => 1285,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isTtySupported' => 
      array (
        'name' => 'isTtySupported',
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
 * Returns whether TTY is supported on the current operating system.
 */',
        'startLine' => 1290,
        'endLine' => 1295,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isPtySupported' => 
      array (
        'name' => 'isPtySupported',
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
 * Returns whether PTY is supported on the current operating system.
 */',
        'startLine' => 1300,
        'endLine' => 1313,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getDescriptors' => 
      array (
        'name' => 'getDescriptors',
        'parameters' => 
        array (
          'hasCallback' => 
          array (
            'name' => 'hasCallback',
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
            'startLine' => 1318,
            'endLine' => 1318,
            'startColumn' => 37,
            'endColumn' => 53,
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
 * Creates the descriptors needed by the proc_open.
 */',
        'startLine' => 1318,
        'endLine' => 1330,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'buildCallback' => 
      array (
        'name' => 'buildCallback',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 1342,
                'endLine' => 1342,
                'startTokenPos' => 6081,
                'startFilePos' => 44295,
                'endTokenPos' => 6081,
                'endFilePos' => 44298,
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
            'startLine' => 1342,
            'endLine' => 1342,
            'startColumn' => 38,
            'endColumn' => 63,
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
            'name' => 'Closure',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Builds up the callback used by wait().
 *
 * The callbacks adds all occurred output to the specific buffer and calls
 * the user callback (if present) with the received output.
 *
 * @param (callable(\'out\'|\'err\', string):void)|null $callback
 *
 * @return \\Closure(\'out\'|\'err\', string):bool
 */',
        'startLine' => 1342,
        'endLine' => 1356,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'updateStatus' => 
      array (
        'name' => 'updateStatus',
        'parameters' => 
        array (
          'blocking' => 
          array (
            'name' => 'blocking',
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
            'startLine' => 1363,
            'endLine' => 1363,
            'startColumn' => 37,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Updates the status of the process, reads pipes.
 *
 * @param bool $blocking Whether to use a blocking read call
 */',
        'startLine' => 1363,
        'endLine' => 1383,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'isSigchildEnabled' => 
      array (
        'name' => 'isSigchildEnabled',
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
 * Returns whether PHP has been compiled with the \'--enable-sigchild\' option or not.
 */',
        'startLine' => 1388,
        'endLine' => 1402,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'readPipesForOutput' => 
      array (
        'name' => 'readPipesForOutput',
        'parameters' => 
        array (
          'caller' => 
          array (
            'name' => 'caller',
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
            'startLine' => 1412,
            'endLine' => 1412,
            'startColumn' => 41,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'blocking' => 
          array (
            'name' => 'blocking',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 1412,
                'endLine' => 1412,
                'startTokenPos' => 6500,
                'startFilePos' => 46468,
                'endTokenPos' => 6500,
                'endFilePos' => 46472,
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
            'startLine' => 1412,
            'endLine' => 1412,
            'startColumn' => 57,
            'endColumn' => 78,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reads pipes for the freshest output.
 *
 * @param string $caller   The name of the method that needs fresh outputs
 * @param bool   $blocking Whether to use blocking calls or not
 *
 * @throws LogicException in case output has been disabled or process is not started
 */',
        'startLine' => 1412,
        'endLine' => 1421,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'validateTimeout' => 
      array (
        'name' => 'validateTimeout',
        'parameters' => 
        array (
          'timeout' => 
          array (
            'name' => 'timeout',
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
                      'name' => 'float',
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
            'startLine' => 1428,
            'endLine' => 1428,
            'startColumn' => 38,
            'endColumn' => 52,
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
                  'name' => 'float',
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
 * Validates and returns the filtered timeout.
 *
 * @throws InvalidArgumentException if the given timeout is a negative number
 */',
        'startLine' => 1428,
        'endLine' => 1439,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'readPipes' => 
      array (
        'name' => 'readPipes',
        'parameters' => 
        array (
          'blocking' => 
          array (
            'name' => 'blocking',
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
            'startLine' => 1447,
            'endLine' => 1447,
            'startColumn' => 32,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'close' => 
          array (
            'name' => 'close',
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
            'startLine' => 1447,
            'endLine' => 1447,
            'startColumn' => 48,
            'endColumn' => 58,
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
 * Reads pipes, executes callback.
 *
 * @param bool $blocking Whether to use blocking calls or not
 * @param bool $close    Whether to close file handles or not
 */',
        'startLine' => 1447,
        'endLine' => 1459,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'close' => 
      array (
        'name' => 'close',
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
 * Closes process resource, closes file handles, sets the exitcode.
 *
 * @return int The exitcode
 */',
        'startLine' => 1466,
        'endLine' => 1492,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'resetProcessData' => 
      array (
        'name' => 'resetProcessData',
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
 * Resets data related to the latest run of the process.
 */',
        'startLine' => 1497,
        'endLine' => 1511,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'doSignal' => 
      array (
        'name' => 'doSignal',
        'parameters' => 
        array (
          'signal' => 
          array (
            'name' => 'signal',
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
            'startLine' => 1523,
            'endLine' => 1523,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'throwException' => 
          array (
            'name' => 'throwException',
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
            'startLine' => 1523,
            'endLine' => 1523,
            'startColumn' => 44,
            'endColumn' => 63,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sends a POSIX signal to the process.
 *
 * @param int  $signal         A valid POSIX signal (see https://php.net/pcntl.constants)
 * @param bool $throwException Whether to throw exception in case signal failed
 *
 * @throws LogicException   In case the process is not running
 * @throws RuntimeException In case --enable-sigchild is activated and the process can\'t be killed
 * @throws RuntimeException In case of failure
 */',
        'startLine' => 1523,
        'endLine' => 1570,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'buildShellCommandline' => 
      array (
        'name' => 'buildShellCommandline',
        'parameters' => 
        array (
          'commandline' => 
          array (
            'name' => 'commandline',
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
            'startLine' => 1575,
            'endLine' => 1575,
            'startColumn' => 44,
            'endColumn' => 68,
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
 * @param string|list<string> $commandline
 */',
        'startLine' => 1575,
        'endLine' => 1588,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'prepareWindowsCommandLine' => 
      array (
        'name' => 'prepareWindowsCommandLine',
        'parameters' => 
        array (
          'cmd' => 
          array (
            'name' => 'cmd',
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
            'startLine' => 1596,
            'endLine' => 1596,
            'startColumn' => 48,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'env' => 
          array (
            'name' => 'env',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1596,
            'endLine' => 1596,
            'startColumn' => 67,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string|list<string> $cmd
 * @param EnvArray            $env
 *
 * @param-out EnvArray $env
 */',
        'startLine' => 1596,
        'endLine' => 1648,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'requireProcessIsStarted' => 
      array (
        'name' => 'requireProcessIsStarted',
        'parameters' => 
        array (
          'functionName' => 
          array (
            'name' => 'functionName',
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
            'startLine' => 1655,
            'endLine' => 1655,
            'startColumn' => 46,
            'endColumn' => 65,
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
 * Ensures the process is running or terminated, throws a LogicException if the process has a not started.
 *
 * @throws LogicException if the process has not run
 */',
        'startLine' => 1655,
        'endLine' => 1660,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'requireProcessIsTerminated' => 
      array (
        'name' => 'requireProcessIsTerminated',
        'parameters' => 
        array (
          'functionName' => 
          array (
            'name' => 'functionName',
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
            'startLine' => 1667,
            'endLine' => 1667,
            'startColumn' => 49,
            'endColumn' => 68,
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
 * Ensures the process is terminated, throws a LogicException if the process has a status different than "terminated".
 *
 * @throws LogicException if the process is not yet terminated
 */',
        'startLine' => 1667,
        'endLine' => 1672,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'escapeArgument' => 
      array (
        'name' => 'escapeArgument',
        'parameters' => 
        array (
          'argument' => 
          array (
            'name' => 'argument',
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
            'startLine' => 1677,
            'endLine' => 1677,
            'startColumn' => 37,
            'endColumn' => 53,
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
 * Escapes a string to be used as a shell argument.
 */',
        'startLine' => 1677,
        'endLine' => 1694,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'replacePlaceholders' => 
      array (
        'name' => 'replacePlaceholders',
        'parameters' => 
        array (
          'commandline' => 
          array (
            'name' => 'commandline',
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
            'startLine' => 1699,
            'endLine' => 1699,
            'startColumn' => 42,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'env' => 
          array (
            'name' => 'env',
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
            'startLine' => 1699,
            'endLine' => 1699,
            'startColumn' => 63,
            'endColumn' => 72,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param EnvArray $env
 */',
        'startLine' => 1699,
        'endLine' => 1708,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'getDefaultEnv' => 
      array (
        'name' => 'getDefaultEnv',
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
 * @return EnvArray
 */',
        'startLine' => 1713,
        'endLine' => 1747,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
        'aliasName' => NULL,
      ),
      'validateWindowsEnvBlockSize' => 
      array (
        'name' => 'validateWindowsEnvBlockSize',
        'parameters' => 
        array (
          'envPairs' => 
          array (
            'name' => 'envPairs',
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
            'startLine' => 1749,
            'endLine' => 1749,
            'startColumn' => 50,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 1749,
        'endLine' => 1759,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Symfony\\Component\\Process',
        'declaringClassName' => 'Symfony\\Component\\Process\\Process',
        'implementingClassName' => 'Symfony\\Component\\Process\\Process',
        'currentClassName' => 'Symfony\\Component\\Process\\Process',
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