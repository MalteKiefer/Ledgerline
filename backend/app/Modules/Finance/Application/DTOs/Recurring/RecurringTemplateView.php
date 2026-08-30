<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTOs\Recurring;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecurringTemplateView
{
    public function __construct(
        public RecurringTemplateId $id,
        public string $uuid,
        public string $mode,
        public string $interval,
        public string $timezone,
        public string $startDate,
        public ?string $endDate,
        public string $runTime,
        public int $anchorDay,
        public bool $monthEndAnchor,
        public DateTimeImmutable $nextRunAt,
        public string $status,
        public ?DateTimeImmutable $pausedAt,
        public int $currentVersionId,
        public int $currentVersionNumber,
        public string $currentEffectiveFrom,
        public string $currentSnapshotSha256,
        public int $version,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'uuid' => $this->uuid,
            'mode' => $this->mode,
            'interval' => $this->interval,
            'timezone' => $this->timezone,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'run_time' => $this->runTime,
            'anchor_day' => $this->anchorDay,
            'month_end_anchor' => $this->monthEndAnchor,
            'next_run_at' => $this->nextRunAt->format('Y-m-d\TH:i:s.uP'),
            'status' => $this->status,
            'paused_at' => $this->pausedAt?->format('Y-m-d\TH:i:s.uP'),
            'current_version_id' => $this->currentVersionId,
            'current_version_number' => $this->currentVersionNumber,
            'current_effective_from' => $this->currentEffectiveFrom,
            'current_snapshot_sha256' => $this->currentSnapshotSha256,
            'version' => $this->version,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            new RecurringTemplateId(self::integer($payload, 'id')),
            self::string($payload, 'uuid'),
            self::string($payload, 'mode'),
            self::string($payload, 'interval'),
            self::string($payload, 'timezone'),
            self::string($payload, 'start_date'),
            self::nullableString($payload, 'end_date'),
            self::string($payload, 'run_time'),
            self::integer($payload, 'anchor_day'),
            self::boolean($payload, 'month_end_anchor'),
            new DateTimeImmutable(self::string($payload, 'next_run_at')),
            self::string($payload, 'status'),
            self::nullableDateTime($payload, 'paused_at'),
            self::integer($payload, 'current_version_id'),
            self::integer($payload, 'current_version_number'),
            self::string($payload, 'current_effective_from'),
            self::string($payload, 'current_snapshot_sha256'),
            self::integer($payload, 'version'),
        );
    }

    /** @param array<string, mixed> $payload */
    private static function integer(array $payload, string $key): int
    {
        $value = $payload[$key] ?? null;
        if (! is_int($value)) {
            throw new InvalidArgumentException('Recurring template replay payload is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException('Recurring template replay payload is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException('Recurring template replay payload is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function boolean(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;
        if (! is_bool($value)) {
            throw new InvalidArgumentException('Recurring template replay payload is invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nullableDateTime(array $payload, string $key): ?DateTimeImmutable
    {
        $value = self::nullableString($payload, $key);

        return $value === null ? null : new DateTimeImmutable($value);
    }
}
