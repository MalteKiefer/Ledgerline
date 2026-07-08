<?php

use App\Support\UserData\BookmarksData;
use App\Support\UserData\FilesData;
use App\Support\UserData\GalleryData;
use App\Support\UserData\PaperlessData;
use App\Support\UserData\SettingsData;
use App\Support\UserData\StoreData;
use App\Support\UserData\TodosData;

// Modules that contribute to per-user GDPR export and account erasure.
// Each class implements App\Support\UserData\UserDataContributor.
return [
    'contributors' => [
        StoreData::class,
        TodosData::class,
        BookmarksData::class,
        FilesData::class,
        GalleryData::class,
        PaperlessData::class,
        SettingsData::class,
    ],
];
