<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Contacts\VCardService;
use PHPUnit\Framework\TestCase;

class VCardServiceTest extends TestCase
{
    public function test_build_then_parse_round_trips_the_fields(): void
    {
        $svc = new VCardService;
        $vcard = $svc->build([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'fn' => 'Jane Doe',
            'org' => 'Acme', 'title' => 'CTO',
            'emails' => [['value' => 'jane@example.com', 'type' => 'work']],
            'phones' => [['value' => '+49 111', 'type' => 'cell']],
            'categories' => ['Friends', 'Work'],
        ]);

        $this->assertStringContainsString('VERSION:4.0', $vcard);

        $parsed = $svc->parse($vcard);
        $this->assertSame('Jane Doe', $parsed['fn']);
        $this->assertSame('Jane', $parsed['first_name']);
        $this->assertSame('Doe', $parsed['last_name']);
        $this->assertSame('Acme', $parsed['org']);
        $this->assertSame('jane@example.com', $parsed['emails'][0]['value']);
        $this->assertSame('+49 111', $parsed['phones'][0]['value']);
        $this->assertContains('Friends', $parsed['categories']);
        $this->assertNotEmpty($parsed['uid']);
    }

    public function test_build_reuses_uid_on_update(): void
    {
        $svc = new VCardService;
        $vcard = $svc->build(['fn' => 'X'], 'fixed-uid-123');

        $this->assertStringContainsString('UID:fixed-uid-123', $vcard);
        $this->assertSame('fixed-uid-123', $svc->parse($vcard)['uid']);
    }

    public function test_denormalize_extracts_list_fields(): void
    {
        $svc = new VCardService;
        $d = $svc->denormalize("BEGIN:VCARD\r\nVERSION:4.0\r\nFN:A B\r\nEMAIL:a@b.c\r\nTEL:123\r\nEND:VCARD\r\n");

        $this->assertSame('A B', $d['fn']);
        $this->assertContains('a@b.c', $d['emails']);
        $this->assertContains('123', $d['phones']);
        $this->assertFalse($d['has_photo']);
    }
}
