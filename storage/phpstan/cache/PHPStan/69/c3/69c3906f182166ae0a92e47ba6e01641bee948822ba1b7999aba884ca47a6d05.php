<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/ImageManagerFactory.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\ImageManagerFactory
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-f72920337a8d9a2ac39a740d7174217c3ea194504bde69939b0049a23cca4bae',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\ImageManagerFactory',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/ImageManagerFactory.php',
      ),
    ),
    'namespace' => 'App\\Support',
    'name' => 'App\\Support\\ImageManagerFactory',
    'shortName' => 'ImageManagerFactory',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Single source of Intervention driver selection: Imagick when the extension is
 * loaded (HEIC/AVIF capable), else GD. Avoids re-expressing the probe everywhere.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 26,
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
      'make' => 
      array (
        'name' => 'make',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Intervention\\Image\\ImageManager',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 17,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\ImageManagerFactory',
        'implementingClassName' => 'App\\Support\\ImageManagerFactory',
        'currentClassName' => 'App\\Support\\ImageManagerFactory',
        'aliasName' => NULL,
      ),
      'hasImagick' => 
      array (
        'name' => 'hasImagick',
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
        'docComment' => NULL,
        'startLine' => 22,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\ImageManagerFactory',
        'implementingClassName' => 'App\\Support\\ImageManagerFactory',
        'currentClassName' => 'App\\Support\\ImageManagerFactory',
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