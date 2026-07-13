<?php declare(strict_types = 1);

// osfsl-/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Concerns/InteractsWithIO.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Concerns\InteractsWithIO
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-78802c58d38ea7687aea50e80a3313c0ef48ae30b5e533dc389674ef176af081-8.5.7-6.70.0.3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/vendor/composer/../laravel/framework/src/Illuminate/Console/Concerns/InteractsWithIO.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Concerns',
    'name' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
    'shortName' => 'InteractsWithIO',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 482,
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
      'components' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'name' => 'components',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The console components factory.
 *
 * @var \\Illuminate\\Console\\View\\Components\\Factory
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'input' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'name' => 'input',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The input interface implementation.
 *
 * @var \\Symfony\\Component\\Console\\Input\\InputInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'output' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'name' => 'output',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The output interface implementation.
 *
 * @var \\Illuminate\\Console\\OutputStyle
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'verbosity' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'name' => 'verbosity',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_NORMAL',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 97,
            'startFilePos' => 1150,
            'endTokenPos' => 99,
            'endFilePos' => 1182,
          ),
        ),
        'docComment' => '/**
 * The default verbosity of output commands.
 *
 * @var \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 61,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'verbosityMap' => 
      array (
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'name' => 'verbosityMap',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'v\' => \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_VERBOSE, \'vv\' => \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_VERY_VERBOSE, \'vvv\' => \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_DEBUG, \'quiet\' => \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_QUIET, \'normal\' => \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_NORMAL]',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 58,
            'startTokenPos' => 110,
            'startFilePos' => 1419,
            'endTokenPos' => 157,
            'endFilePos' => 1692,
          ),
        ),
        'docComment' => '/**
 * The mapping between human-readable verbosity levels and Symfony\'s OutputInterface.
 *
 * @var array<string, \\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 58,
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
      'input' => 
      array (
        'name' => 'input',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 67,
                'endLine' => 67,
                'startTokenPos' => 172,
                'startFilePos' => 1979,
                'endTokenPos' => 172,
                'endFilePos' => 1982,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
            'startColumn' => 27,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 67,
                'endLine' => 67,
                'startTokenPos' => 179,
                'startFilePos' => 1996,
                'endTokenPos' => 179,
                'endFilePos' => 1999,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 67,
            'endLine' => 67,
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
 * Retrieve the command\'s input as a CommandInput instance or retrieve an input item.
 *
 * @param  string|null  $key
 * @param  mixed  $default
 * @return ($key is null ? \\Illuminate\\Console\\CommandInput : mixed)
 */',
        'startLine' => 67,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'hasArgument' => 
      array (
        'name' => 'hasArgument',
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
            'startLine' => 80,
            'endLine' => 80,
            'startColumn' => 33,
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
 * Determine if the given argument is present.
 *
 * @param  string|int  $name
 * @return bool
 */',
        'startLine' => 80,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'argument' => 
      array (
        'name' => 'argument',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 91,
                'endLine' => 91,
                'startTokenPos' => 277,
                'startFilePos' => 2641,
                'endTokenPos' => 277,
                'endFilePos' => 2644,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 91,
            'endLine' => 91,
            'startColumn' => 30,
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
 * Get the value of a command argument.
 *
 * @param  string|null  $key
 * @return ($key is null ? array<array|string|float|int|bool|null> : array|string|float|int|bool|null)
 */',
        'startLine' => 91,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'arguments' => 
      array (
        'name' => 'arguments',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all of the arguments passed to the command.
 *
 * @return array<array|string|float|int|bool|null>
 */',
        'startLine' => 105,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'hasOption' => 
      array (
        'name' => 'hasOption',
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
            'startLine' => 116,
            'endLine' => 116,
            'startColumn' => 31,
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
 * Determine whether the option is defined in the command signature.
 *
 * @param  string  $name
 * @return bool
 */',
        'startLine' => 116,
        'endLine' => 119,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'option' => 
      array (
        'name' => 'option',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 127,
                'endLine' => 127,
                'startTokenPos' => 382,
                'startFilePos' => 3490,
                'endTokenPos' => 382,
                'endFilePos' => 3493,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 127,
            'endLine' => 127,
            'startColumn' => 28,
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
 * Get the value of a command option.
 *
 * @param  string|null  $key
 * @return ($key is null ? array<array|string|float|int|bool|null> : array|string|float|int|bool|null)
 */',
        'startLine' => 127,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
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
 * Get all of the options passed to the command.
 *
 * @return array<array|string|float|int|bool|null>
 */',
        'startLine' => 141,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'confirm' => 
      array (
        'name' => 'confirm',
        'parameters' => 
        array (
          'question' => 
          array (
            'name' => 'question',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 153,
            'endLine' => 153,
            'startColumn' => 29,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 153,
                'endLine' => 153,
                'startTokenPos' => 463,
                'startFilePos' => 4045,
                'endTokenPos' => 463,
                'endFilePos' => 4049,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 153,
            'endLine' => 153,
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
 * Confirm a question with the user.
 *
 * @param  string  $question
 * @param  bool  $default
 * @return bool
 */',
        'startLine' => 153,
        'endLine' => 156,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'ask' => 
      array (
        'name' => 'ask',
        'parameters' => 
        array (
          'question' => 
          array (
            'name' => 'question',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 25,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 165,
                'endLine' => 165,
                'startTokenPos' => 500,
                'startFilePos' => 4319,
                'endTokenPos' => 500,
                'endFilePos' => 4322,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 165,
            'endLine' => 165,
            'startColumn' => 36,
            'endColumn' => 50,
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
 * Prompt the user for input.
 *
 * @param  string  $question
 * @param  string|null  $default
 * @return mixed
 */',
        'startLine' => 165,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'anticipate' => 
      array (
        'name' => 'anticipate',
        'parameters' => 
        array (
          'question' => 
          array (
            'name' => 'question',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 178,
            'endLine' => 178,
            'startColumn' => 32,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'choices' => 
          array (
            'name' => 'choices',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 178,
            'endLine' => 178,
            'startColumn' => 43,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 178,
                'endLine' => 178,
                'startTokenPos' => 540,
                'startFilePos' => 4666,
                'endTokenPos' => 540,
                'endFilePos' => 4669,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 178,
            'endLine' => 178,
            'startColumn' => 53,
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
 * Prompt the user for input with auto completion.
 *
 * @param  string  $question
 * @param  array|callable  $choices
 * @param  string|null  $default
 * @return mixed
 */',
        'startLine' => 178,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'askWithCompletion' => 
      array (
        'name' => 'askWithCompletion',
        'parameters' => 
        array (
          'question' => 
          array (
            'name' => 'question',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 191,
            'endLine' => 191,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'choices' => 
          array (
            'name' => 'choices',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 191,
            'endLine' => 191,
            'startColumn' => 50,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 191,
                'endLine' => 191,
                'startTokenPos' => 581,
                'startFilePos' => 5059,
                'endTokenPos' => 581,
                'endFilePos' => 5062,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 191,
            'endLine' => 191,
            'startColumn' => 60,
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
 * Prompt the user for input with auto completion.
 *
 * @param  string  $question
 * @param  iterable|(callable(string): string[])  $choices
 * @param  string|null  $default
 * @return mixed
 */',
        'startLine' => 191,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'secret' => 
      array (
        'name' => 'secret',
        'parameters' => 
        array (
          'question' => 
          array (
            'name' => 'question',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 28,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'fallback' => 
          array (
            'name' => 'fallback',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 209,
                'endLine' => 209,
                'startTokenPos' => 654,
                'startFilePos' => 5567,
                'endTokenPos' => 654,
                'endFilePos' => 5570,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 209,
            'endLine' => 209,
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
 * Prompt the user for input but hide the answer from the console.
 *
 * @param  string  $question
 * @param  bool  $fallback
 * @return mixed
 */',
        'startLine' => 209,
        'endLine' => 216,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'choice' => 
      array (
        'name' => 'choice',
        'parameters' => 
        array (
          'question' => 
          array (
            'name' => 'question',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 28,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'choices' => 
          array (
            'name' => 'choices',
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
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 39,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'default' => 
          array (
            'name' => 'default',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 228,
                'endLine' => 228,
                'startTokenPos' => 718,
                'startFilePos' => 6143,
                'endTokenPos' => 718,
                'endFilePos' => 6146,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 55,
            'endColumn' => 69,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'attempts' => 
          array (
            'name' => 'attempts',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 228,
                'endLine' => 228,
                'startTokenPos' => 725,
                'startFilePos' => 6161,
                'endTokenPos' => 725,
                'endFilePos' => 6164,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 72,
            'endColumn' => 87,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'multiple' => 
          array (
            'name' => 'multiple',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 228,
                'endLine' => 228,
                'startTokenPos' => 732,
                'startFilePos' => 6179,
                'endTokenPos' => 732,
                'endFilePos' => 6183,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 90,
            'endColumn' => 106,
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
 * Give the user a single choice from an array of answers.
 *
 * @param  string  $question
 * @param  array<\\Stringable|string|float|int|bool>  $choices
 * @param  string|int|null  $default
 * @param  ?positive-int  $attempts
 * @param  bool  $multiple
 * @return string|array
 */',
        'startLine' => 228,
        'endLine' => 235,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'table' => 
      array (
        'name' => 'table',
        'parameters' => 
        array (
          'headers' => 
          array (
            'name' => 'headers',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 27,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'rows' => 
          array (
            'name' => 'rows',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 37,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'tableStyle' => 
          array (
            'name' => 'tableStyle',
            'default' => 
            array (
              'code' => '\'default\'',
              'attributes' => 
              array (
                'startLine' => 246,
                'endLine' => 246,
                'startTokenPos' => 800,
                'startFilePos' => 6810,
                'endTokenPos' => 800,
                'endFilePos' => 6818,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 44,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'columnStyles' => 
          array (
            'name' => 'columnStyles',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 246,
                'endLine' => 246,
                'startTokenPos' => 809,
                'startFilePos' => 6843,
                'endTokenPos' => 810,
                'endFilePos' => 6844,
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
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 69,
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
 * Format input to textual table.
 *
 * @param  array  $headers
 * @param  \\Illuminate\\Contracts\\Support\\Arrayable|array  $rows
 * @param  \\Symfony\\Component\\Console\\Helper\\TableStyle|string  $tableStyle
 * @param  array<int, \\Symfony\\Component\\Console\\Helper\\TableStyle|string>  $columnStyles
 * @return void
 */',
        'startLine' => 246,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'withProgressBar' => 
      array (
        'name' => 'withProgressBar',
        'parameters' => 
        array (
          'totalSteps' => 
          array (
            'name' => 'totalSteps',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 274,
            'endLine' => 274,
            'startColumn' => 37,
            'endColumn' => 47,
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
            'startLine' => 274,
            'endLine' => 274,
            'startColumn' => 50,
            'endColumn' => 66,
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
 * Execute a given callback while advancing a progress bar.
 *
 * @template TKey of array-key
 * @template TValue
 * @template TIterable of iterable<TKey, TValue>
 *
 * @param  TIterable|int  $totalSteps
 * @param  \\Closure(\\Symfony\\Component\\Console\\Helper\\ProgressBar): mixed|\\Closure(TValue, \\Symfony\\Component\\Console\\Helper\\ProgressBar, TKey): mixed  $callback
 * @return ($totalSteps is iterable ? TIterable : void)
 */',
        'startLine' => 274,
        'endLine' => 297,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'info' => 
      array (
        'name' => 'info',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 306,
            'endLine' => 306,
            'startColumn' => 26,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 306,
                'endLine' => 306,
                'startTokenPos' => 1071,
                'startFilePos' => 8606,
                'endTokenPos' => 1071,
                'endFilePos' => 8609,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 306,
            'endLine' => 306,
            'startColumn' => 35,
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
 * Write a string as information output.
 *
 * @param  string  $string
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 306,
        'endLine' => 309,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'line' => 
      array (
        'name' => 'line',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 319,
            'endLine' => 319,
            'startColumn' => 26,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'style' => 
          array (
            'name' => 'style',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 319,
                'endLine' => 319,
                'startTokenPos' => 1107,
                'startFilePos' => 9040,
                'endTokenPos' => 1107,
                'endFilePos' => 9043,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 319,
            'endLine' => 319,
            'startColumn' => 35,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 319,
                'endLine' => 319,
                'startTokenPos' => 1114,
                'startFilePos' => 9059,
                'endTokenPos' => 1114,
                'endFilePos' => 9062,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 319,
            'endLine' => 319,
            'startColumn' => 50,
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
 * Write a string as standard output.
 *
 * @param  string  $string
 * @param  \'info\'|\'comment\'|\'question\'|\'error\'|\'warn\'|\'alert\'|null  $style
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 319,
        'endLine' => 324,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'comment' => 
      array (
        'name' => 'comment',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 333,
            'endLine' => 333,
            'startColumn' => 29,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 333,
                'endLine' => 333,
                'startTokenPos' => 1177,
                'startFilePos' => 9512,
                'endTokenPos' => 1177,
                'endFilePos' => 9515,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 333,
            'endLine' => 333,
            'startColumn' => 38,
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
 * Write a string as comment output.
 *
 * @param  string  $string
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 333,
        'endLine' => 336,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'question' => 
      array (
        'name' => 'question',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 30,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 345,
                'endLine' => 345,
                'startTokenPos' => 1213,
                'startFilePos' => 9878,
                'endTokenPos' => 1213,
                'endFilePos' => 9881,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 39,
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
 * Write a string as question output.
 *
 * @param  string  $string
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 345,
        'endLine' => 348,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'error' => 
      array (
        'name' => 'error',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 357,
            'endLine' => 357,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 357,
                'endLine' => 357,
                'startTokenPos' => 1249,
                'startFilePos' => 10239,
                'endTokenPos' => 1249,
                'endFilePos' => 10242,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 357,
            'endLine' => 357,
            'startColumn' => 36,
            'endColumn' => 52,
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
 * Write a string as error output.
 *
 * @param  string  $string
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 357,
        'endLine' => 360,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'warn' => 
      array (
        'name' => 'warn',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 369,
            'endLine' => 369,
            'startColumn' => 26,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 369,
                'endLine' => 369,
                'startTokenPos' => 1285,
                'startFilePos' => 10598,
                'endTokenPos' => 1285,
                'endFilePos' => 10601,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 369,
            'endLine' => 369,
            'startColumn' => 35,
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
 * Write a string as warning output.
 *
 * @param  string  $string
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 369,
        'endLine' => 378,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'alert' => 
      array (
        'name' => 'alert',
        'parameters' => 
        array (
          'string' => 
          array (
            'name' => 'string',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 27,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'verbosity' => 
          array (
            'name' => 'verbosity',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 387,
                'endLine' => 387,
                'startTokenPos' => 1373,
                'startFilePos' => 11167,
                'endTokenPos' => 1373,
                'endFilePos' => 11170,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 36,
            'endColumn' => 52,
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
 * Write a string in an alert box.
 *
 * @param  string  $string
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $verbosity
 * @return void
 */',
        'startLine' => 387,
        'endLine' => 396,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'newLine' => 
      array (
        'name' => 'newLine',
        'parameters' => 
        array (
          'count' => 
          array (
            'name' => 'count',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 404,
                'endLine' => 404,
                'startTokenPos' => 1471,
                'startFilePos' => 11607,
                'endTokenPos' => 1471,
                'endFilePos' => 11607,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 404,
            'endLine' => 404,
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
 * Write a blank line.
 *
 * @param  int  $count
 * @return $this
 */',
        'startLine' => 404,
        'endLine' => 409,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
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
                'name' => 'Symfony\\Component\\Console\\Input\\InputInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 417,
            'endLine' => 417,
            'startColumn' => 30,
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
 * Set the input interface implementation.
 *
 * @param  \\Symfony\\Component\\Console\\Input\\InputInterface  $input
 * @return void
 */',
        'startLine' => 417,
        'endLine' => 420,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'setOutput' => 
      array (
        'name' => 'setOutput',
        'parameters' => 
        array (
          'output' => 
          array (
            'name' => 'output',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Console\\OutputStyle',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 428,
            'endLine' => 428,
            'startColumn' => 31,
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
 * Set the output interface implementation.
 *
 * @param  \\Illuminate\\Console\\OutputStyle  $output
 * @return void
 */',
        'startLine' => 428,
        'endLine' => 431,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'setVerbosity' => 
      array (
        'name' => 'setVerbosity',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 439,
            'endLine' => 439,
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
 * Set the verbosity level.
 *
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*  $level
 * @return void
 */',
        'startLine' => 439,
        'endLine' => 442,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'parseVerbosity' => 
      array (
        'name' => 'parseVerbosity',
        'parameters' => 
        array (
          'level' => 
          array (
            'name' => 'level',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 450,
                'endLine' => 450,
                'startTokenPos' => 1586,
                'startFilePos' => 12783,
                'endTokenPos' => 1586,
                'endFilePos' => 12786,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 450,
            'endLine' => 450,
            'startColumn' => 39,
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
 * Get the verbosity level in terms of Symfony\'s OutputInterface level.
 *
 * @param  \'v\'|\'vv\'|\'vvv\'|\'quiet\'|\'normal\'|\\Symfony\\Component\\Console\\Output\\OutputInterface::VERBOSITY_*|null  $level
 * @return int
 */',
        'startLine' => 450,
        'endLine' => 461,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'getOutput' => 
      array (
        'name' => 'getOutput',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the output implementation.
 *
 * @return \\Illuminate\\Console\\OutputStyle
 */',
        'startLine' => 468,
        'endLine' => 471,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'aliasName' => NULL,
      ),
      'outputComponents' => 
      array (
        'name' => 'outputComponents',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the output component factory implementation.
 *
 * @return \\Illuminate\\Console\\View\\Components\\Factory
 */',
        'startLine' => 478,
        'endLine' => 481,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Concerns',
        'declaringClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'implementingClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
        'currentClassName' => 'Illuminate\\Console\\Concerns\\InteractsWithIO',
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