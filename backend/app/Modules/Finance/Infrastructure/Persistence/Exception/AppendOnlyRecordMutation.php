<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Exception;

use RuntimeException;

final class AppendOnlyRecordMutation extends RuntimeException
{
    private function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    public static function projectNote(): self
    {
        return new self('project_note', 'Project notes are append-only.');
    }

    public static function projectActivity(): self
    {
        return new self('project_activity', 'Project activities are append-only.');
    }

    public static function projectDocumentLink(): self
    {
        return new self('project_document_link', 'Project document links are immutable except for one-way detach.');
    }
}
