<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use App\Events\EmployeeBaseSalaryChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests driven by the data tables from the business case.
 *
 * The whole stack is exercised: the system and the specialist act through the
 * HTTP API, source data changes arrive as the EmployeeBaseSalaryChanged
 * integration event, events are stored in PostgreSQL and read back from the
 * projections.
 */
final class BusinessCaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const string EMPLOYEE = 'employee-42';

    private const string SPECIALIST = 'payroll-specialist-1';

    private const string CALCULATE = 'system calculates the line';

    private const string SOURCE_CHANGE = 'source data changes, system recalculates';

    private const string ADJUST = 'specialist adds a manual correction';

    private const string MINUS = "\u{2212}";

    /**
     * "Examples and expected numbers" table: step => [event, amount, comment, current value after the step].
     * The brief gives no source value for step 4; any value different from $1,050.00 proves it is ignored.
     */
    private const array STEPS = [
        1 => [self::CALCULATE, '1000.00', null, '$1,000.00'],
        2 => [self::SOURCE_CHANGE, '1050.00', null, '$1,050.00'],
        3 => [self::ADJUST, '-45.55', 'Employee declined dental benefit; reversing deduction', '$1,004.45'],
        4 => [self::SOURCE_CHANGE, '1100.00', null, '$1,004.45'],
        5 => [self::ADJUST, '+100.10', 'Late correction: missed approved overtime bonus', '$1,104.55'],
        6 => [self::ADJUST, '-0.10', 'Minor rounding adjustment', '$1,104.45'],
        7 => [self::ADJUST, '-0.20', 'Second minor rounding adjustment', '$1,104.25'],
        8 => [self::ADJUST, '+0.20', 'Correcting mistake in adjustment #4', '$1,104.45'],
    ];

    /** "Expected final audit history for this line" table. */
    private const array EXPECTED_AUDIT_HISTORY = [
        ['System value (frozen at step 3)', '$1,050.00'],
        ['Adjustment 1', self::MINUS.'$45.55'],
        ['Adjustment 2', '+$100.10'],
        ['Adjustment 3', self::MINUS.'$0.10'],
        ['Adjustment 4', self::MINUS.'$0.20'],
        ['Adjustment 5', '+$0.20'],
        ['Current (new) value', '$1,104.45'],
    ];

    private string $lineId;

    public static function steps(): iterable
    {
        foreach (self::STEPS as $step => [$event, $amount, , $expected]) {
            yield "step {$step}: {$event} ({$amount}) → {$expected}" => [$step];
        }
    }

    #[Test]
    #[DataProvider('steps')]
    public function the_current_value_after_each_step_matches_the_business_case(int $step): void
    {
        $this->runStepsUpTo($step);

        $line = $this->getLine()->assertOk()->json('data');

        $this->assertSame(self::STEPS[$step][3], $line['current_value']['formatted']);
        $this->assertSame($step >= 3, $line['is_locked'], 'The line is locked from the first manual correction on');
        $this->assertSame($step >= 2 ? '$1,050.00' : '$1,000.00', $line['system_value']['formatted']);
    }

    #[Test]
    public function the_final_audit_history_matches_the_business_case(): void
    {
        $this->runStepsUpTo(8);

        $this->assertSame(self::EXPECTED_AUDIT_HISTORY, $this->auditHistory());
        $this->getLine()
            ->assertJsonPath('data.system_value.amount', '1050.00')
            ->assertJsonPath('data.current_value.amount', '1104.45');
    }

    #[Test]
    public function every_correction_stays_visible_with_its_comment_and_author(): void
    {
        $this->runStepsUpTo(8);

        $adjustments = $this->getLine()->json('data.adjustments');
        $expected = array_values(array_filter(self::STEPS, fn (array $step) => $step[0] === self::ADJUST));

        $this->assertCount(count($expected), $adjustments);
        foreach ($expected as $i => [, $amount, $comment]) {
            $this->assertSame($i + 1, $adjustments[$i]['number']);
            $this->assertSame(ltrim($amount, '+'), $adjustments[$i]['amount']);
            $this->assertSame($comment, $adjustments[$i]['comment']);
            $this->assertSame(self::SPECIALIST, $adjustments[$i]['author_id']);
        }
    }

    #[Test]
    public function the_ignored_recalculation_at_step_4_is_traceable_but_has_no_effect(): void
    {
        $this->runStepsUpTo(8);

        $events = $this->getJson("/api/earning-lines/{$this->lineId}/events")->assertOk()->json('data');

        $this->assertSame(
            [
                'earning_line.calculated',              // step 1
                'earning_line.recalculated',            // step 2
                'earning_line.manual_adjustment_added', // step 3
                'earning_line.recalculation_ignored',   // step 4
                'earning_line.manual_adjustment_added', // step 5
                'earning_line.manual_adjustment_added', // step 6
                'earning_line.manual_adjustment_added', // step 7
                'earning_line.manual_adjustment_added', // step 8
            ],
            array_column($events, 'type'),
        );
        $this->assertSame(range(1, 8), array_column($events, 'version'));
        $this->assertSame(110000, $events[3]['payload']['attempted_amount_cents']);
        $this->assertSame(1, $this->getLine()->json('data.ignored_recalculations'));
    }

    #[Test]
    public function the_compensating_correction_restores_the_value_before_the_mistake(): void
    {
        $this->runStepsUpTo(6);
        $beforeMistake = $this->getLine()->json('data.current_value');

        $this->runStep(7);
        $this->runStep(8);

        $this->assertSame($beforeMistake, $this->getLine()->json('data.current_value'));
        $this->assertCount(5, $this->getLine()->json('data.adjustments'), 'The mistaken correction is kept, not removed');
    }

    #[Test]
    public function saved_corrections_cannot_be_edited_or_deleted(): void
    {
        $this->runStepsUpTo(8);
        $historyBefore = $this->auditHistory();

        $this->putJson("/api/earning-lines/{$this->lineId}/adjustments", ['amount' => '0.00'])->assertMethodNotAllowed();
        $this->deleteJson("/api/earning-lines/{$this->lineId}/adjustments")->assertMethodNotAllowed();
        $this->deleteJson("/api/earning-lines/{$this->lineId}")->assertMethodNotAllowed();

        $this->assertSame($historyBefore, $this->auditHistory());
    }

    #[Test]
    public function the_audit_history_can_be_rebuilt_from_the_event_store(): void
    {
        $this->runStepsUpTo(8);

        DB::table('earning_line_adjustments')->delete();
        DB::table('earning_lines')->delete();

        $this->artisan('earning-lines:rebuild-projections')
            ->expectsOutputToContain('Replayed 8 events')
            ->assertSuccessful();

        $this->assertSame(self::EXPECTED_AUDIT_HISTORY, $this->auditHistory());
    }

    private function runStepsUpTo(int $lastStep): void
    {
        foreach (array_keys(self::STEPS) as $step) {
            if ($step <= $lastStep) {
                $this->runStep($step);
            }
        }
    }

    private function runStep(int $step): void
    {
        [$event, $amount, $comment] = self::STEPS[$step];

        match ($event) {
            self::CALCULATE => $this->lineId = $this->postJson('/api/earning-lines', ['employee_id' => self::EMPLOYEE, 'amount' => $amount])
                ->assertCreated()
                ->json('data.id'),
            self::SOURCE_CHANGE => EmployeeBaseSalaryChanged::dispatch(self::EMPLOYEE, $amount),
            self::ADJUST => $this->postJson("/api/earning-lines/{$this->lineId}/adjustments", [
                'amount' => $amount,
                'comment' => $comment,
                'author_id' => self::SPECIALIST,
            ])->assertCreated(),
        };
    }

    private function getLine(): TestResponse
    {
        return $this->getJson("/api/earning-lines/{$this->lineId}");
    }

    /** The line's audit history in the shape of the "Expected final audit history" table. */
    private function auditHistory(): array
    {
        $line = $this->getLine()->assertOk()->json('data');

        return [
            ['System value (frozen at step 3)', $line['system_value']['formatted']],
            ...array_map(fn (array $a) => [$a['label'], $a['formatted']], $line['adjustments']),
            ['Current (new) value', $line['current_value']['formatted']],
        ];
    }
}
