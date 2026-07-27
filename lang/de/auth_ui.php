<?php

declare(strict_types=1);

return [
    // Login
    'login_title' => 'Anmelden',
    'login_subtitle' => 'Melde dich bei deinem Ledgerline-Konto an.',
    'email' => 'E-Mail',
    'password' => 'Passwort',
    'remember' => 'Angemeldet bleiben',
    'sign_in' => 'Anmelden',
    'forgot' => 'Passwort vergessen?',
    'no_account' => 'Noch kein Konto?',
    'register_link' => 'Konto erstellen',

    // Register
    'register_title' => 'Konto erstellen',
    'name' => 'Name',
    'password_confirm' => 'Passwort bestätigen',
    'create_account' => 'Konto erstellen',
    'have_account' => 'Schon ein Konto?',
    'login_link' => 'Anmelden',

    // Forgot / reset password
    'forgot_title' => 'Passwort zurücksetzen',
    'forgot_intro' => 'Gib deine E-Mail ein, wir senden dir einen Link zum Zurücksetzen.',
    'send_link' => 'Link senden',
    'reset_link_sent' => 'Falls die E-Mail existiert, ist ein Link unterwegs.',
    'mail_disabled' => 'E-Mail-Versand ist nicht konfiguriert. Bitte einen Administrator, das Passwort zurückzusetzen, oder führe auf dem Server aus: php artisan user:set-password <email>',
    'back_to_login' => 'Zurück zur Anmeldung',
    'reset_title' => 'Neues Passwort wählen',
    'reset_button' => 'Passwort zurücksetzen',

    // Email verification
    'verify_title' => 'E-Mail bestätigen',
    'verify_intro' => 'Wir haben dir einen Bestätigungslink geschickt. Klicke ihn, um die Anmeldung abzuschließen.',
    'verify_resend' => 'Bestätigungs-E-Mail erneut senden',
    'verify_sent' => 'Ein neuer Bestätigungslink wurde gesendet.',
    'sign_out' => 'Abmelden',

    // Two-factor
    'twofa_title' => 'Zwei-Faktor-Authentifizierung',
    'twofa_intro' => 'Gib den 6-stelligen Code aus deiner Authenticator-App ein.',
    'twofa_code' => 'Authentifizierungscode',
    'twofa_recovery' => 'Wiederherstellungscode',
    'twofa_use_recovery' => 'Wiederherstellungscode verwenden',
    'twofa_use_code' => 'Authentifizierungscode verwenden',
    'twofa_verify' => 'Bestätigen',

    // Confirm password
    'confirm_title' => 'Passwort bestätigen',
    'confirm_intro' => 'Bitte bestätige dein Passwort, um fortzufahren.',
    'confirm_button' => 'Bestätigen',
];
