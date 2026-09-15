<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\CristmedicalsApiServiceInterface;
use App\Helpers\FtpCrypt;
use App\Models\AutoOrder;
use App\Models\GeneralSetting;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use App\Services\ExchangeRateServices;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CristmedicalsApiService implements CristmedicalsApiServiceInterface
{
    private const API_BASE_URL = 'https://apienterprise.cristmedicals.com/api/v1';
    private const DEFAULT_TOKEN = 'GLyQBJZhVhEgCoGm1QTxKxgeUTR9PWPUEcR6GbWJEAVopyV8Tj9Av8JMuYIbmsF7ZcIZapDPUp3nCJnU';
    private const DEFAULT_CO_CLI = 'FAR00818';

    public function __construct(
        protected ExchangeRateServices $exchangeRateService
    ) {
    }

    /**
     * Transmite el pedido automático a la API REST Enterprise de Cristmedicals.
     */
    public function sendOrderApi(AutoOrder $autoOrder): array
    {
        $autoOrder->loadMissing(['details.productSupplier', 'details.product']);

        $supplier = $autoOrder->supplier;
        if (!$supplier) {
            $supplier = Supplier::with('connections')->find($autoOrder->supplier_id);
        }

        // Buscar conexión API del proveedor
        $connection = $supplier?->connections()
            ->whereIn('type', ['api', 'http'])
            ->first()
            ?? $supplier?->connections()->first();

        // 1. Obtener Token de autenticación
        $token = null;
        if ($connection && !empty($connection->password)) {
            try {
                $token = FtpCrypt::decrypt($connection->password);
            } catch (\Throwable) {
                $token = $connection->password;
            }
        }
        $token = $token ?: env('CRISTMEDICALS_API_TOKEN', self::DEFAULT_TOKEN);

        // 2. Obtener Código de Cliente (co_cli)
        $coCli = $connection?->username ?: env('CRISTMEDICALS_USERNAME', self::DEFAULT_CO_CLI);

        // 3. Obtener Datos de la Empresa / Farmacia
        $setting = GeneralSetting::first();
        $companyName = $setting?->app_name ?: 'FARMACIA';
        $rif = $setting?->rif ?: ($setting?->app_rif ?: 'J-00000000-0');

        // 4. Fechas de Emisión y Vencimiento
        $createdAt = $autoOrder->created_at ? Carbon::parse($autoOrder->created_at) : Carbon::now();
        $fecEmis = $createdAt->format('Y-m-d');
        $fecVenc = $createdAt->copy()->addDays(30)->format('Y-m-d');

        // 5. Tasa de Cambio (BCV o fallback a 1.0)
        $bcvRate = $this->exchangeRateService->fetchRateFromApi('BS') ?? 1.0;
        if ($bcvRate <= 0) {
            $bcvRate = 1.0;
        }

        // 6. Preparar Renglones
        $renglones = [];
        $rengNum = 1;
        $totBruto = 0.0;

        foreach ($autoOrder->details as $detail) {
            $supplierItemCode = $detail->productSupplier?->cod_supplier;

            // Si no viene en la relación, buscar por product_suppliers_id
            if (empty($supplierItemCode) && !empty($detail->product_suppliers_id)) {
                $psDirect = ProductSupplier::find($detail->product_suppliers_id);
                $codeDirect = $psDirect?->cod_supplier;
                if (!empty($codeDirect) && $codeDirect !== '0' && $codeDirect !== 0) {
                    $supplierItemCode = $codeDirect;
                }
            }

            // Buscar por código de barras si falta o es 0
            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                $barcode = $detail->product?->barcode ?? $detail->productSupplier?->barcode_match;
                if (!empty($barcode)) {
                    $psCrist = ProductSupplier::where('supplier_id', $autoOrder->supplier_id)
                        ->where('barcode_match', $barcode)
                        ->whereNotNull('cod_supplier')
                        ->whereNotIn('cod_supplier', ['0', '', 'NULL'])
                        ->first();

                    if (!$psCrist) {
                        $psCrist = ProductSupplier::whereHas('supplier', function ($sq) {
                                $sq->where('name', 'LIKE', '%CRIST%');
                            })
                            ->where('barcode_match', $barcode)
                            ->whereNotNull('cod_supplier')
                            ->whereNotIn('cod_supplier', ['0', '', 'NULL'])
                            ->first();
                    }

                    if ($psCrist) {
                        $supplierItemCode = $psCrist->cod_supplier;
                    }
                }
            }

            if (empty($supplierItemCode) || $supplierItemCode === '0' || $supplierItemCode === 0) {
                $supplierItemCode = $detail->productSupplier?->barcode_match
                    ?? $detail->product?->barcode
                    ?? (string) ($detail->product_id ?? $detail->id);
            }

            $qty = max(1, (int) round((float) ($detail->quantity ?? 1)));
            $unitPrice = (float) ($detail->unit_cost ?? $detail->productSupplier?->unit_cost_usd ?? $detail->productSupplier?->unit_cost ?? 0.0);
            $lineTotal = round($qty * $unitPrice, 2);
            $totBruto += $lineTotal;

            $renglones[] = [
                'reng_num' => $rengNum++,
                'co_art' => trim((string) $supplierItemCode),
                'co_alma' => '01',
                'total_art' => $qty,
                'prec_vta' => round($unitPrice, 2),
                'reng_neto' => $lineTotal,
            ];
        }

        if (empty($renglones)) {
            throw new Exception("La orden #{$autoOrder->id} no contiene renglones válidos para transmitir a Cristmedicals.");
        }

        $totBruto = round($totBruto, 2);
        $iva = 0.00;
        $totNeto = round($totBruto + $iva, 2);

        // 7. Construir Payload
        $payload = [
            'fact_num' => (int) $autoOrder->id,
            'nombre' => $companyName,
            'rif' => $rif,
            'co_cli' => $coCli,
            'co_ven' => env('CRISTMEDICALS_CO_VEN', 'VEN01'),
            'fec_emis' => $fecEmis,
            'fec_venc' => $fecVenc,
            'tasa' => round((float) $bcvRate, 4),
            'moneda' => 'USD',
            'tot_bruto' => $totBruto,
            'tot_neto' => $totNeto,
            'iva' => $iva,
            'renglones' => $renglones,
        ];

        Log::info("[CRISTMEDICALS API PEDIDO] Transmitiendo orden #{$autoOrder->id} a Cristmedicals", [
            'url' => self::API_BASE_URL . '/pedidos',
            'payload' => $payload,
        ]);

        // 8. Realizar Petición POST a la API
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->withoutVerifying()
          ->timeout(60)
          ->post(self::API_BASE_URL . '/pedidos', $payload);

        $status = $response->status();
        $responseBody = $response->json() ?? $response->body();

        Log::info("[CRISTMEDICALS API PEDIDO] Respuesta de Cristmedicals para orden #{$autoOrder->id}", [
            'status' => $status,
            'response' => $responseBody,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $responseBody,
                'message' => "Pedido #{$autoOrder->id} transmitido exitosamente a Cristmedicals vía API Enterprise.",
            ];
        }

        $errorMessage = is_array($responseBody)
            ? ($responseBody['message'] ?? $responseBody['error'] ?? json_encode($responseBody))
            : $responseBody;

        throw new Exception("Error al transmitir el pedido a Cristmedicals (HTTP {$status}): {$errorMessage}");
    }
}