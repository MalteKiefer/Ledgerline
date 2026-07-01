<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProjectPriority;
use App\Enums\ProjectType;
use Tests\TestCase;

class ProjectTypeTest extends TestCase
{
    public function test_types_have_labels_and_options(): void
    {
        foreach (ProjectType::cases() as $case) {
            $this->assertNotSame('', $case->label());
            $this->assertSame($case->name, $case->value);
        }

        $this->assertCount(count(ProjectType::cases()), ProjectType::options());
    }

    public function test_priorities_have_labels_and_options(): void
    {
        foreach (ProjectPriority::cases() as $case) {
            $this->assertNotSame('', $case->label());
            $this->assertSame($case->name, $case->value);
        }

        $this->assertCount(count(ProjectPriority::cases()), ProjectPriority::options());
    }
}
