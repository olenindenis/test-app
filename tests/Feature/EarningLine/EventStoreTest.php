<?php

declare(strict_types=1);

namespace Tests\Feature\EarningLine;

use App\Application\EarningLine\Commands\AddManualAdjustment;
use App\Application\EarningLine\Commands\CalculateEarningLine;
use App\Application\EarningLine\Handlers\AddManualAdjustmentHandler;
use App\Application\EarningLine\Handlers\CalculateEarningLineHandler;
use App\Domain\EarningLine\AdjustmentComment;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\EarningLineRepository;
use App\Domain\EarningLine\Exceptions\EarningLineNotFound;
use App\Domain\EarningLine\Money;
use App\Infrastructure\EventStore\ConcurrencyException;
use App\Models\EarningLineAdjustmentView;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EventStoreTest extends TestCase
{
    use RefreshDatabase;

    private string $lineId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lineId = app(CalculateEarningLineHandler::class)->handle(new CalculateEarningLine('employee-1', '1000.00'))->value;
        app(AddManualAdjustmentHandler::class)->handle(new AddManualAdjustment($this->lineId, '-45.55', 'Declined benefit', 'specialist-1'));
    }

    #[Test]
    public function stored_events_cannot_be_updated(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only: UPDATE is not allowed');

        DB::table('earning_line_events')->where('aggregate_id', $this->lineId)->update(['payload' => '{}']);
    }

    #[Test]
    public function stored_events_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only: DELETE is not allowed');

        DB::table('earning_line_events')->where('aggregate_id', $this->lineId)->delete();
    }

    #[Test]
    public function the_event_table_cannot_be_truncated(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only: TRUNCATE is not allowed');

        DB::statement('TRUNCATE earning_line_events');
    }

    #[Test]
    public function concurrent_modifications_of_the_same_line_are_rejected(): void
    {
        $repository = app(EarningLineRepository::class);
        $id = EarningLineId::fromString($this->lineId);

        $specialistA = $repository->get($id);
        $specialistB = $repository->get($id);

        $specialistA->addManualAdjustment(Money::fromString('1.00'), AdjustmentComment::fromString('A'), 'specialist-a', now()->toImmutable());
        $specialistB->addManualAdjustment(Money::fromString('2.00'), AdjustmentComment::fromString('B'), 'specialist-b', now()->toImmutable());

        $repository->save($specialistA);

        try {
            $repository->save($specialistB);
            $this->fail('Expected a ConcurrencyException');
        } catch (ConcurrencyException) {
        }

        $this->assertSame(['Declined benefit', 'A'], EarningLineAdjustmentView::query()->orderBy('number')->pluck('comment')->all());
        $this->assertSame('$955.45', $repository->get($id)->currentValue()->format());
    }

    #[Test]
    public function loading_an_unknown_line_fails(): void
    {
        $this->expectException(EarningLineNotFound::class);

        app(EarningLineRepository::class)->get(EarningLineId::generate());
    }
}
