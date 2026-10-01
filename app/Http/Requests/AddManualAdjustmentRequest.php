<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\EarningLine\AdjustmentComment;
use App\Domain\EarningLine\Money;
use Illuminate\Foundation\Http\FormRequest;

final class AddManualAdjustmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:' . Money::PATTERN],
            'comment' => ['required', 'string', 'max:' . AdjustmentComment::MAX_LENGTH],
            'author_id' => ['required', 'string', 'max:255'],
        ];
    }
}
