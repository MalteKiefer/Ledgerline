<?php

use App\Support\UserData\FilesData;
use App\Support\UserData\GalleryData;
use App\Support\UserData\PaperlessData;
use App\Support\UserData\SettingsData;

// Modules that contribute to per-user GDPR export and account erasure.
// Each class implements App\Support\UserData\UserDataContributor.
return [
    'contributors' => [
        FilesData::class,
        GalleryData::class,
        PaperlessData::class,
        SettingsData::class,
    ],
];
