<?php

use App\Support\UserData\FilesData;
use App\Support\UserData\PaperlessData;
use App\Support\UserData\SettingsData;
use App\Support\UserData\StoreData;

// Modules that contribute to per-user GDPR export and account erasure.
// Each class implements App\Support\UserData\UserDataContributor.
return [
    'contributors' => [
        StoreData::class,
        FilesData::class,
        PaperlessData::class,
        SettingsData::class,
    ],
];
