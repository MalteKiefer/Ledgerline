<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Mail\SmtpSender;
use Tests\TestCase;

class SmtpSenderTest extends TestCase
{
    /** @return array<string,mixed> */
    private function cfg(): array
    {
        return [
            'host' => 'smtp.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'user@example.test',
            'password' => 'secret',
            'from_name' => 'Ada Lovelace',
            'from_email' => 'ada@example.test',
            'reply_to' => 'replies@example.test',
        ];
    }

    public function test_build_produces_expected_headers_and_body(): void
    {
        $sender = new SmtpSender;

        $email = $sender->build($this->cfg(), [
            'to' => ['bob@example.test', 'carol@example.test'],
            'cc' => ['dan@example.test'],
            'bcc' => ['eve@example.test'],
            'subject' => 'Quarterly Report',
            'html' => '<p>Hello <strong>world</strong></p>',
            'attachments' => [
                ['filename' => 'note.txt', 'content' => 'file body', 'mime' => 'text/plain'],
            ],
            'in_reply_to' => '<parent@example.test>',
            'references' => '<root@example.test> <parent@example.test>',
            'from_email' => 'ada@example.test',
        ]);

        $mime = $email->toString();

        // Subject and recipients.
        $this->assertStringContainsString('Quarterly Report', $mime);
        $this->assertStringContainsString('bob@example.test', $mime);
        $this->assertStringContainsString('carol@example.test', $mime);
        $this->assertStringContainsString('dan@example.test', $mime);

        // From with name + reply-to.
        $this->assertStringContainsString('Ada Lovelace', $mime);
        $this->assertStringContainsString('ada@example.test', $mime);
        $this->assertStringContainsString('replies@example.test', $mime);

        // Threading headers.
        $this->assertStringContainsString('In-Reply-To: <parent@example.test>', $mime);
        $this->assertStringContainsString('References: <root@example.test> <parent@example.test>', $mime);

        // Message-ID is populated by Symfony when the message is serialised/sent.
        $this->assertStringContainsString('Message-ID:', $email->toString());

        // HTML body + derived plaintext fallback.
        $this->assertStringContainsString('Hello', $mime);
        $this->assertStringContainsString('note.txt', $mime);

        // Header accessors expose structured values too.
        $this->assertSame('Quarterly Report', $email->getSubject());
        $toAddresses = array_map(fn ($a) => $a->getAddress(), $email->getTo());
        $this->assertContains('bob@example.test', $toAddresses);
    }

    public function test_build_derives_plaintext_from_html_when_no_text_given(): void
    {
        $sender = new SmtpSender;

        $email = $sender->build($this->cfg(), [
            'to' => ['bob@example.test'],
            'subject' => 'Plain fallback',
            'html' => '<p>Visible text</p>',
            'from_email' => 'ada@example.test',
        ]);

        $this->assertNotNull($email->getTextBody());
        $this->assertStringContainsString('Visible text', (string) $email->getTextBody());
    }
}
