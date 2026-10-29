<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\InventoryBookContract;
use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use App\Models\GeneralSetting;
use App\Models\InventoryBook;
use App\Models\InventoryBookEntry;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryBookRepository implements InventoryBookContract
{
    public function getBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = InventoryBook::with(['closedByUser:id,name'])
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('fortnight', 'desc');

        if (!empty($filters['year'])) {
            $query->where('year', (int) $filters['year']);
        }

        if (!empty($filters['month'])) {
            $query->where('month', (int) $filters['month']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    public function findOrCreateBook(int $year, int $month, ?int $fortnight = null): InventoryBook
    {
        $generalSetting = GeneralSetting::first();
        $isSpecialTaxpayer = $generalSetting && (
            $generalSetting->special_taxpayer_status === 'activada' ||
            (bool) $generalSetting->enable_ce
        );

        $periodType = $isSpecialTaxpayer ? PurchaseBookPeriodType::FORTNIGHTLY : PurchaseBookPeriodType::MONTHLY;

        if ($periodType === PurchaseBookPeriodType::FORTNIGHTLY) {
            $fortnight = $fortnight ?? (now()->day <= 15 ? 1 : 2);
            $startDate = Carbon::create($year, $month, $fortnight === 1 ? 1 : 16)->startOfDay();
            $endDate = $fortnight === 1
                ? Carbon::create($year, $month, 15)->endOfDay()
                : Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        } else {
            $fortnight = null;
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        }

        $query = InventoryBook::where('year', $year)->where('month', $month);
        if ($fortnight !== null) {
            $query->where('fortnight', $fortnight);
        } else {
            $query->whereNull('fortnight');
        }

        $book = $query->first();

        if (!$book) {
            $book = InventoryBook::create([
                'period_type' => $periodType,
                'year' => $year,
                'month' => $month,
                'fortnight' => $fortnight,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => PurchaseBookStatus::OPEN,
                'include_negative_adjustments_as_withdrawals' => true,
            ]);

            $this->syncTransactions($book);
        }

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function getBookById(int $id): InventoryBook
    {
        return InventoryBook::with([
            'entries' => fn($query) => $query->orderBy('operation_number', 'asc'),
            'closedByUser:id,name',
        ])->findOrFail($id);
    }

    public function syncTransactions(InventoryBook $book): InventoryBook
    {
        if ($book->isClosed()) {
            return $book;
        }

        $startDateStr = Carbon::parse($book->start_date)->startOfDay()->toDateTimeString();
        $endDateStr = Carbon::parse($book->end_date)->endOfDay()->toDateTimeString();
        $startDateOnly = Carbon::parse($book->start_date)->toDateString();
        $endDateOnly = Carbon::parse($book->end_date)->toDateString();

        // 1. Obtener existencias iniciales desde el libro inmediatamente anterior cerrado
        $previousBook = InventoryBook::where(function ($q) use ($book) {
            $q->where('year', '<', $book->year)
                ->orWhere(function ($q2) use ($book) {
                    $q2->where('year', $book->year)
                        ->where('month', '<', $book->month);
                })
                ->orWhere(function ($q3) use ($book) {
                    $q3->where('year', $book->year)
                        ->where('month', $book->month)
                        ->where('fortnight', '<', $book->fortnight ?? 0);
                });
        })
            ->where('status', PurchaseBookStatus::CLOSED->value)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->orderBy('fortnight', 'desc')
            ->first();

        $previousFinalStocks = [];
        $previousCostsBs = [];
        if ($previousBook) {
            $prevEntries = InventoryBookEntry::where('inventory_book_id', $previousBook->id)->get();
            foreach ($prevEntries as $pe) {
                $previousFinalStocks[$pe->product_id] = (float) $pe->final_stock;
                $previousCostsBs[$pe->product_id] = (float) $pe->unit_cost_bs;
            }
        }

        // Obtener tasa BCV oficial vigente para conversiones de respaldo
        $resourceService = app(\App\Services\Resources\ResourceService::class);
        $bcvRate = (float) $resourceService->getExchangeRate('BS');
        if ($bcvRate <= 0) {
            $bcvRate = (float) $resourceService->getExchangeRate('BCV');
        }
        if ($bcvRate <= 0) {
            $generalSetting = GeneralSetting::first();
            $bcvRate = (float) ($generalSetting?->exchange_rate ?? 1.0);
        }
        if ($bcvRate <= 0) {
            $bcvRate = 1.0;
        }

        // 2. Entradas por compras en el período (InvoiceDetail de facturas del período)
        $purchaseDetails = DB::table('invoice_details')
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->whereBetween('invoices.created_invoice_date', [$startDateOnly, $endDateOnly])
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoices.is_expense', false)
            ->select(
                'invoice_details.product_id',
                'invoice_details.quantity',
                'invoice_details.unit_cost',
                'invoices.currency',
                'invoices.exchange_rate',
                'invoices.created_invoice_date',
                'invoice_details.id as detail_id'
            )
            ->orderBy('invoices.created_invoice_date', 'asc')
            ->orderBy('invoice_details.id', 'asc')
            ->get();

        $purchaseTotals = [];
        $lastPurchaseCostBs = [];
        foreach ($purchaseDetails as $pd) {
            $pid = (int) $pd->product_id;
            $purchaseTotals[$pid] = ($purchaseTotals[$pid] ?? 0) + (float) $pd->quantity;

            // Determinar costo en Bs al momento de la compra con la tasa de esa factura
            $invRate = (float) $pd->exchange_rate;
            if ($invRate <= 0) {
                $invRate = $bcvRate;
            }

            $costBs = (strtoupper((string) $pd->currency) === 'USD' || $invRate > 1)
                ? ((float) $pd->unit_cost * $invRate)
                : (float) $pd->unit_cost;

            $lastPurchaseCostBs[$pid] = $costBs;
        }

        // Últimas compras históricas registradas en facturas para productos sin compras en este período
        $historicalPurchases = DB::table('invoice_details')
            ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoices.is_expense', false)
            ->where('invoices.created_invoice_date', '<=', $endDateOnly)
            ->select(
                'invoice_details.product_id',
                'invoice_details.unit_cost',
                'invoices.currency',
                'invoices.exchange_rate',
                'invoices.created_invoice_date'
            )
            ->orderBy('invoices.created_invoice_date', 'desc')
            ->orderBy('invoice_details.id', 'desc')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        // 3. Salidas por ventas en el período agrupadas a nivel de base de datos
        $salesTotals = DB::table('inventory_movements')
            ->whereBetween('movement_date', [$startDateStr, $endDateStr])
            ->whereIn('movement_type', ['sale', 'return', 'Venta', 'Devolución'])
            ->groupBy('product_id')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN LOWER(movement_type) IN ('sale', 'venta') THEN quantity WHEN LOWER(movement_type) IN ('return', 'devolución') THEN -quantity ELSE 0 END) as net_sales")
            ->pluck('net_sales', 'product_id')
            ->all();

        // 4. Retiros (ajustes negativos, pérdidas, vencidos) agrupados en SQL
        $withdrawalTotals = [];
        if ($book->include_negative_adjustments_as_withdrawals) {
            $withdrawalTotals = DB::table('inventory_movements')
                ->whereBetween('movement_date', [$startDateStr, $endDateStr])
                ->whereIn('movement_type', ['loss', 'expired', 'adjustment', 'Pérdida', 'Caducado', 'Ajuste'])
                ->groupBy('product_id')
                ->select('product_id')
                ->selectRaw("
                    SUM(
                        CASE 
                            WHEN LOWER(movement_type) IN ('adjustment', 'ajuste') THEN 
                                CASE WHEN stock_before > stock_after THEN (stock_before - stock_after)
                                     WHEN quantity < 0 THEN ABS(quantity)
                                     ELSE 0 END
                            ELSE ABS(quantity)
                        END
                    ) as total_withdrawals
                ")
                ->pluck('total_withdrawals', 'product_id')
                ->all();
        }

        // 5. Autoconsumos agrupados en SQL
        $selfConsumptionTotals = DB::table('inventory_movements')
            ->whereBetween('movement_date', [$startDateStr, $endDateStr])
            ->whereIn('movement_type', ['consumption', 'autoconsumo', 'Autoconsumo'])
            ->groupBy('product_id')
            ->select('product_id')
            ->selectRaw('SUM(ABS(quantity)) as total_consumption')
            ->pluck('total_consumption', 'product_id')
            ->all();

        // 6. Cargar productos activos sin sobrecarga de hidratación Eloquent (toBase)
        // EXCLUSIÓN ESTRICTA: Servicios, Zoom, fletes o códigos virtuales no inventariables
        $products = DB::table('products')
            ->where('is_deleted', false)
            ->where(function ($q) {
                $q->whereNull('barcode')
                  ->orWhere(function ($q2) {
                      $q2->where('barcode', '!=', 'ZOOM-SERVICE')
                         ->where('barcode', 'NOT LIKE', 'ZOOM%');
                  });
            })
            ->where('name', 'NOT LIKE', '%SERVICIO ZOOM%')
            ->where('name', 'NOT LIKE', '%ZOOM-SERVICE%')
            ->where('name', 'NOT LIKE', 'SERVICIO %')
            ->where('stock', '<', 500000)
            ->select('id', 'name', 'presentation', 'stock', 'unit_cost', 'iva', 'barcode')
            ->get();

        // Entradas manuales previas que la contadora pudo haber modificado en este libro
        $existingManualOverrides = InventoryBookEntry::where('inventory_book_id', $book->id)
            ->where('is_manual_override', true)
            ->get()
            ->keyBy('product_id');

        $entriesToInsert = [];
        $opNumber = 1;

        foreach ($products as $product) {
            $pid = (int) $product->id;

            // Filtro de seguridad adicional contra ítems de servicio o virtuales
            $nameUpper = mb_strtoupper($product->name, 'UTF-8');
            if (
                str_contains($nameUpper, 'SERVICIO ZOOM') ||
                str_contains($nameUpper, 'ZOOM-SERVICE') ||
                $product->barcode === 'ZOOM-SERVICE' ||
                (float) $product->stock >= 500000
            ) {
                continue;
            }

            // Determinar existencia inicial
            $initialStock = isset($previousFinalStocks[$pid])
                ? (float) $previousFinalStocks[$pid]
                : (float) $product->stock; // Fallback al stock actual si no hay libro previo

            $purchases = (float) ($purchaseTotals[$pid] ?? 0);
            $sales = max(0, (float) ($salesTotals[$pid] ?? 0));
            $withdrawals = (float) ($withdrawalTotals[$pid] ?? 0);
            $selfConsumptions = (float) ($selfConsumptionTotals[$pid] ?? 0);

            // Si hay override manual previo, respetar lo que configuró la contadora
            if (isset($existingManualOverrides[$pid])) {
                $override = $existingManualOverrides[$pid];
                $withdrawals = (float) $override->withdrawals;
                $selfConsumptions = (float) $override->self_consumptions;
                $unitCostBs = (float) $override->unit_cost_bs;
            } else {
                // Regla de valoración: siempre en Bolívares al momento de la compra
                if (isset($lastPurchaseCostBs[$pid]) && $lastPurchaseCostBs[$pid] > 0) {
                    // 1. Comprado en el período: costo en USD de la factura x tasa de esa factura
                    $unitCostBs = (float) $lastPurchaseCostBs[$pid];
                } elseif (isset($previousCostsBs[$pid]) && $previousCostsBs[$pid] > 0) {
                    // 2. Costo en Bs del libro anterior
                    $unitCostBs = (float) $previousCostsBs[$pid];
                } elseif (isset($historicalPurchases[$pid])) {
                    // 3. Última compra histórica registrada en el ERP
                    $hp = $historicalPurchases[$pid];
                    $hpRate = (float) $hp->exchange_rate > 0 ? (float) $hp->exchange_rate : $bcvRate;
                    $unitCostBs = (strtoupper((string) $hp->currency) === 'USD' || $hpRate > 1)
                        ? ((float) $hp->unit_cost * $hpRate)
                        : (float) $hp->unit_cost;
                } else {
                    // 4. Fallback: costo en USD del producto multiplicado por la tasa BCV del momento
                    $unitCostBs = (float) $product->unit_cost * $bcvRate;
                }
            }

            $finalStock = $initialStock + $purchases - $sales - $withdrawals - $selfConsumptions;

            // Criterio del usuario: listar productos con existencia > 0 o con movimientos en la quincena
            $hasActivity = ($initialStock > 0 || $purchases > 0 || $sales > 0 || $withdrawals > 0 || $selfConsumptions > 0 || $finalStock > 0);
            if (!$hasActivity) {
                continue;
            }

            // Cálculo en Bolívares por fila
            $previousValueBs = $initialStock * $unitCostBs;
            $purchasesCostBs = $purchases * $unitCostBs;
            $salesCostBs = $sales * $unitCostBs;
            $withdrawalsCostBs = $withdrawals * $unitCostBs;
            $selfConsumptionsCostBs = $selfConsumptions * $unitCostBs;
            $finalInventoryValueBs = $finalStock * $unitCostBs;

            // Tratamiento de IVA para desincorporaciones / retiros
            $ivaRate = ((float) $product->iva > 0) ? 0.16 : 0.00;
            $withdrawalVatBs = $withdrawalsCostBs * $ivaRate;

            $description = trim($product->name . ' ' . ($product->presentation ?? ''));

            $entriesToInsert[] = [
                'inventory_book_id' => $book->id,
                'product_id' => $pid,
                'operation_number' => $opNumber++,
                'product_code' => (string) $pid,
                'product_description' => $description,
                'unit_cost_bs' => round($unitCostBs, 4),
                'initial_stock' => round($initialStock, 2),
                'purchase_entries' => round($purchases, 2),
                'sales_exits' => round($sales, 2),
                'withdrawals' => round($withdrawals, 2),
                'self_consumptions' => round($selfConsumptions, 2),
                'final_stock' => round($finalStock, 2),
                'previous_value_bs' => round($previousValueBs, 4),
                'purchase_entries_cost_bs' => round($purchasesCostBs, 4),
                'sales_exits_cost_bs' => round($salesCostBs, 4),
                'withdrawals_cost_bs' => round($withdrawalsCostBs, 4),
                'self_consumptions_cost_bs' => round($selfConsumptionsCostBs, 4),
                'final_inventory_value_bs' => round($finalInventoryValueBs, 4),
                'iva_rate' => $ivaRate,
                'withdrawal_vat_bs' => round($withdrawalVatBs, 4),
                'withdrawal_reason' => $withdrawals > 0 ? 'Desincorporación por faltante/deterioro' : null,
                'is_manual_override' => isset($existingManualOverrides[$pid]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Reemplazar entradas existentes no manuales o refrescar todas
        InventoryBookEntry::where('inventory_book_id', $book->id)->delete();

        // Inserción en bloques de 500 para alto rendimiento
        foreach (array_chunk($entriesToInsert, 500) as $chunk) {
            InventoryBookEntry::insert($chunk);
        }

        $this->recalculateTotals($book);

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function updateEntry(InventoryBook $book, int $entryId, array $data): InventoryBookEntry
    {
        $entry = InventoryBookEntry::where('inventory_book_id', $book->id)->findOrFail($entryId);

        $unitCostBs = isset($data['unit_cost_bs']) ? (float) $data['unit_cost_bs'] : (float) $entry->unit_cost_bs;
        $initialStock = isset($data['initial_stock']) ? (float) $data['initial_stock'] : (float) $entry->initial_stock;
        $purchases = isset($data['purchase_entries']) ? (float) $data['purchase_entries'] : (float) $entry->purchase_entries;
        $sales = isset($data['sales_exits']) ? (float) $data['sales_exits'] : (float) $entry->sales_exits;
        $withdrawals = isset($data['withdrawals']) ? (float) $data['withdrawals'] : (float) $entry->withdrawals;
        $selfConsumptions = isset($data['self_consumptions']) ? (float) $data['self_consumptions'] : (float) $entry->self_consumptions;
        $ivaRate = isset($data['iva_rate']) ? (float) $data['iva_rate'] : (float) $entry->iva_rate;

        $finalStock = $initialStock + $purchases - $sales - $withdrawals - $selfConsumptions;

        $previousValueBs = $initialStock * $unitCostBs;
        $purchasesCostBs = $purchases * $unitCostBs;
        $salesCostBs = $sales * $unitCostBs;
        $withdrawalsCostBs = $withdrawals * $unitCostBs;
        $selfConsumptionsCostBs = $selfConsumptions * $unitCostBs;
        $finalInventoryValueBs = $finalStock * $unitCostBs;
        $withdrawalVatBs = $withdrawalsCostBs * $ivaRate;

        $entry->update([
            'unit_cost_bs' => round($unitCostBs, 4),
            'initial_stock' => round($initialStock, 2),
            'purchase_entries' => round($purchases, 2),
            'sales_exits' => round($sales, 2),
            'withdrawals' => round($withdrawals, 2),
            'self_consumptions' => round($selfConsumptions, 2),
            'final_stock' => round($finalStock, 2),
            'previous_value_bs' => round($previousValueBs, 4),
            'purchase_entries_cost_bs' => round($purchasesCostBs, 4),
            'sales_exits_cost_bs' => round($salesCostBs, 4),
            'withdrawals_cost_bs' => round($withdrawalsCostBs, 4),
            'self_consumptions_cost_bs' => round($selfConsumptionsCostBs, 4),
            'final_inventory_value_bs' => round($finalInventoryValueBs, 4),
            'iva_rate' => $ivaRate,
            'withdrawal_vat_bs' => round($withdrawalVatBs, 4),
            'withdrawal_reason' => $data['withdrawal_reason'] ?? $entry->withdrawal_reason,
            'is_manual_override' => true,
        ]);

        $this->recalculateTotals($book);

        return $entry;
    }

    public function toggleAdjustmentsAsWithdrawals(InventoryBook $book, bool $include): InventoryBook
    {
        $book->update(['include_negative_adjustments_as_withdrawals' => $include]);
        return $this->syncTransactions($book);
    }

    public function closeBook(InventoryBook $book, int $userId): InventoryBook
    {
        $this->recalculateTotals($book);

        $book->update([
            'status' => PurchaseBookStatus::CLOSED,
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function reopenBook(InventoryBook $book): InventoryBook
    {
        $book->update([
            'status' => PurchaseBookStatus::OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $book->fresh(['entries', 'closedByUser']);
    }

    public function recalculateTotals(InventoryBook $book): void
    {
        $totals = InventoryBookEntry::where('inventory_book_id', $book->id)
            ->selectRaw('
                COALESCE(SUM(initial_stock), 0) as total_initial_units,
                COALESCE(SUM(purchase_entries), 0) as total_purchase_units,
                COALESCE(SUM(sales_exits), 0) as total_sales_units,
                COALESCE(SUM(withdrawals), 0) as total_withdrawal_units,
                COALESCE(SUM(self_consumptions), 0) as total_self_consumption_units,
                COALESCE(SUM(final_stock), 0) as total_final_units,
                COALESCE(SUM(previous_value_bs), 0) as total_previous_value_bs,
                COALESCE(SUM(purchase_entries_cost_bs), 0) as total_purchase_cost_bs,
                COALESCE(SUM(sales_exits_cost_bs), 0) as total_sales_cost_bs,
                COALESCE(SUM(withdrawals_cost_bs), 0) as total_withdrawal_cost_bs,
                COALESCE(SUM(self_consumptions_cost_bs), 0) as total_self_consumption_cost_bs,
                COALESCE(SUM(final_inventory_value_bs), 0) as total_final_inventory_value_bs,
                COALESCE(SUM(CASE WHEN iva_rate = 0 THEN withdrawals_cost_bs ELSE 0 END), 0) as total_withdrawal_exempt_bs,
                COALESCE(SUM(CASE WHEN iva_rate > 0 THEN withdrawals_cost_bs ELSE 0 END), 0) as total_withdrawal_taxable_bs,
                COALESCE(SUM(withdrawal_vat_bs), 0) as total_withdrawal_vat_bs
            ')
            ->first();

        $book->update([
            'total_initial_units' => $totals->total_initial_units ?? 0,
            'total_purchase_units' => $totals->total_purchase_units ?? 0,
            'total_sales_units' => $totals->total_sales_units ?? 0,
            'total_withdrawal_units' => $totals->total_withdrawal_units ?? 0,
            'total_self_consumption_units' => $totals->total_self_consumption_units ?? 0,
            'total_final_units' => $totals->total_final_units ?? 0,
            'total_previous_value_bs' => $totals->total_previous_value_bs ?? 0,
            'total_purchase_cost_bs' => $totals->total_purchase_cost_bs ?? 0,
            'total_sales_cost_bs' => $totals->total_sales_cost_bs ?? 0,
            'total_withdrawal_cost_bs' => $totals->total_withdrawal_cost_bs ?? 0,
            'total_self_consumption_cost_bs' => $totals->total_self_consumption_cost_bs ?? 0,
            'total_final_inventory_value_bs' => $totals->total_final_inventory_value_bs ?? 0,
            'total_withdrawal_exempt_bs' => $totals->total_withdrawal_exempt_bs ?? 0,
            'total_withdrawal_taxable_bs' => $totals->total_withdrawal_taxable_bs ?? 0,
            'total_withdrawal_vat_bs' => $totals->total_withdrawal_vat_bs ?? 0,
        ]);
    }
}
