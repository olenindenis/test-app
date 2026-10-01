<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\EarningLine\Money;
use Illuminate\Foundation\Http\FormRequest;

final class RecalculateEarningLineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:' . Money::PATTERN],
        ];
    }
}
