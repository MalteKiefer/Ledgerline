<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Vault;
use Illuminate\Contracts\View\View;

/**
 * The post-login landing page: gallery summary counts and the vault status.
 *
 * File statistics are deliberately absent — the file vault is zero-knowledge,
 * so the server knows nothing about the files, not even how many exist.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'gallery' => Photo::counts(),
            'vaultConfigured' => Vault::current() !== null,
        ]);
    }
}
