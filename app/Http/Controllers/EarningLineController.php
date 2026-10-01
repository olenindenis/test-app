<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\EarningLine\Commands\AddManualAdjustment;
use App\Application\EarningLine\Commands\CalculateEarningLine;
use App\Application\EarningLine\Commands\RecalculateEarningLine;
use App\Application\EarningLine\Handlers\AddManualAdjustmentHandler;
use App\Application\EarningLine\Handlers\CalculateEarningLineHandler;
use App\Application\EarningLine\Handlers\RecalculateEarningLineHandler;
use App\Application\EarningLine\Queries\GetEarningLineHistory;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\Shared\DomainEvent;
use App\Http\Requests\AddManualAdjustmentRequest;
use App\Http\Requests\CalculateEarningLineRequest;
use App\Http\Requests\RecalculateEarningLineRequest;
use App\Infrastructure\EventStore\EventStore;
use Illuminate\Http\JsonResponse;

final class EarningLineController extends Controller
{
    public function __construct(private readonly GetEarningLineHistory $history) {}

    public function store(CalculateEarningLineRequest $request, CalculateEarningLineHandler $handler): JsonResponse
    {
        $id = $handler->handle(new CalculateEarningLine($request->validated('employee_id'), $request->validated('amount')));

        return $this->showLine($id->value, status: 201);
    }

    public function show(string $lineId): JsonResponse
    {
        return $this->showLine($lineId);
    }

    public function recalculate(string $lineId, RecalculateEarningLineRequest $request, RecalculateEarningLineHandler $handler): JsonResponse
    {
        $handler->handle(new RecalculateEarningLine($lineId, $request->validated('amount')));

        return $this->showLine($lineId);
    }

    public function addAdjustment(string $lineId, AddManualAdjustmentRequest $request, AddManualAdjustmentHandler $handler): JsonResponse
    {
        $handler->handle(new AddManualAdjustment(
            $lineId,
            $request->validated('amount'),
            $request->validated('comment'),
            $request->validated('author_id'),
        ));

        return $this->showLine($lineId, status: 201);
    }

    /** The raw, immutable event stream of a line — the complete audit log. */
    public function events(string $lineId, EventStore $eventStore): JsonResponse
    {
        $this->history->handle($lineId); // 404 for unknown lines

        $stream = $eventStore->load(EarningLineId::fromString($lineId)->value);

        $events = array_map(fn (DomainEvent $event, int $index): array => [
            'version' => $index + 1,
            'type' => $event::eventType(),
            'occurred_at' => $event->occurredAt()->format(DATE_RFC3339_EXTENDED),
            'payload' => $event->toPayload(),
        ], $stream, array_keys($stream));

        return response()->json(['data' => $events]);
    }

    private function showLine(string $lineId, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->history->handle($lineId)->toArray()], $status);
    }
}
