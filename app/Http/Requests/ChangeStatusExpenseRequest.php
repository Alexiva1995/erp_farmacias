<?php

namespace App\Http\Requests;

use App\Data\ChangeStatusExpenseData;
use App\Helpers\ApiResponse;
use App\Models\Expense;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ChangeStatusExpenseRequest extends FormRequest
{
    public ChangeStatusExpenseData $data;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->role_id === 1;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "id"                     =>    "required|numeric|exists:expenses,id",
            "status"                  =>    [
                "required",
                "string",
                Rule::in([
                    Expense::STATUS_APPROVED,
                    Expense::STATUS_CANCELLED,
                    Expense::STATUS_PENDING,
                ])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Reglas para 'id'
            'id.required' => 'El Id del Gasto es obligatoria.',
            'id.numeric' => 'El Id del Gasto debe ser un valor numérico.',
            'id.exists' => 'El Gasto seleccionado no es válida.',

            // Reglas para 'count'
            'status.required' => 'El estado es obligatorio.',
            'status.string' => 'El estado debe ser una cadena de texto.',
            'status.in' => 'El estado seleccionado no es válido.',
        ];
    }


    protected function failedValidation(Validator $validator): JsonResponse
    {
        $errors = $validator->errors();
        $response = ApiResponse::error("Error", 422, $errors);
        throw new HttpResponseException($response);
    }

}
