<?php

namespace App\Http\Requests;

use App\Data\CreateCompanyData;
use App\Helpers\ApiResponse;
use App\Models\Company;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CreateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */

    public CreateCompanyData $company;

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
            "name.required"                      => "the field is required",
            "name.string"                        => "the field is type string",
            "name.max"                           => "the max of field is 255 characters",

            "identification.required"            => "the field is required",
            "identification.string"              => "the field is type string",

            "type_company.required"              => "the field is required",
            "type_company.string"                => "the field is type string",
            "type_company.in"                    => "The Company type must be one of: Empresa or Clinica",

            "address.required"                   => "the field is required",
            "address.string"                     => "the field is type string",
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
        $this->company = CreateCompanyData::from([
            "name"                    =>    $this->name,
            "identification"          =>    $this->identification,
            "type_company"            =>    $this->type_company,
            "address"                 =>    $this->address,
        ]);
    }
}
