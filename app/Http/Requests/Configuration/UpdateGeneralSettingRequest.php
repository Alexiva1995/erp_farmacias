<?php

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtener las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fiscal_mode' => 'nullable|string|in:demo,activa',
            'special_taxpayer_status' => 'nullable|string|in:activa,desactivada',
            'all_foreign_sales_spe' => 'nullable|boolean',
            'app_name' => 'nullable|string|max:255',
            'app_rif' => 'nullable|string|max:255',
            'app_logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:2048',
            'app_favicon' => 'nullable|image|mimes:jpeg,png,jpg,ico,svg|max:1024',
            'primary_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'tertiary_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'footer_text' => 'nullable|string|max:500',
            'blind_cash_closure' => 'nullable|boolean',
            'business_type' => 'nullable|string|in:pharmacy,restaurant,sports_rental,minimarket',
            'default_currency' => 'nullable|string|in:COP,USD,BS',
            'ecommerce_menu' => 'nullable|array',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string',
            'hero_tagline' => 'nullable|string|max:255',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:4096',
            'hero_button_text' => 'nullable|string|max:255',
            'section2_title' => 'nullable|string|max:255',
            'section2_subtitle' => 'nullable|string',
            'section2_tagline' => 'nullable|string|max:255',
            'section2_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:4096',
            'section2_button_text' => 'nullable|string|max:255',
            'section3_title' => 'nullable|string|max:255',
            'section3_subtitle' => 'nullable|string',
            'section3_tagline' => 'nullable|string|max:255',
            'section3_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:4096',
            'section3_button_text' => 'nullable|string|max:255',
            'cyclic_inventory_mode' => 'nullable|string|in:simple,double',
            'cyclic_inventory_barcode_required' => 'nullable|boolean',
            'enable_lots' => 'nullable|boolean',
            'tpv_mode' => 'nullable|string|in:simple,complete',
        ];
    }
}
