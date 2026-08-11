<?php

declare(strict_types=1);

return [
    // Login
    'login_title' => 'Вход',
    'login_subtitle' => 'Войдите в свой аккаунт Ledgerline.',
    'email' => 'Эл. почта',
    'password' => 'Пароль',
    'remember' => 'Оставаться в системе',
    'sign_in' => 'Войти',
    'invalid_credentials' => 'Неверный e-mail или пароль.',
    'forgot' => 'Забыли пароль?',
    'no_account' => 'Ещё нет аккаунта?',
    'register_link' => 'Создать',
    'or_divider' => 'или',
    'pocketid_login' => 'Войти через Pocket ID',
    'pocketid_unavailable' => 'Вход через Pocket ID недоступен.',
    'pocketid_failed' => 'Ошибка аутентификации. Попробуйте ещё раз.',

    // Passkeys
    'or' => 'или',
    'passkey_sign_in' => 'Войти с помощью passkey',
    'passkey_failed' => 'Не удалось войти по passkey. Попробуйте ещё раз.',
    'verify_email' => 'Сначала подтвердите адрес электронной почты.',

    // Register
    'register_title' => 'Создать аккаунт',
    'name' => 'Имя',
    'password_confirm' => 'Подтвердите пароль',
    'create_account' => 'Создать аккаунт',
    'have_account' => 'Уже есть аккаунт?',
    'login_link' => 'Войти',

    // Forgot / reset password
    'forgot_title' => 'Сброс пароля',
    'forgot_intro' => 'Введите эл. почту — мы пришлём ссылку для сброса.',
    'send_link' => 'Отправить ссылку',
    'reset_link_sent' => 'Если такая почта есть, ссылка уже в пути.',
    'mail_disabled' => 'Отправка почты не настроена. Попросите администратора сбросить пароль или выполните на сервере: php artisan user:set-password <email>',
    'back_to_login' => 'Назад ко входу',
    'reset_title' => 'Выберите новый пароль',
    'reset_button' => 'Сбросить пароль',
    'reset_success' => 'Пароль сброшен. Теперь вы можете войти.',
    'reset_invalid' => 'Ссылка недействительна или срок её действия истёк.',
    'register_disabled' => 'Самостоятельная регистрация сейчас отключена.',

    // Email verification
    'verify_title' => 'Подтвердите эл. почту',
    'verify_intro' => 'Мы отправили ссылку для подтверждения на вашу почту. Перейдите по ней, чтобы завершить вход.',
    'verify_resend' => 'Отправить письмо ещё раз',
    'verify_sent' => 'Новая ссылка для подтверждения отправлена.',
    'sign_out' => 'Выйти',

    // Two-factor
    'twofa_title' => 'Двухфакторная аутентификация',
    'twofa_intro' => 'Введите 6-значный код из приложения-аутентификатора.',
    'twofa_code' => 'Код аутентификации',
    'twofa_recovery' => 'Код восстановления',
    'twofa_use_recovery' => 'Использовать код восстановления',
    'twofa_use_code' => 'Использовать код аутентификации',
    'twofa_verify' => 'Подтвердить',

    // Confirm password
    'confirm_title' => 'Подтвердите пароль',
    'confirm_intro' => 'Пожалуйста, подтвердите пароль, чтобы продолжить.',
    'confirm_button' => 'Подтвердить',

    // Invite / reset link (mail-independent)
    'invite_title' => 'Задайте пароль',
    'invite_subtitle' => 'Выберите пароль для :email.',
    'invite_password_hint' => 'Не менее 12 символов.',
    'invite_button' => 'Задать пароль и войти',
    'invite_invalid' => 'Эта ссылка недействительна, истекла или уже использована.',
    'invite_mail_subject' => 'Ваша ссылка для входа',
    'invite_mail_greeting' => 'Здравствуйте!',
    'invite_mail_line' => 'Нажмите кнопку ниже, чтобы задать пароль и войти.',
    'invite_mail_action' => 'Задать пароль',
    'invite_mail_expires' => 'Срок действия ссылки истекает :time.',
];
