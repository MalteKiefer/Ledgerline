<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

// Conservative Rector configuration used as a DRY-RUN GATE (`composer rector`).
//
// Scope is deliberately narrow: the PHP 8.5 level set plus DEAD_CODE and
// CODE_QUALITY. Aggressive naming / type-coverage / privatization sets are NOT
// enabled because they would fight PHPStan (level 10) or Pint, or produce large
// churn.
//
// Nothing is auto-applied in CI or the release ritual: `rector process` (without
// --dry-run) must only be run deliberately by a human, and any change to the
// crypto / blob / backup / sharing paths (app/Support, app/Services/Backup,
// app/Http/Controllers/*Blob*, SharedVault*/UserKey/PublicShare controllers) must
// be hand-reviewed before it lands. The dry run intentionally scans ALL paths so
// those areas still appear in the report.
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php85: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
    ]);
