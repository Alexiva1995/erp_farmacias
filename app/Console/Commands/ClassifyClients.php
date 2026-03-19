<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassifyClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:classify-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evalúa y clasifica a todos los clientes según su actividad de compras (VIP, Frecuente, Ocasional, En Riesgo, Nuevo)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando clasificación de clientes...');

        $clients = Client::all();
        $now = Carbon::now();
        $updated = 0;

        foreach ($clients as $client) {
            // Obtener estadísticas del cliente
            $completedOrders = Order::where('client_id', $client->id)
                ->where('status', Order::COMPLETED)
                ->get();

            $totalOrders = $completedOrders->count();
            $totalSpent = $completedOrders->sum('total_amount_usd');

            // Última compra
            $lastOrder = Order::where('client_id', $client->id)
                ->where('status', Order::COMPLETED)
                ->orderByDesc('order_date')
                ->first();

            $daysSinceLastPurchase = $lastOrder
                ? Carbon::parse($lastOrder->order_date)->diffInDays($now)
                : null;

            // Determinar tipo de cliente
            $newType = $this->determineClientType($client, $totalOrders, $daysSinceLastPurchase);

            // Solo actualizar si cambió
            if ($client->client_type !== $newType) {
                $client->client_type = $newType;
                $client->save();
                $updated++;
            }
        }

        $this->info("Clasificación completada. {$updated} clientes actualizados de {$clients->count()} totales.");
        Log::info("ClassifyClients: {$updated} clientes actualizados de {$clients->count()} totales.");

        return Command::SUCCESS;
    }

    /**
     * Determina el tipo de cliente según sus estadísticas y las nuevas reglas estratégicas.
     */
    private function determineClientType(Client $client, int $totalOrders, ?int $daysSinceLastPurchase): string
    {
        $now = Carbon::now();
        $daysSinceCreated = $client->created_at ? $client->created_at->diffInDays($now) : 0;

        // 1. Casos de 0 compras
        if ($totalOrders === 0) {
            return ($daysSinceCreated <= 30) 
                ? Client::CLIENT_TYPE_NUEVO 
                : Client::CLIENT_TYPE_INACTIVO;
        }

        // A partir de aquí, tiene al menos 1 compras
        
        // 2. En Riesgo (Inactividad prolongada tras haber comprado)
        if ($daysSinceLastPurchase !== null && $daysSinceLastPurchase > 60) {
            return Client::CLIENT_TYPE_EN_RIESGO;
        }

        // 3. VIP (10+ compras y actividad reciente < 30 días)
        if ($totalOrders >= 10 && $daysSinceLastPurchase !== null && $daysSinceLastPurchase <= 30) {
            return Client::CLIENT_TYPE_VIP;
        }

        // 4. Frecuente (3+ compras y activo < 60 días)
        if ($totalOrders >= 3 && $daysSinceLastPurchase !== null && $daysSinceLastPurchase <= 60) {
            return Client::CLIENT_TYPE_FRECUENTE;
        }

        // 5. Ocasional (1 a 2 compras y activo < 60 días)
        return Client::CLIENT_TYPE_OCASIONAL;
    }
}
