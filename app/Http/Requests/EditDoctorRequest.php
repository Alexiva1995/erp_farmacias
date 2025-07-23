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
            "id"                     =>    "required|numeric|exists:doctors,id",
            "name"                   =>    "required|string|max:255",
            "identification"         =>    "required|string",
            "address"                =>    "nullable|string",
        ];
    }

    public function messages()
    {
        return [
            "id.required" => "El ID del doctor es obligatorio",
            "id.numeric" => "El ID debe ser numérico",
            "id.exists" => "El doctor no fue encontrado",

            "name.required" => "El nombre del doctor es obligatorio",
            "name.string" => "El nombre debe ser texto",
            "name.max" => "El nombre no puede exceder 255 caracteres",

            "identification.required" => "La identificación es obligatoria",
            "identification.string" => "La identificación debe ser texto",

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
        $this->data = EditDoctorData::from([
            "id"                      =>    $this->id,
            "name"                    =>    $this->name,
            "identification"          =>    $this->identification,
            "address"                 =>    $this->address,
        ]);
    }
}
