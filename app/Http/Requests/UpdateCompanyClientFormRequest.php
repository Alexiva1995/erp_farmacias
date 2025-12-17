<?php

namespace App\Http\Requests;

use App\Data\UpdateCompanyClientData;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateCompanyClientFormRequest extends FormRequest
{

    public UpdateCompanyClientData $data;

    /**
     * Determine if the user is authorized to make this request.
     */
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
            "client_id"  => "required|numeric|exists:clients,id",
            "company_id" => "required|numeric|exists:companies,id",
            "status"     => "required|boolean:strict",
        ];
    }

    public function messages()
    {
        return [
            "client_id.required" => "El ID del cliente es obligatorio",
            "client_id.numeric" => "El ID debe ser numérico",
            "client_id.exists" => "La empresa no fue encontrada",

            "company_id.required" => "El ID de la empresa es obligatorio",
            "company_id.numeric" => "El ID debe ser numérico",
            "company_id.exists" => "La empresa no fue encontrada",

            "status.required" => "El Status es obligatorio",
            "status.boolean" => "El formato es invalido solo se acepta true o false",

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
        $this->data = UpdateCompanyClientData::from([
            "client_id"     =>    $this->client_id,
            "company_id"    =>    $this->company_id,
            "status"        =>    $this->status,
        ]);
    }
}
