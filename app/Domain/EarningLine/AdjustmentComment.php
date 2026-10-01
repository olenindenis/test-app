<?php

declare(strict_types=1);

namespace App\Domain\EarningLine;

use App\Domain\EarningLine\Exceptions\InvalidManualAdjustment;

/** The mandatory explanation a specialist must give for every manual adjustment. */
final readonly class AdjustmentComment
{
    public const int MAX_LENGTH = 1000;

    private function __construct(public string $value) {}

    public static function fromString(string $comment): self
    {
        $comment = trim($comment);

        if ($comment === '') {
            throw InvalidManualAdjustment::missingComment();
        }

        if (mb_strlen($comment) > self::MAX_LENGTH) {
            throw InvalidManualAdjustment::commentTooLong(self::MAX_LENGTH);
        }

        return new self($comment);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
