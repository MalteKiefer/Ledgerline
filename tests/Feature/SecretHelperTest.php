<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Secret;
use Tests\TestCase;

class SecretHelperTest extends TestCase
{
    public function test_it_reads_from_the_file_when_a_key_file_var_points_at_one(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sec');
        file_put_contents($path, "s3cr3t-value\n"); // trailing newline (common)
        putenv('WIDGET_TOKEN_FILE='.$path);
        putenv('WIDGET_TOKEN=from-env');

        // The file wins and its trailing newline is trimmed.
        $this->assertSame('s3cr3t-value', Secret::get('WIDGET_TOKEN'));

        putenv('WIDGET_TOKEN_FILE');
        @unlink($path);
    }

    public function test_it_falls_back_to_env_when_no_file_var_is_set(): void
    {
        putenv('WIDGET_TOKEN_FILE');
        putenv('WIDGET_TOKEN=plain-env');
        $this->assertSame('plain-env', Secret::get('WIDGET_TOKEN'));

        putenv('WIDGET_TOKEN');
        $this->assertSame('fallback', Secret::get('WIDGET_TOKEN', 'fallback'));
    }
}
