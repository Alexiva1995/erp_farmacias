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
            "id" => "required|numeric|exists:clients,id",
            "name" => "required|string|max:255",
            "last_name" => "nullable|string|max:255",
            "email" => "nullable|string|max:255|email:rfc,dns",
            "identification_type" => [
                "required",
                "string",
                Rule::in([
                    Client::IDENTIFICATION_TYPE_VENEZOLANO,
                    Client::IDENTIFICATION_TYPE_GOBIERNO,
                    Client::IDENTIFICATION_TYPE_JURIDICO,
                    Client::IDENTIFICATION_TYPE_EXTRANJERO,
                ]),

            ],
            "identification" => "required|string|min:7|max:9",
            "phone" => "required|string|max:50",
            "address" => "required|string",
            "company_id" => "nullable|exists:companies,id",
            "birthdate" => "nullable|date",
            "is_spe" => "nullable|boolean",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->identification_type === Client::IDENTIFICATION_TYPE_JURIDICO) {
                if (!empty($this->last_name)) {
                    $validator->errors()->add('last_name', 'Si el usuario es una entidad jurídica, el apellido no es necesario.');
                }
                if (!empty($this->company_id)) {
                    $validator->errors()->add('company_id', 'Si el usuario es una entidad jurídica, la compañía no es necesaria.');
                }
            }

            $existingClient = Client::where('identification', $this->identification)
                ->where('id', '!=', $this->id)
                ->first();

            if ($existingClient) {
                $validator->errors()->add('identification', 'No se puede actualizar porque la cédula/RIF ya está en uso por otro cliente.');
            }
        });
    }

    public function messages()
    {
        return [
            // ID Cliente
            'id.required' => 'El ID del cliente es obligatorio',
            'id.numeric' => 'El ID debe ser un valor numérico',
            'id.exists' => 'El cliente no existe en nuestros registros',

            // Nombre
            'name.required' => 'El nombre es obligatorio',
            'name.string' => 'El nombre debe ser texto',
            'name.max' => 'El nombre no puede exceder 255 caracteres',

            // Apellido
            'last_name.string' => 'El apellido debe ser texto',
            'last_name.max' => 'El apellido no puede exceder 255 caracteres',

            // Email
            'email.string' => 'El correo electrónico debe ser texto',
            'email.max' => 'El correo no puede exceder 255 caracteres',
            'email.email' => 'Debe ingresar un correo electrónico válido',

            // Tipo de identificación
            'identification_type.required' => 'El tipo de documento es obligatorio',
            'identification_type.string' => 'El tipo de documento debe ser texto',
            'identification_type.in' => 'Tipo de documento inválido. Opciones válidas: V-, J-, G-, E-',

            // Identificación
            'identification.required' => 'La cédula/RIF es obligatoria',
            'identification.string' => 'La cédula/RIF debe ser texto',
            'identification.min' => 'La cédula/RIF debe tener al menos 7 caracteres',
            'identification.max' => 'La cédula/RIF no puede exceder 9 caracteres',

            // Teléfono
            'phone.required' => 'El teléfono es obligatorio',
            'phone.string' => 'El teléfono debe ser texto',
            'phone.max' => 'El teléfono no puede exceder 50 caracteres',

            // Dirección
            'address.required' => 'La dirección es obligatoria',
            'address.string' => 'La dirección debe ser texto',

            // Compañía
            'company_id.exists' => 'La empresa seleccionada no existe',

            // Fecha de nacimiento (CORRECCIÓN: estaba como "data" en lugar de "date")
            'birthdate.date' => 'La fecha de nacimiento debe tener un formato válido',

            // Mensaje adicional que faltaba para la validación email:rfc,dns
            'email.email' => 'El formato del correo electrónico no es válido'
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
            "id" => $this->id,
            "name" => $this->name,
            "last_name" => $this->last_name,
            "identification_type" => $this->identification_type,
            "identification" => $this->identification,
            "phone" => $this->phone,
            "address" => $this->address,
            "company_id" => $this->company_id,
            "email" => $this->email,
            "birthdate" => $this?->birthdate,
            "is_spe" => $this->is_spe,
        ]);
    }
}
