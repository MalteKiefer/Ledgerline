<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Mail\SpamHeaders;
use PHPUnit\Framework\TestCase;

class SpamHeadersTest extends TestCase
{
    public function test_detects_spam_markers(): void
    {
        $this->assertTrue(SpamHeaders::isSpamRaw("X-Spam-Flag: YES\r\nSubject: x\r\n\r\nbody"));
        $this->assertTrue(SpamHeaders::isSpamRaw("X-Spam-Status: Yes, score=9\r\n\r\nb"));
        $this->assertTrue(SpamHeaders::isSpamRaw("X-Spamd-Result: default: true [12.0]\r\n\r\nb"));
        $this->assertTrue(SpamHeaders::isSpamRaw("X-Spam: junk\r\n\r\nb"));
    }

    public function test_clean_mail_is_not_spam(): void
    {
        $this->assertFalse(SpamHeaders::isSpamRaw("Subject: hi\r\nX-Spam-Status: No, score=-1\r\n\r\nbody"));
        $this->assertFalse(SpamHeaders::isSpamRaw("From: a@b\r\n\r\nno markers"));
    }
}
