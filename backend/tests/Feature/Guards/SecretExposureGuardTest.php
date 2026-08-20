<?php

declare(strict_types=1);

namespace Tests\Feature\Guards;

use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * A column is given an encrypted cast because its cleartext must not leave this
 * host: SMTP and IMAP passwords, OAuth refresh tokens, the backup passphrase,
 * PGP private keys. Encryption protects it at rest; it does nothing once the
 * model is serialised into a response, and every one of these models is returned
 * by some endpoint.
 *
 * Controllers whitelist their output today, but that is a habit, not a
 * mechanism — one toArray() on a model is all it takes. $hidden is the mechanism,
 * and all nine models carry it, so this holds the line rather than describing an
 * aspiration.
 */
class SecretExposureGuardTest extends TestCase
{
    public function test_encrypted_columns_are_hidden_from_serialisation(): void
    {
        $offenders = [];
        foreach ((array) glob(app_path('Models/*.php')) as $file) {
            $class = 'App\Models\\'.basename((string) $file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $model = new $class;
            if (! $model instanceof Model) {
                continue;
            }

            $hidden = $model->getHidden();
            foreach ($model->getCasts() as $column => $cast) {
                if (! str_starts_with((string) $cast, 'encrypted')) {
                    continue;
                }
                if (! in_array($column, $hidden, true)) {
                    $offenders[] = class_basename($class).'::'.$column;
                }
            }
        }

        $this->assertSame([], $offenders,
            'Encrypted column(s) not hidden from serialisation. Add them to the #[Hidden] attribute so a plain '
            .'toArray() cannot hand the cleartext to a client.');
    }
}
