<?php

namespace Tests\Feature\Operations;

use App\Models\Category;
use App\Models\CategoryOffer;
use App\Models\Dish;
use App\Models\ExpirationOffer;
use App\Models\GeneralPromotion;
use App\Models\IndividualOffer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Order\OrderActionService;
use App\Services\Promotions\PromotionResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnhancedPromotionsPymeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Admin',
                'guard_name' => 'web',
                'menu_permissions' => ['*'],
            ]
        );

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_general_promotion_respects_schedule_days_channel_coupon_and_min_amount(): void
    {
        $promo = GeneralPromotion::create([
            'name' => 'Happy Hour Salón',
            'type' => 'general',
            'discount_percentage' => 20,
            'is_active' => true,
            'days_of_week' => [5], // Viernes
            'start_time' => '17:00',
            'end_time' => '20:00',
            'sales_channel' => 'dine_in',
            'min_order_amount' => 15.00,
            'coupon_code' => 'VIERNES20',
        ]);

        // Viernes 18:00 en salón
        $fridayHappyHour = Carbon::parse('2026-11-20 18:00:00'); // 2026-11-20 es viernes
        $this->assertTrue($promo->isCurrentlyValid($fridayHappyHour, 'dine_in'));
        $this->assertFalse($promo->isCurrentlyValid($fridayHappyHour, 'takeaway_delivery'));

        // Viernes 15:00 fuera de horario
        $fridayEarly = Carbon::parse('2026-11-20 15:00:00');
        $this->assertFalse($promo->isCurrentlyValid($fridayEarly, 'dine_in'));

        // Sábado 18:00 fuera de día
        $saturday = Carbon::parse('2026-11-21 18:00:00');
        $this->assertFalse($promo->isCurrentlyValid($saturday, 'dine_in'));
    }

    public function test_dish_receives_individual_and_category_offers(): void
    {
        $category = Category::create(['name' => 'Hamburguesas', 'status' => true]);
        $dish = Dish::create([
            'name' => 'Hamburguesa Clásica',
            'category_id' => $category->id,
            'designated_price' => 10.00,
            'suggested_price' => 10.00,
            'cost_price' => 4.00,
            'cpv' => 4.00,
            'percentage_profit' => 60.00,
            'status' => 'active',
        ]);

        CategoryOffer::create([
            'category_id' => $category->id,
            'discount_percentage' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'is_active' => true,
        ]);

        IndividualOffer::create([
            'dish_id' => $dish->id,
            'discount_percent' => 25,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'is_active' => true,
        ]);

        $resolver = app(PromotionResolverService::class);
        $discount = $resolver->resolveItemBaseDiscount(null, $dish, Carbon::now(), 'dine_in');

        $this->assertSame('individual', $discount['type']);
        $this->assertSame(25.0, $discount['pct']);
    }

    public function test_excluded_products_are_ignored_in_expiration_and_general_promotions(): void
    {
        $category = Category::create(['name' => 'Psicotrópicos', 'status' => true]);
        $product = Product::create([
            'name' => 'Producto Regulado',
            'category_id' => $category->id,
            'sale_price' => 20.00,
            'unit_cost' => 15.00,
            'stock' => 10,
            'exclude_from_promotions' => true,
        ]);

        $expOffer = ExpirationOffer::create([
            'months_to_expiration' => 3,
            'discount_percentage' => 30,
            'is_active' => true,
        ]);
        $expOffer->excludedProducts()->attach($product->id);

        $resolver = app(PromotionResolverService::class);
        $activeOffers = $resolver->getActiveExpirationOffersForProduct($product->id);
        $this->assertCount(0, $activeOffers);

        $order = Order::create([
            'seller_id' => $this->admin->id,
            'currency' => 'USD',
            'status' => Order::PENDING,
            'order_date' => now(),
            'total_amount' => 20,
            'total_amount_usd' => 20,
            'money_returns' => 0,
        ]);

        $detail = OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 20,
            'unit_price_usd' => 20,
            'unit_cost' => 15,
            'product_type' => 'product',
        ]);

        GeneralPromotion::create([
            'name' => 'Global 15%',
            'type' => 'general',
            'discount_percentage' => 15,
            'is_active' => true,
        ]);

        app(OrderActionService::class)->applyGeneralPromotions($order);
        $detail->refresh();

        $this->assertNull($detail->discount_type);
        $this->assertEquals(20.0, (float) $detail->price);
    }

    public function test_can_create_general_promotion_with_new_fields_and_fetch_metrics(): void
    {
        $response = $this->postJson('/api/tpv/promotions/general-promotions', [
            'name' => 'Promo Cupón 10',
            'type' => 'general',
            'discount_percentage' => 10,
            'is_active' => true,
            'days_of_week' => [1, 3, 5],
            'start_time' => '12:00',
            'end_time' => '15:00',
            'min_order_amount' => 10,
            'coupon_code' => 'ALMUERZO10',
            'sales_channel' => 'dine_in',
            'stackable' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Promo Cupón 10')
            ->assertJsonPath('data.coupon_code', 'ALMUERZO10')
            ->assertJsonPath('data.sales_channel', 'dine_in');

        $metricsResponse = $this->getJson('/api/tpv/promotions/metrics');
        $metricsResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => ['total_uses', 'total_sales_usd', 'gross_margin_usd'],
                    'by_type',
                ],
            ]);
    }
}
