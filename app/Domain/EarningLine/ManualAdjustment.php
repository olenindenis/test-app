<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

use DateTimeImmutable;

/** A single, immutable manual correction applied to an earning line. */
final readonly class ManualAdjustment
{
    public function __construct(
        public int $number,
        public Money $amount,
        public AdjustmentComment $comment,
        public string $authorId,
        public DateTimeImmutable $addedAt,
    ) {}
}
