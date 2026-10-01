<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Queries;

use App\Domain\EarningLine\Money;
use App\Models\EarningLineAdjustmentView;
use App\Models\EarningLineView;
use DateTimeImmutable;

/**
 * The line's current value together with the audit trail that produced it:
 * the (frozen) system value followed by every manual adjustment in order.
 */
final readonly class EarningLineHistory
{
    /** @param list<array{number: int, amount: Money, comment: string, author_id: string, added_at: DateTimeImmutable}> $adjustments */
    public function __construct(
        public string $lineId,
        public string $employeeId,
        public Money $systemValue,
        public bool $isLocked,
        public ?DateTimeImmutable $lockedAt,
        public array $adjustments,
        public Money $currentValue,
        public int $ignoredRecalculations,
    ) {}

    public static function fromView(EarningLineView $line): self
    {
        return new self(
            lineId: $line->id,
            employeeId: $line->employee_id,
            systemValue: Money::fromCents($line->system_value_cents),
            isLocked: $line->is_locked,
            lockedAt: $line->locked_at,
            adjustments: $line->adjustments->map(fn (EarningLineAdjustmentView $a): array => [
                'number' => $a->number,
                'amount' => Money::fromCents($a->amount_cents),
                'comment' => $a->comment,
                'author_id' => $a->author_id,
                'added_at' => $a->added_at,
            ])->all(),
            currentValue: Money::fromCents($line->current_value_cents),
            ignoredRecalculations: $line->ignored_recalculations,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->lineId,
            'employee_id' => $this->employeeId,
            'is_locked' => $this->isLocked,
            'locked_at' => $this->lockedAt?->format(DATE_RFC3339_EXTENDED),
            'system_value' => self::money($this->systemValue),
            'adjustments' => array_map(fn (array $a): array => [
                'number' => $a['number'],
                'label' => 'Adjustment '.$a['number'],
                'amount' => $a['amount']->toDecimalString(),
                'formatted' => $a['amount']->formatSigned(),
                'comment' => $a['comment'],
                'author_id' => $a['author_id'],
                'added_at' => $a['added_at']->format(DATE_RFC3339_EXTENDED),
            ], $this->adjustments),
            'current_value' => self::money($this->currentValue),
            'ignored_recalculations' => $this->ignoredRecalculations,
        ];
    }

    /** @return array{amount: string, formatted: string} */
    private static function money(Money $money): array
    {
        return ['amount' => $money->toDecimalString(), 'formatted' => $money->format()];
    }
}
