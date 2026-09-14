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
     * Consolida múltiples productos por paciente en un solo registro y mensaje de WhatsApp.
     * Filtra según el historial de contacto en chronic_patient_contacts.
     */
    /**
     * Obtener listado de pacientes con compras de productos clasificados y número de teléfono válido.
     * Consolida múltiples productos por paciente en un solo registro y mensaje de WhatsApp.
     * Filtra según el historial de contacto en chronic_patient_contacts.
     */
    public function getChronicPatients(Request $request): LengthAwarePaginator
    {
        $perPage = (int) $request->input('itemsPerPage', 15);
        $page    = (int) $request->input('page', 1);

        $sorted = $this->getProcessedChronicPatientsCollection($request);
        $total  = $sorted->count();
        $items  = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * Procesar y filtrar la colección consolidada de pacientes crónicos en memoria.
     */
    protected function getProcessedChronicPatientsCollection(Request $request): \Illuminate\Support\Collection
    {
        $search          = $request->input('search');
        $status          = $request->input('status', 'all');
        $consumptionType = $request->input('consumption_type', 'all');
        $productId       = $request->input('product_id');

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
            ->whereRaw('LENGTH(REGEXP_REPLACE(clients.phone, "[^0-9]", "")) >= 10');

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
            'orders.id as order_id',
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

        if ($allRecords->isEmpty()) {
            return collect();
        }

        // Cargar registros de contacto para todos los clientes encontrados
        $clientIds = $allRecords->pluck('client_id')->unique()->toArray();
        $contacts = DB::table('chronic_patient_contacts')
            ->whereIn('client_id', $clientIds)
            ->get()
            ->keyBy(fn($c) => "{$c->client_id}_{$c->product_id}");

        // Cargar modelos de productos con ofertas para calcular el precio exacto con descuento de TPV
        $productIds = $allRecords->pluck('product_id')->unique()->toArray();
        $productsMap = Product::with(['individualOffers', 'category.offers', 'lots' => function ($q) {
                $q->where('quantity', '>', 0)->whereNotNull('expiration_date')->orderBy('expiration_date');
            }])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        // Obtener tasas de cambio activas
        $rateBs  = ExchangeRate::whereIn('currency_code', ['VES', 'BS', 'BCV', 'EUR'])->orderByDesc('id')->value('rate') ?? 0;
        $rateCop = ExchangeRate::where('currency_code', 'COP')->orderByDesc('id')->value('rate') ?? 0;
        $now     = Carbon::now();

        // Procesar productos individuales y filtrar según contacto previo
        $validItems = [];

        foreach ($allRecords as $row) {
            $lastDate            = Carbon::parse($row->last_order_date);
            $durationDaysPerUnit = (int) ($row->treatment_duration_days ?: 30);
            $totalTreatmentDays  = (int) round((float) $row->purchased_quantity * $durationDaysPerUnit);

            $treatmentEndDate = $lastDate->copy()->addDays($totalTreatmentDays);
            $reminderDate     = $treatmentEndDate->copy()->subDays(5);
            $daysUntilEnd     = (int) ceil($now->floatDiffInDays($treatmentEndDate, false));

            $isUrgent  = ($daysUntilEnd <= 5 && $daysUntilEnd >= -30);
            $isExpired = ($daysUntilEnd < -30);
            $isActive  = ($daysUntilEnd > 5);

            $contactKey = "{$row->client_id}_{$row->product_id}";
            if (isset($contacts[$contactKey])) {
                $contact = $contacts[$contactKey];
                $contactOrderDate = $contact->order_date_at_contact ? Carbon::parse($contact->order_date_at_contact) : null;

                // Si no hay orden más reciente que la fecha de contacto
                if ($contactOrderDate && $lastDate->lte($contactOrderDate)) {
                    if ($row->consumption_type === 'single_treatment') {
                        // Tratamiento único: no se vuelve a mostrar hasta nueva compra
                        continue;
                    }
                    if (in_array($row->consumption_type, ['chronic', 'sporadic'], true)) {
                        // Crónico / Esporádico: solo mostrar si next_reminder_at ya pasó
                        if ($contact->next_reminder_at && $now->lt(Carbon::parse($contact->next_reminder_at))) {
                            continue;
                        }
                    }
                }
            }

            // Normalizar número telefónico
            $cleanDigits = preg_replace('/[^0-9]/', '', (string) $row->client_phone);
            $isRepeated  = preg_match('/^(\d)\1+$/', $cleanDigits);
            $isValidVen  = preg_match('/^(58)?(0?)(412|414|424|416|426|2\d{2})\d{7}$/', $cleanDigits);
            $isDummy     = in_array($cleanDigits, [
                '1234567890', '12345678', '01234567890', '0000000000', '00000000000',
                '123456789', '9876543210', '1111111111', '2222222222', '3333333333',
                '4444444444', '5555555555', '6666666666', '7777777777', '8888888888', '9999999999',
                '40000000000', '50000000000', '000000000000'
            ], true);

            $cleanPhone = null;
            if (!$isRepeated && $isValidVen && !$isDummy) {
                if (str_starts_with($cleanDigits, '58') && strlen($cleanDigits) === 12) {
                    $cleanPhone = $cleanDigits;
                } elseif (str_starts_with($cleanDigits, '0') && strlen($cleanDigits) === 11) {
                    $cleanPhone = '58' . substr($cleanDigits, 1);
                } elseif (strlen($cleanDigits) === 10) {
                    $cleanPhone = '58' . $cleanDigits;
                }
            }

            if (empty($cleanPhone)) {
                continue;
            }

            $productModel = $productsMap[$row->product_id] ?? null;
            $baseSalePrice = (float) $row->current_sale_price;
            $discountPct = $productModel ? (float) $productModel->discount_percentage : 0;

            // Calcular precio efectivo con descuento del TPV
            $currentPriceUsd = $discountPct > 0 ? round($baseSalePrice * (1 - ($discountPct / 100)), 2) : $baseSalePrice;
            $currentPriceBs  = $rateBs  > 0 ? round($currentPriceUsd * (float) $rateBs, 2) : 0;
            $currentPriceCop = $rateCop > 0 ? ceil($currentPriceUsd * (float) $rateCop / 100) * 100 : 0;

            $fullName               = trim("{$row->client_name} {$row->client_last_name}");
            $treatmentDateFormatted = $treatmentEndDate->format('d/m/Y');
            $priceFormattedUsd      = '$' . number_format($currentPriceUsd, 2);
            $priceFormattedBs       = $currentPriceBs  > 0 ? ' (Bs. ' . number_format($currentPriceBs, 2) . ')' : '';
            $priceFormattedCop      = $currentPriceCop > 0 ? ' / COP ' . number_format($currentPriceCop, 0, ',', '.') : '';

            $validItems[] = [
                'client_id'                  => $row->client_id,
                'client_name'                => $fullName,
                'identification'             => "{$row->identification_type}{$row->identification}",
                'phone'                      => $row->client_phone,
                'clean_phone'                => $cleanPhone,
                'email'                      => $row->client_email,
                'product_id'                 => $row->product_id,
                'product_name'               => $row->product_name,
                'product_barcode'            => $row->product_barcode,
                'active_ingredient'          => $row->active_ingredient,
                'discount_percentage'        => $discountPct,
                'base_price_usd'             => $baseSalePrice,
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
            ];
        }

        // Agrupar por cliente para consolidar tratamientos y mensajes (sin duplicar el mismo producto por cliente)
        $groupedClients = collect($validItems)->groupBy('client_id')->map(function ($clientProducts) {
            // Deduplicar productos para que el mismo producto solo aparezca una sola vez (la compra más reciente)
            $uniqueProducts = $clientProducts->unique('product_id')->values();

            $first = $uniqueProducts->first();
            $products = $uniqueProducts->all();

            $isUrgent  = $uniqueProducts->contains('is_urgent', true);
            $isExpired = !$isUrgent && $uniqueProducts->contains('is_expired', true);
            $isActive  = !$isUrgent && !$isExpired && $uniqueProducts->contains('is_active', true);

            // Determinar días más urgentes del conjunto
            $minDaysUntilEnd = $uniqueProducts->min('days_until_end');
            $primaryProduct  = $uniqueProducts->sortBy('days_until_end')->first();

            // Construir mensaje consolidado
            $whatsappMessage = $this->buildWhatsAppMessage($first['client_name'], $products);
            $whatsappUrl = 'https://wa.me/' . $first['clean_phone'] . '?text=' . rawurlencode($whatsappMessage);

            return [
                'client_id'                    => $first['client_id'],
                'client_name'                  => $first['client_name'],
                'identification'               => $first['identification'],
                'phone'                        => $first['phone'],
                'clean_phone'                  => $first['clean_phone'],
                'email'                        => $first['email'],
                'products'                     => $products,
                'product_ids'                  => array_column($products, 'product_id'),
                'total_products_count'         => count($products),
                // Campos del producto primario/más urgente para retrocompatibilidad
                'product_id'                   => $primaryProduct['product_id'],
                'product_name'                 => $primaryProduct['product_name'],
                'product_barcode'              => $primaryProduct['product_barcode'],
                'active_ingredient'            => $primaryProduct['active_ingredient'],
                'stock'                        => $primaryProduct['stock'],
                'laboratory_name'              => $primaryProduct['laboratory_name'],
                'consumption_type'             => $primaryProduct['consumption_type'],
                'consumption_type_label'       => $primaryProduct['consumption_type_label'],
                'last_order_date'              => $primaryProduct['last_order_date'],
                'last_order_date_formatted'    => $primaryProduct['last_order_date_formatted'],
                'purchased_quantity'           => $primaryProduct['purchased_quantity'],
                'treatment_duration_days'      => $primaryProduct['treatment_duration_days'],
                'total_treatment_days'         => $primaryProduct['total_treatment_days'],
                'treatment_end_date'           => $primaryProduct['treatment_end_date'],
                'treatment_end_date_formatted' => $primaryProduct['treatment_end_date_formatted'],
                'days_until_end'               => $minDaysUntilEnd,
                'is_urgent'                    => $isUrgent,
                'is_expired'                   => $isExpired,
                'is_active'                    => $isActive,
                'status_label'                 => $this->getStatusLabel($minDaysUntilEnd),
                'status_color'                 => $this->getStatusColor($minDaysUntilEnd),
                'price_usd'                    => $primaryProduct['price_usd'],
                'price_bs'                     => $primaryProduct['price_bs'],
                'price_cop'                    => $primaryProduct['price_cop'],
                'whatsapp_url'                 => $whatsappUrl,
                'whatsapp_message'             => $whatsappMessage,
            ];
        });

        // Filtrar por estado en memoria
        if ($status === 'urgent') {
            $groupedClients = $groupedClients->filter(fn($item) => $item['is_urgent']);
        } elseif ($status === 'expired') {
            $groupedClients = $groupedClients->filter(fn($item) => $item['is_expired']);
        } elseif ($status === 'active') {
            $groupedClients = $groupedClients->filter(fn($item) => $item['is_active']);
        }

        // Ordenar: primero los más urgentes
        return $groupedClients->sortBy(function ($item) {
            $days = $item['days_until_end'];
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
    }

    /**
     * Construir mensaje de WhatsApp consolidado para un paciente.
     */
    protected function buildWhatsAppMessage(string $fullName, array $products): string
    {
        $hasChronic = collect($products)->contains('consumption_type', 'chronic');
        $hasSingle  = collect($products)->contains('consumption_type', 'single_treatment');
        $hasOnlySingle = $hasSingle && !$hasChronic;

        $intro = $hasOnlySingle
            ? "Nos comunicamos para hacerte seguimiento, saber cómo te fue con tu tratamiento y cómo te has sentido:"
            : "Nos comunicamos para hacerle seguimiento y recordarte la renovación de tus tratamientos y medicamentos:";

        $closingQuestion = $hasOnlySingle
            ? "¿Cómo te has sentido con el tratamiento? Si necesitas reponer medicamentos o consultar a nuestro equipo, estamos atentos para ayudarte."
            : "¿Deseas que te preparemos el pedido o te lo reservemos en caja?";

        $message = "¡Hola, {$fullName}! 🩺 Te saludamos de Farmacia Barrio Sucre ❤️\n\n" .
            "{$intro}\n\n";

        foreach ($products as $p) {
            $currentPriceUsd = (float) $p['price_usd'];
            $currentPriceCop = (float) $p['price_cop'];

            $priceFormattedUsd = '$' . number_format($currentPriceUsd, 2, ',', '.');
            $copFormatted      = $currentPriceCop > 0 ? ' / ' . number_format($currentPriceCop, 0, ',', '.') . ' COP' : '';
            $labName           = !empty($p['laboratory_name']) && $p['laboratory_name'] !== 'Sin Laboratorio' ? $p['laboratory_name'] : '';
            $labSuffix         = $labName !== '' ? " - {$labName}" : '';
            $prodName          = trim((string) $p['product_name']);

            if ($p['consumption_type'] === 'single_treatment') {
                $message .= "{$prodName}{$labSuffix} (Tratamiento)\n" .
                    "• Precio actual: {$priceFormattedUsd}{$copFormatted}\n\n";
            } elseif ($p['consumption_type'] === 'sporadic') {
                $message .= "{$prodName}{$labSuffix} (Botiquín)\n" .
                    "• Precio actual: {$priceFormattedUsd}{$copFormatted}\n\n";
            } else {
                $message .= "{$prodName}{$labSuffix} (Uso continuo)\n" .
                    "• Estimado renovación: {$p['treatment_end_date_formatted']}\n" .
                    "• Precio actual: {$priceFormattedUsd}{$copFormatted}\n\n";
            }
        }

        $message .= "🛵 ¡Consulta las condiciones para que tu envío salga con DELIVERY GRATIS hasta tu casa! 📦\n\n" .
            "Tu salud y economía en un solo corazón ❤️\n\n" .
            "{$closingQuestion}";

        return $message;
    }

    /**
     * Marcar seguimiento de paciente y sus productos como contactado.
     */
    public function markAsContacted(int $clientId, array $productIds = [], ?int $userId = null): array
    {
        $now = Carbon::now();
        $userId = $userId ?? auth()->id();

        // Obtener los productos correspondientes a este cliente
        $query = DB::table('orders')
            ->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->where('orders.status', Order::COMPLETED)
            ->where('orders.client_id', $clientId)
            ->whereIn('products.consumption_type', ['chronic', 'single_treatment', 'sporadic']);

        if (!empty($productIds)) {
            $query->whereIn('products.id', $productIds);
        }

        $records = $query->select([
            'products.id as product_id',
            'products.consumption_type',
            'products.treatment_duration_days',
            'orders.order_date',
        ])->orderByDesc('orders.order_date')->get();

        $processedProductIds = [];

        foreach ($records as $row) {
            if (in_array($row->product_id, $processedProductIds, true)) {
                continue;
            }
            $processedProductIds[] = $row->product_id;

            $nextReminderAt = null;
            if ($row->consumption_type === 'chronic') {
                $days = (int) ($row->treatment_duration_days ?: 30);
                $nextReminderAt = $now->copy()->addDays($days);
            } elseif ($row->consumption_type === 'sporadic') {
                $nextReminderAt = $now->copy()->addDays(60);
            } elseif ($row->consumption_type === 'single_treatment') {
                $nextReminderAt = null;
            }

            DB::table('chronic_patient_contacts')->updateOrInsert(
                [
                    'client_id'  => $clientId,
                    'product_id' => $row->product_id,
                ],
                [
                    'user_id'                => $userId,
                    'consumption_type'       => $row->consumption_type,
                    'last_contacted_at'      => $now,
                    'next_reminder_at'       => $nextReminderAt,
                    'order_date_at_contact'  => $row->order_date,
                    'updated_at'             => $now,
                    'created_at'             => $now,
                ]
            );
        }

        return [
            'client_id'           => $clientId,
            'processed_products'  => count($processedProductIds),
            'marked_at'           => $now->toDateTimeString(),
        ];
    }

    /**
     * Verificar si un paciente sigue disponible para contactar o si ya fue atendido.
     */
    public function checkAvailability(int $clientId): array
    {
        $now = Carbon::now();

        // Obtener los productos del cliente clasificados
        $orders = DB::table('orders')
            ->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->where('orders.status', Order::COMPLETED)
            ->where('orders.client_id', $clientId)
            ->whereIn('products.consumption_type', ['chronic', 'single_treatment', 'sporadic'])
            ->select([
                'products.id as product_id',
                'products.consumption_type',
                'orders.order_date',
            ])
            ->orderByDesc('orders.order_date')
            ->get();

        if ($orders->isEmpty()) {
            return [
                'available' => false,
                'message'   => 'El paciente no posee tratamientos o medicamentos activos.',
            ];
        }

        $contacts = DB::table('chronic_patient_contacts')
            ->where('client_id', $clientId)
            ->get()
            ->keyBy('product_id');

        $hasAvailableProduct = false;

        foreach ($orders as $row) {
            $lastDate = Carbon::parse($row->order_date);

            if (isset($contacts[$row->product_id])) {
                $contact = $contacts[$row->product_id];
                $contactOrderDate = $contact->order_date_at_contact ? Carbon::parse($contact->order_date_at_contact) : null;

                if ($contactOrderDate && $lastDate->lte($contactOrderDate)) {
                    if ($row->consumption_type === 'single_treatment') {
                        continue;
                    }
                    if (in_array($row->consumption_type, ['chronic', 'sporadic'], true)) {
                        if ($contact->next_reminder_at && $now->lt(Carbon::parse($contact->next_reminder_at))) {
                            continue;
                        }
                    }
                }
            }

            $hasAvailableProduct = true;
            break;
        }

        if (!$hasAvailableProduct) {
            return [
                'available' => false,
                'message'   => 'Este paciente ya fue contactado y atendido por otro usuario.',
            ];
        }

        return [
            'available' => true,
            'message'   => 'Paciente disponible para contactar.',
        ];
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
     * Obtener estadísticas y resumen de clientes crónicos, incluyendo cuota individual del usuario logueado.
     */
     public function getStats(?int $userId = null): array
     {
         $userId = $userId ?? auth()->id();
         $collection = $this->getProcessedChronicPatientsCollection(new Request());

         // Conteo de pacientes contactados hoy por el usuario logueado
         $todayContacted = 0;
         if ($userId) {
             $todayContacted = DB::table('chronic_patient_contacts')
                 ->where('user_id', $userId)
                 ->whereDate('last_contacted_at', Carbon::today())
                 ->distinct('client_id')
                 ->count('client_id');
         }

         return [
             'total_patients'      => $collection->pluck('client_id')->unique()->count(),
             'total_treatments'    => $collection->count(),
             'urgent_reminders'    => $collection->where('is_urgent', true)->count(),
             'expired_treatments'  => $collection->where('is_expired', true)->count(),
             'active_treatments'   => $collection->where('is_active', true)->count(),
             'patients_with_phone' => $collection->filter(fn($p) => !empty($p['phone']))->count(),
             'today_contacted'     => $todayContacted,
             'daily_quota'         => 5,
             'is_quota_completed'  => ($todayContacted >= 5),
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

    /**
     * Obtener matriz mensual de cumplimiento de cuotas diarias de fidelización por usuario/operador.
     */
    public function getDailyFidelityQuotasMatrixData(int $month, int $year): array
    {
        $dailyQuota = 5;

        // Obtener todos los empleados activos con usuario vinculado
        $employees = \App\Models\Employee::where('is_active', true)
            ->whereNotNull('user_id')
            ->select(['id', 'name', 'last_name', 'user_id', 'photo'])
            ->orderBy('name', 'asc')
            ->get();

        // Obtener contactos registrados en el mes/año agrupados por fecha y user_id
        $contacts = DB::table('chronic_patient_contacts')
            ->whereYear('last_contacted_at', $year)
            ->whereMonth('last_contacted_at', $month)
            ->whereNotNull('user_id')
            ->selectRaw('DATE(last_contacted_at) as contact_date, user_id, COUNT(DISTINCT client_id) as total_clients')
            ->groupBy('contact_date', 'user_id')
            ->get();

        $matrixMap = [];
        foreach ($contacts as $item) {
            $uid = (int) $item->user_id;
            $matrixMap[$item->contact_date][$uid] = (int) $item->total_clients;
        }

        $startDate   = Carbon::create($year, $month, 1);
        $daysInMonth = $startDate->daysInMonth;
        $today       = now()->toDateString();

        $rows = [];
        for ($d = $daysInMonth; $d >= 1; $d--) {
            $currentDate = Carbon::create($year, $month, $d)->toDateString();
            if ($currentDate > $today) {
                continue;
            }

            $userCells = [];
            $dayTotal  = 0;

            foreach ($employees as $emp) {
                $uId      = (int) $emp->user_id;
                $empId    = (int) $emp->id;
                $countVal = $matrixMap[$currentDate][$uId] ?? $matrixMap[$currentDate][$empId] ?? 0;
                $dayTotal += $countVal;

                $cellData = [
                    'count'     => $countVal,
                    'quota'     => $dailyQuota,
                    'fulfilled' => ($countVal >= $dailyQuota),
                ];

                $userCells[$uId] = $cellData;
                $userCells[(string) $uId] = $cellData;
                $userCells[$empId] = $cellData;
                $userCells[(string) $empId] = $cellData;
            }

            if (!empty($matrixMap[$currentDate])) {
                $dayTotal = array_sum($matrixMap[$currentDate]);
            }

            $rows[] = [
                'date'           => $currentDate,
                'formatted_date' => Carbon::parse($currentDate)->format('d/m/Y'),
                'day_total'      => $dayTotal,
                'users'          => (object) $userCells,
            ];
        }

        // Calcular resumen mensual
        $totalMonthCounts = 0;
        $activeDaysCount  = 0;
        $employeeTotals   = [];

        foreach ($rows as $row) {
            $totalMonthCounts += (int) $row['day_total'];
            if ($row['day_total'] > 0) {
                $activeDaysCount++;
            }
            $usersArr = (array) $row['users'];
            foreach ($employees as $emp) {
                $uId = (int) $emp->user_id;
                $c = (int) ($usersArr[$uId]['count'] ?? 0);
                $employeeTotals[$uId] = ($employeeTotals[$uId] ?? 0) + $c;
            }
        }

        $dailyAverage = $activeDaysCount > 0 ? round($totalMonthCounts / $activeDaysCount, 1) : 0.0;
        $topEmployee  = null;
        if (!empty($employeeTotals)) {
            arsort($employeeTotals);
            $topUserId = array_key_first($employeeTotals);
            $topEmpModel = $employees->firstWhere('user_id', $topUserId);
            if ($topEmpModel && $employeeTotals[$topUserId] > 0) {
                $topEmployee = [
                    'id'           => $topEmpModel->id,
                    'user_id'      => $topEmpModel->user_id,
                    'name'         => trim("{$topEmpModel->name} {$topEmpModel->last_name}"),
                    'total_counts' => $employeeTotals[$topUserId],
                ];
            }
        }

        return [
            'month'       => $month,
            'year'        => $year,
            'daily_quota' => $dailyQuota,
            'employees'   => $employees,
            'data'        => $rows,
            'summary'     => [
                'total_month_counts' => $totalMonthCounts,
                'active_days'        => $activeDaysCount,
                'daily_average'      => $dailyAverage,
                'top_employee'       => $topEmployee,
            ],
        ];
    }

    /**
     * Limpiar el teléfono de un cliente cuando no posee cuenta en WhatsApp o es erróneo.
     */
    public function removeInvalidPhone(int $clientId): array
    {
        $client = \App\Models\Client::find($clientId);

        if (!$client) {
            throw new \Exception("Cliente no encontrado.");
        }

        $oldPhone = $client->phone;
        $client->phone = null;
        $client->save();

        return [
            'client_id'   => $clientId,
            'client_name' => trim("{$client->name} {$client->last_name}"),
            'old_phone'   => $oldPhone,
            'cleaned_at'  => Carbon::now()->toDateTimeString(),
        ];
    }
}