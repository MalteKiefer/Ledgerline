<?php

declare(strict_types=1);

return [
    // Login
    'email' => 'Эл. почта',
    'password' => 'Пароль',
    'sign_in' => 'Войти',
    'invalid_credentials' => 'Неверный e-mail или пароль.',
    'forgot' => 'Забыли пароль?',
    'no_account' => 'Ещё нет аккаунта?',
    'register_link' => 'Создать',

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
    'back_to_login' => 'Назад ко входу',
    'reset_title' => 'Выберите новый пароль',
    'reset_button' => 'Сбросить пароль',
    'reset_success' => 'Пароль сброшен. Теперь вы можете войти.',
    'reset_invalid' => 'Ссылка недействительна или срок её действия истёк.',
    'register_disabled' => 'Самостоятельная регистрация сейчас отключена.',

    // Email verification
    'verify_title' => 'Подтвердите эл. почту',
    'verify_intro' => 'Мы отправили ссылку для подтверждения на вашу почту. Перейдите по ней, чтобы завершить вход.',

    // Two-factor
    'twofa_code' => 'Код аутентификации',

    // Confirm password

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
