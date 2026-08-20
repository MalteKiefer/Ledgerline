<?php

declare(strict_types=1);

return [
    // Login
    'email' => 'E-Mail',
    'password' => 'Passwort',
    'sign_in' => 'Anmelden',
    'invalid_credentials' => 'E-Mail oder Passwort falsch.',
    'forgot' => 'Passwort vergessen?',
    'no_account' => 'Noch kein Konto?',
    'register_link' => 'Konto erstellen',

    // Passkeys
    'or' => 'oder',
    'passkey_sign_in' => 'Mit Passkey anmelden',
    'passkey_failed' => 'Passkey-Anmeldung fehlgeschlagen. Bitte erneut versuchen.',
    'verify_email' => 'Bitte bestätige zuerst deine E-Mail-Adresse.',

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
    'back_to_login' => 'Zurück zur Anmeldung',
    'reset_title' => 'Neues Passwort wählen',
    'reset_button' => 'Passwort zurücksetzen',
    'reset_success' => 'Dein Passwort wurde zurückgesetzt. Du kannst dich jetzt anmelden.',
    'reset_invalid' => 'Dieser Link ist ungültig oder abgelaufen.',
    'register_disabled' => 'Die Selbstregistrierung ist derzeit deaktiviert.',

    // Email verification
    'verify_title' => 'E-Mail bestätigen',
    'verify_intro' => 'Wir haben dir einen Bestätigungslink geschickt. Klicke ihn, um die Anmeldung abzuschließen.',

    // Two-factor
    'twofa_code' => 'Authentifizierungscode',

    // Confirm password

    // Invite / reset link (mail-independent)
    'invite_title' => 'Passwort festlegen',
    'invite_subtitle' => 'Wähle ein Passwort für :email.',
    'invite_password_hint' => 'Mindestens 12 Zeichen.',
    'invite_button' => 'Passwort setzen und anmelden',
    'invite_invalid' => 'Dieser Link ist ungültig, abgelaufen oder wurde bereits verwendet.',
    'invite_mail_subject' => 'Dein Login-Link',
    'invite_mail_greeting' => 'Hallo!',
    'invite_mail_line' => 'Nutze den Button unten, um dein Passwort zu setzen und dich anzumelden.',
    'invite_mail_action' => 'Passwort setzen',
    'invite_mail_expires' => 'Dieser Link läuft am :time ab.',
];
