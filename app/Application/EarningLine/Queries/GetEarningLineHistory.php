<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Queries;

use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Exceptions\EarningLineNotFound;
use App\Models\EarningLineView;

final readonly class GetEarningLineHistory
{
    public function handle(string $lineId): EarningLineView
    {
        $id = EarningLineId::fromString($lineId);

        return EarningLineView::query()->with('adjustments')->find($id->value)
            ?? throw EarningLineNotFound::withId($id);
    }
}
