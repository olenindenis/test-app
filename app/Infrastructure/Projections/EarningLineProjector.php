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
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps the earning_lines / earning_line_adjustments read models in sync with
 * the event stream. Registered as a Laravel event subscriber; the same handlers
 * are used when replaying the event store.
 */
final class EarningLineProjector
{
    /** @var array<class-string<DomainEvent>, string> */
    private const array HANDLERS = [
        EarningLineCalculated::class => 'onCalculated',
        EarningLineRecalculated::class => 'onRecalculated',
        SystemRecalculationIgnored::class => 'onRecalculationIgnored',
        ManualAdjustmentAdded::class => 'onManualAdjustmentAdded',
    ];

    /** @return array<class-string<DomainEvent>, string> */
    public function subscribe(): array
    {
        return self::HANDLERS;
    }

    public function project(DomainEvent $event): void
    {
        $this->{self::HANDLERS[$event::class]}($event);
    }

    public function onCalculated(EarningLineCalculated $event): void
    {
        EarningLineView::query()->create([
            'id' => $event->lineId->value,
            'employee_id' => $event->employeeId,
            'system_value_cents' => $event->amount->cents,
        ]);
    }

    public function onRecalculated(EarningLineRecalculated $event): void
    {
        $this->line($event)->update(['system_value_cents' => $event->newAmount->cents]);
    }

    public function onRecalculationIgnored(SystemRecalculationIgnored $event): void
    {
        $this->line($event)->increment('ignored_recalculations');
    }

    public function onManualAdjustmentAdded(ManualAdjustmentAdded $event): void
    {
        EarningLineAdjustmentView::query()->create([
            'earning_line_id' => $event->lineId->value,
            'number' => $event->adjustmentNumber,
            'amount_cents' => $event->amount->cents,
            'comment' => $event->comment->value,
            'author_id' => $event->authorId,
            'added_at' => $event->addedAt,
        ]);

        $this->line($event)->increment('adjustments_total_cents', $event->amount->cents);
        $this->line($event)->whereNull('locked_at')->update(['locked_at' => $event->addedAt]);
    }

    /** @return Builder<EarningLineView> */
    private function line(DomainEvent $event): Builder
    {
        return EarningLineView::query()->whereKey($event->aggregateId());
    }
}
