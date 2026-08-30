<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared\Workflow;

use App\Modules\Finance\Domain\Shared\Workflow\Exception\InvalidTransition;
use InvalidArgumentException;

final readonly class StateMachine
{
    /** @var array<string, array<string, Transition>> */
    private array $transitions;

    /**
     * @param  array<mixed>  $transitions
     */
    public function __construct(array $transitions)
    {
        $this->transitions = self::normalizeTransitions($transitions);
    }

    public function assertCan(string $from, string $to): void
    {
        if (! $this->can($from, $to)) {
            throw new InvalidTransition($from, $to);
        }
    }

    public function can(string $from, string $to): bool
    {
        return isset($this->transitions[$from][$to]);
    }

    /**
     * @param  array<mixed>  $transitions
     * @return array<string, array<string, Transition>>
     */
    private static function normalizeTransitions(array $transitions): array
    {
        $normalized = [];

        foreach ($transitions as $from => $targets) {
            if (! is_string($from) || $from === '') {
                throw new InvalidArgumentException('Transition source states must be non-empty strings.');
            }

            if (! is_array($targets)) {
                throw new InvalidArgumentException('Transition targets must be arrays of state names.');
            }

            $normalized[$from] = [];

            foreach ($targets as $to) {
                if (! is_string($to) || $to === '') {
                    throw new InvalidArgumentException('Transition target states must be non-empty strings.');
                }

                $normalized[$from][$to] = new Transition($from, $to);
            }
        }

        return $normalized;
    }
}
