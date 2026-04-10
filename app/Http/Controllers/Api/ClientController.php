<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateClientRequest;
use App\Http\Requests\EditClientRequest;
use App\Http\Requests\UpdateCompanyClientFormRequest;
use App\Models\Client as ClientModel;
use App\Models\Order;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    //

    public function __construct(
        protected Client $client
    ) {
    }


    public function create(CreateClientRequest $request): JsonResponse
    {
        if ($request->client->identification_type == "J-") {
            if ($request->client->last_name != "" | $request->client->last_name != null) {
                $errors = [
                    "last_name" => ["Si el usuario es una entidad jurídica, el apellido no es necesario."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, el apellido no es necesario.", 400, $errors);
            }
            if ($request->client->company_id != "" | $request->client->company_id != null) {
                $errors = [
                    "company_id" => ["Si el usuario es una entidad jurídica, la compañía no es necesaria."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, la compañía no es necesaria.", 400, $errors);
            }
        }

        $respuestaDB = $this->client->create($request->client->all());

        return ApiResponse::success($respuestaDB, "Cliente creado exitosamente", 200);
    }

    public function edit(EditClientRequest $request): JsonResponse
    {
        $buscarPorIdentificaion = $this->client->consultByIdentification($request->client->identification);
        if ($buscarPorIdentificaion) {
            if ($request->client->id != $buscarPorIdentificaion->id) {
                $errors = [
                    "identification" => ["No se puede actualizar porque la cédula/RIF ya está en uso"]
                ];
                return ApiResponse::error("No se puede actualizar porque la cédula/RIF ya está en uso", 400, $errors);
            }
        }

        if ($request->client->identification_type == "J-") {
            if ($request->client->last_name != "" | $request->client->last_name != null) {
                $errors = [
                    "last_name" => ["Si el usuario es una entidad jurídica, el apellido no es necesario."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, el apellido no es necesario.", 400, $errors);
            }
            if ($request->client->company_id != "" | $request->client->company_id != null) {
                $errors = [
                    "company_id" => ["Si el usuario es una entidad jurídica, la compañía no es necesaria."]
                ];
                return ApiResponse::error("Si el usuario es una entidad jurídica, la compañía no es necesaria.", 400, $errors);
            }
        }

        $respuestaDB = $this->client->edit($request->client->all());

        return ApiResponse::success($respuestaDB, "Cliente editado exitosamente", 200);
    }

    public function updateCompany(UpdateCompanyClientFormRequest $request): JsonResponse
    {
        $client_id = $request->data->client_id;
        $company_id = $request->data->company_id;
        $status = $request->data->status;
        $respuestaDB = $this->client->updateCompany($client_id, $company_id, $status);


        return ApiResponse::success($respuestaDB, "Cliente editado exitosamente", 200);
    }

    public function consultAll(Request $request)
    {
        $respuestaDB = $this->client->consultAll();
        return ApiResponse::success($respuestaDB, "Operación exitosa", 200);
    }

    public function consultById(Request $request)
    {
        $respuestaDB = $this->client->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("El cliente no fue encontrado", 404);
        }

        return ApiResponse::success($respuestaDB, "Operación exitosa", 200);
    }

    public function consultByIdentification(Request $request)
    {
        $respuestaDB = $this->client->consultByIdentification($request->identification);

        if (!$respuestaDB) {
            return ApiResponse::error("El cliente no fue encontrado", 404);
        }

        return ApiResponse::success($respuestaDB, "Operación exitosa", 200);
    }

    public function deleteById(Request $request)
    {
        $respuestaDB = $this->client->consultById($request->id);

        if (!$respuestaDB) {
            return ApiResponse::error("El cliente no fue encontrado", 404);
        }

        $this->client->deleteById($request->id);

        $validarEliminacio = $this->client->consultById($request->id);

        if ($validarEliminacio) {
            return ApiResponse::error("El cliente no fue eliminado", 404);
        }

        return ApiResponse::success($validarEliminacio, "Cliente eliminado exitosamente", 200);
    }

    public function filtrar(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
        ];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_identificacion_filtro")) {
            $filtros["tipo_identificacion_filtro"] = $request->tipo_identificacion_filtro;
        }

        if ($request->filled("tipo") && $request->filled("tipo_identificacion_filtro") == false) {
            $filtros["tipo"] = $request->tipo;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("company_id")) {
            $filtros["company_id"] = $request->company_id;
        }

        if ($request->filled("client_type")) {
            $filtros["client_type"] = $request->client_type;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->client->filtrar($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }

    public function filtrarSinPaginar(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
        ];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_identificacion_filtro")) {
            $filtros["tipo_identificacion_filtro"] = $request->tipo_identificacion_filtro;
        }

        if ($request->filled("tipo") && $request->filled("tipo_identificacion_filtro") == false) {
            $filtros["tipo"] = $request->tipo;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("company_id")) {
            $filtros["company_id"] = $request->company_id;
        }

        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->client->filterWithoutPaginate($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }

    public function exportarExcel(Request $request)
    {
        $filtros = [];

        if ($request->filled("buscardor_filtro")) {
            $filtros["buscardor_filtro"] = $request->buscardor_filtro;
        }

        if ($request->filled("tipo_identificacion_filtro")) {
            $filtros["tipo_identificacion_filtro"] = $request->tipo_identificacion_filtro;
        }

        if ($request->filled("tipo") && $request->filled("tipo_identificacion_filtro") == false) {
            $filtros["tipo"] = $request->tipo;
        }

        if ($request->filled("fechaDesde_filtro") && $request->filled("fechaHasta_filtro")) {
            $filtros["fechaDesde_filtro"] = $request->fechaDesde_filtro;
            $filtros["fechaHasta_filtro"] = $request->fechaHasta_filtro;
        }

        if ($request->filled("company_id")) {
            $filtros["company_id"] = $request->company_id;
        }

        $excel = $this->client->exportExcel($filtros);

        $fileName = 'clientes-' . now()->format('Y-m-d') . '.' . $request->formato;

        return Excel::download($excel, $fileName);
    }

    public function pending(Request $request)
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
            "status" => $request->status
        ];

        $repuesta = $this->client->pending($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }

    /**
     * Obtener estadísticas del cliente para el dashboard modal.
     */
    public function stats(Request $request, $id): JsonResponse
    {
        $client = ClientModel::with('company')->find($id);

        if (!$client) {
            return ApiResponse::error("El cliente no fue encontrado", 404);
        }

        // Órdenes completadas del cliente
        $totalOrders = Order::where('client_id', $id)
            ->where('status', Order::COMPLETED)
            ->count();

        // Total gastado en USD (manejo robusto: usa total_amount_usd si existe, si no y es USD usa total_amount)
        $totalSpent = (float) Order::where('client_id', $id)
            ->where('status', Order::COMPLETED)
            ->sum(DB::raw('
                CASE 
                    WHEN total_amount_usd IS NOT NULL AND total_amount_usd > 0 THEN total_amount_usd 
                    WHEN currency = "USD" THEN total_amount 
                    ELSE 0 
                END
            '));

        $averageTicket = $totalOrders > 0 ? round($totalSpent / $totalOrders, 2) : 0;

        // Días desde la última compra
        $lastOrder = Order::where('client_id', $id)
            ->where('status', Order::COMPLETED)
            ->orderByDesc('order_date')
            ->first();

        $daysSinceLastPurchase = $lastOrder
            ? Carbon::parse($lastOrder->order_date)->diffInDays(Carbon::now())
            : null;

        // Fecha de última compra
        $lastPurchaseDate = $lastOrder
            ? Carbon::parse($lastOrder->order_date)->format('d/m/Y')
            : null;

        // Top 5 productos más comprados
        $topProducts = OrderDetail::select(
                'order_details.product_id',
                'products.name as product_name',
                'laboratories.name as laboratory_name',
                DB::raw('SUM(order_details.quantity) as total_quantity')
            )
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->where('orders.client_id', $id)
            ->where('orders.status', Order::COMPLETED)
            ->groupBy('order_details.product_id', 'products.name', 'laboratories.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        // Últimos 10 productos comprados con precio en USD
        $lastProducts = OrderDetail::select(
                'order_details.product_id',
                'products.name as product_name',
                'laboratories.name as laboratory_name',
                'order_details.quantity',
                DB::raw('
                    CASE 
                        WHEN order_details.unit_price_usd IS NOT NULL AND order_details.unit_price_usd > 0 THEN order_details.unit_price_usd
                        WHEN orders.currency = "USD" AND order_details.quantity > 0 THEN (order_details.price / order_details.quantity)
                        ELSE 0
                    END as unit_price_usd_calc
                '),
                DB::raw('
                    CASE 
                        WHEN order_details.unit_price_usd IS NOT NULL AND order_details.unit_price_usd > 0 THEN (order_details.unit_price_usd * order_details.quantity)
                        WHEN orders.currency = "USD" THEN order_details.price
                        ELSE 0
                    END as total_usd
                '),
                'orders.order_date'
            )
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->where('orders.client_id', $id)
            ->where('orders.status', Order::COMPLETED)
            ->orderByDesc('orders.order_date')
            ->limit(10)
            ->get();

        // Badge: usar el guardado en DB o calcular en tiempo real
        $badge = $client->client_type ?? 'Nuevo';
        // Recalcular si no tiene tipo asignado
        if (!$client->client_type || $client->client_type === '') {
            if ($totalOrders === 0) {
                $badge = 'Nuevo';
            } elseif ($daysSinceLastPurchase !== null && $daysSinceLastPurchase > 30) {
                $badge = 'En Riesgo';
            } elseif ($totalSpent >= 500 || $totalOrders >= 20) {
                $badge = 'VIP';
            } elseif ($totalOrders >= 5) {
                $badge = 'Frecuente';
            } else {
                $badge = 'Ocasional';
            }
        }

        $data = [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'last_name' => $client->last_name,
                'identification_type' => $client->identification_type,
                'identification' => $client->identification,
                'email' => $client->email,
                'phone' => $client->phone,
                'company' => $client->company?->name,
                'client_type' => $client->client_type,
            ],
            'stats' => [
                'total_spent' => round($totalSpent, 2),
                'total_orders' => $totalOrders,
                'average_ticket' => $averageTicket,
                'days_since_last_purchase' => $daysSinceLastPurchase,
                'last_purchase_date' => $lastPurchaseDate,
                'badge' => $badge,
            ],
            'top_products' => $topProducts->map(fn($item) => [
                'product_name' => $item->product_name,
                'laboratory_name' => $item->laboratory_name,
                'total_quantity' => $item->total_quantity,
            ]),
            'last_products' => $lastProducts->map(fn($item) => [
                'product_name' => $item->product_name,
                'laboratory_name' => $item->laboratory_name,
                'quantity' => $item->quantity,
                'price_usd' => round($item->unit_price_usd_calc ?? 0, 2),
                'total_usd' => round($item->total_usd ?? 0, 2),
                'date' => Carbon::parse($item->order_date)->format('d/m/Y'),
            ]),
        ];

        return ApiResponse::success($data, "Estadísticas del cliente obtenidas exitosamente", 200);
    }

    public function bulkCleanup(): JsonResponse
    {
        try {
            DB::beginTransaction();
            $count = $this->client->bulkCleanupInvalid();
            DB::commit();

            return ApiResponse::success([
                'deleted_at' => now(),
                'count' => $count
            ], "Limpieza completada: {$count} clientes eliminados.", 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error("Error al realizar la limpieza masiva: " . $e->getMessage(), 500);
        }
    }
}
