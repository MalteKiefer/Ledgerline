<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Redactor;
use Tests\TestCase;

final class RedactorTest extends TestCase
{
    // From ErrorRecorder: password key=value style
    public function test_redacts_password_key_value(): void
    {
        $this->assertSame(
            "password='***'",
            Redactor::redact("password='secret123'")
        );
    }

    // From ErrorRecorder: secret/token/key/passphrase
    public function test_redacts_token_key_value(): void
    {
        $this->assertSame(
            'token=***',
            Redactor::redact('token=abc123')
        );
        $this->assertSame(
            'key=***',
            Redactor::redact('key=myvalue')
        );
    }

    // From ErrorRecorder: URI credentials
    public function test_redacts_uri_credentials(): void
    {
        $this->assertSame(
            'mysql://user:***@host/db',
            Redactor::redact('mysql://user:secret@host/db')
        );
    }

    // From ErrorRecorder: Bearer token
    public function test_redacts_bearer_token(): void
    {
        $this->assertSame(
            'Authorization: Bearer ***',
            Redactor::redact('Authorization: Bearer abc.def.xyz')
        );
    }

    // From BackupManager: --password flag
    public function test_redacts_mysqldump_password_flag(): void
    {
        $this->assertSame(
            'mysqldump --password=*** --host=db',
            Redactor::redact('mysqldump --password=secret123 --host=db')
        );
        $this->assertSame(
            'mysqldump --password *** --host=db',
            Redactor::redact('mysqldump --password secret123 --host=db')
        );
    }

    // From BackupManager: -p flag
    public function test_redacts_short_password_flag(): void
    {
        $this->assertSame(
            'mysqldump -p*** --host=db',
            Redactor::redact('mysqldump -psecret --host=db')
        );
    }

    // The INTENTIONAL fix: Bearer now also stripped in backup log context
    public function test_redacts_bearer_in_backup_context(): void
    {
        $this->assertSame(
            'Error: Bearer *** in backup',
            Redactor::redact('Error: Bearer abc123token in backup')
        );
    }

    public function test_empty_string_returns_empty_string(): void
    {
        $this->assertSame('', Redactor::redact(''));
    }

    public function test_no_secrets_unchanged(): void
    {
        $plain = 'mysqldump --host=db --user=root --databases mydb';
        $this->assertSame($plain, Redactor::redact($plain));
    }
}
