<?php

use App\Support\UserData\BookmarksData;
use App\Support\UserData\CalendarData;
use App\Support\UserData\ContactsData;
use App\Support\UserData\FilesData;
use App\Support\UserData\GalleryData;
use App\Support\UserData\MailData;
use App\Support\UserData\NotesData;
use App\Support\UserData\PaperlessData;
use App\Support\UserData\SettingsData;
use App\Support\UserData\TodosData;

// Modules that contribute to per-user GDPR export and account erasure.
// Each class implements App\Support\UserData\UserDataContributor.
return [
    'contributors' => [
        NotesData::class,
        TodosData::class,
        BookmarksData::class,
        ContactsData::class,
        CalendarData::class,
        FilesData::class,
        GalleryData::class,
        MailData::class,
        PaperlessData::class,
        SettingsData::class,
    ],
];
