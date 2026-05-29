<?php

namespace Tests\Feature\Bi;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAnalyticsComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Client $c1;
    private Client $c2;
    private Client $c3;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear vendedor de prueba
        $this->seller = User::create([
            'username' => 'seller_bi',
            'email' => 'sellerbi@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear clientes de prueba con fechas de creación controladas
        $this->c1 = Client::create([
            'identification' => 'V-11111111',
            'name' => 'Platinum Client',
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $this->c2 = Client::create([
            'identification' => 'V-22222222',
            'name' => 'Gold Client',
            'created_at' => '2026-01-02 10:00:00',
        ]);

        $this->c3 = Client::create([
            'identification' => 'V-33333333',
            'name' => 'Silver Client',
            'created_at' => '2026-01-03 10:00:00',
        ]);
    }

    /**
     * Test completo de la respuesta de analíticas de clientes e indicadores
     */
    public function test_can_retrieve_comprehensive_customer_analytics(): void
    {
        // 1. Crear órdenes de prueba para simular LTV, recompra y valoración
        
        // Client 1 (Platinum): 3 órdenes de alto valor (Total 500 USD) en el periodo de interés
        Order::create([
            'client_id' => $this->c1->id,
            'seller_id' => $this->seller->id,
            'order_date' => '2026-05-10 12:00:00',
            'status' => 'Completed',
            'total_amount' => 200.00,
            'total_amount_usd' => 200.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        Order::create([
            'client_id' => $this->c1->id,
            'seller_id' => $this->seller->id,
            'order_date' => '2026-05-12 12:00:00',
            'status' => 'Completed',
            'total_amount' => 150.00,
            'total_amount_usd' => 150.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        Order::create([
            'client_id' => $this->c1->id,
            'seller_id' => $this->seller->id,
            'order_date' => '2026-05-15 12:00:00',
            'status' => 'Completed',
            'total_amount' => 150.00,
            'total_amount_usd' => 150.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        // Client 2 (Gold): 2 órdenes (Total 200 USD) en el periodo de interés
        Order::create([
            'client_id' => $this->c2->id,
            'seller_id' => $this->seller->id,
            'order_date' => '2026-05-11 12:00:00',
            'status' => 'Completed',
            'total_amount' => 100.00,
            'total_amount_usd' => 100.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        Order::create([
            'client_id' => $this->c2->id,
            'seller_id' => $this->seller->id,
            'order_date' => '2026-05-14 12:00:00',
            'status' => 'Completed',
            'total_amount' => 100.00,
            'total_amount_usd' => 100.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        // Client 3 (Silver): 1 orden (Total 50 USD) en el periodo de interés
        Order::create([
            'client_id' => $this->c3->id,
            'seller_id' => $this->seller->id,
            'order_date' => '2026-05-13 12:00:00',
            'status' => 'Completed',
            'total_amount' => 50.00,
            'total_amount_usd' => 50.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        // 2. Realizar la petición HTTP para consultar los KPIs analíticos
        $response = $this->actingAs($this->seller, 'sanctum')
            ->getJson('/api/bi/customers/dashboard?start_date=2026-05-01&end_date=2026-05-31');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'kpis' => [
                    'total_customers',
                    'repurchase_count',
                    'repurchase_rate',
                    'avg_ltv',
                    'crr',
                    'churn_rate',
                ],
                'growth' => [
                    'new_customers_daily'
                ],
                'segmentation' => [
                    'platinum',
                    'gold',
                    'silver',
                    'bronze',
                    'total_revenue'
                ],
                'frequency',
                'cohorts',
                'at_risk',
            ]);

        // 3. Validaciones de negocio específicas sobre los KPIs
        $kpis = $response->json('kpis');
        $this->assertEquals(3, $kpis['total_customers']); // C1, C2, C3 compraron
        $this->assertEquals(2, $kpis['repurchase_count']); // C1 y C2 compraron más de una vez
        $this->assertEquals(66.7, round($kpis['repurchase_rate'], 1));
        $this->assertEquals(250.00, $kpis['avg_ltv']); // 750 USD total / 3 clientes = 250 USD

        // 4. Validaciones de la distribución de frecuencias de compra
        $frequency = $response->json('frequency');
        // Distribución esperada:
        // - 1 cliente con 1 orden (Client 3)
        // - 1 cliente con 2 órdenes (Client 2)
        // - 1 cliente con 3 órdenes (Client 1)
        $this->assertEquals(1, $frequency[1]);
        $this->assertEquals(1, $frequency[2]);
        $this->assertEquals(1, $frequency[3]);
    }

    /**
     * Test del indicador Churn Rate y retención de cohortes
     */
    public function test_customer_analytics_churn_rate_and_cohorts(): void
    {
        // 1. Simular un cliente inactivo hace más de 90 días (Churned)
        $oldOrderDate = now()->subDays(95)->format('Y-m-d H:i:s');
        Order::create([
            'client_id' => $this->c3->id,
            'seller_id' => $this->seller->id,
            'order_date' => $oldOrderDate,
            'status' => 'Completed',
            'total_amount' => 100.00,
            'total_amount_usd' => 100.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        // 2. Simular un cliente activo en los últimos 30 días
        Order::create([
            'client_id' => $this->c1->id,
            'seller_id' => $this->seller->id,
            'order_date' => now()->subDays(10)->format('Y-m-d H:i:s'),
            'status' => 'Completed',
            'total_amount' => 200.00,
            'total_amount_usd' => 200.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        $response = $this->actingAs($this->seller, 'sanctum')
            ->getJson('/api/bi/customers/dashboard');

        $response->assertStatus(200);
        
        // De los 2 clientes activos en total históricamente (C1 y C3):
        // C3 no compra hace 95 días (Churned), C1 compró hace 10 días. Churn = 50%
        $kpis = $response->json('kpis');
        $this->assertEquals(50.0, round($kpis['churn_rate'], 1));
    }

    /**
     * Test de detección de clientes valiosos en riesgo (RFM - At Risk)
     */
    public function test_customer_analytics_rfm_at_risk_detection(): void
    {
        // Simular cliente VIP que compró frecuentemente pero no ha vuelto en 70 días (> 60 días)
        // Su última orden fue hace 70 días.
        $vipDate = now()->subDays(70)->format('Y-m-d H:i:s');

        // 6 compras para alta frecuencia (> 5)
        for ($i = 0; $i < 6; $i++) {
            Order::create([
                'client_id' => $this->c1->id,
                'seller_id' => $this->seller->id,
                'order_date' => $vipDate,
                'status' => 'Completed',
                'total_amount' => 50.00,
                'total_amount_usd' => 50.00,
                'money_returns' => 0.00,
                'currency' => 'USD',
                'payment_methods' => [],
            ]);
        }

        // Cliente que compró hace poco (no en riesgo)
        Order::create([
            'client_id' => $this->c2->id,
            'seller_id' => $this->seller->id,
            'order_date' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'status' => 'Completed',
            'total_amount' => 150.00,
            'total_amount_usd' => 150.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
        ]);

        $response = $this->actingAs($this->seller, 'sanctum')
            ->getJson('/api/bi/customers/dashboard');

        $response->assertStatus(200);
        
        // El cliente C1 debe aparecer en la lista de 'at_risk' porque:
        // recencia = 70 días (> 60), frecuencia = 6 (> 5)
        $atRisk = $response->json('at_risk');
        $this->assertCount(1, $atRisk);
        $this->assertEquals($this->c1->id, $atRisk[0]['id']);
    }
}
