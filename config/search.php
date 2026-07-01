<?php

declare(strict_types=1);

use App\Search\Providers\BranchSearchProvider;
use App\Search\Providers\ContactSearchProvider;
use App\Search\Providers\CustomerSearchProvider;
use App\Search\Providers\FileSearchProvider;
use App\Search\Providers\ProjectSearchProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Global search providers
    |--------------------------------------------------------------------------
    |
    | Each entry is a class implementing App\Search\SearchProvider. To make a
    | new entity searchable in the global search, write a provider and add its
    | class here — nothing else needs to change. Results are grouped and shown
    | in the order listed below.
    |
    */

    'providers' => [
        CustomerSearchProvider::class,
        ContactSearchProvider::class,
        BranchSearchProvider::class,
        ProjectSearchProvider::class,
        FileSearchProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Result limit per group
    |--------------------------------------------------------------------------
    |
    | The maximum number of results returned by each provider for one query.
    |
    */

    'limit_per_group' => 8,

];
