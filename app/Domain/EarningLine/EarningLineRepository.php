<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

use App\Domain\EarningLine\Exceptions\EarningLineNotFound;

interface EarningLineRepository
{
    /** @throws EarningLineNotFound */
    public function get(EarningLineId $id): EarningLine;

    public function save(EarningLine $line): void;
}
