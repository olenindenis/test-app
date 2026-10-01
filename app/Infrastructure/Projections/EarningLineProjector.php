<?php

declare(strict_types=1);

namespace App\Infrastructure\Projections;

use App\Domain\EarningLine\Events\EarningLineCalculated;
use App\Domain\EarningLine\Events\EarningLineRecalculated;
use App\Domain\EarningLine\Events\ManualAdjustmentAdded;
use App\Domain\EarningLine\Events\SystemRecalculationIgnored;
use App\Domain\Shared\DomainEvent;
use App\Models\EarningLineAdjustmentView;
use App\Models\EarningLineView;
use Illuminate\Events\Dispatcher;

/**
 * Keeps the earning_lines / earning_line_adjustments read models in sync with
 * the event stream. Registered as a Laravel event subscriber.
 */
final class EarningLineProjector
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            EarningLineCalculated::class => 'onCalculated',
            EarningLineRecalculated::class => 'onRecalculated',
            SystemRecalculationIgnored::class => 'onRecalculationIgnored',
            ManualAdjustmentAdded::class => 'onManualAdjustmentAdded',
        ];
    }

    /** Used when replaying the whole event store into fresh projections. */
    public function project(DomainEvent $event): void
    {
        match (true) {
            $event instanceof EarningLineCalculated => $this->onCalculated($event),
            $event instanceof EarningLineRecalculated => $this->onRecalculated($event),
            $event instanceof SystemRecalculationIgnored => $this->onRecalculationIgnored($event),
            $event instanceof ManualAdjustmentAdded => $this->onManualAdjustmentAdded($event),
            default => null,
        };
    }

    public function onCalculated(EarningLineCalculated $event): void
    {
        EarningLineView::query()->create([
            'id' => $event->lineId->value,
            'employee_id' => $event->employeeId,
            'system_value_cents' => $event->amount->cents,
            'adjustments_total_cents' => 0,
            'current_value_cents' => $event->amount->cents,
            'is_locked' => false,
            'version' => 1,
            'calculated_at' => $event->calculatedAt,
            'updated_at' => $event->calculatedAt,
        ]);
    }

    public function onRecalculated(EarningLineRecalculated $event): void
    {
        $line = $this->find($event);
        $line->system_value_cents = $event->newAmount->cents;
        $line->current_value_cents = $event->newAmount->cents + $line->adjustments_total_cents;
        $this->touch($line, $event);
    }

    public function onRecalculationIgnored(SystemRecalculationIgnored $event): void
    {
        $line = $this->find($event);
        $line->ignored_recalculations++;
        $this->touch($line, $event);
    }

    public function onManualAdjustmentAdded(ManualAdjustmentAdded $event): void
    {
        $line = $this->find($event);

        EarningLineAdjustmentView::query()->create([
            'earning_line_id' => $line->id,
            'number' => $event->adjustmentNumber,
            'amount_cents' => $event->amount->cents,
            'comment' => $event->comment->value,
            'author_id' => $event->authorId,
            'added_at' => $event->addedAt,
        ]);

        $line->adjustments_total_cents += $event->amount->cents;
        $line->current_value_cents = $line->system_value_cents + $line->adjustments_total_cents;
        $line->locked_at ??= $event->addedAt;
        $line->is_locked = true;
        $this->touch($line, $event);
    }

    private function find(DomainEvent $event): EarningLineView
    {
        return EarningLineView::query()->lockForUpdate()->findOrFail($event->aggregateId());
    }

    private function touch(EarningLineView $line, DomainEvent $event): void
    {
        $line->version++;
        $line->updated_at = $event->occurredAt();
        $line->save();
    }
}
