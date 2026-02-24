<?php

namespace App\Http\Requests;

use App\Data\ExchangeRateCreateData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ExchangeRateCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */

    public ExchangeRateCreateData $data;

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
            "currency_code" => "required",
            "rate" => "nullable|numeric",
        ];
    }

    public function menssages()
    {
        return [
            "currency_code.required" => "the currency code is required",
            "rate.required" => "the rate is required",

        ];
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422)
        );
    }


    protected function passedValidation()
    {
        $this->data = ExchangeRateCreateData::from([
            "id" => $this->id,
            "currency_code" => $this->currency_code,
            "rate" => $this->rate,
            "source" => $this->source,
        ]);
    }
}
