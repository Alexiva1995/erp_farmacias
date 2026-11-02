<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class BulkMarkAsOrderedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
            'supplierId' => ['nullable', 'integer'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'q' => ['nullable', 'string'],
        ];
    }
}
