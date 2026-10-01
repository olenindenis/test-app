<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use App\Application\EarningLine\Commands\AddManualAdjustment;
use App\Application\EarningLine\Commands\CalculateEarningLine;
use App\Application\EarningLine\Handlers\AddManualAdjustmentHandler;
use App\Application\EarningLine\Handlers\CalculateEarningLineHandler;
use App\Application\EarningLine\Queries\GetEarningLineHistory;
use App\Events\EmployeeBaseSalaryChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** How a source data change (EmployeeBaseSalaryChanged) fans out to earning lines. */
final class SourceDataChangeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_source_data_change_only_affects_the_given_employees_unlocked_lines(): void
    {
        $calculate = app(CalculateEarningLineHandler::class);
        $locked = $calculate->handle(new CalculateEarningLine('employee-42', '1000.00'))->value;
        $unlocked = $calculate->handle(new CalculateEarningLine('employee-42', '1000.00'))->value;
        $otherEmployee = $calculate->handle(new CalculateEarningLine('employee-7', '1000.00'))->value;

        app(AddManualAdjustmentHandler::class)->handle(new AddManualAdjustment($locked, '5.00', 'Bonus', 'specialist-1'));

        EmployeeBaseSalaryChanged::dispatch('employee-42', '2000.00');

        $query = app(GetEarningLineHistory::class);
        $this->assertSame('$1,005.00', $query->handle($locked)->currentValue()->format());
        $this->assertSame('$2,000.00', $query->handle($unlocked)->currentValue()->format());
        $this->assertSame('$1,000.00', $query->handle($otherEmployee)->currentValue()->format());
    }
}
