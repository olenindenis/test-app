<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
