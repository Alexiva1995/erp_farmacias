<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class SaveConnectionConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'          => 'required|in:ftp,sftp,http,api,dronena_bot,file,email',
            'host'          => 'nullable|string|max:500',
            'port'          => 'nullable|numeric|min:1|max:65535',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string',
            'path'          => 'nullable|string|max:500',
            'pasv'          => 'boolean',
            'has_header'    => 'boolean',
            'invoice_path'  => 'nullable|string|max:500',
            // Configuración FTP complementaria para transmisión de pedidos (PC-CORREO)
            'ftp_orders_enabled'  => 'nullable|boolean',
            'ftp_orders_host'     => 'nullable|string|max:500',
            'ftp_orders_port'     => 'nullable|numeric|min:1|max:65535',
            'ftp_orders_username' => 'nullable|string|max:255',
            'ftp_orders_password' => 'nullable|string',
            'ftp_orders_path'     => 'nullable|string|max:500',
        ];
    }
}
