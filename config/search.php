<?php

declare(strict_types=1);

use App\Search\Providers\FileSearchProvider;

return [
    /*
    | Global search providers. Each implements App\Search\SearchProvider; add a
    | class here to make a new entity searchable.
    */
    'providers' => [
        FileSearchProvider::class,
    ],

    'limit_per_group' => 8,
];
