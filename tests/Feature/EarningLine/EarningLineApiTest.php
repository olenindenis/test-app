<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EarningLineApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_reference_scenario_works_over_http(): void
    {
        $id = $this->postJson('/api/earning-lines', ['employee_id' => 'employee-42', 'amount' => '1000.00'])
            ->assertCreated()
            ->assertJsonPath('data.current_value.formatted', '$1,000.00')
            ->json('data.id');

        $this->postJson("/api/earning-lines/{$id}/recalculations", ['amount' => '1050.00'])
            ->assertOk()
            ->assertJsonPath('data.current_value.formatted', '$1,050.00');

        $this->postJson("/api/earning-lines/{$id}/adjustments", [
            'amount' => '-45.55',
            'comment' => 'Employee declined dental benefit; reversing deduction',
            'author_id' => 'specialist-1',
        ])->assertCreated()->assertJsonPath('data.current_value.formatted', '$1,004.45');

        $this->postJson("/api/earning-lines/{$id}/recalculations", ['amount' => '1300.00'])
            ->assertOk()
            ->assertJsonPath('data.current_value.formatted', '$1,004.45');

        foreach ([['+100.10', 'Late correction: missed approved overtime bonus'], ['-0.10', 'Minor rounding adjustment'], ['-0.20', 'Second minor rounding adjustment'], ['+0.20', 'Correcting mistake in adjustment #4']] as [$amount, $comment]) {
            $this->postJson("/api/earning-lines/{$id}/adjustments", ['amount' => $amount, 'comment' => $comment, 'author_id' => 'specialist-1'])
                ->assertCreated();
        }

        $this->getJson("/api/earning-lines/{$id}")
            ->assertOk()
            ->assertJsonPath('data.system_value', ['amount' => '1050.00', 'formatted' => '$1,050.00'])
            ->assertJsonPath('data.current_value', ['amount' => '1104.45', 'formatted' => '$1,104.45'])
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.ignored_recalculations', 1)
            ->assertJsonCount(5, 'data.adjustments')
            ->assertJsonPath('data.adjustments.0.amount', '-45.55')
            ->assertJsonPath('data.adjustments.4.formatted', '+$0.20')
            ->assertJsonPath('data.adjustments.4.comment', 'Correcting mistake in adjustment #4');

        $this->getJson("/api/earning-lines/{$id}/events")
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0.type', 'earning_line.calculated')
            ->assertJsonPath('data.3.type', 'earning_line.recalculation_ignored')
            ->assertJsonPath('data.3.payload.attempted_amount_cents', 130000)
            ->assertJsonPath('data.7.version', 8);
    }

    #[Test]
    public function an_adjustment_without_a_comment_is_rejected(): void
    {
        $id = $this->createLine();

        $this->postJson("/api/earning-lines/{$id}/adjustments", ['amount' => '10.00', 'comment' => '', 'author_id' => 's-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->postJson("/api/earning-lines/{$id}/adjustments", ['amount' => '10.00', 'comment' => '   ', 'author_id' => 's-1'])
            ->assertUnprocessable();

        $this->getJson("/api/earning-lines/{$id}")->assertJsonCount(0, 'data.adjustments');
    }

    #[Test]
    public function invalid_amounts_are_rejected(): void
    {
        $id = $this->createLine();

        foreach (['abc', '1.234', '1,000'] as $amount) {
            $this->postJson("/api/earning-lines/{$id}/adjustments", ['amount' => $amount, 'comment' => 'x', 'author_id' => 's-1'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('amount');
        }

        $this->postJson("/api/earning-lines/{$id}/adjustments", ['amount' => '0.00', 'comment' => 'x', 'author_id' => 's-1'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A manual adjustment amount cannot be zero.');
    }

    #[Test]
    public function adjustments_cannot_be_edited_or_deleted_over_http(): void
    {
        $id = $this->createLine();
        $this->postJson("/api/earning-lines/{$id}/adjustments", ['amount' => '10.00', 'comment' => 'Bonus', 'author_id' => 's-1']);

        $this->putJson("/api/earning-lines/{$id}/adjustments", ['amount' => '20.00'])->assertMethodNotAllowed();
        $this->deleteJson("/api/earning-lines/{$id}/adjustments")->assertMethodNotAllowed();
        $this->deleteJson("/api/earning-lines/{$id}")->assertMethodNotAllowed();
    }

    #[Test]
    public function unknown_lines_return_not_found(): void
    {
        $missing = '0199a1b2-0000-7000-8000-000000000000';

        $this->getJson("/api/earning-lines/{$missing}")->assertNotFound();
        $this->getJson("/api/earning-lines/{$missing}/events")->assertNotFound();
        $this->postJson("/api/earning-lines/{$missing}/recalculations", ['amount' => '1.00'])->assertNotFound();
        $this->getJson('/api/earning-lines/not-a-uuid')->assertNotFound();
    }

    private function createLine(): string
    {
        return $this->postJson('/api/earning-lines', ['employee_id' => 'employee-1', 'amount' => '1000.00'])->json('data.id');
    }
}
