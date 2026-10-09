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
            "phone" => "required|string",
            "address" => "required|string",
            "company_id" => "nullable|exists:companies,id",
            "birthdate" => "nullable|date",
            "is_spe" => "nullable|boolean",
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
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

            $this->validatePhoneRules($validator, (string) $this->phone, (int) $this->id);
        });
    }

    /**
     * Valida formato venezolano válido, patrones no ficticios y unicidad en base de datos.
     */
    protected function validatePhoneRules(Validator $validator, ?string $phone, ?int $ignoreClientId = null): void
    {
        if (empty($phone)) {
            $validator->errors()->add('phone', 'El número de teléfono es obligatorio.');
            return;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $phone);

        // 1. Longitud válida (10 dígitos sin 0 inicial, 11 dígitos con 0, o 12 con prefijo país 58)
        if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 12) {
            $validator->errors()->add('phone', 'El número de teléfono debe tener 11 dígitos (Ej: 04141234567) o 10 dígitos (Ej: 4141234567).');
            return;
        }

        // 2. Prefijo venezolano válido (0412, 0414, 0424, 0416, 0426 o fijos 02XX)
        $isValidVenezuelan = (bool) preg_match('/^(58)?(0?)(412|414|424|416|426|2\d{2})\d{7}$/', $cleanPhone);
        if (!$isValidVenezuelan) {
            $validator->errors()->add('phone', 'El prefijo del teléfono no es válido. Debe iniciar con un código de área o móvil venezolano (0414, 0412, 0424, 0416, 0426, 02XX).');
            return;
        }

        // 3. Extracción de los 7 dígitos del suscriptor
        $subscriber = substr($cleanPhone, -7);

        // 4. Detección de patrones falsos, repetitivos o sin sentido
        $allRepeated = (bool) preg_match('/^(\d)\1+$/', $cleanPhone);
        $subscriberAllRepeated = (bool) preg_match('/^(\d)\1+$/', $subscriber);
        $uniqueDigitsInSubscriber = count(array_unique(str_split($subscriber)));
        $uniqueDigitsInWholeNumber = count(array_unique(str_split($cleanPhone)));

        $invalidSequences = [
            '1234567', '7654321', '0123456', '6543210', '0000000', '1111111', '2222222',
            '3333333', '4444444', '5555555', '6666666', '7777777', '8888888', '9999999',
            '1212121', '1231231', '0101010', '4445445', '5554555'
        ];

        $isDummyWholeNumber = in_array($cleanPhone, [
            '1234567890', '01234567890', '12345678901', '0000000000', '00000000000',
            '1111111111', '11111111111', '2222222222', '3333333333', '4444444444',
            '5555555555', '6666666666', '7777777777', '8888888888', '9999999999',
            '04141234567', '04121234567', '04241234567', '04161234567', '04261234567',
            '4141234567', '4121234567', '4241234567', '4161234567', '4261234567'
        ], true);

        if (
            $allRepeated ||
            $subscriberAllRepeated ||
            $isDummyWholeNumber ||
            in_array($subscriber, $invalidSequences, true) ||
            $uniqueDigitsInSubscriber <= 2 ||
            $uniqueDigitsInWholeNumber <= 2
        ) {
            $validator->errors()->add('phone', 'El número de teléfono ingresado contiene un patrón inválido o ficticio. Ingrese un número de contacto real.');
            return;
        }

        // 5. Unicidad del teléfono (evitar duplicados en base de datos excluyendo el cliente actual)
        $normalized11 = strlen($cleanPhone) === 10 ? '0' . $cleanPhone : (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '58') ? '0' . substr($cleanPhone, 2) : $cleanPhone);
        $normalized10 = substr($normalized11, 1);

        $query = Client::where(function ($q) use ($phone, $cleanPhone, $normalized11, $normalized10) {
            $q->where('phone', $phone)
              ->orWhere('phone', $cleanPhone)
              ->orWhere('phone', $normalized11)
              ->orWhere('phone', $normalized10)
              ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') IN (?, ?, ?)", [$cleanPhone, $normalized11, $normalized10]);
        })
        ->whereNotNull('phone')
        ->where('phone', '!=', '');

        if ($ignoreClientId) {
            $query->where('id', '!=', $ignoreClientId);
        }

        $existingClient = $query->first();
        if ($existingClient) {
            $validator->errors()->add('phone', "El número de teléfono ya está registrado para el cliente '{$existingClient->name} {$existingClient->last_name}' (C.I. {$existingClient->identification}).");
        }
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
