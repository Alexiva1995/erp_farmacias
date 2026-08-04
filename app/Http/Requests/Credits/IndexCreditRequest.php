<?php

namespace App\Http\Requests\Credits;

use Illuminate\Foundation\Http\FormRequest;

class IndexCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortBy' => ['nullable', 'string'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
