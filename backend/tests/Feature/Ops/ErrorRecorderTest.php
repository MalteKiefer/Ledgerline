<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\ErrorEvent;
use App\Services\Ops\ErrorRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_server_exception(): void
    {
        app(ErrorRecorder::class)->record(new RuntimeException('boom'));

        $this->assertSame(1, ErrorEvent::count());
        $this->assertSame('RuntimeException', ErrorEvent::first()->exception);
    }

    public function test_it_deduplicates_by_fingerprint(): void
    {
        $recorder = app(ErrorRecorder::class);
        $e = new RuntimeException('same fault'); // one throw site → one fingerprint
        $recorder->record($e);
        $recorder->record($e);

        $this->assertSame(1, ErrorEvent::count());
        $this->assertSame(2, ErrorEvent::first()->count);
    }

    public function test_it_ignores_expected_exceptions(): void
    {
        $recorder = app(ErrorRecorder::class);
        $recorder->record(ValidationException::withMessages(['x' => 'bad']));
        $recorder->record(new NotFoundHttpException);

        $this->assertSame(0, ErrorEvent::count());
    }

    public function test_it_redacts_secrets_in_the_message(): void
    {
        app(ErrorRecorder::class)->record(new RuntimeException('connect password=hunter2 failed'));

        $this->assertStringNotContainsString('hunter2', (string) ErrorEvent::first()->message);
    }
}
