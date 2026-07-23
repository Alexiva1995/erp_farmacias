<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.telegram.gemini_api_key') ?: env('GEMINI_API_KEY');
    }

    /**
     * Analizar la imagen de la factura en base64 y extraer la información estructurada.
     */
    public function analyzeInvoice(string $imagePath): ?array
    {
        $key = $this->apiKey ?: env('GEMINI_API_KEY');
        if (empty($key)) {
            Log::error('[GeminiService] GEMINI_API_KEY no está configurado.');
            return null;
        }

        if (!file_exists($imagePath)) {
            Log::error("[GeminiService] El archivo de imagen no existe en la ruta: {$imagePath}");
            return null;
        }

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $prompt = "Analiza esta imagen que corresponde a una factura de compra. Extrae la información y devuélvela estrictamente en formato JSON con los siguientes campos:\n"
                . "- supplier_name: Nombre o Razón Social del Proveedor.\n"
                . "- supplier_rif: RIF, NIT, RUT o identificación tributaria del proveedor (sin guiones ni puntos si es posible, o limpia el formato).\n"
                . "- invoice_number: Número de factura.\n"
                . "- control_number: Número de control (si no tiene, usa el mismo número de factura o '0').\n"
                . "- currency: Moneda de la factura. Debe ser estrictamente uno de estos tres valores: 'Bs', 'USD', 'COP'. Si no es claro, asume la moneda local según el RIF/dirección (Bs para Venezuela, COP para Colombia, USD si está explícito en dólares).\n"
                . "- invoice_date: Fecha de emisión de la factura en formato YYYY-MM-DD.\n"
                . "- exempt_amount: Monto exento (número decimal o 0).\n"
                . "- taxable_base: Base imponible (número decimal o 0).\n"
                . "- tax_amount: Monto del impuesto/IVA (número decimal o 0).\n"
                . "- total_amount: Monto total de la factura (número decimal).\n"
                . "IMPORTANTE: Si el final de la factura está cortado o no se visualiza el bloque de totales, calcula el total sumando los precios de todos los artículos visibles y asígnalo a 'total_amount'.\n"
                . "Devuelve exclusivamente el JSON estructurado, sin rodeos, sin markdown block de tipo ```json, solo el objeto plano.";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$key}";

            $response = Http::timeout(30)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                // Limpiar posibles bloques de código de markdown si la IA no obedeció del todo
                $textResponse = preg_replace('/^```json\s*/i', '', trim($textResponse));
                $textResponse = preg_replace('/```$/', '', trim($textResponse));

                $data = json_decode($textResponse, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }

                Log::error('[GeminiService] Error al decodificar JSON de la respuesta: ' . $textResponse);
                return null;
            }

            Log::error('[GeminiService] Error en la API de Gemini: ' . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error('[GeminiService] Excepción al analizar factura: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extraer el número de referencia bancaria o número de transacción de una imagen de comprobante de pago.
     */
    public function extractPaymentReference(string $imagePath): ?string
    {
        $key = $this->apiKey ?: env('GEMINI_API_KEY');
        if (empty($key)) {
            Log::error('[GeminiService] GEMINI_API_KEY no está configurado.');
            return null;
        }

        if (!file_exists($imagePath)) {
            Log::error("[GeminiService] El archivo de imagen no existe en la ruta: {$imagePath}");
            return null;
        }

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $prompt = "Analiza esta imagen que corresponde a un comprobante de pago o capture de transferencia bancaria / móvil. "
                . "Busca y extrae el número de referencia, número de transacción, número de operación, número de aprobación, o cualquier identificador único de la transacción. "
                . "Devuelve estrictamente un objeto JSON con el siguiente formato:\n"
                . "{\n"
                . "  \"reference\": \"NÚMERO_DE_REFERENCIA_DETECTADO\" o null si no se detecta ninguno claro\n"
                . "}\n"
                . "Devuelve exclusivamente el JSON estructurado, sin rodeos, sin bloques de código, solo el objeto plano.";

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$key}";

            $response = Http::timeout(20)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
                $textResponse = preg_replace('/^```json\s*/i', '', trim($textResponse));
                $textResponse = preg_replace('/```$/', '', trim($textResponse));

                $data = json_decode($textResponse, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($data['reference'])) {
                    return trim($data['reference']);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('[GeminiService] Excepción al extraer referencia de pago: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Emparejar un producto local con candidatos del catálogo de proveedor.
     * Usa responseMimeType y responseSchema para garantizar JSON estructurado.
     * Retorna { matched, product_supplier_id, confidence_score, reason } o null.
     */
    public function matchProduct(array $product, array $candidates, array $rejections = []): ?array
    {
        $key = $this->apiKey ?: env('GEMINI_API_KEY');
        if (empty($key) || empty($candidates)) {
            return null;
        }

        // Construir lista de candidatos enriquecida
        $candidateLines = '';
        foreach ($candidates as $cand) {
            $candidateLines .= "- ID:{$cand['id']} | {$cand['name']} | Lab: {$cand['laboratory']} | IA: {$cand['active_ingredient']}\n";
        }

        // Construir historial de rechazos si existe para aprendizaje en contexto (in-context learning)
        $rejectionsSection = '';
        if (!empty($rejections)) {
            $rejectionsSection = "\nHISTORIAL DE RECHAZOS ANTERIORES POR EL FARMACÉUTICO (NO SUGERIR ESTOS Y APRENDER DEL ERROR):\n";
            foreach ($rejections as $rej) {
                $rejectionsSection .= "- Nombre Proveedor: '{$rej['supplier_product_name']}' | Razón del rechazo: '{$rej['reason']}'\n";
            }
            $rejectionsSection .= "Analiza por qué fueron rechazados. Por ejemplo, si se rechazó por ser de marca comercial diferente o forma farmacéutica incorrecta, no cometas el mismo error.\n";
        }

        $prompt = "Eres un farmacéutico experto de control de inventario. Compara este producto de inventario con la lista de productos del proveedor.\n\n"
            . "PRODUCTO LOCAL:\n"
            . "  Nombre: {$product['name']}\n"
            . "  Laboratorio (Marca): {$product['laboratory']}\n"
            . "  Ingrediente Activo: {$product['active_ingredient']}\n\n"
            . "REGLAS FARMACÉUTICAS ESTRICTAS:\n"
            . "1. Ingrediente Activo y Concentración: Solo marca matched=true si el ingrediente activo y la concentración numérica son EXACTAMENTE IDÉNTICOS (ej. 500mg != 250mg, 10ml != 15ml).\n"
            . "2. Forma Farmacéutica: Deben tener la misma vía de administración y forma (ej. Crema != Ungüento, Tabletas != Cápsulas, Inyectable != Gotas).\n"
            . "3. Marca / Genérico: Si el producto local es una marca comercial muy específica (ej: 'Advil') y el candidato de proveedor es un genérico puro (ej: 'Ibuprofeno'), NO asocies a menos que no haya otra opción, pero prioriza la marca exacta si existe. Si el local es genérico, sí puede matchear con genéricos de otros laboratorios.\n"
            . "4. Si hay duda razonable, prefiere matched=false.\n"
            . $rejectionsSection . "\n"
            . "CANDIDATOS DEL PROVEEDOR (ID | Nombre | Laboratorio | Ingrediente Activo):\n"
            . $candidateLines
            . "\nResponde con el ID del candidato que coincide o null si ninguno cumple las reglas.";

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$key}";

            $maxRetries = 3;
            $retryDelay = 5;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $response = Http::timeout(25)->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    // Forzar JSON estructurado con schema definido
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema'   => [
                            'type'       => 'object',
                            'properties' => [
                                'matched'             => ['type' => 'boolean'],
                                'product_supplier_id' => [
                                    'type'     => 'integer',
                                    'nullable' => true,
                                ],
                                'confidence_score'    => ['type' => 'number', 'description' => '0.0 to 1.0'],
                                'reason'              => ['type' => 'string'],
                            ],
                            'required' => ['matched'],
                        ],
                    ],
                ]);

                if ($response->successful()) {
                    $result       = $response->json();
                    $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $data         = json_decode($textResponse, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($data['matched'])) {
                        return $data;
                    }

                    Log::warning('[GeminiService::matchProduct] JSON inválido: ' . $textResponse);
                    return null;
                }

                if ($response->status() === 429) {
                    Log::warning("[GeminiService::matchProduct] Límite de cuota alcanzado (429). Reintento {$attempt} de {$maxRetries} en {$retryDelay} segundos...");
                    sleep($retryDelay);
                    $retryDelay *= 2;
                    continue;
                }

                Log::error('[GeminiService::matchProduct] API error: ' . $response->body());
                break;
            }
        } catch (\Exception $e) {
            Log::error('[GeminiService::matchProduct] Excepción: ' . $e->getMessage());
        }

        return null;
    }
}

