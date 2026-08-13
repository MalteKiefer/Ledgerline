<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Ops\ErrorRecorder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One deduplicated unhandled-exception signature and how often it has recurred.
 * Written by {@see ErrorRecorder}; shown on the System page.
 */
#[Fillable([
    'fingerprint', 'level', 'exception', 'message', 'file', 'line',
    'context', 'trace', 'count', 'first_seen_at', 'last_seen_at', 'resolved_at', 'alerted_at',
])]
class ErrorEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'count' => 'integer',
            'line' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'alerted_at' => 'datetime',
        ];
    }
}
