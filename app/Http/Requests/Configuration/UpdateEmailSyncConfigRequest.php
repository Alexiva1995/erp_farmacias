<?php

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSyncConfigRequest extends FormRequest
{
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
            'gmail_sync_email' => ['nullable', 'email', 'max:255'],
            'gmail_sync_password' => ['nullable', 'string', 'max:255'],
            'gmail_sync_host' => ['nullable', 'string', 'max:255'],
            'gmail_sync_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'gmail_sync_folder' => ['nullable', 'string', 'max:100'],
            'gmail_sync_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Nombres de atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'gmail_sync_email' => 'correo de Gmail',
            'gmail_sync_password' => 'contraseña de aplicación',
            'gmail_sync_host' => 'servidor IMAP',
            'gmail_sync_port' => 'puerto IMAP',
            'gmail_sync_folder' => 'carpeta IMAP',
            'gmail_sync_enabled' => 'activación de sincronización',
        ];
    }
}
