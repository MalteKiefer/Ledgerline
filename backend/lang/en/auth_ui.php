<?php

declare(strict_types=1);

return [
    // Login
    'email' => 'Email',
    'password' => 'Password',
    'sign_in' => 'Sign in',
    'invalid_credentials' => 'Wrong email or password.',
    'forgot' => 'Forgot your password?',
    'no_account' => 'No account yet?',
    'register_link' => 'Create one',

    // Passkeys
    'or' => 'or',
    'passkey_sign_in' => 'Sign in with a passkey',
    'passkey_failed' => 'Passkey sign-in failed. Please try again.',
    'verify_email' => 'Please verify your email address first.',

    // Register
    'register_title' => 'Create account',
    'name' => 'Name',
    'password_confirm' => 'Confirm password',
    'create_account' => 'Create account',
    'have_account' => 'Already have an account?',
    'login_link' => 'Sign in',

    // Forgot / reset password
    'forgot_title' => 'Reset password',
    'forgot_intro' => 'Enter your email and we will send you a reset link.',
    'send_link' => 'Send reset link',
    'reset_link_sent' => 'If that email exists, a reset link is on its way.',
    'back_to_login' => 'Back to sign in',
    'reset_title' => 'Choose a new password',
    'reset_button' => 'Reset password',
    'reset_success' => 'Your password has been reset. You can now sign in.',
    'reset_invalid' => 'This reset link is invalid or has expired.',
    'register_disabled' => 'Self-registration is currently disabled.',

    // Email verification
    'verify_title' => 'Verify your email',
    'verify_intro' => 'We sent a verification link to your email. Click it to finish signing in.',

    // Two-factor
    'twofa_code' => 'Authentication code',

    // Confirm password

    // Invite / reset link (mail-independent)
    'invite_title' => 'Set your password',
    'invite_subtitle' => 'Choose a password for :email.',
    'invite_password_hint' => 'At least 12 characters.',
    'invite_button' => 'Set password and sign in',
    'invite_invalid' => 'This link is invalid, has expired, or was already used.',
    'invite_mail_subject' => 'Your login link',
    'invite_mail_greeting' => 'Hello!',
    'invite_mail_line' => 'Use the button below to set your password and sign in.',
    'invite_mail_action' => 'Set password',
    'invite_mail_expires' => 'This link expires on :time.',
];
