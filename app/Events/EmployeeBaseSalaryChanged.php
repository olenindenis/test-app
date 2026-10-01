<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class EmployeeBaseSalaryChanged
{
    use Dispatchable;

    public function __construct(
        public string $employeeId,
        public string $newBaseSalary,
    ) {}
}
