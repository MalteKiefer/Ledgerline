<?php

use App\Support\UserData\ContactsData;
use App\Support\UserData\ExploreData;
use App\Support\UserData\FilesData;
use App\Support\UserData\GalleryData;
use App\Support\UserData\InvoicesData;
use App\Support\UserData\MailData;
use App\Support\UserData\NotesData;
use App\Support\UserData\PaperlessData;
use App\Support\UserData\PasswordsData;
use App\Support\UserData\SettingsData;
use App\Support\UserData\SharedData;
use App\Support\UserData\StoreData;

// Modules that contribute to per-user GDPR export and account erasure.
// Each class implements App\Support\UserData\UserDataContributor.
return [
    'contributors' => [
        StoreData::class,
        FilesData::class,
        GalleryData::class,
        NotesData::class,
        PasswordsData::class,
        InvoicesData::class,
        MailData::class,
        ExploreData::class,
        ContactsData::class,
        SharedData::class,
        PaperlessData::class,
        SettingsData::class,
    ],
];
