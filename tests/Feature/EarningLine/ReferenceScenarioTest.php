<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use App\Application\EarningLine\Commands\AddManualAdjustment;
use App\Application\EarningLine\Commands\CalculateEarningLine;
use App\Application\EarningLine\Handlers\AddManualAdjustmentHandler;
use App\Application\EarningLine\Handlers\CalculateEarningLineHandler;
use App\Application\EarningLine\Queries\GetEarningLineHistory;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\EarningLineRepository;
use App\Events\EmployeeBaseSalaryChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Runs the reference scenario end-to-end against PostgreSQL: commands go
 * through the event store, source data changes arrive as an integration event,
 * and values are read back from the projections.
 */
final class ReferenceScenarioTest extends TestCase
{
    use RefreshDatabase;

    private const string EMPLOYEE = 'employee-42';

    private string $lineId;

    #[Test]
    public function it_produces_the_expected_values_and_audit_history(): void
    {
        // 1. System calculates the line
        $this->lineId = app(CalculateEarningLineHandler::class)
            ->handle(new CalculateEarningLine(self::EMPLOYEE, '1000.00'))->value;
        $this->assertCurrentValue('$1,000.00');

        // 2. Source data changes, system recalculates
        EmployeeBaseSalaryChanged::dispatch(self::EMPLOYEE, '1050.00');
        $this->assertCurrentValue('$1,050.00');

        // 3. First manual correction
        $this->adjust('-45.55', 'Employee declined dental benefit; reversing deduction');
        $this->assertCurrentValue('$1,004.45');

        // 4. Source data changes again — must be ignored
        EmployeeBaseSalaryChanged::dispatch(self::EMPLOYEE, '1100.00');
        $this->assertCurrentValue('$1,004.45');

        // 5–8.
        $this->adjust('+100.10', 'Late correction: missed approved overtime bonus');
        $this->assertCurrentValue('$1,104.55');
        $this->adjust('-0.10', 'Minor rounding adjustment');
        $this->assertCurrentValue('$1,104.45');
        $this->adjust('-0.20', 'Second minor rounding adjustment');
        $this->assertCurrentValue('$1,104.25');
        $this->adjust('+0.20', 'Correcting mistake in adjustment #4');
        $this->assertCurrentValue('$1,104.45');

        $history = app(GetEarningLineHistory::class)->handle($this->lineId)->toArray();

        $this->assertSame('$1,050.00', $history['system_value']['formatted']);
        $this->assertTrue($history['is_locked']);
        $this->assertSame(1, $history['ignored_recalculations']);
        $this->assertSame(
            [
                ['Adjustment 1', "\u{2212}$45.55", 'Employee declined dental benefit; reversing deduction'],
                ['Adjustment 2', '+$100.10', 'Late correction: missed approved overtime bonus'],
                ['Adjustment 3', "\u{2212}$0.10", 'Minor rounding adjustment'],
                ['Adjustment 4', "\u{2212}$0.20", 'Second minor rounding adjustment'],
                ['Adjustment 5', '+$0.20', 'Correcting mistake in adjustment #4'],
            ],
            array_map(fn (array $a) => [$a['label'], $a['formatted'], $a['comment']], $history['adjustments']),
        );
        $this->assertSame('$1,104.45', $history['current_value']['formatted']);

        // The event store holds the complete, ordered story of the line.
        $this->assertSame(
            [
                'earning_line.calculated',
                'earning_line.recalculated',
                'earning_line.manual_adjustment_added',
                'earning_line.recalculation_ignored',
                'earning_line.manual_adjustment_added',
                'earning_line.manual_adjustment_added',
                'earning_line.manual_adjustment_added',
                'earning_line.manual_adjustment_added',
            ],
            DB::table('earning_line_events')->where('aggregate_id', $this->lineId)->orderBy('version')->pluck('event_type')->all(),
        );

        // The aggregate rebuilt from the event store agrees with the projection.
        $aggregate = app(EarningLineRepository::class)->get(EarningLineId::fromString($this->lineId));
        $this->assertSame('$1,104.45', $aggregate->currentValue()->format());
        $this->assertSame(8, $aggregate->version());
    }

    #[Test]
    public function a_source_data_change_only_affects_the_given_employees_unlocked_lines(): void
    {
        $calculate = app(CalculateEarningLineHandler::class);
        $locked = $calculate->handle(new CalculateEarningLine(self::EMPLOYEE, '1000.00'))->value;
        $unlocked = $calculate->handle(new CalculateEarningLine(self::EMPLOYEE, '1000.00'))->value;
        $otherEmployee = $calculate->handle(new CalculateEarningLine('employee-7', '1000.00'))->value;

        app(AddManualAdjustmentHandler::class)->handle(new AddManualAdjustment($locked, '5.00', 'Bonus', 'specialist-1'));

        EmployeeBaseSalaryChanged::dispatch(self::EMPLOYEE, '2000.00');

        $query = app(GetEarningLineHistory::class);
        $this->assertSame('$1,005.00', $query->handle($locked)->currentValue->format());
        $this->assertSame('$2,000.00', $query->handle($unlocked)->currentValue->format());
        $this->assertSame('$1,000.00', $query->handle($otherEmployee)->currentValue->format());
    }

    private function adjust(string $amount, string $comment): void
    {
        app(AddManualAdjustmentHandler::class)->handle(new AddManualAdjustment($this->lineId, $amount, $comment, 'specialist-1'));
    }

    private function assertCurrentValue(string $expected): void
    {
        $this->assertSame($expected, app(GetEarningLineHistory::class)->handle($this->lineId)->currentValue->format());
    }
}
