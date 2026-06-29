<?php

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
}
