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

    public function test_addresses_related_custom_fields_and_favorite_round_trip(): void
    {
        $svc = new VCardService;
        $vcard = $svc->build([
            'fn' => 'Jane Doe',
            'addresses' => [[
                'type' => 'home', 'ext' => 'Apt 4', 'street' => 'Main St 1',
                'city' => 'Berlin', 'region' => 'BE', 'zip' => '10115', 'country' => 'Germany',
            ]],
            'related' => [
                ['type' => 'spouse', 'uid' => 'partner-uid-1'],
                ['type' => 'friend', 'value' => 'Max Mustermann'],
            ],
            'custom_fields' => [['label' => 'Insurance no.', 'value' => 'XY-123']],
            'favorite' => true,
        ]);

        $parsed = $svc->parse($vcard);

        $this->assertCount(1, $parsed['addresses']);
        $this->assertSame('Main St 1', $parsed['addresses'][0]['street']);
        $this->assertSame('Apt 4', $parsed['addresses'][0]['ext']);
        $this->assertSame('Berlin', $parsed['addresses'][0]['city']);
        $this->assertSame('BE', $parsed['addresses'][0]['region']);
        $this->assertSame('10115', $parsed['addresses'][0]['zip']);
        $this->assertSame('Germany', $parsed['addresses'][0]['country']);
        $this->assertSame('home', strtolower((string) $parsed['addresses'][0]['type']));

        $this->assertCount(2, $parsed['related']);
        $this->assertSame('partner-uid-1', $parsed['related'][0]['uid']);
        $this->assertNull($parsed['related'][0]['value']);
        $this->assertSame('Max Mustermann', $parsed['related'][1]['value']);
        $this->assertNull($parsed['related'][1]['uid']);

        $this->assertSame([['label' => 'Insurance no.', 'value' => 'XY-123']], $parsed['custom_fields']);
        $this->assertTrue($parsed['favorite']);
        $this->assertTrue($svc->denormalize($vcard)['favorite']);

        // Absent favorite parses (and denormalises) to false.
        $plain = $svc->build(['fn' => 'X']);
        $this->assertFalse($svc->parse($plain)['favorite']);
        $this->assertFalse($svc->denormalize($plain)['favorite']);
    }

    public function test_packed_street_component_is_split_into_structured_fields(): void
    {
        // Apple/Google exports pack the whole address into the street part.
        $svc = new VCardService;
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Peter\r\n"
            ."ADR;TYPE=HOME,pref:;;Pechgraben 57\\nNeudrossenfeld\\n95512\\nDeutschland;;;;\r\n"
            ."END:VCARD\r\n";

        $a = $svc->parse($vcard)['addresses'][0];
        $this->assertSame('Pechgraben 57', $a['street']);
        $this->assertSame('Neudrossenfeld', $a['city']);
        $this->assertSame('95512', $a['zip']);
        $this->assertSame('Deutschland', $a['country']);

        // "Zip City" on one line also splits.
        $vcard2 = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:X\r\n"
            ."ADR:;;Main St 1\\n10115 Berlin\\nGermany;;;;\r\nEND:VCARD\r\n";
        $b = $svc->parse($vcard2)['addresses'][0];
        $this->assertSame('Main St 1', $b['street']);
        $this->assertSame('10115', $b['zip']);
        $this->assertSame('Berlin', $b['city']);
        $this->assertSame('Germany', $b['country']);

        // Properly structured ADR stays untouched.
        $vcard3 = "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Y\r\n"
            ."ADR:;;Some St 2;Town;;12345;US;\r\nEND:VCARD\r\n";
        $c = $svc->parse($vcard3)['addresses'][0];
        $this->assertSame('Some St 2', $c['street']);
        $this->assertSame('Town', $c['city']);
        $this->assertSame('12345', $c['zip']);
    }

    public function test_custom_fields_and_anniversaries_share_the_item_group_counter(): void
    {
        $svc = new VCardService;
        $vcard = $svc->build([
            'fn' => 'X',
            'anniversaries' => [['date' => '2020-01-02', 'label' => 'Wedding']],
            'custom_fields' => [['label' => 'Nick', 'value' => 'Zed']],
        ]);

        // Both grouped properties must survive one another (distinct itemN groups).
        $parsed = $svc->parse($vcard);
        $this->assertSame('2020-01-02', $parsed['anniversaries'][0]['date']);
        $this->assertSame([['label' => 'Nick', 'value' => 'Zed']], $parsed['custom_fields']);
    }

    public function test_urls_round_trip_with_type_labels(): void
    {
        $svc = new VCardService;
        $vcard = $svc->build([
            'fn' => 'X',
            'urls' => [
                ['value' => 'https://example.com', 'type' => 'work'],
                ['value' => 'https://blog.example.com', 'type' => 'home'],
            ],
        ]);

        $urls = $svc->parse($vcard)['urls'];
        $this->assertSame('https://example.com', $urls[0]['value']);
        $this->assertSame('work', strtolower((string) $urls[0]['type']));
        $this->assertSame('home', strtolower((string) $urls[1]['type']));
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
