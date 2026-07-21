<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The authenticated user, or a 401 if there somehow is none.
     *
     * Every caller sits behind auth/auth:sanctum middleware, so this only ever
     * returns the user at runtime; the guard is defence-in-depth and gives the
     * type-checker a non-null App\Models\User instead of User|null.
     */
    protected function requireUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
