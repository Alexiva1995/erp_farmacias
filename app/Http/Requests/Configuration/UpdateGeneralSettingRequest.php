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
            'app_logo' => 'nullable',
            'app_favicon' => 'nullable',
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
            'hero_image' => 'nullable',
            'hero_button_text' => 'nullable|string|max:255',
            'section2_title' => 'nullable|string|max:255',
            'section2_subtitle' => 'nullable|string',
            'section2_tagline' => 'nullable|string|max:255',
            'section2_image' => 'nullable',
            'section2_button_text' => 'nullable|string|max:255',
            'section3_title' => 'nullable|string|max:255',
            'section3_subtitle' => 'nullable|string',
            'section3_tagline' => 'nullable|string|max:255',
            'section3_image' => 'nullable',
            'section3_button_text' => 'nullable|string|max:255',
            'cyclic_inventory_mode' => 'nullable|string|in:simple,double',
            'cyclic_inventory_barcode_required' => 'nullable|boolean',
            'enable_lots' => 'nullable|boolean',
            'tpv_mode' => 'nullable|string|in:simple,complete',
            'enable_product_types' => 'nullable|boolean',
            'enabled_product_types' => 'nullable|array',
            'enable_favorites' => 'nullable|boolean',
            'enable_variations' => 'nullable|boolean',
            'enable_groups' => 'nullable|boolean',
            'enable_merge' => 'nullable|boolean',
            'product_form_fields' => 'nullable|array',
            'enable_stock_control' => 'nullable|boolean',
            'enable_expirations' => 'nullable|boolean',
            'enable_brand_groups' => 'nullable|boolean',
            'enable_donations' => 'nullable|boolean',
            'enable_locations' => 'nullable|boolean',
            'enable_optimization' => 'nullable|boolean',
            'traceability_mode' => 'nullable|string|in:units,consumption',
            'enable_dishes' => 'nullable|boolean',
            'enable_quotations' => 'nullable|boolean',
            'quotation_style' => 'nullable|string|in:pharmacy,restaurant,cosmetic',
            'tpv_style' => 'nullable|string|in:pharmacy,restaurant,sports_rental',
            'enable_flash_checkout' => 'nullable|boolean',
            'tpv_payment_methods' => 'nullable|array',
            'tpv_rate_type' => 'nullable|string|in:bcv,eur,binance',
            'enabled_offer_types' => 'nullable|array',
            'enabled_crm_views' => 'nullable|array',
            'enabled_rrhh_views' => 'nullable|array',
            'enabled_supplier_views' => 'nullable|array',
            'enabled_supplier_types' => 'nullable|array',
            'supplier_form_fields' => 'nullable|array',
            'expense_supplier_form_fields' => 'nullable|array',
            'enabled_finance_views' => 'nullable|array',
            'profitability_calculation_type' => 'nullable|string|in:simple,compound',
            'expense_mode' => 'nullable|string|in:simple,real',
            'expense_auto_approve' => 'nullable|boolean',
            'enabled_ia_assistant_views' => 'nullable|array',
            'enable_invoices' => 'nullable|boolean',
            'enable_invoice_locations' => 'nullable|boolean',
        ];
    }
}
