<?php

declare(strict_types=1);

namespace App\Application\EarningLine\Handlers;

use App\Application\EarningLine\Commands\CalculateEarningLine;
use App\Domain\EarningLine\EarningLine;
use App\Domain\EarningLine\EarningLineId;
use App\Domain\EarningLine\EarningLineRepository;
use App\Domain\EarningLine\Money;

final readonly class CalculateEarningLineHandler
{
    public function __construct(private EarningLineRepository $lines) {}

    public function handle(CalculateEarningLine $command): EarningLineId
    {
        $line = EarningLine::calculate(
            EarningLineId::generate(),
            $command->employeeId,
            Money::fromString($command->amount),
            now()->toImmutable(),
        );

        $this->lines->save($line);

        return $line->id();
    }
}
