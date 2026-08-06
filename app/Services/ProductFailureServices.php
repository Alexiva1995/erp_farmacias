<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProductFailure as ProductFailureContract;
use App\Models\ProductFailure;
use App\Repositories\ProductFailureRepository;
use App\Services\TelegramService;

class ProductFailureServices implements ProductFailureContract
{
    public function __construct(
        protected ProductFailureRepository $repository,
        protected TelegramService $telegramService
    ) {}

    /**
     * Almacenar un nuevo reporte de falla y notificar a Telegram.
     */
    public function store(array $data): ProductFailure
    {
        // 1. Guardar en base de datos
        $failure = $this->repository->create([
            'product_id' => $data['product_id'],
            'user_id' => $data['user_id'] ?? null,
            'comment' => $data['comment'] ?? null,
        ]);

        // 2. Enviar a Telegram
        $this->notifyFailureToTelegram($failure);

        return $failure;
    }

    /**
     * Notificar el reporte de falla al canal de Telegram con coincidencia directa (sin forzar IA por defecto).
     */
    protected function notifyFailureToTelegram(ProductFailure $failure): void
    {
        $product = $failure->product ?? \App\Models\Product::find($failure->product_id);
        if (!$product) {
            return;
        }

        $failuresChatId = config('services.telegram.failures_chat_id') ?: $this->telegramService->getChatId();
        if (empty($failuresChatId)) {
            return;
        }

        $user = $failure->user ?? ($failure->user_id ? \App\Models\User::find($failure->user_id) : null);
        $userName = $user ? $user->name : 'Usuario del TPV';

        $message = "⚠️ *[REPORTE DE FALLA DE PRODUCTO]*\n\n";
        $message .= "📦 *Producto:* {$product->name}\n";
        $message .= "🆔 *ID Producto:* {$product->id}\n";
        $message .= "🔬 *Laboratorio:* " . ($product->laboratory->name ?? 'Genérico') . "\n";
        $message .= "👤 *Reportado por:* {$userName}\n";
        if (!empty($failure->comment)) {
            $message .= "💬 *Comentario:* {$failure->comment}\n";
        }
        $message .= "📅 *Fecha:* " . now()->setTimezone('America/Caracas')->format('d/m/Y h:i A') . "\n\n";

        // 1. Intentar buscar por código de barras coincidente
        $matches = collect();
        if (!empty($product->barcode)) {
            $matches = \App\Models\ProductSupplier::where('barcode_match', $product->barcode)
                ->with('supplier')
                ->get();
        }

        // 2. Intentar buscar por ID del producto (asociación directa previa)
        if ($matches->isEmpty()) {
            $matches = \App\Models\ProductSupplier::where('product_id', $product->id)
                ->with('supplier')
                ->get();
        }

        // Calcular unidades recomendadas a pedir según el método de rotación/demanda (mínimo 1)
        $ventas = \DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('order_details.product_id', $product->id)
            ->where('orders.status', 'Completed')
            ->whereBetween('orders.created_at', [now()->subDays(30)->startOfDay(), now()->endOfDay()])
            ->sum('order_details.quantity');

        $stockActual = \DB::table('product_lots')
            ->where('product_id', $product->id)
            ->sum('quantity');

        $autoOrder = \DB::table('auto_order_details')
            ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
            ->join('product_suppliers', 'product_suppliers.id', '=', 'auto_order_details.product_suppliers_id')
            ->where('product_suppliers.product_id', $product->id)
            ->whereIn('auto_orders.status', [0, 1])
            ->where('auto_order_details.status', 0)
            ->whereNull('auto_orders.deleted_at')
            ->sum('auto_order_details.quantity');

        $promedio = (float)($product->sales_average ?? 0);
        $demandaPonderada = ($ventas + $promedio) / 2;
        $resultado = $demandaPonderada - $stockActual - $autoOrder;
        $solicitar = -$resultado;
        $solicitarRedondeado = $solicitar > 0 ? (int)ceil($solicitar) : (int)floor($solicitar);
        $qtyToRecommend = max(1, $solicitarRedondeado);

        $buttons = [];

        if ($matches->isNotEmpty()) {
            // Seleccionar por costo bruto completo en USD (sin descuento aplicado)
            $bestOffer = $matches->sortBy(function($offer) {
                return (float)$offer->unit_cost_usd;
            })->first();

            $costoProveedor = (float)$bestOffer->unit_cost_usd;
            $costoLocal = (float)($product->unit_cost ?? 0);

            $diffPercent = 0;
            if ($costoLocal > 0) {
                $diffPercent = (($costoProveedor - $costoLocal) / $costoLocal) * 100;
            }

            if ($diffPercent >= 20.0) {
                $qtyToRecommend = 1;
            }

            if ($diffPercent < 0) {
                $comparacionText = "📉 *Ahorro del " . abs(round($diffPercent, 2)) . "%* respecto al costo actual.";
            } elseif ($diffPercent > 0) {
                $comparacionText = "📈 *Encarece un " . round($diffPercent, 2) . "%* respecto al costo actual.";
            } else {
                $comparacionText = "⚖️ *Mismo costo* que el actual.";
            }

            $message .= "🔍 *[COINCIDENCIA DIRECTA ENCONTRADA]*\n";
            $message .= "Se encontró en el catálogo del proveedor:\n";
            $message .= "👉 *{$bestOffer->name}* (Laboratorio: {$bestOffer->laboratory})\n\n";
            $message .= "🏢 *Proveedor:* {$bestOffer->supplier->name}\n";
            $message .= "💵 *Costo Completo USD:* " . number_format($costoProveedor, 2) . " USD (Sin descuento)\n";
            $message .= "💵 *Costo Local Actual:* " . number_format($costoLocal, 2) . " USD\n";
            $message .= "📊 *Comparativa:* {$comparacionText}\n";
            $message .= "💡 *Cantidad Recomendada:* Pedir *{$qtyToRecommend}* unidades.\n\n";
            $message .= "¿Deseas aprobar la solicitud de este producto?";

            $buttons = [
                [
                    [
                        'text' => "✅ Aprobar Pedido ({$qtyToRecommend} ud.)",
                        'callback_data' => "falla_aprobar_{$product->id}_{$bestOffer->supplier_id}_{$qtyToRecommend}_{$bestOffer->id}"
                    ],
                    [
                        'text' => "❌ Cancelar",
                        'callback_data' => "falla_cancelar_{$failure->id}"
                    ]
                ]
            ];
        } else {
            $message .= "❌ *No se encontró coincidencia directa de catálogo para este producto.*\n\n";
            $message .= "¿Qué deseas hacer?";

            $buttons = [
                [
                    [
                        'text' => "🔍 Buscar con IA",
                        'callback_data' => "falla_buscar_ia_{$product->id}_{$failure->id}"
                    ],
                    [
                        'text' => "❌ Cancelar",
                        'callback_data' => "falla_cancelar_{$failure->id}"
                    ]
                ]
            ];
        }

        $this->telegramService->sendMessage($message, $failuresChatId, ['inline_keyboard' => $buttons]);
    }
}
