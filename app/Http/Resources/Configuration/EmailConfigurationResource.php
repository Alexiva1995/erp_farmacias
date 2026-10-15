<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailConfigurationResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     */
    public function toArray(Request $request): array
    {
        $defaultTemplates = app(\App\Services\Ecommerce\EcommerceMailService::class)->getDefaultTemplates();
        $savedTemplates = is_array($this->ecommerce_email_templates) ? $this->ecommerce_email_templates : [];

        // Combinar plantillas guardadas con las predeterminadas
        $mergedTemplates = array_merge($defaultTemplates, $savedTemplates);

        return [
            'mail_mailer' => $this->mail_mailer ?: 'smtp',
            'mail_host' => $this->mail_host ?: '',
            'mail_port' => (int) ($this->mail_port ?: 587),
            'mail_username' => $this->mail_username ?: '',
            'mail_password' => $this->mail_password ?: '',
            'has_password' => !empty($this->mail_password),
            'mail_encryption' => $this->mail_encryption ?: 'tls',
            'mail_from_address' => $this->mail_from_address ?: '',
            'mail_from_name' => $this->mail_from_name ?: ($this->app_name ?: ''),
            'ecommerce_email_templates' => $mergedTemplates,
        ];
    }
}
