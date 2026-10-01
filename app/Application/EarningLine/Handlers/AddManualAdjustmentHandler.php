<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Handlers;

use App\Application\EarningLine\Commands\AddManualAdjustment;
use App\Domain\EarningLine\AdjustmentComment;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\EarningLineRepository;
use App\Domain\EarningLine\Money;

final readonly class AddManualAdjustmentHandler
{
    public function __construct(private EarningLineRepository $lines) {}

    public function handle(AddManualAdjustment $command): void
    {
        $line = $this->lines->get(EarningLineId::fromString($command->lineId));

        $line->addManualAdjustment(
            Money::fromString($command->amount),
            AdjustmentComment::fromString($command->comment),
            $command->authorId,
            now()->toImmutable(),
        );

        $this->lines->save($line);
    }
}
