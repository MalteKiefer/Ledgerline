<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Providers\FortifyServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly self-registered user. Self-registration must be
     * enabled workspace-wide (admins create users otherwise); a new user is always
     * a plain 'user' — the privileged 'role' is never taken from request input.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (! FortifyServiceProvider::registrationOpen()) {
            throw new HttpException(403, 'Registration is disabled.');
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        // 'role' is NOT in $fillable; a self-registered user is always 'user'.
        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'], // hashed by the model's 'password' => 'hashed' cast
        ]);
    }
}
