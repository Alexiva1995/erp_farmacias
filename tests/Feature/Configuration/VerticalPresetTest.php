<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Contracts\Repositories\GeneralSettingRepositoryInterface;
use App\Contracts\VerticalPresetServiceInterface;
use App\Enums\BusinessVerticalEnum;
use App\Models\GeneralSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerticalPresetTest extends TestCase
{
    use RefreshDatabase;

    protected VerticalPresetServiceInterface $presetService;
    protected GeneralSettingRepositoryInterface $settingRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presetService = app(VerticalPresetServiceInterface::class);
        $this->settingRepository = app(GeneralSettingRepositoryInterface::class);
    }

    public function test_available_presets_returns_all_enum_cases(): void
    {
        $presets = $this->presetService->getAvailablePresets();

        $this->assertCount(count(BusinessVerticalEnum::cases()), $presets);
        $keys = array_column($presets, 'key');

        $this->assertContains('pharmacy', $keys);
        $this->assertContains('restaurant', $keys);
        $this->assertContains('fast_food', $keys);
        $this->assertContains('minimarket', $keys);
    }

    public function test_apply_pharmacy_preset_configures_pharmacy_flags(): void
    {
        $settings = $this->presetService->applyPreset(BusinessVerticalEnum::PHARMACY);

        $this->assertEquals('pharmacy', $settings->business_type);
        $this->assertEquals('pharmacy', $settings->tpv_style);
        $this->assertFalse($settings->enable_dishes);
        $this->assertFalse($settings->enable_tables);
        $this->assertFalse($settings->enable_kds);
        $this->assertFalse($settings->enable_modifiers);
        $this->assertFalse($settings->enable_split_check);
        $this->assertFalse($settings->enable_tips);
        $this->assertFalse($settings->enable_sub_recipes);
        $this->assertTrue($settings->enable_lots);
        $this->assertTrue($settings->enable_expirations);
        $this->assertContains('active_ingredient', $settings->product_form_fields);
        $this->assertContains('laboratory', $settings->product_form_fields);
    }

    public function test_apply_restaurant_preset_configures_restaurant_flags(): void
    {
        $settings = $this->presetService->applyPreset(BusinessVerticalEnum::RESTAURANT);

        $this->assertEquals('restaurant', $settings->business_type);
        $this->assertEquals('restaurant', $settings->tpv_style);
        $this->assertTrue($settings->enable_dishes);
        $this->assertTrue($settings->enable_tables);
        $this->assertTrue($settings->enable_kds);
        $this->assertTrue($settings->enable_modifiers);
        $this->assertTrue($settings->enable_split_check);
        $this->assertTrue($settings->enable_tips);
        $this->assertEquals(10.00, (float) $settings->default_service_fee_pct);
        $this->assertTrue($settings->enable_sub_recipes);
        $this->assertTrue($settings->enable_reservations);
        $this->assertContains('presentation', $settings->product_form_fields);
        $this->assertNotContains('active_ingredient', $settings->product_form_fields);
    }

    public function test_apply_fast_food_preset_disables_tables_and_split_check(): void
    {
        $settings = $this->presetService->applyPreset(BusinessVerticalEnum::FAST_FOOD);

        $this->assertEquals('restaurant', $settings->business_type);
        $this->assertTrue($settings->enable_dishes);
        $this->assertFalse($settings->enable_tables);
        $this->assertTrue($settings->enable_kds);
        $this->assertTrue($settings->enable_modifiers);
        $this->assertFalse($settings->enable_split_check);
        $this->assertTrue($settings->enable_flash_checkout);
    }

    public function test_apply_preset_preserves_corporate_identity(): void
    {
        // Configurar valores identitarios iniciales
        $this->settingRepository->updateSettings([
            'app_name' => 'RESTAURANTE EL CHEF C.A.',
            'app_rif' => 'J-99887766-5',
            'primary_color' => '#123456',
        ]);

        // Aplicar preset preservando identidad
        $settings = $this->presetService->applyPreset(BusinessVerticalEnum::RESTAURANT, true);

        $this->assertEquals('RESTAURANTE EL CHEF C.A.', $settings->app_name);
        $this->assertEquals('J-99887766-5', $settings->app_rif);
        $this->assertEquals('#123456', $settings->primary_color);
        $this->assertEquals('restaurant', $settings->business_type);
    }

    public function test_granular_override_can_be_performed_after_preset(): void
    {
        // 1. Aplicar preset de restaurante
        $this->presetService->applyPreset(BusinessVerticalEnum::RESTAURANT);

        // 2. Simular que el usuario activa manualmente donaciones y cambia el estilo de cotizaciones
        $customized = $this->settingRepository->updateSettings([
            'enable_donations' => true,
            'quotation_style' => 'custom_header',
        ]);

        $this->assertEquals('restaurant', $customized->business_type);
        $this->assertTrue($customized->enable_dishes);
        $this->assertTrue($customized->enable_donations); // Sobrescrito manualmente
        $this->assertEquals('custom_header', $customized->quotation_style); // Sobrescrito manualmente
    }

    public function test_public_presets_endpoint_returns_catalog(): void
    {
        $response = $this->getJson('/api/public/general-settings/presets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['key', 'label', 'description'],
                ],
            ]);
    }

    public function test_apply_preset_endpoint_validates_vertical(): void
    {
        Role::firstOrCreate(['id' => 1], [
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create(['role_id' => 1]);

        // Petición con vertical inválida
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/general-settings/apply-preset', [
            'vertical' => 'astrophysics',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vertical']);

        // Petición con vertical válida
        $validResponse = $this->actingAs($user, 'sanctum')->postJson('/api/general-settings/apply-preset', [
            'vertical' => 'restaurant',
            'preserve_identity' => true,
        ]);

        $validResponse->assertStatus(200)
            ->assertJsonPath('data.business_type', 'restaurant')
            ->assertJsonPath('data.enable_dishes', true);
    }
}
