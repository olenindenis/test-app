<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Commands;

final readonly class CalculateEarningLine
{
    public function __construct(
        public string $employeeId,
        public string $amount,
    ) {}
}
