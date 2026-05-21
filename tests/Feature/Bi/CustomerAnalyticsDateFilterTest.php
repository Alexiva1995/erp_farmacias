<?php

namespace Tests\Feature\Bi;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAnalyticsDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_analytics_dashboard_filters_by_dates_correctly(): void
    {
        $user = User::create([
            'username' => 'seller',
            'email' => 'seller@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $client = Client::create([
            'identification' => 'V-12345678',
            'name' => 'John Doe',
        ]);

        // Crear una orden dentro del rango de interés (Enero 2026)
        Order::create([
            'client_id' => $client->id,
            'seller_id' => $user->id,
            'order_date' => '2026-01-15 12:00:00',
            'status' => 'Completed',
            'total_amount' => 500.00,
            'total_amount_usd' => 500.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
            'created_at' => '2026-01-15 12:00:00',
        ]);

        // Crear otra orden fuera del rango de interés (Febrero 2026)
        Order::create([
            'client_id' => $client->id,
            'seller_id' => $user->id,
            'order_date' => '2026-02-15 12:00:00',
            'status' => 'Completed',
            'total_amount' => 1000.00,
            'total_amount_usd' => 1000.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'payment_methods' => [],
            'created_at' => '2026-02-15 12:00:00',
        ]);

        // 1. Consultar el rango de Enero 2026
        $responseEnero = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bi/customers/dashboard?start_date=2026-01-01&end_date=2026-01-31');

        $responseEnero->assertStatus(200);
        $dataEnero = $responseEnero->json();
        $this->assertEquals(1, $dataEnero['kpis']['total_customers']);
        $this->assertEquals(500.00, $dataEnero['kpis']['avg_ltv']);

        // 2. Consultar el rango de Febrero 2026
        $responseFebrero = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bi/customers/dashboard?start_date=2026-02-01&end_date=2026-02-28');

        $responseFebrero->assertStatus(200);
        $dataFebrero = $responseFebrero->json();
        $this->assertEquals(1, $dataFebrero['kpis']['total_customers']);
        $this->assertEquals(1000.00, $dataFebrero['kpis']['avg_ltv']);

        // 3. Consultar todo el periodo (Enero + Febrero)
        $responseTodo = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bi/customers/dashboard?start_date=2026-01-01&end_date=2026-02-28');

        $responseTodo->assertStatus(200);
        $dataTodo = $responseTodo->json();
        $this->assertEquals(1, $dataTodo['kpis']['total_customers']);
        $this->assertEquals(1500.00, $dataTodo['kpis']['avg_ltv']);
    }
}
