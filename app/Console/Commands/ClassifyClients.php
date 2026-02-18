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
            $newType = $this->determineClientType($totalOrders, $totalSpent, $daysSinceLastPurchase);

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
     * Determina el tipo de cliente según sus estadísticas.
     */
    private function determineClientType(int $totalOrders, float $totalSpent, ?int $daysSinceLastPurchase): string
    {
        // Sin compras = Nuevo
        if ($totalOrders === 0) {
            return Client::CLIENT_TYPE_NUEVO;
        }

        // Tiene compras pero no compra hace más de 30 días = En Riesgo
        if ($daysSinceLastPurchase !== null && $daysSinceLastPurchase > 30) {
            return Client::CLIENT_TYPE_EN_RIESGO;
        }

        // Alto gasto o muchas compras = VIP
        if ($totalSpent >= 500 || $totalOrders >= 20) {
            return Client::CLIENT_TYPE_VIP;
        }

        // Compras moderadas = Frecuente
        if ($totalOrders >= 5) {
            return Client::CLIENT_TYPE_FRECUENTE;
        }

        // Pocas compras = Ocasional
        return Client::CLIENT_TYPE_OCASIONAL;
    }
}
