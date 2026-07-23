<?php

namespace App\Http\Requests\SocialBenefit;

use Illuminate\Foundation\Http\FormRequest;

class UploadSignedSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }
}
