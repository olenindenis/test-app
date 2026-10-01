<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Queries;

use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\Exceptions\EarningLineNotFound;
use App\Models\EarningLineView;

/** Reads the earning line projection (the query side of CQRS). */
final readonly class GetEarningLineHistory
{
    /** @throws EarningLineNotFound */
    public function handle(string $lineId): EarningLineHistory
    {
        $id = EarningLineId::fromString($lineId);

        $line = EarningLineView::query()->with('adjustments')->find($id->value)
            ?? throw EarningLineNotFound::withId($id);

        return EarningLineHistory::fromView($line);
    }
}
