<?php

declare(strict_types=1);

namespace App\Services\Contacts;

use App\Models\AddressBook;
use App\Models\DavCredential;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Manages the external CardDAV login (Basic auth, separate from the app session)
 * and ensures the user has at least one address book. The app-password is shown
 * once at generation and only its hash is stored.
 */
class DavCredentialService
{
    public function for(int $userId): ?DavCredential
    {
        return DavCredential::where('user_id', $userId)->first();
    }

    /**
     * Create the credential (once) or rotate its password. Returns the plaintext
     * password to show the user a single time, plus the credential.
     *
     * @return array{credential: DavCredential, password: string}
     */
    public function generate(int $userId): array
    {
        $password = Str::password(28, symbols: false);
        $credential = $this->for($userId);

        if ($credential === null) {
            $credential = DavCredential::create([
                'user_id' => $userId,
                'username' => 'contacts-'.Str::lower(Str::random(8)),
                'password_hash' => Hash::make($password),
            ]);
        } else {
            $credential->forceFill(['password_hash' => Hash::make($password)])->save();
        }

        $this->ensureDefaultBook($userId);

        return ['credential' => $credential, 'password' => $password];
    }

    /** Verify DAV Basic-auth credentials; touches last_used_at on success. */
    public function verify(string $username, string $password): ?DavCredential
    {
        $credential = DavCredential::where('username', $username)->first();
        if ($credential === null || ! Hash::check($password, $credential->password_hash)) {
            return null;
        }

        $credential->forceFill(['last_used_at' => now()])->saveQuietly();

        return $credential;
    }

    public function ensureDefaultBook(int $userId): AddressBook
    {
        return AddressBook::firstOrCreate(
            ['user_id' => $userId, 'uri' => 'default'],
            ['name' => 'Contacts', 'description' => null, 'synctoken' => 1],
        );
    }
}
