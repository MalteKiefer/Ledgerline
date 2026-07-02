<?php

declare(strict_types=1);

use App\Search\Providers\PhotoSearchProvider;

return [
    /*
    | Global search providers. Each implements App\Search\SearchProvider; add a
    | class here to make a new entity searchable.
    */
    'providers' => [
        PhotoSearchProvider::class,
    ],

    'limit_per_group' => 8,
];
