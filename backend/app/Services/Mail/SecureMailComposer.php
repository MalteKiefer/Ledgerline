<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\CryptoRecipient;
use App\Models\MailPgpKey;
use App\Support\Crypto\FileCipher;
use App\Support\Mail\PgpEncryptedPart;
use App\Support\Mail\PgpSignedPart;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\SMimeEncrypter;
use Symfony\Component\Mime\Crypto\SMimeSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractPart;

/**
 * Builds RFC 3156 OpenPGP/MIME and S/MIME wire messages without letting secret
 * key material leave the server. It intentionally has no plaintext fallback:
 * a missing key, expired material or crypto failure aborts the send.
 */
final class SecureMailComposer
{
    public function __construct(private readonly FileCipher $cipher) {}

    /**
     * @param  list<CryptoRecipient>  $recipients
     */
    public function compose(ComposedMessage $composed, MailPgpKey $ownKey, array $recipients): Message
    {
        if (! in_array($composed->cryptoMode, ['sign', 'encrypt', 'sign_encrypt'], true)
            || ! in_array($composed->cryptoType, MailPgpKey::TYPES, true)
            || $ownKey->type !== $composed->cryptoType) {
            throw new RuntimeException('mail crypto configuration is invalid');
        }
        if ($ownKey->expires_at !== null && $ownKey->expires_at->isPast()) {
            throw new RuntimeException('mail crypto signing key has expired');
        }
        if (in_array($composed->cryptoMode, ['encrypt', 'sign_encrypt'], true) && $recipients === []) {
            throw new RuntimeException('mail encryption requires recipient keys');
        }

        $email = $this->email($composed);
        // Bcc belongs exclusively to the SMTP envelope, never to a signed or
        // encrypted payload (otherwise recipients could see one another).
        $email->getHeaders()->remove('Bcc');

        $message = $email;
        if (in_array($composed->cryptoMode, ['sign', 'sign_encrypt'], true)) {
            $message = $composed->cryptoType === 'pgp'
                ? $this->signPgp($message, $ownKey)
                : $this->signSmime($message, $ownKey);
        }
        if (in_array($composed->cryptoMode, ['encrypt', 'sign_encrypt'], true)) {
            $message = $composed->cryptoType === 'pgp'
                ? $this->encryptPgp($message, $ownKey, $recipients)
                : $this->encryptSmime($message, $ownKey, $recipients);
        }

        // Envelope cannot be encoded into Message. MailSender gets it through
        // this private header-free object by recreating it from the original
        // recipient lists before the call to transport->send().
        return $message;
    }

    private function email(ComposedMessage $composed): Email
    {
        $email = (new Email)
            ->from(new Address($composed->fromEmail, $composed->fromName ?? ''))
            ->subject($composed->subject);
        foreach ($composed->to as $address) {
            $email->addTo(new Address($address['email'], $address['name'] ?? ''));
        }
        foreach ($composed->cc as $address) {
            $email->addCc(new Address($address['email'], $address['name'] ?? ''));
        }
        foreach ($composed->bcc as $address) {
            $email->addBcc(new Address($address['email'], $address['name'] ?? ''));
        }
        if ($composed->text !== null) {
            $email->text($composed->text, 'utf-8');
        }
        if ($composed->html !== null && $composed->html !== '') {
            $email->html($composed->html, 'utf-8');
        }
        if ($composed->messageId !== null) {
            $email->getHeaders()->addIdHeader('Message-ID', $composed->messageId);
        }
        if ($composed->inReplyTo !== null && $composed->inReplyTo !== '') {
            $email->getHeaders()->addTextHeader('In-Reply-To', $composed->inReplyTo);
        }
        if ($composed->references !== []) {
            $email->getHeaders()->addTextHeader('References', implode(' ', $composed->references));
        }
        if ($composed->readReceipt) {
            $email->getHeaders()->addTextHeader('Disposition-Notification-To', $composed->fromEmail);
        }
        if ($composed->highPriority) {
            $email->getHeaders()->addTextHeader('X-Priority', '1 (Highest)');
            $email->getHeaders()->addTextHeader('Importance', 'high');
        }
        foreach ($composed->attachments as $attachment) {
            $email->attach($attachment['bytes'], $attachment['filename'], $attachment['mime']);
        }

        return $email;
    }

    private function signPgp(Message $message, MailPgpKey $key): Message
    {
        $body = $this->body($message);
        $signature = $this->cipher->signPgpMime($body->toString(), (string) $key->private_key, $key->passphrase);
        if ($signature === null) {
            throw new RuntimeException('mail PGP signing failed');
        }

        return new Message($message->getHeaders(), new PgpSignedPart($body, $signature));
    }

    /** @param list<CryptoRecipient> $recipients */
    private function encryptPgp(Message $message, MailPgpKey $ownKey, array $recipients): Message
    {
        $keys = [(string) $ownKey->public_key];
        foreach ($recipients as $recipient) {
            $keys[] = (string) $recipient->public_key;
        }
        $payload = $this->cipher->encryptPgpMime($this->body($message)->toString(), $keys);
        if ($payload === null) {
            throw new RuntimeException('mail PGP encryption failed');
        }

        return new Message($message->getHeaders(), new PgpEncryptedPart($payload));
    }

    private function signSmime(Message $message, MailPgpKey $key): Message
    {
        return $this->withSmimeFiles($key, static fn (string $cert, string $private): Message => (new SMimeSigner($cert, $private, $key->passphrase))->sign($message));
    }

    /** @param list<CryptoRecipient> $recipients */
    private function encryptSmime(Message $message, MailPgpKey $ownKey, array $recipients): Message
    {
        return $this->withSmimeFiles($ownKey, function (string $cert, string $_private) use ($message, $recipients): Message {
            $dir = dirname($cert);
            $certificatePaths = [$cert];
            foreach ($recipients as $index => $recipient) {
                $path = $dir.'/recipient-'.$index.'.pem';
                file_put_contents($path, (string) $recipient->cert_pem);
                @chmod($path, 0600);
                $certificatePaths[] = $path;
            }

            return (new SMimeEncrypter($certificatePaths))->encrypt($message);
        });
    }

    /** @param callable(string,string):Message $callback */
    private function withSmimeFiles(MailPgpKey $key, callable $callback): Message
    {
        if (! $this->cipher->smimeAvailable() || trim((string) $key->cert_pem) === '' || trim((string) $key->private_key) === '') {
            throw new RuntimeException('mail S/MIME is unavailable');
        }
        $dir = sys_get_temp_dir().'/ll-mail-smime-'.bin2hex(random_bytes(12));
        if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('mail S/MIME temporary directory failed');
        }
        try {
            $cert = $dir.'/certificate.pem';
            $private = $dir.'/private.pem';
            file_put_contents($cert, (string) $key->cert_pem);
            file_put_contents($private, (string) $key->private_key);
            @chmod($cert, 0600);
            @chmod($private, 0600);

            return $callback($cert, $private);
        } finally {
            foreach (scandir($dir) ?: [] as $file) {
                if ($file !== '.' && $file !== '..') {
                    @unlink($dir.'/'.$file);
                }
            }
            @rmdir($dir);
        }
    }

    private function body(Message $message): AbstractPart
    {
        $body = $message->getBody();
        if ($body === null) {
            throw new RuntimeException('mail crypto message body is missing');
        }

        return $body;
    }
}
