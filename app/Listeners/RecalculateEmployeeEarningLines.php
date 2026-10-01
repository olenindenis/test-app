<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\EarningLine\Commands\RecalculateEarningLine;
use App\Application\EarningLine\Handlers\RecalculateEarningLineHandler;
use App\Events\EmployeeBaseSalaryChanged;
use App\Models\EarningLineView;

final readonly class RecalculateEmployeeEarningLines
{
    public function __construct(private RecalculateEarningLineHandler $recalculate) {}

    public function handle(EmployeeBaseSalaryChanged $event): void
    {
        EarningLineView::query()
            ->where('employee_id', $event->employeeId)
            ->pluck('id')
            ->each(fn(string $lineId) => $this->recalculate->handle(
                new RecalculateEarningLine($lineId, $event->newBaseSalary),
            ));
    }
}
