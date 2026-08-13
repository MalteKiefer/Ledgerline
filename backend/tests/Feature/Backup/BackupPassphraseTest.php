<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Models\BackupJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupPassphraseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_environment_passphrase_takes_precedence_over_the_db_column(): void
    {
        $job = new BackupJob(['passphrase' => 'from-database']);

        // No env passphrase → the legacy per-job DB value is used.
        config(['backup.passphrase' => null]);
        $this->assertSame('from-database', $job->effectivePassphrase());

        // Env passphrase set → it wins, so the key never lives in the dumped DB.
        config(['backup.passphrase' => 'from-environment']);
        $this->assertSame('from-environment', $job->effectivePassphrase());
    }

    public function test_a_blank_passphrase_resolves_to_null(): void
    {
        config(['backup.passphrase' => '']);
        $this->assertNull((new BackupJob(['passphrase' => null]))->effectivePassphrase());
    }
}
