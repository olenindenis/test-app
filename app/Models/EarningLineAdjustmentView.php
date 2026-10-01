<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Read model of a single manual adjustment. Written exclusively by EarningLineProjector.
 *
 * @property int $id
 * @property string $earning_line_id
 * @property int $number
 * @property int $amount_cents
 * @property string $comment
 * @property string $author_id
 * @property CarbonImmutable $added_at
 */
final class EarningLineAdjustmentView extends Model
{
    protected $table = 'earning_line_adjustments';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'amount_cents' => 'integer',
            'added_at' => 'immutable_datetime',
        ];
    }
}
