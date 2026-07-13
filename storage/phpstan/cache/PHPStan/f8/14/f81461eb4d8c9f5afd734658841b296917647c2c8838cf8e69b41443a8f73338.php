<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/ThemeBootstrap.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\ThemeBootstrap
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-cef219c3d7c843944fb7ddbfc412892833481d47cf951909144f9a9bf14bc61c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\ThemeBootstrap',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Support/ThemeBootstrap.php',
      ),
    ),
    'namespace' => 'App\\Support',
    'name' => 'App\\Support\\ThemeBootstrap',
    'shortName' => 'ThemeBootstrap',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The inline <head> script that applies the dark class before first paint
 * (server-side rendering only knows the stored setting, not the OS scheme for
 * "system"). Kept here as a single constant so the CSP hash in
 * SecurityHeaders can never drift from what the layout emits.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 22,
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
      'SCRIPT' => 
      array (
        'declaringClassName' => 'App\\Support\\ThemeBootstrap',
        'implementingClassName' => 'App\\Support\\ThemeBootstrap',
        'name' => 'SCRIPT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '"if(document.documentElement.dataset.theme===\'dark\'||(document.documentElement.dataset.theme===\'system\'&&matchMedia(\'(prefers-color-scheme: dark)\').matches))document.documentElement.classList.add(\'dark\');"',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 31,
            'startFilePos' => 395,
            'endTokenPos' => 31,
            'endFilePos' => 599,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 232,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'cspHash' => 
      array (
        'name' => 'cspHash',
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
        'docComment' => '/** CSP source expression allowing exactly this script. */',
        'startLine' => 18,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support',
        'declaringClassName' => 'App\\Support\\ThemeBootstrap',
        'implementingClassName' => 'App\\Support\\ThemeBootstrap',
        'currentClassName' => 'App\\Support\\ThemeBootstrap',
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