<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Suppliers\MafartaScraperServiceInterface;
use App\Enums\ScraperKey;
use App\Helpers\FtpCrypt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\ReceiveMafartaTokenRequest;
use App\Http\Requests\Suppliers\SaveSupplierScraperRequest;
use App\Http\Resources\SupplierScraperResource;
use App\Models\Supplier;
use App\Models\SupplierScraper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierScraperController extends Controller
{
    /**
     * Obtiene la configuración del scraper financiero de un proveedor sin exponer la contraseña.
     */
    public function show(Supplier $supplier, string $scraperKey): JsonResponse
    {
        SupplierScraper::ensureSchema();

        $key = ScraperKey::normalize($scraperKey);
        if (!$key) {
            return response()->json(['message' => 'Tipo de scraper no soportado.'], 422);
        }

        $scraper = SupplierScraper::where('supplier_id', $supplier->id)
            ->where('scraper_key', $key->value)
            ->first();

        if (!$scraper) {
            return response()->json([
                'data' => [
                    'id' => null,
                    'supplier_id' => $supplier->id,
                    'scraper_key' => $key->value,
                    'portal_url' => null,
                    'username' => null,
                    'has_password' => false,
                    'session_token' => null,
                    'has_session_token' => false,
                    'is_active' => true,
                    'last_scraped_at' => null,
                    'last_status' => null,
                    'last_message' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => (new SupplierScraperResource($scraper))->resolve(),
        ]);
    }

    /**
     * Guarda o actualiza credenciales del portal web para análisis de facturas.
     */
    public function upsert(SaveSupplierScraperRequest $request, Supplier $supplier, string $scraperKey): JsonResponse
    {
        SupplierScraper::ensureSchema();

        $key = ScraperKey::normalize($scraperKey);
        if (!$key) {
            return response()->json(['message' => 'Tipo de scraper no soportado.'], 422);
        }

        $validated = $request->validated();
        $existing = SupplierScraper::where('supplier_id', $supplier->id)
            ->where('scraper_key', $key->value)
            ->first();

        $portalUrl = $validated['portal_url'] ?? $validated['host'] ?? $existing?->portal_url;
        $username = array_key_exists('username', $validated)
            ? ($validated['username'] !== null ? trim((string) $validated['username']) : null)
            : $existing?->username;

        $authPayload = is_array($existing?->auth_payload) ? $existing->auth_payload : [];
        if (array_key_exists('session_token', $validated) && !empty($validated['session_token'])) {
            $authPayload['session_token'] = trim((string) $validated['session_token']);
        }

        $data = [
            'portal_url' => $portalUrl ? trim((string) $portalUrl) : null,
            'username' => $username,
            'auth_payload' => !empty($authPayload) ? $authPayload : null,
            'is_active' => array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : ($existing?->is_active ?? true),
        ];

        if (!empty($validated['password'])) {
            $data['password'] = FtpCrypt::encrypt((string) $validated['password']);
        }

        $scraper = SupplierScraper::updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'scraper_key' => $key->value,
            ],
            $data
        );

        return response()->json([
            'message' => 'Configuración del scraper guardada correctamente.',
            'data' => (new SupplierScraperResource($scraper))->resolve(),
        ]);
    }

    /**
     * Valida y opcionalmente persiste el token de sesión del portal Cobeca/Mafarta en supplier_scrapers.
     */
    public function testMafartaToken(Request $request, Supplier $supplier): JsonResponse
    {
        SupplierScraper::ensureSchema();

        $token = (string) $request->input('token', '');
        if (trim($token) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar un token de sesión válido.',
            ], 422);
        }

        $scraperService = app(MafartaScraperServiceInterface::class);
        $clientCode = (int) (preg_replace('/\D/', '', (string) ($request->input('username') ?: '31373')) ?: 31373);
        $result = $scraperService->testSessionToken($token, $clientCode);

        if ($result['valid']) {
            if ($request->boolean('save', true)) {
                $existing = SupplierScraper::where('supplier_id', $supplier->id)
                    ->where('scraper_key', ScraperKey::MAFARTA->value)
                    ->first();

                $authPayload = is_array($existing?->auth_payload) ? $existing->auth_payload : [];
                $authPayload['session_token'] = trim($token);

                SupplierScraper::updateOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'scraper_key' => ScraperKey::MAFARTA->value,
                    ],
                    [
                        'auth_payload' => $authPayload,
                        'is_active' => true,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Token de sesión de Cobeca validado exitosamente. Conexión establecida con el portal SIC.',
                'documents_count' => $result['documents_count'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'El token de sesión no es válido o ha expirado en Cobeca.',
        ], 400);
    }

    /**
     * Recibe el token de sesión de Cobeca/Mafarta desde la extensión de Chrome y lo guarda en supplier_scrapers.
     */
    public function receivePublicMafartaToken(ReceiveMafartaTokenRequest $request): JsonResponse
    {
        SupplierScraper::ensureSchema();

        $validated = $request->validated();
        $token = trim($validated['token']);

        $supplierId = !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null;
        $supplier = $supplierId ? Supplier::find($supplierId) : null;

        if (!$supplier) {
            $supplier = Supplier::where('name', 'LIKE', '%COBECA%')
                ->orWhere('name', 'LIKE', '%MAFARTA%')
                ->first();
        }

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el proveedor Cobeca/Mafarta en el ERP.',
            ], 404);
        }

        $scraperService = app(MafartaScraperServiceInterface::class);
        $result = $scraperService->testSessionToken($token, 31373);

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'El token no fue aceptado por el portal Cobeca.',
            ], 400);
        }

        $existing = SupplierScraper::where('supplier_id', $supplier->id)
            ->where('scraper_key', ScraperKey::MAFARTA->value)
            ->first();

        $authPayload = is_array($existing?->auth_payload) ? $existing->auth_payload : [];
        $authPayload['session_token'] = $token;
        $authPayload['last_token_source'] = 'chrome_extension';
        $authPayload['last_token_sync_at'] = now()->toDateTimeString();

        SupplierScraper::updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'scraper_key' => ScraperKey::MAFARTA->value,
            ],
            [
                'auth_payload' => $authPayload,
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Token de sesión recibido y activado en el ERP con éxito.',
            'supplier' => $supplier->name,
            'documents_count' => $result['documents_count'] ?? 0,
        ]);
    }
}
