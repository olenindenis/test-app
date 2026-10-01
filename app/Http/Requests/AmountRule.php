<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class AmountRule
{
    /** Decimal amount with an optional sign and at most two decimals, e.g. "-45.55". */
    public const string REGEX = 'regex:/^[+-]?\d{1,12}(\.\d{1,2})?$/';
}
