<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve a safe [column, direction] sort pair from the request.
     *
     * Only columns in the allow-list are honoured (guarding against SQL
     * injection via the sort parameter); anything else falls back to $default.
     *
     * @param  list<string>  $allowed
     * @return array{0: string, 1: string}
     */
    protected function sortFor(Request $request, array $allowed, string $default): array
    {
        $sort = $request->query('sort');
        $column = in_array($sort, $allowed, true) ? (string) $sort : $default;
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return [$column, $direction];
    }
}
