<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Events;

use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Money;
use App\Domain\Shared\DomainEvent;
use DateTimeImmutable;

final readonly class SystemRecalculationIgnored implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public Money $attemptedAmount,
        public DateTimeImmutable $attemptedAt,
    ) {}

    public static function eventType(): string
    {
        return 'earning_line.recalculation_ignored';
    }

    public function aggregateId(): string
    {
        return $this->lineId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function toPayload(): array
    {
        return [
            'line_id' => $this->lineId->value,
            'attempted_amount_cents' => $this->attemptedAmount->cents,
            'attempted_at' => $this->attemptedAt->format(self::DATE_FORMAT),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString($payload['line_id']),
            Money::fromCents($payload['attempted_amount_cents']),
            new DateTimeImmutable($payload['attempted_at']),
        );
    }
}
