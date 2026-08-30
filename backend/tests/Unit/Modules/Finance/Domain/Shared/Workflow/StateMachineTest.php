<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance\Domain\Shared\Workflow;

use App\Modules\Finance\Domain\Shared\Workflow\Exception\InvalidTransition;
use App\Modules\Finance\Domain\Shared\Workflow\StateMachine;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StateMachineTest extends TestCase
{
    public function test_it_allows_only_configured_transitions(): void
    {
        $stateMachine = new StateMachine([
            'draft' => ['sent'],
            'sent' => ['accepted', 'declined'],
            'accepted' => ['converted'],
        ]);

        $this->assertTrue($stateMachine->can('draft', 'sent'));
        $this->assertTrue($stateMachine->can('sent', 'accepted'));
        $this->assertTrue($stateMachine->can('sent', 'declined'));
        $this->assertTrue($stateMachine->can('accepted', 'converted'));

        $stateMachine->assertCan('draft', 'sent');
        $stateMachine->assertCan('sent', 'accepted');
        $stateMachine->assertCan('sent', 'declined');
        $stateMachine->assertCan('accepted', 'converted');
    }

    public function test_it_rejects_reverse_self_and_unknown_state_transitions(): void
    {
        $stateMachine = new StateMachine([
            'draft' => ['sent'],
            'sent' => ['accepted', 'declined'],
            'accepted' => ['converted'],
        ]);

        foreach ([
            ['sent', 'draft'],
            ['draft', 'draft'],
            ['unknown', 'sent'],
            ['draft', 'unknown'],
        ] as [$from, $to]) {
            $this->assertFalse($stateMachine->can($from, $to));
        }
    }

    public function test_it_exposes_rejected_transition_states_on_a_stable_exception(): void
    {
        $stateMachine = new StateMachine([
            'draft' => ['sent'],
            'sent' => ['accepted', 'declined'],
            'accepted' => ['converted'],
        ]);

        try {
            $stateMachine->assertCan('sent', 'draft');
            $this->fail('The reverse transition must be rejected.');
        } catch (InvalidTransition $exception) {
            $this->assertSame('sent', $exception->from);
            $this->assertSame('draft', $exception->to);
            $this->assertSame('invalid_transition', $exception->getCode());
        }
    }

    /** @param array<mixed> $transitions */
    #[DataProvider('malformedTransitionMaps')]
    public function test_it_rejects_malformed_transition_maps(array $transitions): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StateMachine($transitions);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function malformedTransitionMaps(): iterable
    {
        yield 'numeric source state' => [[0 => ['sent']]];
        yield 'non-array targets' => [['draft' => 'sent']];
        yield 'empty target state' => [['draft' => ['']]];
        yield 'numeric target state' => [['draft' => [0]]];
    }
}
