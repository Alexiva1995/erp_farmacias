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
            'type'          => 'required|in:ftp,sftp,http,api',
            'host'          => 'required|string|max:500',
            'port'          => 'nullable|numeric|min:1|max:65535',
            'username'      => 'nullable|string|max:255',
            'password'      => 'nullable|string',
            'path'          => 'nullable|string|max:500',
            'pasv'          => 'boolean',
            'has_header'    => 'boolean',
            'invoice_path'  => 'nullable|string|max:500',
        ];
    }
}
