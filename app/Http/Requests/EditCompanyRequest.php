<?php

namespace App\Http\Requests;

use App\Data\EditCompanyData;
use App\Helpers\ApiResponse;
use App\Models\Company;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class EditCompanyRequest extends FormRequest
{

    public EditCompanyData $company;

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
            //
            "id"                     =>    "required|numeric|exists:companies,id",
            "name"                   =>    "required|string|max:255",
            "identification"         =>    "required|string",
            "type_company"           =>    [
                "required",
                "string",
                Rule::in([
                    Company::COMPANY,
                    Company::CLINIC,
                ]),

            ],
            "address"                =>    "nullable|string",
        ];
    }

    public function messages()
    {
        return [
            "id.required" => "El ID de la empresa es obligatorio",
            "id.numeric" => "El ID debe ser numérico",
            "id.exists" => "La empresa no fue encontrada",

            "name.required" => "El nombre de la empresa es obligatorio",
            "name.string" => "El nombre debe ser texto",
            "name.max" => "El nombre no puede exceder 255 caracteres",

            "identification.required" => "El RIF/identificación es obligatorio",
            "identification.string" => "La identificación debe ser texto",

            "type_company.required" => "El tipo de empresa es obligatorio",
            "type_company.string" => "El tipo de empresa debe ser texto",
            "type_company.in" => "El tipo de empresa debe ser: Empresa o Clínica",

            "address.string" => "La dirección debe ser texto"
        ];
    }

    protected function failedValidation(Validator $validator): JsonResponse
    {
        $errors = $validator->errors();
        $response = ApiResponse::error("Error", 422, $errors);
        throw new HttpResponseException($response);
    }

    protected function passedValidation()
    {
        $this->company = EditCompanyData::from([
            "id"                      =>    $this->id,
            "name"                    =>    $this->name,
            "identification"          =>    $this->identification,
            "type_company"            =>    $this->type_company,
            "address"                 =>    $this->address,
        ]);
    }
}
