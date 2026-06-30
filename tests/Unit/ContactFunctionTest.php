<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ContactFunction;
use PHPUnit\Framework\TestCase;

class ContactFunctionTest extends TestCase
{
    public function test_every_case_has_a_non_empty_label(): void
    {
        foreach (ContactFunction::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_it_exposes_all_cases_as_options(): void
    {
        $options = ContactFunction::options();

        $this->assertCount(count(ContactFunction::cases()), $options);
        $this->assertSame(
            ['value' => 'TECHNICAL_CONTACT', 'label' => 'Technical Contact'],
            collect($options)->firstWhere('value', 'TECHNICAL_CONTACT'),
        );
    }

    public function test_backing_values_match_case_names(): void
    {
        foreach (ContactFunction::cases() as $case) {
            $this->assertSame($case->name, $case->value);
        }
    }
}
