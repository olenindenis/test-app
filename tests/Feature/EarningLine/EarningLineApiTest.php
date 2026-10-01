<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** HTTP-specific behaviour: validation and error responses. The happy path is covered by BusinessCaseIntegrationTest. */
final class EarningLineApiTest extends TestCase
{
    use RefreshDatabase;

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
