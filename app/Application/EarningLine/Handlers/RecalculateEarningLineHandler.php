<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Handlers;

use App\Application\EarningLine\Commands\RecalculateEarningLine;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\EarningLineRepository;
use App\Domain\EarningLine\Money;

final readonly class RecalculateEarningLineHandler
{
    public function __construct(private EarningLineRepository $lines) {}

    public function handle(RecalculateEarningLine $command): void
    {
        $line = $this->lines->get(EarningLineId::fromString($command->lineId));

        $line->recalculate(Money::fromString($command->amount), now()->toImmutable());

        $this->lines->save($line);
    }
}
