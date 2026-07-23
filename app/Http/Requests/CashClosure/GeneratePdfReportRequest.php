<?php

namespace App\Http\Requests\CashClosure;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePdfReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'html' => 'required|string',
            'filename' => 'required|string'
        ];
    }
}
