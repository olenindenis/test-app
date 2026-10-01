<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

interface EarningLineRepository
{
    public function get(EarningLineId $id): EarningLine;

    public function save(EarningLine $line): void;
}
