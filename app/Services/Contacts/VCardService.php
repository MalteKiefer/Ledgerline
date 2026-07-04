<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use Sabre\VObject\Reader;
use Throwable;

/**
 * Parses vCards into the denormalised columns the contacts UI/search use. The
 * raw vCard remains the source of truth; this only mirrors a few fields.
 */
class VCardService
{
    /**
     * @return array{fn: ?string, first_name: ?string, last_name: ?string, org: ?string, emails: list<string>, phones: list<string>, has_photo: bool}
     */
    public function denormalize(string $vcard): array
    {
        try {
            $card = Reader::read($vcard, Reader::OPTION_FORGIVING);
        } catch (Throwable) {
            return $this->empty();
        }

        $emails = [];
        foreach ($card->EMAIL ?? [] as $email) {
            $value = trim((string) $email);
            if ($value !== '') {
                $emails[] = $value;
            }
        }

        $phones = [];
        foreach ($card->TEL ?? [] as $tel) {
            $value = trim((string) $tel);
            if ($value !== '') {
                $phones[] = $value;
            }
        }

        $n = isset($card->N) ? $card->N->getParts() : [];

        return [
            'fn' => $this->str($card->FN ?? null),
            'last_name' => $this->clean($n[0] ?? null),
            'first_name' => $this->clean($n[1] ?? null),
            'org' => $this->str($card->ORG ?? null),
            'emails' => $emails,
            'phones' => $phones,
            'has_photo' => isset($card->PHOTO),
        ];
    }

    /**
     * @return array{fn: null, first_name: null, last_name: null, org: null, emails: array<int, string>, phones: array<int, string>, has_photo: false}
     */
    private function empty(): array
    {
        return ['fn' => null, 'first_name' => null, 'last_name' => null, 'org' => null, 'emails' => [], 'phones' => [], 'has_photo' => false];
    }

    private function str(mixed $value): ?string
    {
        $value = $value === null ? '' : trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function clean(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value !== '' ? $value : null;
    }
}
