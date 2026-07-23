<?php

namespace App\Http\Requests\Donation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'institution_name' => 'required|string|max:255',
            'expired_log_ids' => 'required|array|min:1',
            'expired_log_ids.*' => [
                'integer',
                Rule::exists('expired_logs', 'id')->where(function ($query) {
                    $query->whereNotIn('id', function ($subQuery) {
                        $subQuery->select('expired_log_id')->from('donative_logs');
                    });
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'expired_log_ids.*.exists' => 'Uno o más productos seleccionados ya han sido donados o no son válidos.'
        ];
    }
}
