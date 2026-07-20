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
            'ecommerce_menu' => $this->ecommerce_menu ?? [],
            'hero_title' => $this->hero_title ?? 'YOUR NEW BOMB NUDES',
            'hero_subtitle' => $this->hero_subtitle ?? 'Tonos sofisticados, texturas sedosas y fórmulas de alta gama diseñadas para realzar tu belleza natural con un acabado impecable de pasarela.',
            'hero_tagline' => $this->hero_tagline ?? 'NUEVA COLECCIÓN',
            'hero_image' => $this->hero_image ?? '/tova_editorial_campaign_1782228591006.png',
            'hero_button_text' => $this->hero_button_text ?? 'COMPRAR AHORA',
            'section2_title' => $this->section2_title ?? 'MEET YOUR DONE-IN-ONE TINTED MOISTURIZER',
            'section2_subtitle' => $this->section2_subtitle ?? 'Nuestra fórmula ultraligera que unifica el tono de la piel, hidrata profundamente y aporta una luminosidad natural y fresca durante todo el día. Disponible en 25 tonos flexibles.',
            'section2_tagline' => $this->section2_tagline ?? 'PIEL RADIANTE',
            'section2_image' => $this->section2_image ?? '/tova_product_tint_1782228603853.png',
            'section2_button_text' => $this->section2_button_text ?? 'DESCUBRIR TONOS',
            'section3_title' => $this->section3_title ?? 'SUN STALK\'R SOUFFLÉ PRESSED MOUSSE BRONZER',
            'section3_subtitle' => $this->section3_subtitle ?? 'El bronceador definitivo que aporta calidez instantánea a tu rostro con un acabado sedoso y de larga duración. Su textura mousse prensada se funde perfectamente sobre la piel sin esfuerzo.',
            'section3_tagline' => $this->section3_tagline ?? 'EFECTO SOL',
            'section3_image' => $this->section3_image ?? '/tova_product_bronzer_1782228617577.png',
            'section3_button_text' => $this->section3_button_text ?? 'COMPRAR BRONCEADOR',
            'cyclic_inventory_mode' => $this->cyclic_inventory_mode ?? 'double',
            'cyclic_inventory_barcode_required' => $this->cyclic_inventory_barcode_required ?? true,
            'enable_lots' => (bool) ($this->enable_lots ?? true),
            'tpv_mode' => $this->tpv_mode ?? 'complete',
            'enable_product_types' => (bool) ($this->enable_product_types ?? true),
            'enabled_product_types' => $this->enabled_product_types ?? ['redundantes', 'col', 'iva', 'exento', 'novaventa', 'eliminados', 'pvp', 'ingredients', 'mixed'],
            'enable_favorites' => (bool) ($this->enable_favorites ?? true),
            'enable_variations' => (bool) ($this->enable_variations ?? true),
            'enable_merge'      => (bool) ($this->enable_merge      ?? false),
            'enable_groups'     => (bool) ($this->enable_groups     ?? true),
            'enable_stock_control' => (bool) ($this->enable_stock_control ?? true),
            'enable_expirations' => (bool) ($this->enable_expirations ?? true),
            'enable_brand_groups' => (bool) ($this->enable_brand_groups ?? false),
            'enable_donations' => (bool) ($this->enable_donations ?? true),
            'enable_locations' => (bool) ($this->enable_locations ?? true),
            'enable_optimization' => (bool) ($this->enable_optimization ?? true),
            'traceability_mode' => $this->traceability_mode ?? 'units',
            'enable_dishes' => (bool) ($this->enable_dishes ?? true),
            'product_form_fields' => $this->product_form_fields ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
