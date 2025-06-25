<?php

namespace App\Http\Requests;

use App\Data\EditClientData;
use App\Helpers\ApiResponse;
use App\Models\Client;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class EditClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */

    public EditClientData $client;

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
            "id"                     =>    "required|numeric|exists:clients,id",
            "name"                   =>    "required|string|max:255",
            "last_name"              =>    "required|string|max:255",
            "email"                  =>    "required|string|max:255|email:rfc,dns",
            "identification_type"    =>    [
                "required",
                "string",
                Rule::in([
                    Client::IDENTIFICATION_TYPE_VENEZOLANO,
                    Client::IDENTIFICATION_TYPE_GOBIERNO,
                    Client::IDENTIFICATION_TYPE_JURIDICO,
                    Client::IDENTIFICATION_TYPE_EXTRANJERO,
                ]),

            ],
            "identification"         =>    "required|string",
            "phone"                  =>    "required|string|max:50",
            "address"                =>    "required|string",
            "company_id"             =>    "required|exists:companies,id",
            "birthdate"              =>    "nullable|date",
        ];
    }

    public function menssages()
    {
        return [
            "id.required"                        => "the field is required",
            "id.numeric"                         => "the field is type numeric",
            "id.exists"                          => "the client is not found",

            "name.required"                      => "the field is required",
            "name.string"                        => "the field is type string",
            "name.max"                           => "the max of field is 255 characters",

            "last_name.required"                 => "the field is required",
            "last_name.string"                   => "the field is type string",
            "last_name.max"                      => "the max of field is 255 characters",

            "email.required"                     => "the field is required",
            "email.string"                       => "the field is type string",
            "email.max"                          => "the max of field is 255 characters",
            "email.email"                        => "the format of email is invalid",

            "identification_type.required"       => "the field is required",
            "identification_type.string"         => "the field is type string",
            "identification_type.in"             => "The document type must be one of: V-, J-, G-, E-",

            "identification.required"            => "the field is required",
            "identification.string"              => "the field is type string",
            "identification.unique"              => "the ID is already in use",

            "phone.required"                     => "the field is required",
            "phone.string"                       => "the field is type string",
            "phone.max"                          => "the max of field is 255 characters",

            "address.required"                   => "the field is required",
            "address.string"                     => "the field is type string",

            "company_id.required"                => "the field is required",
            "company_id.exists"                  => "the company is not found",

            "birthdate.data"                     => "the date format is not valid",

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
        $this->client = EditClientData::from([
            "id"                      =>    $this->id,
            "name"                    =>    $this->name,
            "last_name"               =>    $this->last_name,
            "identification_type"     =>    $this->identification_type,
            "identification"          =>    $this->identification,
            "phone"                   =>    $this->phone,
            "address"                 =>    $this->address,
            "company_id"              =>    $this->company_id,
            "email"                   =>    $this->email,
            "birthdate"               =>    $this?->birthdate,
        ]);
    }
}
