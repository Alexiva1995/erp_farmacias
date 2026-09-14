<?php

declare(strict_types=1);

namespace App\Services\Chronic;

use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChronicClientService
{
    /**
     * Obtener listado de pacientes con compras de productos clasificados y número de teléfono válido.
     * Sin restricción de fecha — incluye todo el historial de compras.
     */
    public function getChronicPatients(Request $request): LengthAwarePaginator
    {
        $search        = $request->input('search');
        $status        = $request->input('status', 'all');
        $consumptionType = $request->input('consumption_type', 'all');
        $productId     = $request->input('product_id');
        $perPage       = (int) $request->input('itemsPerPage', 15);
        $page          = (int) $request->input('page', 1);

        $query = DB::table('orders')
            ->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->where('orders.status', Order::COMPLETED)
            ->whereNotNull('orders.client_id')
            ->whereIn('products.consumption_type', ['chronic', 'single_treatment', 'sporadic'])
            ->whereNull('clients.deleted_at')
            ->where('products.is_deleted', false)
            ->whereNotNull('clients.phone')
            ->where('clients.phone', '!=', '')
            // Validar mínimo 7 dígitos limpios (sin guiones, espacios, +)
            ->where(
                DB::raw('LENGTH(REPLACE(REPLACE(REPLACE(TRIM(clients.phone), "-", ""), " ", ""), "+", ""))'),
                '>=',
                7
            );

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
            'products.active_ingredient',
            'products.sale_price as current_sale_price',
            'products.stock as product_stock',
            'products.treatment_duration_days',
            'products.consumption_type',
            'products.is_chronic',
            'laboratories.name as laboratory_name',
            'orders.order_date as last_order_date',
            'order_details.quantity as purchased_quantity',
            'order_details.unit_price_usd',
        ]);

        $allRecords = $query->orderByDesc('orders.order_date')->get();

        // Obtener tasas de cambio activas
        $rateBs  = ExchangeRate::whereIn('currency_code', ['VES', 'BS', 'BCV', 'EUR'])->orderByDesc('id')->value('rate') ?? 0;
        $rateCop = ExchangeRate::where('currency_code', 'COP')->orderByDesc('id')->value('rate') ?? 0;

        $processed = $allRecords->map(function ($row) use ($rateBs, $rateCop) {
            $lastDate            = Carbon::parse($row->last_order_date);
            $durationDaysPerUnit = (int) ($row->treatment_duration_days ?: 30);
            $totalTreatmentDays  = (int) round((float) $row->purchased_quantity * $durationDaysPerUnit);

            $treatmentEndDate = $lastDate->copy()->addDays($totalTreatmentDays);
            $reminderDate     = $treatmentEndDate->copy()->subDays(5);
            $now              = Carbon::now();
            $daysUntilEnd     = (int) ceil($now->floatDiffInDays($treatmentEndDate, false));

            $isUrgent  = ($daysUntilEnd <= 5 && $daysUntilEnd >= -30);
            $isExpired = ($daysUntilEnd < -30);
            $isActive  = ($daysUntilEnd > 5);

            $currentPriceUsd = (float) $row->current_sale_price;
            $currentPriceBs  = $rateBs  > 0 ? round($currentPriceUsd * (float) $rateBs, 2) : 0;
            $currentPriceCop = $rateCop > 0 ? ceil($currentPriceUsd * (float) $rateCop / 100) * 100 : 0;

            // Normalizar número de teléfono a formato internacional Venezuela
            $cleanPhone = preg_replace('/[^0-9]/', '', (string) $row->client_phone);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '58' . substr($cleanPhone, 1);
            } elseif (strlen($cleanPhone) === 10 && !str_starts_with($cleanPhone, '58')) {
                $cleanPhone = '58' . $cleanPhone;
            }

            $fullName               = trim("{$row->client_name} {$row->client_last_name}");
            $treatmentDateFormatted = $treatmentEndDate->format('d/m/Y');
            $priceFormattedUsd      = '$' . number_format($currentPriceUsd, 2);
            $priceFormattedBs       = $currentPriceBs  > 0 ? ' (Bs. ' . number_format($currentPriceBs, 2) . ')' : '';
            $priceFormattedCop      = $currentPriceCop > 0 ? ' / COP ' . number_format($currentPriceCop, 0, ',', '.') : '';

            $isSingleTreatment = ($row->consumption_type === 'single_treatment');
            $isSporadic        = ($row->consumption_type === 'sporadic');

            // Construir mensaje de WhatsApp según tipo de consumo
            if ($isSingleTreatment) {
                $whatsappMessage = "¡Hola, {$fullName}! 👋 Te saludamos de Farmacia Barrio Sucre 💚\n\n" .
                    "Te contactamos para hacer seguimiento a tu tratamiento con *{$row->product_name}* (estimado hasta: {$treatmentDateFormatted}).\n\n" .
                    "¿Cómo te has sentido con el tratamiento? Si requieres renovar o necesitas algún medicamento complementario, cuentas con nosotros.\n\n" .
                    "📦 ¡Delivery sin costo hasta tu casa! 🚚💨";
            } elseif ($isSporadic) {
                $whatsappMessage = "¡Hola, {$fullName}! 👋 Te saludamos de Farmacia Barrio Sucre 💚\n\n" .
                    "Esperamos te encuentres muy bien. Te escribimos para consultar si aún tienes disponibilidad de *{$row->product_name}* en tu botiquín.\n\n" .
                    "💵 Precio actual: *{$priceFormattedUsd}*{$priceFormattedBs}{$priceFormattedCop}\n" .
                    "📦 ¡Delivery sin costo hasta tu casa! Escríbenos y con gusto te lo llevamos. 🚚💨";
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
                'client_id'                  => $row->client_id,
                'client_name'                => $fullName,
                'identification'             => "{$row->identification_type}{$row->identification}",
                'phone'                      => $row->client_phone,
                'email'                      => $row->client_email,
                'product_id'                 => $row->product_id,
                'product_name'               => $row->product_name,
                'product_barcode'            => $row->product_barcode,
                'active_ingredient'          => $row->active_ingredient,
                'stock'                      => (float) ($row->product_stock ?? 0),
                'laboratory_name'            => $row->laboratory_name ?: 'Sin Laboratorio',
                'consumption_type'           => $row->consumption_type ?: 'chronic',
                'consumption_type_label'     => match ($row->consumption_type) {
                    'single_treatment' => 'Tratamiento Único / Ciclo',
                    'sporadic'         => 'Esporádico / Ocasional',
                    default            => 'Crónico (Uso Continuo)',
                },
                'last_order_date'            => $lastDate->format('Y-m-d H:i:s'),
                'last_order_date_formatted'  => $lastDate->format('d/m/Y'),
                'purchased_quantity'         => (float) $row->purchased_quantity,
                'treatment_duration_days'    => $durationDaysPerUnit,
                'total_treatment_days'       => $totalTreatmentDays,
                'treatment_end_date'         => $treatmentEndDate->format('Y-m-d'),
                'treatment_end_date_formatted' => $treatmentDateFormatted,
                'reminder_date'              => $reminderDate->format('Y-m-d'),
                'days_until_end'             => $daysUntilEnd,
                'is_urgent'                  => $isUrgent,
                'is_expired'                 => $isExpired,
                'is_active'                  => $isActive,
                'status_label'               => $this->getStatusLabel($daysUntilEnd),
                'status_color'               => $this->getStatusColor($daysUntilEnd),
                'price_usd'                  => $currentPriceUsd,
                'price_bs'                   => $currentPriceBs,
                'price_cop'                  => $currentPriceCop,
                'whatsapp_url'               => $whatsappUrl,
                'whatsapp_message'           => $whatsappMessage,
            ];
        });

        // Filtrar por estado de tratamiento en memoria
        if ($status === 'urgent') {
            $processed = $processed->filter(fn($item) => $item['is_urgent']);
        } elseif ($status === 'expired') {
            $processed = $processed->filter(fn($item) => $item['is_expired']);
        } elseif ($status === 'active') {
            $processed = $processed->filter(fn($item) => $item['is_active']);
        }

        // Ordenar: primero los más próximos a culminar / vencidos recientemente (ej. 0 días, -1 día, -5 días...), y al final los vencidos hace años
        $sorted = $processed->sortBy(function ($item) {
            $days = $item['days_until_end'];
            // Asignar prioridad de urgencia / inmediatez:
            // 0 a 5 días: prioridad 1 (urgentes próximos a vencer)
            // -1 a -30 días: prioridad 2 (vencidos recientemente)
            // > 5 días: prioridad 3 (activos a futuro)
            // < -30 días: prioridad 4 (vencidos hace mucho tiempo)
            if ($days >= 0 && $days <= 5) {
                return [1, $days];
            }
            if ($days < 0 && $days >= -30) {
                return [2, abs($days)];
            }
            if ($days > 5) {
                return [3, $days];
            }
            return [4, abs($days)];
        })->values();
        $total  = $sorted->count();
        $items  = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * Obtener catálogo de productos para clasificación de consumo.
     * Solo muestra productos con al menos una orden completada desde 2026-09-01.
     * Usa DB::table() para evitar los appends pesados del modelo Product.
     */
    public function getProductConsumptionConfig(Request $request): LengthAwarePaginator
    {
        $search          = $request->input('search');
        $consumptionType = $request->input('consumption_type');
        $perPage         = (int) $request->input('itemsPerPage', 15);
        $page            = (int) $request->input('page', 1);
        $fromDate        = '2026-09-01 00:00:00';

        // Subconsulta: IDs de productos con órdenes completadas desde sep-2026
        $productIdsWithOrders = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', Order::COMPLETED)
            ->where('orders.order_date', '>=', $fromDate)
            ->pluck('order_details.product_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($productIdsWithOrders)) {
            // Si no hay órdenes desde sep-2026, devolver paginador vacío
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = DB::table('products')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_deleted', false)
            ->whereIn('products.id', $productIdsWithOrders)
            ->select([
                'products.id',
                'products.name',
                'products.barcode',
                'products.active_ingredient',
                'products.consumption_type',
                'products.treatment_duration_days',
                'products.is_chronic',
                'products.sale_price',
                'laboratories.id as laboratory_id',
                'laboratories.name as laboratory_name',
                'categories.id as category_id',
                'categories.name as category_name',
            ]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.barcode', 'like', "%{$search}%")
                    ->orWhere('products.active_ingredient', 'like', "%{$search}%")
                    ->orWhere('products.id', 'like', "%{$search}%");
            });
        }

        if (!empty($consumptionType) && $consumptionType !== 'all') {
            if ($consumptionType === 'unclassified') {
                $query->whereNull('products.consumption_type');
            } else {
                $query->where('products.consumption_type', $consumptionType);
            }
        }

        // Ordenar: clasificados primero (chronic, single_treatment, no_alert, sporadic), luego sin clasificar
        $query->orderByRaw("
            CASE
                WHEN products.consumption_type = 'chronic'           THEN 1
                WHEN products.consumption_type = 'single_treatment'  THEN 2
                WHEN products.consumption_type = 'no_alert'          THEN 3
                WHEN products.consumption_type = 'sporadic'          THEN 4
                ELSE 5
            END
        ")->orderBy('products.name');

        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()->map(function ($row) {
            return [
                'id'                     => $row->id,
                'name'                   => $row->name,
                'barcode'                => $row->barcode,
                'active_ingredient'      => $row->active_ingredient,
                'consumption_type'       => $row->consumption_type,
                'treatment_duration_days' => $row->treatment_duration_days,
                'is_chronic'             => (bool) $row->is_chronic,
                'sale_price'             => (float) $row->sale_price,
                'laboratory'             => $row->laboratory_id ? ['id' => $row->laboratory_id, 'name' => $row->laboratory_name] : null,
                'category'               => $row->category_id   ? ['id' => $row->category_id,   'name' => $row->category_name]   : null,
            ];
        });

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * Actualizar tipo de consumo y duración de tratamiento de un producto.
     * Usa DB::table() directamente para evitar cualquier accessor/observer del modelo.
     */
    public function updateProductConsumption(int $productId, array $data): array
    {
        // Verificar que el producto existe y no está eliminado
        $exists = DB::table('products')
            ->where('id', $productId)
            ->where('is_deleted', false)
            ->exists();

        if (!$exists) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Product [{$productId}] not found."
            );
        }

        $consumptionType = !empty($data['consumption_type']) && $data['consumption_type'] !== 'none'
            ? $data['consumption_type']
            : null;

        $durationDays = isset($data['treatment_duration_days']) && $data['treatment_duration_days'] !== ''
            ? (int) $data['treatment_duration_days']
            : null;

        $isChronic        = ($consumptionType === 'chronic');
        $treatmentDuration = in_array($consumptionType, ['chronic', 'single_treatment', 'sporadic'], true)
            ? ($durationDays ?: ($consumptionType === 'single_treatment' ? 7 : 30))
            : null;

        DB::table('products')
            ->where('id', $productId)
            ->update([
                'consumption_type'        => $consumptionType,
                'is_chronic'              => $isChronic,
                'treatment_duration_days' => $treatmentDuration,
                'updated_at'              => now(),
            ]);

        // Retornar datos actualizados del producto
        $product = DB::table('products')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.id', $productId)
            ->select([
                'products.id',
                'products.name',
                'products.barcode',
                'products.active_ingredient',
                'products.consumption_type',
                'products.treatment_duration_days',
                'products.is_chronic',
                'products.sale_price',
                'laboratories.id as laboratory_id',
                'laboratories.name as laboratory_name',
                'categories.id as category_id',
                'categories.name as category_name',
            ])
            ->first();

        return [
            'id'                      => $product->id,
            'name'                    => $product->name,
            'barcode'                 => $product->barcode,
            'active_ingredient'       => $product->active_ingredient,
            'consumption_type'        => $product->consumption_type,
            'treatment_duration_days' => $product->treatment_duration_days,
            'is_chronic'              => (bool) $product->is_chronic,
            'sale_price'              => (float) $product->sale_price,
            'laboratory'              => $product->laboratory_id ? ['id' => $product->laboratory_id, 'name' => $product->laboratory_name] : null,
            'category'                => $product->category_id   ? ['id' => $product->category_id,   'name' => $product->category_name]   : null,
        ];
    }

    /**
     * Obtener estadísticas y resumen de clientes crónicos.
     */
    public function getStats(): array
    {
        $allPatients = $this->getChronicPatients(new \Illuminate\Http\Request(['itemsPerPage' => 99999, 'page' => 1]))->items();
        $collection  = collect($allPatients);

        return [
            'total_patients'      => $collection->pluck('client_id')->unique()->count(),
            'total_treatments'    => $collection->count(),
            'urgent_reminders'    => $collection->where('is_urgent', true)->count(),
            'expired_treatments'  => $collection->where('is_expired', true)->count(),
            'active_treatments'   => $collection->where('is_active', true)->count(),
            'patients_with_phone' => $collection->filter(fn($p) => !empty($p['phone']))->count(),
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

        $chronicCategoryIds        = [1, 6, 10];
        $singleTreatmentCategoryIds = [5];

        $chronicKeywords = [
            'losartan', 'valsartan', 'candesartan', 'irbesartan', 'telmisartan', 'olmesartan',
            'amlodipina', 'amlodipino', 'nifedipino', 'verapamilo', 'diltiazem',
            'enalapril', 'captopril', 'lisinopril', 'ramipril',
            'atenolol', 'bisoprolol', 'carvedilol', 'metoprolol', 'nebivolol', 'propranolol',
            'hidroclorotiazida', 'furosemida', 'espironolactona', 'indapamida',
            'metformina', 'glibenclamida', 'glimepirida', 'gliclazida', 'sitagliptina', 'vildagliptina',
            'linagliptina', 'dapagliflozina', 'empagliflozina', 'insulina', 'janumet', 'galvus',
            'atorvastatina', 'rosuvastatina', 'simvastatina', 'pravastatina', 'fenofibrato',
            'gemfibrozilo', 'ezetimiba', 'lipitor', 'crestor',
            'levotiroxina', 'eutirox', 'tiroxina', 'metimazol', 'tapazol',
            'aspirina', 'acido acetilsalicilico', 'clopidogrel', 'warfarina', 'rivaroxaban',
            'apixaban', 'xarelto', 'eliquis', 'plavix',
            'salbutamol', 'budesonida', 'formoterol', 'fluticasona', 'salmeterol', 'montelukast',
            'ipratropio', 'tiotropio', 'seretide', 'symbicort', 'berodual', 'spiriva',
            'acido valproico', 'valproato', 'carbamazepina', 'lamotrigina', 'levetiracetam',
            'fenitoina', 'topiramato', 'pregabalina', 'gabapentina', 'lyrica',
            'sertralina', 'escitalopram', 'fluoxetina', 'paroxetina', 'duloxetina', 'venlafaxina',
            'clonazepam', 'alprazolam', 'quetiapina', 'olanzapina', 'risperidona', 'rivotril',
            'tamsulosina', 'finasterida', 'dutasterida', 'silodosina', 'secotex', 'avodart',
            'timolol', 'latanoprost', 'travoprost', 'bimatoprost', 'brimonidina', 'dorzolamida',
            'xalatan', 'cosopt',
            'alendronato', 'ibandronato', 'calcitriol', 'metotrexato', 'leflunomida',
            'hidroxicloroquina', 'sulfasalazina',
            'anticonceptivo', 'etinilestradiol', 'levonorgestrel', 'drospirenona', 'dienogest',
            'yasmin', 'diane', 'yaz',
            'retinal', 'retinol', 'isotretinoina', 'tacrolimus',
        ];

        $singleTreatmentKeywords = [
            'amoxicilina', 'azitromicina', 'ciprofloxacina', 'cefalexina', 'ampicilina', 'claritromicina',
            'levofloxacina', 'trimetoprim', 'sulfametoxazol', 'albendazol', 'mebendazol', 'metronidazol',
            'fluconazol', 'ketoconazol', 'itraconazol', 'secnidazol', 'nitazoxanida',
        ];

        $updatedCount = 0;
        $chronicFound = 0;

        foreach ($products as $product) {
            $consumptionType = 'sporadic';
            $duration        = 30;

            if (in_array($product->category_id, $chronicCategoryIds, true)) {
                $consumptionType = 'chronic';
                $duration        = 30;
            } else {
                $haystack = mb_strtolower("{$product->name} {$product->active_ingredient} {$product->description}");
                foreach ($chronicKeywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        $consumptionType = 'chronic';
                        $duration        = 30;
                        break;
                    }
                }

                if ($consumptionType === 'sporadic') {
                    if (in_array($product->category_id, $singleTreatmentCategoryIds, true)) {
                        $consumptionType = 'single_treatment';
                        $duration        = 7;
                    } else {
                        foreach ($singleTreatmentKeywords as $kw) {
                            if (str_contains($haystack, $kw)) {
                                $consumptionType = 'single_treatment';
                                $duration        = 7;
                                break;
                            }
                        }
                    }
                }
            }

            if ($consumptionType !== 'sporadic') {
                $chronicFound++;
                if ($product->consumption_type !== $consumptionType || !$product->is_chronic || empty($product->treatment_duration_days)) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'consumption_type'        => $consumptionType,
                            'is_chronic'              => ($consumptionType === 'chronic'),
                            'treatment_duration_days' => $product->treatment_duration_days ?: $duration,
                            'updated_at'              => now(),
                        ]);
                    $updatedCount++;
                }
            }
        }

        return [
            'total_analyzed'  => $products->count(),
            'chronic_detected' => $chronicFound,
            'updated_count'   => $updatedCount,
            'ai_assisted'     => true,
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