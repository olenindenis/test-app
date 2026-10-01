<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property int $current_value_cents
 * @property bool $is_locked
 * @property CarbonImmutable|null $locked_at
 * @property int $ignored_recalculations
 * @property int $version
 * @property CarbonImmutable $calculated_at
 * @property CarbonImmutable $updated_at
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
            'current_value_cents' => 'integer',
            'is_locked' => 'boolean',
            'locked_at' => 'immutable_datetime',
            'ignored_recalculations' => 'integer',
            'version' => 'integer',
            'calculated_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<EarningLineAdjustmentView, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(EarningLineAdjustmentView::class, 'earning_line_id')->orderBy('number');
    }
}
