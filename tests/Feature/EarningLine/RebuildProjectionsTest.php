<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use App\Application\EarningLine\Commands\AddManualAdjustment;
use App\Application\EarningLine\Commands\CalculateEarningLine;
use App\Application\EarningLine\Commands\RecalculateEarningLine;
use App\Application\EarningLine\Handlers\AddManualAdjustmentHandler;
use App\Application\EarningLine\Handlers\CalculateEarningLineHandler;
use App\Application\EarningLine\Handlers\RecalculateEarningLineHandler;
use App\Application\EarningLine\Queries\GetEarningLineHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RebuildProjectionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function projections_can_be_rebuilt_from_the_event_store_alone(): void
    {
        $lineId = app(CalculateEarningLineHandler::class)->handle(new CalculateEarningLine('employee-1', '1000.00'))->value;
        app(RecalculateEarningLineHandler::class)->handle(new RecalculateEarningLine($lineId, '1050.00'));
        app(AddManualAdjustmentHandler::class)->handle(new AddManualAdjustment($lineId, '-45.55', 'Declined benefit', 'specialist-1'));
        app(RecalculateEarningLineHandler::class)->handle(new RecalculateEarningLine($lineId, '1100.00'));
        app(AddManualAdjustmentHandler::class)->handle(new AddManualAdjustment($lineId, '+100.10', 'Overtime', 'specialist-1'));

        $before = app(GetEarningLineHistory::class)->handle($lineId)->toArray();

        // Simulate lost / corrupted read models.
        DB::table('earning_line_adjustments')->delete();
        DB::table('earning_lines')->update(['current_value_cents' => 0]);

        $this->artisan('earning-lines:rebuild-projections')
            ->expectsOutputToContain('Replayed 5 events')
            ->assertSuccessful();

        $this->assertSame($before, app(GetEarningLineHistory::class)->handle($lineId)->toArray());
    }
}
