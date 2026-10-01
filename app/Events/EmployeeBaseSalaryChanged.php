<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Integration event: the source data used to calculate an employee's earning
 * lines has changed (e.g. HR updated the base salary). Payroll reacts to it by
 * asking every affected earning line to recalculate itself.
 */
final readonly class EmployeeBaseSalaryChanged
{
    use Dispatchable;

    public function __construct(
        public string $employeeId,
        public string $newBaseSalary,
    ) {}
}
