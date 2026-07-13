<?php declare(strict_types = 1);

// odsl-/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Backup/DiskMirror.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Backup\DiskMirror
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.70.0.3-8.5.7-347078498a25351aa392a9ca8df76c59611a0d934254b29ee6cc13f82d5aad3a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Backup\\DiskMirror',
        'filename' => '/Users/malte.kiefer/Entwicklung/ledgerline/app/Services/Backup/DiskMirror.php',
      ),
    ),
    'namespace' => 'App\\Services\\Backup',
    'name' => 'App\\Services\\Backup\\DiskMirror',
    'shortName' => 'DiskMirror',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Incrementally mirrors a prefix of the files disk to a backup destination.
 *
 * Stored file blobs are immutable (their name is content-addressed) and
 * already client-side encrypted, so there is nothing to archive or compress:
 * this uploads only objects the destination is missing and removes objects that
 * no longer exist at the source. Server-to-server streaming, no local staging.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 155,
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
      'mirror' => 
      array (
        'name' => 'mirror',
        'parameters' => 
        array (
          'dest' => 
          array (
            'name' => 'dest',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'League\\Flysystem\\Filesystem',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 28,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sourcePrefix' => 
          array (
            'name' => 'sourcePrefix',
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 46,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destPrefix' => 
          array (
            'name' => 'destPrefix',
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 68,
            'endColumn' => 85,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'step' => 
          array (
            'name' => 'step',
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 88,
            'endColumn' => 101,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'checkCancel' => 
          array (
            'name' => 'checkCancel',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 31,
                'endLine' => 31,
                'startTokenPos' => 68,
                'startFilePos' => 1274,
                'endTokenPos' => 68,
                'endFilePos' => 1277,
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
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 104,
            'endColumn' => 132,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Full list-and-prune reconcile: upload objects the destination is missing
 * and delete destination objects the source no longer has. This scans both
 * the whole source prefix and the whole destination, so it is the expensive
 * path — the manager only runs it once per reconcile window; the fast
 * incremental delta() below handles the routine runs.
 *
 * @param  callable(string):void  $step  progress logger
 * @param  (callable():void)|null  $checkCancel  throws to abort mid-mirror
 * @return array{source:int, uploaded:int, removed:int}
 */',
        'startLine' => 31,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\DiskMirror',
        'implementingClassName' => 'App\\Services\\Backup\\DiskMirror',
        'currentClassName' => 'App\\Services\\Backup\\DiskMirror',
        'aliasName' => NULL,
      ),
      'delta' => 
      array (
        'name' => 'delta',
        'parameters' => 
        array (
          'dest' => 
          array (
            'name' => 'dest',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'League\\Flysystem\\Filesystem',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 27,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sourcePrefix' => 
          array (
            'name' => 'sourcePrefix',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 45,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'destPrefix' => 
          array (
            'name' => 'destPrefix',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 67,
            'endColumn' => 84,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'blobIds' => 
          array (
            'name' => 'blobIds',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 87,
            'endColumn' => 103,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'step' => 
          array (
            'name' => 'step',
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 106,
            'endColumn' => 119,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
          'checkCancel' => 
          array (
            'name' => 'checkCancel',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 109,
                'endLine' => 109,
                'startTokenPos' => 585,
                'startFilePos' => 4599,
                'endTokenPos' => 585,
                'endFilePos' => 4602,
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
            'startLine' => 109,
            'endLine' => 109,
            'startColumn' => 122,
            'endColumn' => 150,
            'parameterIndex' => 5,
            'isOptional' => true,
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
 * Incremental delta upload: given the blob ids added since the last run (from
 * the blob ledger, cheapest possible source of truth), upload just those —
 * no full source scan, no full destination listing, no per-object HEAD. Blobs
 * are immutable and content-addressed, so a blob that is new to the ledger is
 * guaranteed absent at the destination; we still tolerate a re-write (idempotent).
 *
 * @param  iterable<string>  $blobIds  ledger blob uuids created since the cursor
 * @param  callable(string):void  $step
 * @param  (callable():void)|null  $checkCancel
 * @return array{uploaded:int, missing:int}
 */',
        'startLine' => 109,
        'endLine' => 154,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Backup',
        'declaringClassName' => 'App\\Services\\Backup\\DiskMirror',
        'implementingClassName' => 'App\\Services\\Backup\\DiskMirror',
        'currentClassName' => 'App\\Services\\Backup\\DiskMirror',
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