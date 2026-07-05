<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Mail\MailArchiveReader;
use App\Services\Mail\MimeHeader;
use PHPUnit\Framework\TestCase;

class MimeHeaderDecodeTest extends TestCase
{
    public function test_decodes_a_multi_word_encoded_word_subject(): void
    {
        $raw = '=?UTF-8?Q?Deutschland_ist_raus._Daf=C3=BCr_wartet?= '
            .'=?UTF-8?Q?_etwas_Besonderes_auf_dich,_Malte!?=';

        $this->assertSame(
            'Deutschland ist raus. Dafür wartet etwas Besonderes auf dich, Malte!',
            MimeHeader::decode($raw),
        );
    }

    public function test_decodes_a_single_q_encoded_word(): void
    {
        $this->assertSame('Dafür', MimeHeader::decode('=?UTF-8?Q?Daf=C3=BCr?='));
    }

    public function test_decodes_a_base64_encoded_word(): void
    {
        $this->assertSame('Hello World', MimeHeader::decode('=?ISO-8859-1?B?SGVsbG8gV29ybGQ=?='));
    }

    public function test_passes_plain_ascii_through_unchanged(): void
    {
        $this->assertSame('Re: Your invoice #123', MimeHeader::decode('Re: Your invoice #123'));
    }

    public function test_leaves_already_decoded_utf8_untouched(): void
    {
        // Guard against blindly running iconv_mime_decode over decoded text,
        // which would strip the multibyte "ü".
        $this->assertSame('Dafür wartet', MimeHeader::decode('Dafür wartet'));
    }

    public function test_handles_null_and_empty_input(): void
    {
        $this->assertSame('', MimeHeader::decode(null));
        $this->assertSame('', MimeHeader::decode(''));
    }

    public function test_mail_archive_reader_decodes_subject_and_from_name(): void
    {
        $raw = "From: =?UTF-8?Q?Daf=C3=BCr_Wartet?= <sender@example.com>\r\n"
            ."To: malte@example.com\r\n"
            ."Subject: =?UTF-8?Q?Deutschland_ist_raus._Daf=C3=BCr_wartet?= =?UTF-8?Q?_etwas_Besonderes_auf_dich,_Malte!?=\r\n"
            ."Date: Sun, 05 Jul 2026 10:00:00 +0000\r\n"
            ."\r\n"
            ."Body text\r\n";

        $parsed = (new MailArchiveReader)->parse($raw);

        $this->assertSame(
            'Deutschland ist raus. Dafür wartet etwas Besonderes auf dich, Malte!',
            $parsed['subject'],
        );
        $this->assertSame('Dafür Wartet', $parsed['from']['name']);
    }
}
