<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProductFailure as ProductFailureContract;
use App\Models\ProductFailure;
use App\Repositories\ProductFailureRepository;
use App\Services\TelegramService;
use App\Services\GeminiService;

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
     * Notificar el reporte de falla al canal específico de Telegram con lógica de cotización de proveedores.
     */
    protected function notifyFailureToTelegram(ProductFailure $failure): void
    {
        $product = $failure->product ?? \App\Models\Product::find($failure->product_id);
        if (!$product) {
            return;
        }

        $failuresChatId = config('services.telegram.failures_chat_id');
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

        // 1. Intentar buscar por código de barras
        $matches = collect();
        if (!empty($product->barcode)) {
            $matches = \App\Models\ProductSupplier::where('barcode_match', $product->barcode)
                ->with('supplier')
                ->get();
        }

        // 2. Intentar buscar por ID del producto (asociaciones previas)
        if ($matches->isEmpty()) {
            $matches = \App\Models\ProductSupplier::where('product_id', $product->id)
                ->with('supplier')
                ->get();
        }

        $isAiMatched = false;

        // 3. Si no hay coincidencia, usar IA (Gemini) para emparejar productos sin código de barras
        if ($matches->isEmpty()) {
            $candidates = \App\Models\ProductSupplier::where(function($q) {
                $q->whereNull('barcode_match')->orWhere('barcode_match', '');
            })->limit(50)->get();

            if ($candidates->isNotEmpty()) {
                try {
                    $gemini = app(GeminiService::class);
                    $prompt = "Tengo un producto local llamado: '{$product->name}' (Laboratorio: " . ($product->laboratory->name ?? 'Genérico') . ", Ingrediente Activo: {$product->active_ingredient}).\n";
                    $prompt .= "Compara este producto con la siguiente lista de productos de proveedores y decide si alguno es exactamente el mismo producto o una alternativa equivalente muy cercana (mismo ingrediente, concentración y forma farmacéutica).\n";
                    $prompt .= "Devuelve tu respuesta estrictamente en formato JSON con la siguiente estructura (si no hay coincidencia clara, matched debe ser false y product_supplier_id null):\n";
                    $prompt .= "{\n  \"matched\": true,\n  \"product_supplier_id\": ID_DEL_PRODUCTO_PROVEEDOR_EMPARETADO\n}\n\n";
                    $prompt .= "Lista de proveedores (ID | Nombre | Laboratorio | Ingrediente Activo):\n";
                    foreach ($candidates as $cand) {
                        $prompt .= "- {$cand->id} | {$cand->name} | {$cand->laboratory} | {$cand->active_ingredient}\n";
                    }
                    $prompt .= "\nDevuelve solo el JSON estructurado, sin markdown block de tipo ```json, solo el objeto plano.";

                    $aiResponse = $gemini->generateText($prompt);
                    if ($aiResponse) {
                        $aiResponse = preg_replace('/^```json\s*/i', '', trim($aiResponse));
                        $aiResponse = preg_replace('/```$/', '', trim($aiResponse));
                        $data = json_decode($aiResponse, true);
                        if (!empty($data['matched']) && !empty($data['product_supplier_id'])) {
                            $matchedSupplierProduct = \App\Models\ProductSupplier::with('supplier')->find($data['product_supplier_id']);
                            if ($matchedSupplierProduct) {
                                $matches = collect([$matchedSupplierProduct]);
                                $isAiMatched = true;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Error al emparejar producto por IA en fallas: " . $e->getMessage());
                }
            }
        }

        // Calcular unidades recomendadas a pedir (según promedio de ventas o por defecto 10)
        $qtyToRecommend = 10;
        if ($product->sales_average && $product->sales_average > 0) {
            $qtyToRecommend = max(5, (int)round($product->sales_average * 1.5));
        }

        $buttons = [];

        if ($matches->isNotEmpty()) {
            // Seleccionar el mejor costo (oferta más barata)
            $bestOffer = $matches->sortBy(function($offer) {
                return (float)($offer->unit_cost_usd_with_discount > 0 ? $offer->unit_cost_usd_with_discount : $offer->unit_cost_usd);
            })->first();

            $costoProveedor = (float)($bestOffer->unit_cost_usd_with_discount > 0 ? $bestOffer->unit_cost_usd_with_discount : $bestOffer->unit_cost_usd);
            $costoLocal = (float)($product->unit_cost ?? 0);

            $diffPercent = 0;
            if ($costoLocal > 0) {
                $diffPercent = (($costoProveedor - $costoLocal) / $costoLocal) * 100;
            }

            if ($diffPercent < 0) {
                $comparacionText = "📉 *Ahorro del " . abs(round($diffPercent, 2)) . "%* respecto a nuestro costo.";
            } elseif ($diffPercent > 0) {
                $comparacionText = "📈 *Encarece un " . round($diffPercent, 2) . "%* respecto a nuestro costo.";
            } else {
                $comparacionText = "⚖️ *Mismo costo* que el nuestro.";
            }

            if ($isAiMatched) {
                $message .= "🤖 *[COINCIDENCIA POR IA]*\n";
                $message .= "El sistema sugiere por IA que coincide con:\n";
                $message .= "👉 *{$bestOffer->name}* (Laboratorio: {$bestOffer->laboratory})\n\n";
            } else {
                $message .= "🔍 *[COINCIDENCIA ENCONTRADA]*\n";
                $message .= "Se encontró en el catálogo del proveedor:\n";
                $message .= "👉 *{$bestOffer->name}* (Laboratorio: {$bestOffer->laboratory})\n\n";
            }

            $message .= "🏢 *Proveedor:* {$bestOffer->supplier->name}\n";
            $message .= "💵 *Costo Local:* " . number_format($costoLocal, 2) . " USD\n";
            $message .= "💵 *Costo Proveedor:* " . number_format($costoProveedor, 2) . " USD\n";
            $message .= "📊 *Comparativa:* {$comparacionText}\n";
            $message .= "💡 *Cantidad Recomendada:* Pedir *{$qtyToRecommend}* unidades.\n\n";
            $message .= "¿Deseas aprobar la solicitud de este producto?";

            // Botón interactivo para aprobar la orden automática
            $buttons = [
                [
                    [
                        'text' => "✅ Aprobar Pedido ({$qtyToRecommend} ud.)",
                        'callback_data' => "falla_aprobar_{$product->id}_{$bestOffer->supplier_id}_{$qtyToRecommend}_{$bestOffer->id}"
                    ]
                ]
            ];
        } else {
            $message .= "❌ *No se encontró coincidencia en proveedores para este producto.*";
        }

        $this->telegramService->sendMessage($message, $failuresChatId, !empty($buttons) ? ['inline_keyboard' => $buttons] : null);
    }
}
