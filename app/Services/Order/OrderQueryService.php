<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OrderQueryService
{

    private function getBaseQuery($valor): Builder
    {
        if ($valor == 'Completed') {
            $start = now()->startOfDay();
            $end = now()->endOfDay();
            return Order::query()->where('status', $valor)->whereBetween('order_date', [$start, $end])->with('client', 'seller');
        } else if ($valor == 'all') {
            return Order::query()->with('client', 'seller');
        }

        return Order::query()->where('status', $valor)->with('client', 'seller');
    }


    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";

            $query->where(function ($subQuery) use ($searchTerm) {
                // Búsqueda en la relación 'client' por identificación
                $subQuery->whereHas('client', function ($clientQuery) use ($searchTerm) {
                    $clientQuery->where('identification', 'like', $searchTerm);
                });

                // Búsqueda en la relación 'seller' por username
                $subQuery->orWhereHas('seller', function ($sellerQuery) use ($searchTerm) {
                    $sellerQuery->where('username', 'like', $searchTerm);
                });
            });
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (!empty($filters['state'])) {
            $query->where('status', $filters['state']);
        }

        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $startDate = !empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
            $endDate = !empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

            if ($startDate && $endDate) {
                $query->whereBetween('order_date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('order_date', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('order_date', '<=', $endDate);
            }
        }

        return $query;
    }

    public function getFilteredQuery(Request $request, $valor): Builder
    {
        $query = $this->getBaseQuery($valor);
        $filters = [
            'id' => $request->id,
            'q' => $request->q,
            'currency' => $request->currency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'state' => $request->state,
        ];
        $this->applyFilters($query, $filters);
        return $query;
    }



    private function getBaseQueryProduct(): Builder
    {
        return Product::query()->select(
            'products.*'
        )
            ->with([
                'laboratory',
                'origin',
                'group',
            ])
            ->addSelect(DB::raw('COALESCE((SELECT SUM(pl.quantity) FROM product_lots pl WHERE pl.product_id = products.id AND pl.expiration_date >= CURDATE() AND pl.quantity > 0), 0) as valid_stock_sum'));
    }


    private function applyFiltersProduct(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {

                if ($isStrictSearch) {
                    $subQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('active_ingredient', 'like', "%{$searchTerm}%")
                        ->orWhere('barcode', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $subQuery->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('name', 'like', "%{$word}%")
                                ->orWhere('active_ingredient', 'like', "%{$word}%")
                                ->orWhereHas('laboratory', function ($labQuery) use ($word) {
                                    $labQuery->where('name', 'like', "%{$word}%");
                                });
                        });
                    }
                }
            });
        }

        if (!empty($filters['laboratoryId'])) {
            $query->where('laboratory_id', $filters['laboratoryId']);
        }

        if (!empty($filters['originId'])) {
            $query->where('origin_id', $filters['originId']);
        }

        if (!empty($filters['groupId'])) {
            $query->where('group_id', $filters['groupId']);
        }

        $hasStock = $filters['hasStock'] ?? null;
        if ($hasStock === true) {
            $query->groupBy('products.id')
                ->havingRaw('valid_stock_sum > 0');
        } elseif ($hasStock === false) {
            $query->groupBy('products.id')
                ->havingRaw('valid_stock_sum <= 0');
        }

        if (!empty($filters['groupId'])) {
            $query->where('group_id', $filters['groupId']);
        }

        return $query;
    }

    private function applySortingProduct(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        if (empty($sortBy)) {
            return $query->orderBy('products.name', 'asc');
        }

        switch ($sortBy) {
            case 'laboratory.name':
                return $query->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'valid_stock':
            case 'lots_sum_quantity':
            case 'valid_stock_sum':
                return $query->orderBy('valid_stock_sum', $orderBy);

            case 'next_expiration':
                $subQuery = DB::raw('(SELECT MIN(expiration_date) FROM product_lots WHERE product_lots.product_id = products.id AND product_lots.expiration_date >= CURDATE() AND product_lots.quantity > 0)');
                return $query->orderBy($subQuery, $orderBy);

            case 'sales_average':
                return $query->orderBy('products.sales_average', $orderBy);

            case 'id':
            case 'name':
            case 'cost_price':
            case 'sale_price':
                return $query->orderBy("products.{$sortBy}", $orderBy);
        }

        return $query;
    }

    public function getFilteredQueryProduct(Request $request): Builder
    {
        $query = $this->getBaseQueryProduct();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->has('hasStock') ? filter_var($request->hasStock, FILTER_VALIDATE_BOOLEAN) : null,
            'groupId' => $request->get('groupId'),
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $this->applyFiltersProduct($query, $filters);
        $this->applySortingProduct($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
    public function getDebitoFiscal(string $startDate, string $endDate): array
    {
        try {
            // Consultar tabla fiscal_history para registros con iva_amount
            $query = DB::table('fiscal_history')
                ->whereNotNull('iva_amount')
                ->where('iva_amount', '>', 0)
                ->whereBetween('invoice_date', [$startDate, $endDate]);

            $fiscalRecords = $query->get();

            // Calcular totales
            $totalIvaAmount = $fiscalRecords->sum('iva_amount');
            $totalSpeAmount = $fiscalRecords->sum('spe_amount') ?? 0; // Si existe campo SPE

            // El débito fiscal es la suma del IVA cobrado en ventas
            $totalDebito = $totalIvaAmount + $totalSpeAmount;

            Log::info('Cálculo débito fiscal:', [
                'periodo' => [$startDate, $endDate],
                'total_records' => $fiscalRecords->count(),
                'total_iva_amount' => $totalIvaAmount,
                'total_spe_amount' => $totalSpeAmount,
                'total_debito' => $totalDebito
            ]);

            return [
                'total_records' => $fiscalRecords->count(),
                'total_iva_amount' => $totalIvaAmount,
                'total_spe_amount' => $totalSpeAmount,
                'total_debito' => $totalDebito,
                'records' => $fiscalRecords
            ];

        } catch (\Exception $e) {
            Log::error('Error en getDebitoFiscal: ' . $e->getMessage());
            throw $e;
        }
    }
    public function getFiscalHistoryRecords(string $startDate, string $endDate, int $page = 1, int $itemsPerPage = 10): array
    {
        try {
            // Query base para fiscal_history con IVA
            $query = DB::table('fiscal_history')
                ->whereNotNull('iva_amount')
                ->where('iva_amount', '>', 0)
                ->whereBetween('invoice_date', [$startDate, $endDate])
                ->orderBy('invoice_date', 'desc')
                ->orderBy('order_id', 'desc');

            // Clonar query para el conteo total
            $totalQuery = clone $query;
            $totalRecords = $totalQuery->count();

            // Aplicar paginación
            $offset = ($page - 1) * $itemsPerPage;
            $records = $query
                ->skip($offset)
                ->take($itemsPerPage)
                ->get();

            // Formatear los registros para el frontend
            $formattedRecords = $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'order_id' => $record->order_id,
                    'invoice_number' => $record->invoice_number,
                    'identification' => $record->identification,
                    'business_name' => $record->business_name,
                    'address' => $record->address,
                    'exempt_amount' => (float) $record->exempt_amount,
                    'iva_amount' => (float) $record->iva_amount,
                    'total_amount' => (float) $record->total_amount,
                    'invoice_date' => $record->invoice_date,
                    'spe' => (bool) $record->spe,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at
                ];
            });

            // Calcular totales para la página actual
            $pageTotals = [
                'total_exempt' => $formattedRecords->sum('exempt_amount'),
                'total_iva' => $formattedRecords->sum('iva_amount'),
                'total_amount' => $formattedRecords->sum('total_amount')
            ];

            Log::info('Registros de fiscal history obtenidos:', [
                'periodo' => [$startDate, $endDate],
                'page' => $page,
                'items_per_page' => $itemsPerPage,
                'total_records' => $totalRecords,
                'records_in_page' => $formattedRecords->count(),
                'page_totals' => $pageTotals
            ]);

            return [
                'data' => $formattedRecords->toArray(),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $itemsPerPage,
                    'total' => $totalRecords,
                    'last_page' => ceil($totalRecords / $itemsPerPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $itemsPerPage, $totalRecords)
                ],
                'totals' => $pageTotals,
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error en getFiscalHistoryRecords: ' . $e->getMessage());
            throw $e;
        }
    }
}
