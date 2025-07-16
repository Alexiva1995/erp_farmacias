<?php

namespace App\Http\Requests;

use App\Data\CreateDoctorData;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class CreateDoctorRequest extends FormRequest
{


    public CreateDoctorData $data;

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
            "name"                   =>    "required|string|max:255",
            "identification"         =>    "required|string|unique:doctors",
            "address"                =>    "nullable|string",
        ];
    }

    public function messages()
    {
        return [
            "name.required" => "El nombre del doctor es obligatorio",
            "name.string" => "El nombre debe ser texto",
            "name.max" => "El nombre no puede exceder 255 caracteres",

            "identification.required" => "La identificación es obligatoria",
            "identification.string" => "La identificación debe ser texto",
            "identification.unique" => "La identificación ya está registrada",

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
        $this->data = CreateDoctorData::from([
            "name"                    =>    $this->name,
            "identification"          =>    $this->identification,
            "address"                 =>    $this->address,
        ]);
    }
}
