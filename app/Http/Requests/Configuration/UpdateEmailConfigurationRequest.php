<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailConfigurationRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la configuración de correos y plantillas.
     */
    public function rules(): array
    {
        return [
            'mail_mailer' => ['nullable', 'string', 'max:50'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'ecommerce_email_templates' => ['nullable', 'array'],
            'ecommerce_email_templates.order_created' => ['nullable', 'array'],
            'ecommerce_email_templates.order_created.enabled' => ['nullable', 'boolean'],
            'ecommerce_email_templates.order_created.subject' => ['nullable', 'string', 'max:255'],
            'ecommerce_email_templates.order_created.body' => ['nullable', 'string'],
            'ecommerce_email_templates.order_approved' => ['nullable', 'array'],
            'ecommerce_email_templates.order_approved.enabled' => ['nullable', 'boolean'],
            'ecommerce_email_templates.order_approved.subject' => ['nullable', 'string', 'max:255'],
            'ecommerce_email_templates.order_approved.body' => ['nullable', 'string'],
            'ecommerce_email_templates.order_shipped' => ['nullable', 'array'],
            'ecommerce_email_templates.order_shipped.enabled' => ['nullable', 'boolean'],
            'ecommerce_email_templates.order_shipped.subject' => ['nullable', 'string', 'max:255'],
            'ecommerce_email_templates.order_shipped.body' => ['nullable', 'string'],
        ];
    }

    /**
     * Mensajes personalizados de error.
     */
    public function messages(): array
    {
        return [
            'mail_port.integer' => 'El puerto debe ser un número entero.',
            'mail_from_address.email' => 'El correo remitente debe ser una dirección de email válida.',
        ];
    }
}
