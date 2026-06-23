<?php

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralSettingResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiscal_mode' => $this->fiscal_mode,
            'special_taxpayer_status' => $this->special_taxpayer_status,
            'all_foreign_sales_spe' => (bool) $this->all_foreign_sales_spe,
            'app_name' => $this->app_name ?? 'Tova - Cerebro Operativo',
            'app_rif' => $this->app_rif,
            'app_logo' => $this->app_logo,
            'app_favicon' => $this->app_favicon,
            'primary_color' => $this->primary_color ?? '#E20074',
            'secondary_color' => $this->secondary_color ?? '#7A0099',
            'tertiary_color' => $this->tertiary_color ?? '#F5C842',
            'footer_text' => $this->footer_text ?? 'Todos los derechos reservados de Tova',
            'blind_cash_closure' => (bool) $this->blind_cash_closure,
            'business_type' => $this->business_type ?? 'pharmacy',
            'default_currency' => $this->default_currency ?? 'COP',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
