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
use App\Domain\EarningLine\Exceptions\EarningLineNotFound;
use App\Domain\Shared\DomainEvent;
use App\Http\Requests\AddManualAdjustmentRequest;
use App\Http\Requests\CalculateEarningLineRequest;
use App\Http\Requests\RecalculateEarningLineRequest;
use App\Http\Resources\EarningLineResource;
use App\Infrastructure\EventStore\EventStore;
use Illuminate\Http\JsonResponse;

final class EarningLineController extends Controller
{
    public function __construct(private readonly GetEarningLineHistory $history) {}

    public function store(CalculateEarningLineRequest $request, CalculateEarningLineHandler $handler): JsonResponse
    {
        $id = $handler->handle(new CalculateEarningLine($request->validated('employee_id'), $request->validated('amount')));

        return $this->showLine($id->value)->setStatusCode(201);
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

        return $this->showLine($lineId)->setStatusCode(201);
    }

    public function events(string $lineId, EventStore $eventStore): JsonResponse
    {
        $id = EarningLineId::fromString($lineId);
        $stream = $eventStore->load($id->value);

        if ($stream === []) {
            throw EarningLineNotFound::withId($id);
        }

        $events = array_map(fn(DomainEvent $event, int $index): array => [
            'version' => $index + 1,
            'type' => $event::eventType(),
            'occurred_at' => $event->occurredAt()->format(DATE_RFC3339_EXTENDED),
            'payload' => $event->toPayload(),
        ], $stream, array_keys($stream));

        return response()->json(['data' => $events]);
    }

    private function showLine(string $lineId): JsonResponse
    {
        return EarningLineResource::make($this->history->handle($lineId))->response();
    }
}
