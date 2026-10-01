<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

use App\Domain\EarningLine\Events\EarningLineCalculated;
use App\Domain\EarningLine\Events\EarningLineRecalculated;
use App\Domain\EarningLine\Events\ManualAdjustmentAdded;
use App\Domain\EarningLine\Events\SystemRecalculationIgnored;
use App\Domain\EarningLine\Exceptions\InvalidManualAdjustment;
use App\Domain\Shared\AggregateRoot;
use App\Domain\Shared\DomainEvent;
use DateTimeImmutable;

final class EarningLine extends AggregateRoot
{
    private EarningLineId $id;

    private Money $systemValue;

    private array $adjustments = [];

    public static function calculate(EarningLineId $id, string $employeeId, Money $amount, DateTimeImmutable $at): self
    {
        $line = new self;
        $line->recordThat(new EarningLineCalculated($id, $employeeId, $amount, $at));

        return $line;
    }

    public function recalculate(Money $amount, DateTimeImmutable $at): void
    {
        if ($this->hasManualAdjustments()) {
            $this->recordThat(new SystemRecalculationIgnored($this->id, $amount, $at));

            return;
        }

        if ($amount->equals($this->systemValue)) {
            return;
        }

        $this->recordThat(new EarningLineRecalculated($this->id, $this->systemValue, $amount, $at));
    }

    public function addManualAdjustment(Money $amount, AdjustmentComment $comment, string $authorId, DateTimeImmutable $at): ManualAdjustment
    {
        if ($amount->isZero()) {
            throw InvalidManualAdjustment::zeroAmount();
        }

        if (trim($authorId) === '') {
            throw InvalidManualAdjustment::missingAuthor();
        }

        $event = new ManualAdjustmentAdded($this->id, count($this->adjustments) + 1, $amount, $comment, $authorId, $at);
        $this->recordThat($event);

        return $event->toAdjustment();
    }

    public function id(): EarningLineId
    {
        return $this->id;
    }

    public function systemValue(): Money
    {
        return $this->systemValue;
    }

    public function currentValue(): Money
    {
        return array_reduce(
            $this->adjustments,
            static fn (Money $total, ManualAdjustment $adjustment): Money => $total->add($adjustment->amount),
            $this->systemValue,
        );
    }

    public function hasManualAdjustments(): bool
    {
        return $this->adjustments !== [];
    }

    public function adjustments(): array
    {
        return $this->adjustments;
    }

    protected function apply(DomainEvent $event): void
    {
        match (true) {
            $event instanceof EarningLineCalculated => $this->applyCalculated($event),
            $event instanceof EarningLineRecalculated => $this->systemValue = $event->newAmount,
            $event instanceof ManualAdjustmentAdded => $this->adjustments[] = $event->toAdjustment(),
            $event instanceof SystemRecalculationIgnored => null,
        };
    }

    private function applyCalculated(EarningLineCalculated $event): void
    {
        $this->id = $event->lineId;
        $this->systemValue = $event->amount;
    }
}
