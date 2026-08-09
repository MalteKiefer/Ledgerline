<?php

declare(strict_types=1);

return [
    // Login
    'login_title' => 'Sign in',
    'login_subtitle' => 'Sign in to your Ledgerline account.',
    'email' => 'Email',
    'password' => 'Password',
    'remember' => 'Keep me signed in',
    'sign_in' => 'Sign in',
    'invalid_credentials' => 'Wrong email or password.',
    'forgot' => 'Forgot your password?',
    'no_account' => 'No account yet?',
    'register_link' => 'Create one',
    'or_divider' => 'or',
    'pocketid_login' => 'Sign in with Pocket ID',
    'pocketid_unavailable' => 'Pocket ID sign-in is not available.',
    'pocketid_failed' => 'Authentication failed. Please try again.',

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
    'mail_disabled' => 'Email delivery is not configured. Ask an administrator to reset your password, or on the server run: php artisan user:set-password <email>',
    'back_to_login' => 'Back to sign in',
    'reset_title' => 'Choose a new password',
    'reset_button' => 'Reset password',
    'reset_success' => 'Your password has been reset. You can now sign in.',
    'reset_invalid' => 'This reset link is invalid or has expired.',
    'register_disabled' => 'Self-registration is currently disabled.',

    // Email verification
    'verify_title' => 'Verify your email',
    'verify_intro' => 'We sent a verification link to your email. Click it to finish signing in.',
    'verify_resend' => 'Resend verification email',
    'verify_sent' => 'A new verification link has been sent.',
    'sign_out' => 'Sign out',

    // Two-factor
    'twofa_title' => 'Two-factor authentication',
    'twofa_intro' => 'Enter the 6-digit code from your authenticator app.',
    'twofa_code' => 'Authentication code',
    'twofa_recovery' => 'Recovery code',
    'twofa_use_recovery' => 'Use a recovery code',
    'twofa_use_code' => 'Use an authentication code',
    'twofa_verify' => 'Verify',

    // Confirm password
    'confirm_title' => 'Confirm your password',
    'confirm_intro' => 'Please confirm your password to continue.',
    'confirm_button' => 'Confirm',

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
