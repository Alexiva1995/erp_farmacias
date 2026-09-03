<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AiCategorizeProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:ai-categorize {--batch=30 : Cantidad de productos por lote} {--limit=0 : Límite de productos a procesar (0 = todos)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clasifica los productos pendientes de categoría usando Inteligencia Artificial (Gemini)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            $this->error('No se encontró GEMINI_API_KEY en el archivo .env.');
            return 1;
        }

        $categories = \App\Models\Category::all();
        if ($categories->isEmpty()) {
            $this->error('No existen categorías. Ejecuta primero categories:renew-ecommerce.');
            return 1;
        }

        $categoryListString = $categories->map(fn($c) => "ID {$c->id}: {$c->name}")->implode("\n");

        $batchSize = (int) $this->option('batch');
        $limit = (int) $this->option('limit');

        $query = \App\Models\Product::withoutGlobalScope('not_deleted')
            ->whereNull('category_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $totalPending = $query->count();
        if ($totalPending === 0) {
            $this->info('No hay productos pendientes de categorizar.');
            return 0;
        }

        $this->info("Productos pendientes por categorizar con IA: {$totalPending}");

        $processed = 0;

        while (true) {
            $products = \App\Models\Product::withoutGlobalScope('not_deleted')
                ->whereNull('category_id')
                ->take($batchSize)
                ->get(['id', 'name', 'active_ingredient', 'description']);

            if ($products->isEmpty()) {
                break;
            }

            $productsPayload = $products->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'active_ingredient' => $p->active_ingredient,
            ])->values()->toArray();

            $prompt = "Actúa como un Farmacéutico Experto y Categorizador de E-commerce Farmacéutico.
A continuación tienes la lista oficial de categorías disponibles con sus IDs:
{$categoryListString}

Analiza la siguiente lista de productos y clasifica CADA UNO en el category_id más exacto y apropiado según su uso médico, principio activo o propósito comercial:
" . json_encode($productsPayload, JSON_UNESCAPED_UNICODE) . "

IMPORTANTE: Responde ÚNICAMENTE un JSON válido (sin markdown, sin bloques ```json, solo texto plano JSON) con un array de objetos con formato exacto:
[{\"id\": 1001, \"category_id\": 3}, ...]";

            try {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(45)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ]
                ]);

                if ($response->successful()) {
                    $resultText = $response->json('candidates.0.content.parts.0.text');
                    // Limpiar posibles bloques markdown
                    $cleanedJson = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($resultText));
                    $assignments = json_decode($cleanedJson, true);

                    if (is_array($assignments)) {
                        foreach ($assignments as $item) {
                            if (!empty($item['id']) && !empty($item['category_id'])) {
                                \App\Models\Product::withoutGlobalScope('not_deleted')
                                    ->where('id', $item['id'])
                                    ->update(['category_id' => $item['category_id']]);
                            }
                        }
                        $processed += count($assignments);
                        $this->info("Procesados con IA: {$processed} / {$totalPending}");
                    } else {
                        $this->warn("Respuesta de IA no parseable en este lote, reintentando...");
                        break;
                    }
                } else {
                    $this->error("Error en llamada a Gemini API: " . $response->body());
                    break;
                }
            } catch (\Exception $e) {
                $this->error("Excepción durante categorización con IA: " . $e->getMessage());
                break;
            }

            // Pausa preventiva de 1 segundo entre lotes para respetar rate limits
            sleep(1);
        }

        $this->info("Categorización con IA finalizada. Total asignados: {$processed}");
        return 0;
    }
}
