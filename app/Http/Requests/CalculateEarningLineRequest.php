<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CalculateEarningLineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string', AmountRule::REGEX],
        ];
    }
}
