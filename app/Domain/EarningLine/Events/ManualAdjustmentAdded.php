<?php

declare(strict_types=1);

namespace App\Domain\EarningLine\Events;

use App\Domain\EarningLine\AdjustmentComment;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\ManualAdjustment;
use App\Domain\EarningLine\Money;
use App\Domain\Shared\DomainEvent;
use DateTimeImmutable;

/** A payroll specialist added a manual correction to the line. */
final readonly class ManualAdjustmentAdded implements DomainEvent
{
    public function __construct(
        public EarningLineId $lineId,
        public int $adjustmentNumber,
        public Money $amount,
        public AdjustmentComment $comment,
        public string $authorId,
        public DateTimeImmutable $addedAt,
    ) {}

    public static function eventType(): string
    {
        return 'earning_line.manual_adjustment_added';
    }

    public function aggregateId(): string
    {
        return $this->lineId->value;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function toAdjustment(): ManualAdjustment
    {
        return new ManualAdjustment($this->adjustmentNumber, $this->amount, $this->comment, $this->authorId, $this->addedAt);
    }

    public function toPayload(): array
    {
        return [
            'line_id' => $this->lineId->value,
            'adjustment_number' => $this->adjustmentNumber,
            'amount_cents' => $this->amount->cents,
            'comment' => $this->comment->value,
            'author_id' => $this->authorId,
            'added_at' => $this->addedAt->format(self::DATE_FORMAT),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            EarningLineId::fromString($payload['line_id']),
            $payload['adjustment_number'],
            Money::fromCents($payload['amount_cents']),
            AdjustmentComment::fromString($payload['comment']),
            $payload['author_id'],
            new DateTimeImmutable($payload['added_at']),
        );
    }
}
