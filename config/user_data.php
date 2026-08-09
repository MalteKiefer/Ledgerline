<?php

use App\Support\UserData\ContactsData;
use App\Support\UserData\FilesData;
use App\Support\UserData\FinanceData;
use App\Support\UserData\MailData;
use App\Support\UserData\PaperlessData;
use App\Support\UserData\SettingsData;

// Modules that contribute to per-user GDPR export and account erasure.
// Each class implements App\Support\UserData\UserDataContributor.
return [
    'contributors' => [
        ContactsData::class,
        FilesData::class,
        FinanceData::class,
        MailData::class,
        PaperlessData::class,
        SettingsData::class,
    ],
];
