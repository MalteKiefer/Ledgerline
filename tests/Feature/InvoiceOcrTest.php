<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Invoices\ReceiptOcr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

/**
 * POST /api/v1/invoices/ocr — transient server-side receipt OCR. The HTTP
 * contract (auth, size/type gates, no-text, unavailable, no-store) is covered
 * here with the OCR engine mocked; the actual tesseract/poppler integration is
 * verified at deploy (the binaries are not present in CI), exactly like the
 * gallery ML endpoints.
 */
class InvoiceOcrTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('phone', ['device'])->plainTextToken;
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$this->token($user)];
    }

    /** Bind a ReceiptOcr double with the given availability + extract result. */
    private function fakeOcr(bool $available, array $result = ['text' => 'Gesamt 45,90', 'source' => 'ocr', 'pages' => 1]): void
    {
        $mock = Mockery::mock(ReceiptOcr::class);
        $mock->shouldReceive('available')->andReturn($available);
        $mock->shouldReceive('extract')->andReturn($result);
        $this->app->instance(ReceiptOcr::class, $mock);
    }

    public function test_requires_a_device_token(): void
    {
        $this->postJson('/api/v1/invoices/ocr')->assertUnauthorized();
    }

    public function test_returns_line_structured_text(): void
    {
        $this->fakeOcr(true, ['text' => "Kaufland\nGesamt 45,90 EUR", 'source' => 'ocr', 'pages' => 1]);
        $user = User::factory()->create();

        $response = $this->post('/api/v1/invoices/ocr', [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('source', 'ocr')
            ->assertJsonPath('pages', 1)
            ->assertJson(fn ($json) => $json->where('text', "Kaufland\nGesamt 45,90 EUR")->etc());

        // Symfony reorders Cache-Control directives; assert the key one is present.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_empty_text_is_422_no_text(): void
    {
        $this->fakeOcr(true, ['text' => '   ', 'source' => 'ocr', 'pages' => 1]);
        $user = User::factory()->create();

        $this->post('/api/v1/invoices/ocr', [
            'file' => UploadedFile::fake()->image('blank.png'),
        ], $this->bearer($user))
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_text');
    }

    public function test_missing_toolchain_is_501(): void
    {
        $this->fakeOcr(false);
        $user = User::factory()->create();

        $this->post('/api/v1/invoices/ocr', [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ], $this->bearer($user))->assertStatus(501);
    }

    public function test_unsupported_type_is_415(): void
    {
        $this->fakeOcr(true);
        $user = User::factory()->create();

        $this->post('/api/v1/invoices/ocr', [
            'file' => UploadedFile::fake()->createWithContent('note.txt', 'just some text'),
        ], $this->bearer($user))->assertStatus(415);
    }

    public function test_oversize_is_413(): void
    {
        $this->fakeOcr(true);
        $user = User::factory()->create();

        // 26 MiB > the 25 MiB cap.
        $this->post('/api/v1/invoices/ocr', [
            'file' => UploadedFile::fake()->create('big.pdf', 26 * 1024, 'application/pdf'),
        ], $this->bearer($user))->assertStatus(413);
    }

    public function test_blocked_when_finance_module_disabled(): void
    {
        $this->fakeOcr(true);
        $user = User::factory()->create(['modules' => ['notes']]);

        $this->post('/api/v1/invoices/ocr', [
            'file' => UploadedFile::fake()->image('receipt.jpg'),
        ], $this->bearer($user))->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
