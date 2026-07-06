<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The inline <head> script that applies the dark class before first paint
 * (server-side rendering only knows the stored setting, not the OS scheme for
 * "system"). Kept here as a single constant so the CSP hash in
 * SecurityHeaders can never drift from what the layout emits.
 */
class ThemeBootstrap
{
    public const SCRIPT = "if(document.documentElement.dataset.theme==='dark'||(document.documentElement.dataset.theme==='system'&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');";

    /** CSP source expression allowing exactly this script. */
    public static function cspHash(): string
    {
        return "'sha256-".base64_encode(hash('sha256', self::SCRIPT, true))."'";
    }
}
