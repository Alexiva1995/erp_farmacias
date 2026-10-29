<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Contracts\Repositories\GeneralSettingRepositoryInterface;
use App\Contracts\VerticalPresetServiceInterface;
use App\Enums\BusinessVerticalEnum;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VerticalPresetService implements VerticalPresetServiceInterface
{
    public function __construct(
        protected GeneralSettingRepositoryInterface $settingRepository
    ) {}

    /**
     * @inheritDoc
     */
    public function applyPreset(BusinessVerticalEnum|string $vertical, bool $preserveIdentity = true): GeneralSetting
    {
        $verticalKey = $vertical instanceof BusinessVerticalEnum ? $vertical->value : strtolower(trim((string) $vertical));
        $presetConfig = $this->getPresetConfig($vertical);

        return DB::transaction(function () use ($verticalKey, $presetConfig, $preserveIdentity) {
            $currentSettings = $this->settingRepository->getSettings();

            if ($preserveIdentity) {
                // Conservar campos identitarios, legales y fiscales existentes
                $protectedFields = [
                    'app_name',
                    'app_rif',
                    'rif',
                    'address',
                    'fiscal_printer_serial',
                    'fiscal_machine_type',
                    'factory_printer_ip',
                    'factory_printer_port',
                    'app_logo',
                    'app_favicon',
                    'app_signature_stamp',
                    'primary_color',
                    'secondary_color',
                    'tertiary_color',
                    'footer_text',
                    'default_currency',
                    'fiscal_mode',
                    'special_taxpayer_status',
                ];

                foreach ($protectedFields as $field) {
                    unset($presetConfig[$field]);
                }
            }

            // Aplicar actualización atómica
            $updated = $this->settingRepository->updateSettings($presetConfig);

            // Invalidar cachés operativas
            Cache::forget('general_settings');
            Cache::forget('bootstrap_config');
            Cache::forget('branding_settings');

            // Sincronizar campo vertical en la base de datos central de tenants si estamos en un tenant activo
            if (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant) {
                try {
                    tenancy()->tenant->update([
                        'vertical' => $verticalKey,
                    ]);
                } catch (\Throwable $e) {
                    // Silencioso si ocurre error secundario en master
                }
            }

            return $updated;
        });
    }

    /**
     * @inheritDoc
     */
    public function getPresetConfig(BusinessVerticalEnum|string $vertical): array
    {
        if (is_string($vertical)) {
            $custom = $this->getCustomPresets();
            $normalizedKey = strtolower(trim($vertical));
            if (isset($custom[$normalizedKey])) {
                return $custom[$normalizedKey]['config'];
            }
        }

        $enum = $this->resolveEnum($vertical);

        return match ($enum) {
            BusinessVerticalEnum::PHARMACY => [
                'business_type' => 'pharmacy',
                'tpv_mode' => 'complete',
                'tpv_style' => 'pharmacy',
                'quotation_style' => 'pharmacy',
                'enable_dishes' => false,
                'enable_tables' => false,
                'enable_kds' => false,
                'enable_modifiers' => false,
                'enable_split_check' => false,
                'enable_tips' => false,
                'default_service_fee_pct' => 0.00,
                'enable_sub_recipes' => false,
                'enable_reservations' => false,
                'enable_lots' => true,
                'enable_expirations' => true,
                'enable_groups' => true,
                'enable_brand_groups' => false,
                'enable_donations' => true,
                'enable_locations' => true,
                'enable_stock_control' => true,
                'enable_optimization' => true,
                'traceability_mode' => 'units',
                'enable_flash_checkout' => false,
                'product_form_fields' => [
                    'active_ingredient',
                    'laboratory',
                    'psychotropic',
                    'is_chronic',
                    'barcode',
                    'presentation',
                    'unit_of_measure',
                ],
                'enabled_product_types' => [
                    'iva',
                    'exento',
                    'col',
                    'pvp',
                    'redundantes',
                    'novaventa',
                    'eliminados',
                    'ingredients',
                    'mixed',
                ],
                'enabled_offer_types' => [
                    'general',
                    'individual',
                    'category',
                    'pack',
                    'company',
                    'doctor',
                    'prescription',
                    'expiration',
                ],
                'enabled_crm_views' => [
                    'clients',
                    'companies',
                    'doctors',
                    'specialties',
                    'chronic',
                    'loyalty',
                ],
                'enabled_telegram_views' => [
                    'generales',
                    'farmacia',
                ],
            ],

            BusinessVerticalEnum::RESTAURANT => [
                'business_type' => 'restaurant',
                'tpv_mode' => 'complete',
                'tpv_style' => 'restaurant',
                'quotation_style' => 'restaurant',
                'enable_dishes' => true,
                'enable_tables' => true,
                'enable_kds' => true,
                'enable_modifiers' => true,
                'enable_split_check' => true,
                'enable_tips' => true,
                'default_service_fee_pct' => 10.00,
                'enable_sub_recipes' => true,
                'enable_reservations' => true,
                'enable_lots' => true,
                'enable_expirations' => true,
                'enable_groups' => true,
                'enable_brand_groups' => false,
                'enable_donations' => false,
                'enable_locations' => true,
                'enable_stock_control' => true,
                'enable_optimization' => true,
                'traceability_mode' => 'units',
                'enable_flash_checkout' => false,
                'product_form_fields' => [
                    'presentation',
                    'unit_of_measure',
                    'barcode',
                ],
                'enabled_product_types' => [
                    'ingredients',
                    'mixed',
                    'iva',
                    'exento',
                    'eliminados',
                ],
                'enabled_offer_types' => [
                    'general',
                    'individual',
                    'category',
                    'pack',
                ],
                'enabled_crm_views' => [
                    'clients',
                    'companies',
                    'loyalty',
                ],
                'enabled_telegram_views' => [
                    'generales',
                    'restaurante',
                ],
            ],

            BusinessVerticalEnum::FAST_FOOD => [
                'business_type' => 'restaurant',
                'tpv_mode' => 'complete',
                'tpv_style' => 'restaurant',
                'quotation_style' => 'restaurant',
                'enable_dishes' => true,
                'enable_tables' => false,
                'enable_kds' => true,
                'enable_modifiers' => true,
                'enable_split_check' => false,
                'enable_tips' => false,
                'default_service_fee_pct' => 0.00,
                'enable_sub_recipes' => true,
                'enable_reservations' => false,
                'enable_lots' => true,
                'enable_expirations' => true,
                'enable_groups' => true,
                'enable_brand_groups' => false,
                'enable_donations' => false,
                'enable_locations' => true,
                'enable_stock_control' => true,
                'enable_optimization' => true,
                'traceability_mode' => 'units',
                'enable_flash_checkout' => true,
                'product_form_fields' => [
                    'presentation',
                    'unit_of_measure',
                    'barcode',
                ],
                'enabled_product_types' => [
                    'ingredients',
                    'mixed',
                    'iva',
                    'exento',
                    'eliminados',
                ],
                'enabled_offer_types' => [
                    'general',
                    'individual',
                    'category',
                    'pack',
                ],
                'enabled_crm_views' => [
                    'clients',
                    'companies',
                    'loyalty',
                ],
                'enabled_telegram_views' => [
                    'generales',
                    'restaurante',
                ],
            ],

            BusinessVerticalEnum::MINIMARKET => [
                'business_type' => 'minimarket',
                'tpv_mode' => 'complete',
                'tpv_style' => 'pharmacy',
                'quotation_style' => 'pharmacy',
                'enable_dishes' => false,
                'enable_tables' => false,
                'enable_kds' => false,
                'enable_modifiers' => false,
                'enable_split_check' => false,
                'enable_tips' => false,
                'default_service_fee_pct' => 0.00,
                'enable_sub_recipes' => false,
                'enable_reservations' => false,
                'enable_lots' => true,
                'enable_expirations' => true,
                'enable_groups' => true,
                'enable_brand_groups' => true,
                'enable_donations' => false,
                'enable_locations' => true,
                'enable_stock_control' => true,
                'enable_optimization' => true,
                'traceability_mode' => 'units',
                'enable_flash_checkout' => false,
                'product_form_fields' => [
                    'barcode',
                    'presentation',
                    'unit_of_measure',
                ],
                'enabled_product_types' => [
                    'iva',
                    'exento',
                    'col',
                    'pvp',
                    'eliminados',
                ],
                'enabled_offer_types' => [
                    'general',
                    'individual',
                    'category',
                    'pack',
                ],
                'enabled_crm_views' => [
                    'clients',
                    'companies',
                    'loyalty',
                ],
                'enabled_telegram_views' => [
                    'generales',
                ],
            ],

            BusinessVerticalEnum::DROGUERIA => [
                'business_type' => 'drogueria',
                'tpv_mode' => 'complete',
                'tpv_style' => 'pharmacy',
                'quotation_style' => 'pharmacy',
                'enable_dishes' => false,
                'enable_tables' => false,
                'enable_kds' => false,
                'enable_modifiers' => false,
                'enable_split_check' => false,
                'enable_tips' => false,
                'default_service_fee_pct' => 0.00,
                'enable_sub_recipes' => false,
                'enable_reservations' => false,
                'enable_lots' => true,
                'enable_expirations' => true,
                'enable_groups' => true,
                'enable_brand_groups' => true,
                'enable_donations' => false,
                'enable_locations' => true,
                'enable_stock_control' => true,
                'enable_optimization' => true,
                'traceability_mode' => 'units',
                'enable_flash_checkout' => false,
                'product_form_fields' => [
                    'active_ingredient',
                    'laboratory',
                    'credit_days',
                    'sanitary_registration',
                    'barcode',
                    'presentation',
                    'unit_of_measure',
                    'requires_cold_chain',
                ],
                'enabled_product_types' => [
                    'iva',
                    'exento',
                    'col',
                    'pvp',
                    'novaventa',
                    'eliminados',
                ],
                'enabled_offer_types' => [
                    'general',
                    'individual',
                    'category',
                    'pack',
                    'company',
                    'expiration',
                ],
                'enabled_crm_views' => [
                    'clients',
                    'companies',
                    'chronic',
                ],
                'enabled_telegram_views' => [
                    'generales',
                    'farmacia',
                ],
            ],
        };
    }

    /**
     * @inheritDoc
     */
    public function getAvailablePresets(): array
    {
        $standard = array_map(function (BusinessVerticalEnum $case) {
            return [
                'key' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
                'is_custom' => false,
            ];
        }, BusinessVerticalEnum::cases());

        $custom = array_values(array_map(function ($item) {
            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'description' => $item['description'],
                'is_custom' => true,
            ];
        }, $this->getCustomPresets()));

        return array_merge($standard, $custom);
    }

    /**
     * @inheritDoc
     */
    public function registerCustomPreset(string $key, string $label, string $description, array $config): array
    {
        $normalizedKey = strtolower(trim($key));
        $customPresets = $this->getCustomPresets();

        $entry = [
            'key' => $normalizedKey,
            'label' => $label,
            'description' => $description,
            'config' => array_merge([
                'business_type' => $normalizedKey,
                'tpv_mode' => 'complete',
                'tpv_style' => 'pharmacy',
                'quotation_style' => 'pharmacy',
                'enable_lots' => true,
                'enable_expirations' => true,
                'enable_groups' => true,
                'enable_locations' => true,
                'enable_stock_control' => true,
            ], $config),
        ];

        $customPresets[$normalizedKey] = $entry;

        Cache::forever('custom_vertical_presets', $customPresets);

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function getCustomPresets(): array
    {
        return Cache::get('custom_vertical_presets', []);
    }

    /**
     * Resolver la instancia del Enum asegurando tipo estricto.
     */
    protected function resolveEnum(BusinessVerticalEnum|string $vertical): BusinessVerticalEnum
    {
        if ($vertical instanceof BusinessVerticalEnum) {
            return $vertical;
        }

        $enum = BusinessVerticalEnum::tryFromValue($vertical);
        if (!$enum) {
            throw new InvalidArgumentException(
                "La vertical '{$vertical}' no es válida. Valores permitidos: " . implode(', ', BusinessVerticalEnum::values())
            );
        }

        return $enum;
    }
}
