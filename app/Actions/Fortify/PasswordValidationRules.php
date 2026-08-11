<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\AppSettings;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Password rules driven by the admin password policy (app_settings):
     * min length (default 12), optional mixed-case / numbers / symbols, and an
     * optional breach check (HIBP k-anonymity, opt-in). Applied to registration,
     * admin-set passwords and reset.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', self::passwordRule(), 'confirmed'];
    }

    /** The configured Password rule (single source of truth for every call site). */
    public static function passwordRule(): Password
    {
        $s = AppSettings::current();
        $min = is_int($s->pw_min_length) && $s->pw_min_length >= 8 ? $s->pw_min_length : 12;
        $rule = Password::min($min);
        if ($s->pw_require_mixed_case) {
            $rule->mixedCase();
        }
        if ($s->pw_require_numbers) {
            $rule->numbers();
        }
        if ($s->pw_require_symbols) {
            $rule->symbols();
        }
        if ($s->pw_check_breaches) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
