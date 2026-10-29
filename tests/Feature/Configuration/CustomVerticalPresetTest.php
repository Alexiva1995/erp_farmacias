<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Contracts\VerticalPresetServiceInterface;
use App\Enums\BusinessVerticalEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomVerticalPresetTest extends TestCase
{
    use RefreshDatabase;

    protected VerticalPresetServiceInterface $presetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presetService = app(VerticalPresetServiceInterface::class);
        Cache::flush();
    }

    public function test_drogueria_preset_is_available_in_presets_list(): void
    {
        $presets = $this->presetService->getAvailablePresets();
        $keys = array_column($presets, 'key');

        $this->assertContains('drogueria', $keys);
    }

    public function test_register_and_apply_custom_vertical_preset(): void
    {
        $custom = $this->presetService->registerCustomPreset(
            'ferreteria',
            'Ferretería & Construcción',
            'Gestión de herramientas, materiales pesados y venta por metros/kilos.',
            [
                'tpv_mode' => 'complete',
                'enable_lots' => false,
                'enable_expirations' => false,
                'enable_stock_control' => true,
            ]
        );

        $this->assertEquals('ferreteria', $custom['key']);

        $presets = $this->presetService->getAvailablePresets();
        $keys = array_column($presets, 'key');
        $this->assertContains('ferreteria', $keys);

        // Aplicar la nueva vertical personalizada
        $settings = $this->presetService->applyPreset('ferreteria');
        $this->assertEquals('ferreteria', $settings->business_type);
        $this->assertFalse($settings->enable_lots);
        $this->assertFalse($settings->enable_expirations);
    }
}
