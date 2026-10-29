<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Wholesale;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Wholesale\WholesaleMarketplaceService;
use App\Services\Wholesale\WholesaleOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2BMarketplaceController extends Controller
{
    public function __construct(
        protected WholesaleMarketplaceService $marketplaceService,
        protected WholesaleOrderService $orderService
    ) {}

    /**
     * Resuelve el cliente farmacia autenticado o seleccionado.
     */
    protected function resolveCurrentClient(Request $request): Client
    {
        $clientId = $request->input('client_id');
        if ($clientId) {
            return Client::findOrFail($clientId);
        }

        // Si el usuario autenticado está vinculado a un cliente farmacia
        $user = auth()->user();
        if ($user) {
            $client = Client::where('user_id', $user->id)->first();
            if ($client) {
                return $client;
            }
        }

        return Client::firstOrFail();
    }

    /**
     * Catálogo de productos para el Marketplace B2B de Farmacias.
     */
    public function catalog(Request $request): JsonResponse
    {
        $client = $this->resolveCurrentClient($request);
        $filters = $request->only([
            'search',
            'laboratory_id',
            'category_id',
            'requires_cold_chain',
        ]);

        $perPage = (int) $request->input('per_page', 20);
        $catalog = $this->marketplaceService->getMarketplaceCatalog($client, $filters, $perPage);

        return response()->json([
            'success' => true,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'commercial_discount_pct' => (float) ($client->commercial_discount_pct ?: 0),
            ],
            'data' => $catalog,
        ]);
    }

    /**
     * Consulta el estado de cuenta y facturas pendientes de la farmacia.
     */
    public function accountStatement(Request $request): JsonResponse
    {
        $client = $this->resolveCurrentClient($request);
        $statement = $this->marketplaceService->getAccountStatement($client);

        return response()->json([
            'success' => true,
            'data' => $statement,
        ]);
    }

    /**
     * Envío de pedido desde el carrito o archivo Excel del Marketplace B2B.
     */
    public function submitOrder(Request $request): JsonResponse
    {
        $client = $this->resolveCurrentClient($request);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $payload = [
            'client_id' => $client->id,
            'items' => $request->items,
            'notes' => $request->notes,
            'delivery_route_id' => $client->delivery_route_id,
        ];

        $result = $this->orderService->createWholesaleOrder($payload);

        return response()->json([
            'success' => true,
            'message' => 'Tu pedido mayorista ha sido recibido exitosamente.',
            'data' => $result,
        ], 201);
    }

    /**
     * Envío de comprobante de pago o retención desde el portal de autogestión.
     */
    public function submitPayment(Request $request): JsonResponse
    {
        $client = $this->resolveCurrentClient($request);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|in:USD,BS,VES,COP',
            'payment_method' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'receipt_photo_url' => 'nullable|string|max:255',
            'withheld_iva_amount' => 'nullable|numeric|min:0',
            'withheld_islr_amount' => 'nullable|numeric|min:0',
            'retention_voucher_number' => 'nullable|string|max:100',
            'retention_voucher_photo_url' => 'nullable|string|max:255',
            'invoice_allocations' => 'nullable|array',
            'invoice_allocations.*.credit_id' => 'required|integer|exists:credits,id',
            'invoice_allocations.*.amount_usd' => 'required|numeric|min:0.01',
            'invoice_allocations.*.early_discount_applied' => 'nullable|numeric|min:0',
        ]);

        $payment = $this->marketplaceService->submitCustomerPayment($client, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Tu pago ha sido registrado y está en proceso de conciliación.',
            'data' => $payment,
        ], 201);
    }
}
