<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Events;

use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Money;
use App\Domain\Shared\DomainEvent;
use DateTimeImmutable;

final readonly class EarningLineCalculated implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public string $employeeId,
        public Money $amount,
        public DateTimeImmutable $calculatedAt,
    ) {}

    public static function eventType(): string
    {
        return 'earning_line.calculated';
    }

    public function aggregateId(): string
    {
        return $this->lineId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function toPayload(): array
    {
        return [
            'line_id' => $this->lineId->value,
            'employee_id' => $this->employeeId,
            'amount_cents' => $this->amount->cents,
            'calculated_at' => $this->calculatedAt->format(self::DATE_FORMAT),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString($payload['line_id']),
            $payload['employee_id'],
            Money::fromCents($payload['amount_cents']),
            new DateTimeImmutable($payload['calculated_at']),
        );
    }
}
