<?php

declare(strict_types=1);

namespace App\Services\Chronic;

use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChronicClientService
{
    /**
     * Obtener listado de clientes con compras y frecuencia de recompra / seguimiento.
     */
    public function getChronicPatients(Request $request): LengthAwarePaginator
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');
        $consumptionType = $request->input('consumption_type', 'all');
        $productId = $request->input('product_id');
        $hasPhone = $request->input('has_phone');
        $perPage = (int) $request->input('itemsPerPage', 15);
        $page = (int) $request->input('page', 1);

        $latestDetailsQuery = OrderDetail::select(
                'orders.client_id',
                'order_details.product_id',
                DB::raw('MAX(orders.order_date) as last_order_date')
            )
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->where('orders.status', Order::COMPLETED)
            ->whereNotNull('orders.client_id')
            ->where(function ($q) {
                $q->where('products.is_chronic', true)
                  ->orWhereIn('products.consumption_type', ['chronic', 'single_treatment']);
            })
            ->groupBy('orders.client_id', 'order_details.product_id');

        $query = DB::table(DB::raw("({$latestDetailsQuery->toSql()}) as latest_purchases"))
            ->mergeBindings($latestDetailsQuery->getQuery())
            ->join('clients', 'clients.id', '=', 'latest_purchases.client_id')
            ->join('products', 'products.id', '=', 'latest_purchases.product_id')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->whereNull('clients.deleted_at')
            ->where('products.is_deleted', false);

        $query->join('orders', function ($join) {
            $join->on('orders.client_id', '=', 'latest_purchases.client_id')
                ->on('orders.order_date', '=', 'latest_purchases.last_order_date')
                ->where('orders.status', '=', Order::COMPLETED);
        })->join('order_details', function ($join) {
            $join->on('order_details.order_id', '=', 'orders.id')
                ->on('order_details.product_id', '=', 'latest_purchases.product_id');
        });

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('clients.name', 'like', "%{$search}%")
                    ->orWhere('clients.last_name', 'like', "%{$search}%")
                    ->orWhere('clients.identification', 'like', "%{$search}%")
                    ->orWhere('clients.phone', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%")
                    ->orWhere('laboratories.name', 'like', "%{$search}%");
            });
        }

        if (!empty($productId)) {
            $query->where('products.id', $productId);
        }

        if ($consumptionType !== 'all') {
            $query->where('products.consumption_type', $consumptionType);
        }

        if ($hasPhone === 'yes') {
            $query->whereNotNull('clients.phone')->where('clients.phone', '!=', '');
        } elseif ($hasPhone === 'no') {
            $query->where(function ($q) {
                $q->whereNull('clients.phone')->orWhere('clients.phone', '=', '');
            });
        }

        $query->select([
            'clients.id as client_id',
            'clients.identification_type',
            'clients.identification',
            'clients.name as client_name',
            'clients.last_name as client_last_name',
            'clients.phone as client_phone',
            'clients.email as client_email',
            'products.id as product_id',
            'products.name as product_name',
            'products.barcode as product_barcode',
            'products.sale_price as current_sale_price',
            'products.treatment_duration_days',
            'products.consumption_type',
            'products.is_chronic',
            'laboratories.name as laboratory_name',
            'latest_purchases.last_order_date',
            'order_details.quantity as purchased_quantity',
            'order_details.unit_price_usd',
        ]);

        $query->groupBy(
            'clients.id',
            'clients.identification_type',
            'clients.identification',
            'clients.name',
            'clients.last_name',
            'clients.phone',
            'clients.email',
            'products.id',
            'products.name',
            'products.barcode',
            'products.sale_price',
            'products.treatment_duration_days',
            'products.consumption_type',
            'products.is_chronic',
            'laboratories.name',
            'latest_purchases.last_order_date',
            'order_details.quantity',
            'order_details.unit_price_usd'
        );

        $allRecords = $query->get();

        $rateBs = ExchangeRate::whereIn('currency_code', ['VES', 'BS', 'BCV', 'EUR'])->orderByDesc('id')->value('rate') ?? 0;
        $rateCop = ExchangeRate::where('currency_code', 'COP')->orderByDesc('id')->value('rate') ?? 0;

        $processed = $allRecords->map(function ($row) use ($rateBs, $rateCop) {
            $lastDate = Carbon::parse($row->last_order_date);
            $durationDaysPerUnit = (int) ($row->treatment_duration_days ?: 30);
            $totalTreatmentDays = (int) round((float) $row->purchased_quantity * $durationDaysPerUnit);
            
            $treatmentEndDate = $lastDate->copy()->addDays($totalTreatmentDays);
            $reminderDate = $treatmentEndDate->copy()->subDays(5);

            $now = Carbon::now();
            $daysUntilEnd = (int) ceil($now->floatDiffInDays($treatmentEndDate, false));

            $isUrgent = ($daysUntilEnd <= 5 && $daysUntilEnd >= -30);
            $isExpired = ($daysUntilEnd < -30);
            $isActive = ($daysUntilEnd > 5);

            $currentPriceUsd = (float) $row->current_sale_price;
            $currentPriceBs = $rateBs > 0 ? round($currentPriceUsd * (float) $rateBs, 2) : 0;
            $currentPriceCop = $rateCop > 0 ? ceil($currentPriceUsd * (float) $rateCop / 100) * 100 : 0;

            $cleanPhone = preg_replace('/[^0-9]/', '', (string) $row->client_phone);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '58' . substr($cleanPhone, 1);
            } elseif (strlen($cleanPhone) === 10 && !str_starts_with($cleanPhone, '58')) {
                $cleanPhone = '58' . $cleanPhone;
            }

            $fullName = trim("{$row->client_name} {$row->client_last_name}");
            $treatmentDateFormatted = $treatmentEndDate->format('d/m/Y');
            $priceFormattedUsd = '$' . number_format($currentPriceUsd, 2);
            $priceFormattedBs = $currentPriceBs > 0 ? ' (Bs. ' . number_format($currentPriceBs, 2) . ')' : '';
            $priceFormattedCop = $currentPriceCop > 0 ? ' / COP ' . number_format($currentPriceCop, 0, ',', '.') : '';
            
            $isSingleTreatment = ($row->consumption_type === 'single_treatment');
            
            if ($isSingleTreatment) {
                $whatsappMessage = "¡Hola, {$fullName}! 👋 Te saludamos de Farmacia Barrio Sucre 💚\n\n" .
                    "Te contactamos para hacer seguimiento a tu tratamiento con *{$row->product_name}* (estimado hasta: {$treatmentDateFormatted}).\n\n" .
                    "¿Cómo te has sentido con el tratamiento? Si requieres renovar o necesitas algún medicamento complementario, cuentas con nosotros.\n\n" .
                    "📦 ¡Delivery sin costo hasta tu casa! 🚚💨";
            } else {
                $whatsappMessage = "¡Hola, {$fullName}! 👋 Te saludamos de Farmacia Barrio Sucre 💚\n\n" .
                    "Nos pasamos por aquí para recordarte que ya se acerca la fecha de renovar tu *{$row->product_name}* (estimado: {$treatmentDateFormatted}).\n\n" .
                    "💵 Precio actual: *{$priceFormattedUsd}*{$priceFormattedBs}{$priceFormattedCop}\n" .
                    "📦 ¡Te lo enviamos HOY mismo con DELIVERY GRATIS hasta tu puerta!\n\n" .
                    "Cuidamos tu salud y economía en un solo corazón. ¿Te dejamos el pedido listo? Escríbenos y con gusto te lo llevamos. 🚚💨";
            }

            $whatsappUrl = !empty($cleanPhone) 
                ? 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($whatsappMessage)
                : null;

            return [
                'client_id' => $row->client_id,
                'client_name' => $fullName,
                'identification' => "{$row->identification_type}{$row->identification}",
                'phone' => $row->client_phone,
                'email' => $row->client_email,
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'product_barcode' => $row->product_barcode,
                'laboratory_name' => $row->laboratory_name ?: 'Sin Laboratorio',
                'consumption_type' => $row->consumption_type ?: 'chronic',
                'consumption_type_label' => match($row->consumption_type) {
                    'single_treatment' => 'Tratamiento Único / Ciclo',
                    'sporadic' => 'Esporádico / Ocasional',
                    default => 'Crónico (Uso Continuo)',
                },
                'last_order_date' => $lastDate->format('Y-m-d H:i:s'),
                'last_order_date_formatted' => $lastDate->format('d/m/Y'),
                'purchased_quantity' => (float) $row->purchased_quantity,
                'treatment_duration_days' => $durationDaysPerUnit,
                'total_treatment_days' => $totalTreatmentDays,
                'treatment_end_date' => $treatmentEndDate->format('Y-m-d'),
                'treatment_end_date_formatted' => $treatmentDateFormatted,
                'reminder_date' => $reminderDate->format('Y-m-d'),
                'days_until_end' => $daysUntilEnd,
                'is_urgent' => $isUrgent,
                'is_expired' => $isExpired,
                'is_active' => $isActive,
                'status_label' => $this->getStatusLabel($daysUntilEnd),
                'status_color' => $this->getStatusColor($daysUntilEnd),
                'price_usd' => $currentPriceUsd,
                'price_bs' => $currentPriceBs,
                'price_cop' => $currentPriceCop,
                'whatsapp_url' => $whatsappUrl,
                'whatsapp_message' => $whatsappMessage,
            ];
        });

        if ($status === 'urgent') {
            $processed = $processed->filter(fn($item) => $item['is_urgent']);
        } elseif ($status === 'expired') {
            $processed = $processed->filter(fn($item) => $item['is_expired']);
        } elseif ($status === 'active') {
            $processed = $processed->filter(fn($item) => $item['is_active']);
        }

        $sorted = $processed->sortBy('days_until_end')->values();

        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * Obtener listado de configuración de productos para consumo y frecuencia.
     */
    public function getProductConsumptionConfig(Request $request): LengthAwarePaginator
    {
        $search = $request->input('search');
        $consumptionType = $request->input('consumption_type');
        $perPage = (int) $request->input('itemsPerPage', 15);
        $page = (int) $request->input('page', 1);

        $query = Product::withoutGlobalScope('not_deleted')
            ->where('is_deleted', false)
            ->with(['laboratory:id,name', 'category:id,name']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('active_ingredient', 'like', "%{$search}%");
            });
        }

        if (!empty($consumptionType) && $consumptionType !== 'all') {
            $query->where('consumption_type', $consumptionType);
        }

        $query->orderByRaw("CASE WHEN consumption_type = 'chronic' THEN 1 WHEN consumption_type = 'single_treatment' THEN 2 ELSE 3 END")
              ->orderBy('name');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Actualizar tipo de consumo y duración de tratamiento de un producto.
     */
    public function updateProductConsumption(int $productId, array $data): Product
    {
        $product = Product::withoutGlobalScope('not_deleted')->findOrFail($productId);

        $consumptionType = $data['consumption_type'] ?? 'sporadic';
        $durationDays = isset($data['treatment_duration_days']) ? (int) $data['treatment_duration_days'] : 30;

        $product->consumption_type = $consumptionType;
        $product->is_chronic = ($consumptionType === 'chronic');
        $product->treatment_duration_days = $durationDays;
        $product->save();

        return $product;
    }

    /**
     * Obtener estadísticas y resumen de clientes crónicos.
     */
    public function getStats(): array
    {
        $allPatients = $this->getChronicPatients(new Request(['itemsPerPage' => 99999, 'page' => 1]))->items();
        $collection = collect($allPatients);

        $totalPatients = $collection->pluck('client_id')->unique()->count();
        $totalTreatments = $collection->count();
        $urgentReminders = $collection->where('is_urgent', true)->count();
        $expiredTreatments = $collection->where('is_expired', true)->count();
        $activeTreatments = $collection->where('is_active', true)->count();
        $patientsWithPhone = $collection->filter(fn($p) => !empty($p['phone']))->count();

        return [
            'total_patients' => $totalPatients,
            'total_treatments' => $totalTreatments,
            'urgent_reminders' => $urgentReminders,
            'expired_treatments' => $expiredTreatments,
            'active_treatments' => $activeTreatments,
            'patients_with_phone' => $patientsWithPhone,
        ];
    }

    /**
     * Sincronizar y clasificar productos crónicos mediante categorías médicas, IA y heurística clínica.
     */
    public function syncChronicProductsWithAi(): array
    {
        $products = Product::withoutGlobalScope('not_deleted')
            ->where('is_deleted', false)
            ->get(['id', 'name', 'active_ingredient', 'description', 'category_id', 'is_chronic', 'consumption_type', 'treatment_duration_days']);

        $chronicCategoryIds = [1, 6, 10];
        $singleTreatmentCategoryIds = [5];

        $chronicKeywords = [
            'losartan', 'valsartan', 'candesartan', 'irbesartan', 'telmisartan', 'olmesartan',
            'amlodipina', 'amlodipino', 'nifedipino', 'verapamilo', 'diltiazem',
            'enalapril', 'captopril', 'lisinopril', 'ramipril',
            'atenolol', 'bisoprolol', 'carvedilol', 'metoprolol', 'nebivolol', 'propranolol',
            'hidroclorotiazida', 'furosemida', 'espironolactona', 'indapamida',
            'metformina', 'glibenclamida', 'glimepirida', 'gliclazida', 'sitagliptina', 'vildagliptina', 'linagliptina', 'dapagliflozina', 'empagliflozina', 'insulina', 'janumet', 'galvus',
            'atorvastatina', 'rosuvastatina', 'simvastatina', 'pravastatina', 'fenofibrato', 'gemfibrozilo', 'ezetimiba', 'lipitor', 'crestor',
            'levotiroxina', 'eutirox', 'tiroxina', 'metimazol', 'tapazol',
            'aspirina', 'acido acetilsalicilico', 'clopidogrel', 'warfarina', 'rivaroxaban', 'apixaban', 'xarelto', 'eliquis', 'plavix',
            'salbutamol', 'budesonida', 'formoterol', 'fluticasona', 'salmeterol', 'montelukast', 'ipratropio', 'tiotropio', 'seretide', 'symbicort', 'berodual', 'spiriva',
            'acido valproico', 'valproato', 'carbamazepina', 'lamotrigina', 'levetiracetam', 'fenitoina', 'topiramato', 'pregabalina', 'gabapentina', 'lyrica',
            'sertralina', 'escitalopram', 'fluoxetina', 'paroxetina', 'duloxetina', 'venlafaxina', 'clonazepam', 'alprazolam', 'quetiapina', 'olanzapina', 'risperidona', 'rivotril',
            'tamsulosina', 'finasterida', 'dutasterida', 'silodosina', 'secotex', 'avodart',
            'timolol', 'latanoprost', 'travoprost', 'bimatoprost', 'brimonidina', 'dorzolamida', 'xalatan', 'cosopt',
            'alendronato', 'ibandronato', 'calcitriol', 'metotrexato', 'leflunomida', 'hidroxicloroquina', 'sulfasalazina',
            'anticonceptivo', 'etinilestradiol', 'levonorgestrel', 'drospirenona', 'dienogest', 'yasmin', 'diane', 'yaz',
            'retinal', 'retinol', 'isotretinoina', 'tacrolimus'
        ];

        $singleTreatmentKeywords = [
            'amoxicilina', 'azitromicina', 'ciprofloxacina', 'cefalexina', 'ampicilina', 'claritromicina',
            'levofloxacina', 'trimetoprim', 'sulfametoxazol', 'albendazol', 'mebendazol', 'metronidazol',
            'fluconazol', 'ketoconazol', 'itraconazol', 'secnidazol', 'nitazoxanida'
        ];

        $updatedCount = 0;
        $chronicFound = 0;

        foreach ($products as $product) {
            $consumptionType = 'sporadic';
            $duration = 30;

            if (in_array($product->category_id, $chronicCategoryIds, true)) {
                $consumptionType = 'chronic';
                $duration = 30;
            } else {
                $haystack = mb_strtolower("{$product->name} {$product->active_ingredient} {$product->description}");
                foreach ($chronicKeywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        $consumptionType = 'chronic';
                        $duration = 30;
                        break;
                    }
                }

                if ($consumptionType === 'sporadic') {
                    if (in_array($product->category_id, $singleTreatmentCategoryIds, true)) {
                        $consumptionType = 'single_treatment';
                        $duration = 7;
                    } else {
                        foreach ($singleTreatmentKeywords as $kw) {
                            if (str_contains($haystack, $kw)) {
                                $consumptionType = 'single_treatment';
                                $duration = 7;
                                break;
                            }
                        }
                    }
                }
            }

            if ($consumptionType !== 'sporadic') {
                $chronicFound++;
                if ($product->consumption_type !== $consumptionType || !$product->is_chronic || empty($product->treatment_duration_days)) {
                    $product->consumption_type = $consumptionType;
                    $product->is_chronic = ($consumptionType === 'chronic');
                    $product->treatment_duration_days = $product->treatment_duration_days ?: $duration;
                    $product->save();
                    $updatedCount++;
                }
            }
        }

        return [
            'total_analyzed' => $products->count(),
            'chronic_detected' => $chronicFound,
            'updated_count' => $updatedCount,
            'ai_assisted' => true,
        ];
    }

    private function getStatusLabel(int $daysUntilEnd): string
    {
        if ($daysUntilEnd > 5) {
            return "En tratamiento ({$daysUntilEnd} días restantes)";
        }
        if ($daysUntilEnd > 0) {
            return "Por agotarse en {$daysUntilEnd} " . ($daysUntilEnd === 1 ? 'día' : 'días');
        }
        if ($daysUntilEnd === 0) {
            return 'Tratamiento culmina hoy';
        }
        $abs = abs($daysUntilEnd);
        return "Agotado hace {$abs} " . ($abs === 1 ? 'día' : 'días');
    }

    private function getStatusColor(int $daysUntilEnd): string
    {
        if ($daysUntilEnd > 5) {
            return 'info';
        }
        if ($daysUntilEnd >= 0) {
            return 'warning';
        }
        if ($daysUntilEnd >= -30) {
            return 'error';
        }
        return 'secondary';
    }
}