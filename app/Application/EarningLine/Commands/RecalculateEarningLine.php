<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Commands;

final readonly class RecalculateEarningLine
{
    public function __construct(
        public string $lineId,
        public string $amount,
    ) {}
}
