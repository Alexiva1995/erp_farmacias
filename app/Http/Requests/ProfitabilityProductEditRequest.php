<?php

namespace App\Http\Requests;

use App\Data\ProfitabilityEditData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ProfitabilityProductEditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public ProfitabilityEditData $profitability;

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
            "id"                       =>    "required|integer",
            "product_id"               =>    "required|integer",
            "profitability_percentage" =>    "required|numeric",
            "is_locked"                =>    "required|integer",
            "shipping_cost"            =>    "nullable|numeric",
            "packaging_cost"           =>    "nullable|numeric",
            "expense_margin"           =>    "nullable|numeric",
            "profit_margin"            =>    "nullable|numeric",
            "tax_usa"                  =>    "nullable|numeric",
        ];
    }

    public function menssages()
    {
        return [
            "id"                                 => "the id is required",
            "product_id.required"                => "the product id is required",
            "profitability_percentage.required"  => "the the percentage is required",
            "is_locked.required"                 => "the is locked is required",
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
        $this->profitability = ProfitabilityEditData::from([
            "id"                       => $this->id,
            "product_id"               => $this->product_id,
            "profitability_percentage" => $this->profitability_percentage,
            "is_locked"                => $this->is_locked,
            "shipping_cost"            => $this->shipping_cost,
            "packaging_cost"           => $this->packaging_cost,
            "expense_margin"           => $this->expense_margin,
            "profit_margin"            => $this->profit_margin,
            "tax_usa"                  => $this->tax_usa,
        ]);
    }
}
