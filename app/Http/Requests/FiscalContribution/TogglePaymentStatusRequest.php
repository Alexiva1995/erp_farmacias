<?php

declare(strict_types=1);

namespace App\Http\Requests\FiscalContribution;

use Illuminate\Foundation\Http\FormRequest;

class TogglePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'            => ['nullable', 'string', 'in:pending,paid,expired'],
            'payment_date'      => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
