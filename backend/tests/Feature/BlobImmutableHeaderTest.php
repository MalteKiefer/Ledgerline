<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\BlobStore;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

final class BlobImmutableHeaderTest extends TestCase
{
    public function test_immutable_response_sets_exact_headers(): void
    {
        $base = new StreamedResponse(fn () => print ('body'), 200);
        $result = BlobStore::immutableResponse($base, 'test-etag-value');

        $this->assertSame('application/octet-stream', $result->headers->get('Content-Type'));
        $this->assertSame('nosniff', $result->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'; sandbox", $result->headers->get('Content-Security-Policy'));
        $this->assertSame('immutable, max-age=31536000, private', $result->headers->get('Cache-Control'));
        $this->assertSame('"test-etag-value"', $result->headers->get('ETag'));
    }

    public function test_immutable_response_returns_same_response_instance(): void
    {
        $base = new StreamedResponse(fn () => null, 200);
        $result = BlobStore::immutableResponse($base, 'x');
        $this->assertSame($base, $result);
    }
}
