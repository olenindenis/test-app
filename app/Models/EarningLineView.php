<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\EarningLine\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read model of an earning line. Written exclusively by EarningLineProjector.
 *
 * @property string $id
 * @property string $employee_id
 * @property int $system_value_cents
 * @property int $adjustments_total_cents
 * @property CarbonImmutable|null $locked_at
 * @property int $ignored_recalculations
 */
final class EarningLineView extends Model
{
    protected $table = 'earning_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'system_value_cents' => 'integer',
            'adjustments_total_cents' => 'integer',
            'locked_at' => 'immutable_datetime',
            'ignored_recalculations' => 'integer',
        ];
    }

    /** @return HasMany<EarningLineAdjustmentView, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(EarningLineAdjustmentView::class, 'earning_line_id')->orderBy('number');
    }

    public function systemValue(): Money
    {
        return Money::fromCents($this->system_value_cents);
    }

    public function currentValue(): Money
    {
        return $this->systemValue()->add(Money::fromCents($this->adjustments_total_cents));
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
