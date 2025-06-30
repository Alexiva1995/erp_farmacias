<?php

namespace App\Http\Requests;

use App\Data\ProfitabilityCreateData;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ProfitabilityCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */

    public ProfitabilityCreateData $profitability;

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
            "profitability_percentage" =>    "required|int",
            "is_locked"                =>    "required|int",
        ];
    }

    public function menssages()
    {
        return [
            "profitability_percentage.required"                      => "the field is required",
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
        $this->profitability = ProfitabilityCreateData::from([
            "product_id"                 =>   $this->id,
            "profitability_percentage"   =>   $this->profitability_percentage,
            "is_locked"                  =>   $this->is_locked,
        ]);
    }
}
