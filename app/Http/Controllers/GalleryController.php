<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The zero-knowledge photo gallery: the browser holds all keys, so the gallery
 * renders entirely client-side from the sealed index + decrypted blobs. The
 * server never sees photo bytes or metadata in the clear; this controller only
 * ships the empty client shell. Upload/process/blob/store/reconcile live in the
 * dedicated Gallery{Blob,Store,Process}Controller.
 */
class GalleryController extends Controller
{
    public function index(): View
    {
        return view('gallery.index');
    }
}
