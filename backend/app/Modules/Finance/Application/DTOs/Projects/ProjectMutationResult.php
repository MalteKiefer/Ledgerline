<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Projects;

final readonly class ProjectMutationResult
{
    private function __construct(
        public bool $applied,
        public ProjectView $current,
    ) {}

    public static function applied(ProjectView $current): self
    {
        return new self(true, $current);
    }

    public static function conflict(ProjectView $current): self
    {
        return new self(false, $current);
    }
}
