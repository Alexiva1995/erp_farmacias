<?php

namespace App\Http\Requests;

use App\Data\EditDoctorData;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class EditDoctorRequest extends FormRequest
{


    public EditDoctorData $data;

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
            "address"                =>    "nullable|string",
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

            "identification.required"            => "the field is required",
            "identification.string"              => "the field is type string",

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
        $this->data = EditDoctorData::from([
            "id"                      =>    $this->id,
            "name"                    =>    $this->name,
            "identification"          =>    $this->identification,
            "address"                 =>    $this->address,
        ]);
    }
}
