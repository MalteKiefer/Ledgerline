<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProjectStatus;
use PHPUnit\Framework\TestCase;

class ProjectStatusTest extends TestCase
{
    public function test_every_case_has_a_non_empty_label(): void
    {
        foreach (ProjectStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_it_exposes_all_cases_as_options(): void
    {
        $options = ProjectStatus::options();

        $this->assertCount(count(ProjectStatus::cases()), $options);
        $this->assertSame(
            ['value' => 'ON_HOLD', 'label' => 'On hold'],
            collect($options)->firstWhere('value', 'ON_HOLD'),
        );
    }

    public function test_backing_values_match_case_names(): void
    {
        foreach (ProjectStatus::cases() as $case) {
            $this->assertSame($case->name, $case->value);
        }
    }
}
