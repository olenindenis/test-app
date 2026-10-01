<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\EarningLine\Money;
use App\Models\EarningLineAdjustmentView;
use App\Models\EarningLineView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The line's current value with its audit trail: the (frozen) system value
 * followed by every manual adjustment in order.
 *
 * @mixin EarningLineView
 */
final class EarningLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'is_locked' => $this->isLocked(),
            'locked_at' => $this->locked_at?->format(DATE_RFC3339_EXTENDED),
            'system_value' => self::money($this->systemValue()),
            'adjustments' => $this->adjustments->map(fn (EarningLineAdjustmentView $a): array => [
                'number' => $a->number,
                'label' => 'Adjustment '.$a->number,
                'amount' => Money::fromCents($a->amount_cents)->toDecimalString(),
                'formatted' => Money::fromCents($a->amount_cents)->formatSigned(),
                'comment' => $a->comment,
                'author_id' => $a->author_id,
                'added_at' => $a->added_at->format(DATE_RFC3339_EXTENDED),
            ])->all(),
            'current_value' => self::money($this->currentValue()),
            'ignored_recalculations' => $this->ignored_recalculations,
        ];
    }

    /** @return array{amount: string, formatted: string} */
    private static function money(Money $money): array
    {
        return ['amount' => $money->toDecimalString(), 'formatted' => $money->format()];
    }
}
