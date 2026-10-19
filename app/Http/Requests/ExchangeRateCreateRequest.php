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
        $rules = [
            "currency_code" => "required|string",
            "rate" => "nullable|numeric",
        ];

        // Validar que las tasas de peso colombiano se expresen en pesos por dólar (mínimo 100)
        if (in_array($this->input('currency_code'), ['COP', 'COPC'])) {
            $rules['rate'] = "required|numeric|min:100";
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            "currency_code.required" => "El código de moneda es requerido.",
            "rate.required" => "La tasa es requerida.",
            "rate.numeric" => "La tasa debe ser un número válido.",
            "rate.min" => "La tasa en pesos (COP) debe expresarse en Pesos por Dólar (ej. 3300 o superior).",
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
