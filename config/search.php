<?php

declare(strict_types=1);

use App\Search\Providers\NoteSearchProvider;
use App\Search\Providers\PhotoSearchProvider;
use App\Search\Providers\TodoSearchProvider;

return [
    /*
    | Global search providers. Each implements App\Search\SearchProvider; add a
    | class here to make a new entity searchable.
    */
    'providers' => [
        PhotoSearchProvider::class,
        NoteSearchProvider::class,
        TodoSearchProvider::class,
    ],

    'limit_per_group' => 8,
];
