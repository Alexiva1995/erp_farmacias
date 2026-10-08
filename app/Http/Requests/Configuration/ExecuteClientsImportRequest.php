<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteClientsImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clients'                          => ['required', 'array', 'min:1'],
            'clients.*.identification_type'    => ['required', 'string', 'in:V-,E-,J-,G-'],
            'clients.*.identification'         => ['required', 'string'],
            'clients.*.name'                   => ['required', 'string'],
            'clients.*.last_name'              => ['nullable', 'string'],
            'clients.*.phone'                  => ['nullable', 'string'],
            'clients.*.address'                => ['nullable', 'string'],
            'clients.*.is_new'                 => ['required', 'boolean'],
            'clients.*.existing_id'            => ['nullable', 'integer'],
            'clients.*.update_data'            => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'clients.required'                      => 'Debe enviar al menos un cliente para procesar.',
            'clients.array'                         => 'La estructura de clientes debe ser una lista.',
            'clients.*.identification.required'     => 'El número de cédula/RIF es obligatorio.',
            'clients.*.name.required'               => 'El nombre del cliente es obligatorio.',
        ];
    }
}
