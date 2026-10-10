<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Client;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OnboardingSalesOrdersImportService
{
    /**
     * Parsea uno o múltiples archivos de transacciones de ventas y realiza el pre-análisis de clientes y productos.
     *
     * @param string|array<string> $filePaths
     */
    public function parseAndAnalyze(string|array $filePaths): array
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '600');

        $paths = is_array($filePaths) ? $filePaths : [$filePaths];
        $allRows = [];

        foreach ($paths as $filePath) {
            if (!empty($filePath) && file_exists($filePath)) {
                $rows = $this->extractRawRows($filePath);
                $allRows = array_merge($allRows, $rows);
            }
        }

        $parsedOrders = $this->structureSalesOrders($allRows);

        return $this->correlateOrdersData($parsedOrders);
    }

    /**
     * Ejecuta la persistencia de las órdenes de venta y sus detalles en base de datos.
     */
    public function executeImport(array $ordersPayload): array
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '1200');

        return DB::transaction(function () use ($ordersPayload) {
            $sellerId = Auth::id() ?: 1;

            // Obtener o crear cliente genérico de respaldo
            $genericClient = $this->getOrCreateGenericClient();

            $stats = [
                'orders_created'         => 0,
                'order_details_created'  => 0,
                'clients_auto_created'   => 0,
                'orders_skipped'         => 0,
                'total_amount_bs'        => 0.0,
                'total_amount_usd'       => 0.0,
            ];

            // Pre-cargar productos en memoria
            $allBarcodes = [];
            foreach ($ordersPayload as $orderItem) {
                foreach ($orderItem['items'] ?? [] as $item) {
                    if (!empty($item['barcode'])) {
                        $allBarcodes[] = (string) $item['barcode'];
                    }
                }
            }
            $productsByBarcode = Product::withoutGlobalScope('not_deleted')
                ->whereIn('barcode', array_unique($allBarcodes))
                ->get()
                ->keyBy('barcode');

            // Pre-cargar clientes
            $existingClients = Client::select(['id', 'identification_type', 'identification'])->get();
            $clientsMap = [];
            foreach ($existingClients as $c) {
                $key = $c->identification_type . ltrim((string) $c->identification, '0');
                $clientsMap[$key] = $c->id;
                $clientsMap['NUM_' . ltrim((string) $c->identification, '0')] = $c->id;
            }

            foreach ($ordersPayload as $orderItem) {
                $docNumber = trim((string) ($orderItem['document_number'] ?? ''));
                $orderDate = !empty($orderItem['order_date']) ? Carbon::parse($orderItem['order_date']) : Carbon::now();
                $sectionType = $orderItem['section_type'] ?? 'FAC';
                $totalAmount = (float) ($orderItem['total_amount'] ?? 0.0);
                $neto = (float) ($orderItem['net_amount'] ?? $totalAmount);
                $exento = (float) ($orderItem['exempt_amount'] ?? 0.0);
                $taxableBase = max(0.0, $neto - $exento);

                // Obtener tasa BCV para la fecha de la venta
                $bcvRate = $this->getBcvRateForDate($orderDate);
                $totalAmountUsd = ($bcvRate > 0) ? round(abs($totalAmount) / $bcvRate, 2) : 0.0;

                // 1. Resolver o crear cliente
                $clientId = null;
                $clientIdent = $orderItem['client_ident'] ?? null;
                $clientName = trim((string) ($orderItem['client_name'] ?? ''));

                if (!empty($clientIdent)) {
                    $parsedIdent = $this->parseClientIdent($clientIdent);
                    if ($parsedIdent) {
                        $key = $parsedIdent['type'] . ltrim($parsedIdent['number'], '0');
                        $numKey = 'NUM_' . ltrim($parsedIdent['number'], '0');

                        if (isset($clientsMap[$key])) {
                            $clientId = $clientsMap[$key];
                        } elseif (isset($clientsMap[$numKey])) {
                            $clientId = $clientsMap[$numKey];
                        } else {
                            $nameParts = $this->splitNameAndLastName($clientName, $parsedIdent['type']);
                            $newClient = Client::create([
                                'identification_type' => $parsedIdent['type'],
                                'identification'      => $parsedIdent['number'],
                                'name'                => $nameParts['name'] ?: 'Cliente',
                                'last_name'           => $nameParts['last_name'],
                                'client_type'         => Client::CLIENT_TYPE_NUEVO,
                                'status'              => 1,
                                'user_id'             => $sellerId,
                                'balance'             => 0.0,
                            ]);

                            $clientId = $newClient->id;
                            $clientsMap[$key] = $clientId;
                            $clientsMap[$numKey] = $clientId;
                            $stats['clients_auto_created']++;
                        }
                    }
                }

                if (!$clientId) {
                    $clientId = $genericClient->id;
                }

                // Estado de la orden
                $orderStatus = ($sectionType === 'NCR' || $totalAmount < 0) ? Order::CANCELLED : Order::COMPLETED;

                // 2. Crear la orden
                $order = Order::create([
                    'client_id'               => $clientId,
                    'seller_id'               => $sellerId,
                    'total_amount'            => abs($totalAmount),
                    'money_returns'           => 0.0,
                    'usd_conversion'          => $bcvRate > 0 ? $bcvRate : 0.0,
                    'total_cost'              => 0.0,
                    'taxable_base'            => abs($taxableBase),
                    'currency'                => 'Bs',
                    'order_date'              => $orderDate,
                    'status'                  => $orderStatus,
                    'has_multiple_currencies' => false,
                    'payment_methods'         => [
                        [
                            'type'   => 'efectivo',
                            'amount' => abs($totalAmount),
                        ],
                    ],
                    'total_amount_usd'        => $totalAmountUsd,
                ]);

                $orderTotalCost = 0.0;

                // 3. Crear detalles de orden
                $items = $orderItem['items'] ?? [];
                foreach ($items as $item) {
                    $barcode = trim((string) ($item['barcode'] ?? ''));
                    $qty = abs((float) ($item['quantity'] ?? 1.0));
                    $priceBs = abs((float) ($item['price'] ?? 0.0));
                    $priceUsd = ($bcvRate > 0) ? round($priceBs / $bcvRate, 2) : 0.0;
                    $itemTotal = abs((float) ($item['total'] ?? ($qty * $priceBs)));
                    $description = trim((string) ($item['description'] ?? ''));

                    $productId = null;
                    $unitCost = 0.0;

                    if (!empty($barcode) && isset($productsByBarcode[$barcode])) {
                        $product = $productsByBarcode[$barcode];
                        $productId = $product->id;
                        $unitCost = (float) ($product->unit_cost ?? 0.0);
                    }

                    $orderTotalCost += ($unitCost * $qty);

                    OrderDetail::create([
                        'order_id'                 => $order->id,
                        'product_id'               => $productId,
                        'product_type'             => 'product',
                        'quantity'                 => $qty,
                        'quantity_expiration'      => 0.0,
                        'price'                    => $priceUsd > 0 ? $priceUsd : $priceBs,
                        'price_bs'                 => $priceBs,
                        'price_before_discount'    => $priceUsd > 0 ? $priceUsd : $priceBs,
                        'price_before_discount_bs' => $priceBs,
                        'unit_cost'                => $unitCost,
                        'discount_percentage'      => 0.0,
                        'notes'                    => $description,
                    ]);

                    $stats['order_details_created']++;
                }

                $order->total_cost = round($orderTotalCost, 2);
                $order->save();

                $stats['orders_created']++;
                $stats['total_amount_bs'] += abs($totalAmount);
                $stats['total_amount_usd'] += $totalAmountUsd;
            }

            $stats['total_amount_bs'] = round($stats['total_amount_bs'], 2);
            $stats['total_amount_usd'] = round($stats['total_amount_usd'], 2);

            Log::info('[OnboardingSalesOrdersImport] Importación de ventas completada', $stats);

            return $stats;
        });
    }

    /**
     * Extrae filas de datos del archivo soportado.
     */
    protected function extractRawRows(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("El archivo de ventas no existe en la ruta: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsvFile($filePath);
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        $rows = [];
        for ($r = 1; $r <= $highestRow; $r++) {
            $rowRange = $sheet->rangeToArray("A{$r}:{$highestCol}{$r}", null, true, true, false)[0];
            $rows[] = array_map(function ($val) {
                return $val !== null ? (string) $val : '';
            }, $rowRange);
        }

        return $rows;
    }

    /**
     * Parsea un archivo CSV.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        while (($data = fgetcsv($handle, 4096, ',')) !== false) {
            $rows[] = array_map('strval', $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Estructura las transacciones de ventas agrupando encabezados de orden y sus detalles de productos.
     */
    protected function structureSalesOrders(array $rows): array
    {
        $orders = [];
        $currentOrder = null;
        $currentSection = 'FAC';

        foreach ($rows as $row) {
            $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));
            if (empty($nonEmpty)) {
                continue;
            }

            $rowString = implode(' ', $nonEmpty);

            if (count($nonEmpty) === 1) {
                $tag = strtoupper($nonEmpty[0]);
                if (in_array($tag, ['FAC', 'NCR', 'NET'], true)) {
                    $currentSection = $tag;
                    continue;
                }
            }

            if (
                stripos($rowString, 'Relación de Transacciones') !== false ||
                stripos($rowString, 'ENSALUD') !== false ||
                stripos($rowString, 'Fecha Impresión') !== false ||
                stripos($rowString, 'Período') !== false ||
                (stripos($rowString, 'Fecha') !== false && stripos($rowString, 'Documento') !== false) ||
                (stripos($rowString, 'Código') !== false && stripos($rowString, 'Descripción') !== false && stripos($rowString, 'Cantidad') !== false)
            ) {
                continue;
            }

            $orderHeader = $this->extractOrderHeader($row);
            if ($orderHeader !== null) {
                if ($currentOrder !== null && !empty($currentOrder['items'])) {
                    $orders[] = $currentOrder;
                }

                $currentOrder = [
                    'section_type'    => $currentSection,
                    'order_date'      => $orderHeader['date'],
                    'document_number' => $orderHeader['document'],
                    'client_ident'    => $orderHeader['client_ident'],
                    'client_name'     => $orderHeader['client_name'],
                    'net_amount'      => $orderHeader['net_amount'],
                    'exempt_amount'   => $orderHeader['exempt_amount'],
                    'tax_amount'      => $orderHeader['tax_amount'],
                    'total_amount'    => $orderHeader['total_amount'],
                    'items'           => [],
                ];
                continue;
            }

            if ($currentOrder !== null) {
                $orderItem = $this->extractOrderItem($row);
                if ($orderItem !== null) {
                    $currentOrder['items'][] = $orderItem;
                }
            }
        }

        if ($currentOrder !== null && !empty($currentOrder['items'])) {
            $orders[] = $currentOrder;
        }

        return $orders;
    }

    /**
     * Extrae información de cabecera de orden.
     */
    protected function extractOrderHeader(array $row): ?array
    {
        $dateCandidate = null;
        $dateIdx = null;

        foreach ($row as $idx => $cell) {
            $c = trim($cell);
            if ($this->isValidDate($c)) {
                $dateCandidate = $this->parseDateString($c);
                $dateIdx = $idx;
                break;
            }
        }

        if ($dateCandidate === null || $dateIdx === null) {
            return null;
        }

        $doc = '';
        $clientIdent = '';
        $clientName = '';
        $clientNameIdx = null;

        for ($i = $dateIdx + 1; $i < count($row); $i++) {
            $val = trim($row[$i]);
            if (empty($val)) {
                continue;
            }

            if (empty($doc) && preg_match('/^\d{5,12}$/', $val)) {
                $doc = $val;
                continue;
            }

            if (!empty($doc) && empty($clientIdent) && (preg_match('/^[VJEGvjeg]-?\d+/', $val) || preg_match('/^\d{5,10}$/', $val))) {
                $clientIdent = $val;
                continue;
            }

            if (!empty($doc) && !empty($clientIdent) && empty($clientName) && !$this->isNumericFormat($val)) {
                $clientName = $val;
                $clientNameIdx = $i;
                break;
            }
        }

        if (empty($doc)) {
            return null;
        }

        // Extraer montos numéricos que vienen después del nombre del cliente
        $startIdx = $clientNameIdx !== null ? $clientNameIdx + 1 : $dateIdx + 1;
        $numericValues = [];

        for ($i = $startIdx; $i < count($row); $i++) {
            $val = trim($row[$i]);
            if ($val !== '' && $this->isNumericFormat($val)) {
                $num = $this->parseNumericValue($val);
                // Ignorar el número de documento si coincidió
                if ((string)$num !== $doc && (string)$num !== ltrim($doc, '0')) {
                    $numericValues[] = $num;
                }
            }
        }

        $netAmount = $numericValues[0] ?? 0.0;
        $exemptAmount = $numericValues[1] ?? 0.0;
        $taxAmount = $numericValues[2] ?? 0.0;
        $totalAmount = $numericValues[3] ?? ($netAmount + $taxAmount);

        return [
            'date'          => $dateCandidate,
            'document'      => $doc,
            'client_ident'  => $clientIdent,
            'client_name'   => $clientName,
            'net_amount'    => $netAmount,
            'exempt_amount' => $exemptAmount,
            'tax_amount'    => $taxAmount,
            'total_amount'  => $totalAmount,
        ];
    }

    /**
     * Extrae un ítem de producto dentro de una orden de venta.
     */
    protected function extractOrderItem(array $row): ?array
    {
        $nonEmpty = array_values(array_filter(array_map('trim', $row), fn($c) => $c !== ''));
        if (count($nonEmpty) < 4) {
            return null;
        }

        $barcode = $nonEmpty[0];
        $description = $nonEmpty[1];

        if (stripos($barcode, 'Código') !== false || stripos($description, 'Descripción') !== false) {
            return null;
        }

        $numericValues = [];
        $unit = 'UND';

        for ($i = 2; $i < count($nonEmpty); $i++) {
            $val = $nonEmpty[$i];
            if ($this->isNumericFormat($val)) {
                $numericValues[] = $this->parseNumericValue($val);
            } elseif (in_array(strtoupper($val), ['UND', 'SOBRE', 'PAR', 'FRASCO', 'BLISTER', 'CAJA', 'BOTELLA', 'AMP', 'SACHETS', 'PAQUETE'], true)) {
                $unit = strtoupper($val);
            }
        }

        if (empty($numericValues)) {
            return null;
        }

        $quantity = $numericValues[0] ?? 1.0;
        $price = $numericValues[1] ?? 0.0;
        $discount = count($numericValues) >= 4 ? $numericValues[2] : 0.0;
        $total = count($numericValues) >= 4 ? $numericValues[3] : ($numericValues[2] ?? ($quantity * $price));

        return [
            'barcode'     => $barcode,
            'description' => $description,
            'quantity'    => $quantity,
            'unit'        => $unit,
            'price'       => $price,
            'discount'    => $discount,
            'total'       => $total,
        ];
    }

    /**
     * Correlaciona las órdenes extraídas con los clientes y productos del ERP.
     */
    protected function correlateOrdersData(array $parsedOrders): array
    {
        $allBarcodes = [];
        foreach ($parsedOrders as $order) {
            foreach ($order['items'] as $item) {
                if (!empty($item['barcode'])) {
                    $allBarcodes[] = (string) $item['barcode'];
                }
            }
        }
        $existingProducts = Product::withoutGlobalScope('not_deleted')
            ->select(['id', 'barcode', 'name', 'unit_cost', 'sale_price'])
            ->whereIn('barcode', array_unique($allBarcodes))
            ->get()
            ->keyBy('barcode');

        $existingClients = Client::select(['id', 'identification_type', 'identification', 'name', 'last_name'])->get();
        $clientsMap = [];
        foreach ($existingClients as $c) {
            $key = $c->identification_type . ltrim((string) $c->identification, '0');
            $clientsMap[$key] = $c;
            $clientsMap['NUM_' . ltrim((string) $c->identification, '0')] = $c;
        }

        $ordersList = [];
        $totalItemsCount = 0;
        $totalSalesBs = 0.0;
        $totalSalesUsd = 0.0;
        $matchedProductsCount = 0;
        $missingProductsCount = 0;

        foreach ($parsedOrders as $order) {
            $orderDateCarbon = !empty($order['order_date']) ? Carbon::parse($order['order_date']) : Carbon::now();
            $bcvRate = $this->getBcvRateForDate($orderDateCarbon);
            $orderTotalBs = (float) ($order['total_amount'] ?? 0.0);
            $orderTotalUsd = ($bcvRate > 0) ? round($orderTotalBs / $bcvRate, 2) : 0.0;

            $clientIdent = $order['client_ident'];
            $clientMatch = null;

            if (!empty($clientIdent)) {
                $parsedIdent = $this->parseClientIdent($clientIdent);
                if ($parsedIdent) {
                    $key = $parsedIdent['type'] . ltrim($parsedIdent['number'], '0');
                    $numKey = 'NUM_' . ltrim($parsedIdent['number'], '0');
                    $clientMatch = $clientsMap[$key] ?? $clientsMap[$numKey] ?? null;
                }
            }

            $orderItems = [];
            foreach ($order['items'] as $item) {
                $barcode = $item['barcode'];
                $productMatch = $existingProducts[$barcode] ?? null;

                if ($productMatch) {
                    $matchedProductsCount++;
                } else {
                    $missingProductsCount++;
                }

                $priceBs = (float) ($item['price'] ?? 0.0);
                $priceUsd = ($bcvRate > 0) ? round($priceBs / $bcvRate, 2) : 0.0;

                $orderItems[] = [
                    'barcode'         => $barcode,
                    'description'     => $item['description'],
                    'quantity'        => $item['quantity'],
                    'unit'            => $item['unit'],
                    'price'           => $priceBs,
                    'price_usd'       => $priceUsd,
                    'total'           => $item['total'],
                    'total_usd'       => ($bcvRate > 0) ? round(((float) $item['total']) / $bcvRate, 2) : 0.0,
                    'product_matched' => $productMatch !== null,
                    'product_name'    => $productMatch?->name ?? null,
                ];
                $totalItemsCount++;
            }

            $totalSalesBs += $orderTotalBs;
            $totalSalesUsd += $orderTotalUsd;

            $ordersList[] = [
                'section_type'      => $order['section_type'],
                'order_date'        => $order['order_date'],
                'document_number'   => $order['document_number'],
                'client_ident'      => $order['client_ident'],
                'client_name'       => $order['client_name'],
                'client_matched'    => $clientMatch !== null,
                'matched_client'    => $clientMatch ? ($clientMatch->name . ' ' . ($clientMatch->last_name ?? '')) : null,
                'net_amount'        => round($order['net_amount'], 2),
                'exempt_amount'     => round($order['exempt_amount'], 2),
                'tax_amount'        => round($order['tax_amount'], 2),
                'total_amount'      => round($orderTotalBs, 2),
                'total_amount_usd'  => $orderTotalUsd,
                'bcv_rate'          => $bcvRate,
                'items_count'       => count($orderItems),
                'items'             => $orderItems,
            ];
        }

        return [
            'summary' => [
                'total_orders'           => count($ordersList),
                'total_items'            => $totalItemsCount,
                'total_sales_bs'         => round($totalSalesBs, 2),
                'total_sales_usd'        => round($totalSalesUsd, 2),
                'matched_products_count' => $matchedProductsCount,
                'missing_products_count' => $missingProductsCount,
            ],
            'orders' => $ordersList,
        ];
    }

    /**
     * Cache de tasas BCV indexadas por fecha.
     * @var array<string, float>
     */
    protected array $bcvRateCache = [];

    /**
     * Obtiene la tasa BCV oficial más representativa para la fecha de la orden de venta.
     */
    public function getBcvRateForDate(Carbon $date): float
    {
        $dateKey = $date->toDateString();
        if (isset($this->bcvRateCache[$dateKey])) {
            return $this->bcvRateCache[$dateKey];
        }

        // 1. Buscar en BD local en esa fecha exacta
        $rate = \App\Models\ExchangeRate::whereIn('currency_code', ['BS', 'VES', 'BCV', 'USD_VES'])
            ->whereDate('created_at', $dateKey)
            ->orderByDesc('id')
            ->value('rate');

        if ($rate && (float) $rate > 0) {
            $this->bcvRateCache[$dateKey] = (float) $rate;
            return (float) $rate;
        }

        // 2. Consultar la API histórica oficial de DolarApi (ve.dolarapi.com/v1/historicos/dolares/oficial/{YYYY}/{MM}/{DD})
        $apiRate = $this->fetchHistoricalBcvRateFromApi($date);
        if ($apiRate && $apiRate > 0) {
            $this->bcvRateCache[$dateKey] = $apiRate;
            return $apiRate;
        }

        // 3. Si no hay en la API para esa fecha exacta, buscar en BD la más cercana anterior o igual
        $rate = \App\Models\ExchangeRate::whereIn('currency_code', ['BS', 'VES', 'BCV', 'USD_VES'])
            ->where('created_at', '<=', $date->endOfDay())
            ->orderByDesc('created_at')
            ->value('rate');

        // 4. Si sigue sin existir, buscar la primera histórica en BD
        if (!$rate || (float) $rate <= 0) {
            $rate = \App\Models\ExchangeRate::whereIn('currency_code', ['BS', 'VES', 'BCV', 'USD_VES'])
                ->orderBy('created_at', 'asc')
                ->value('rate');
        }

        // 5. Fallback a la última tasa registrada
        if (!$rate || (float) $rate <= 0) {
            $rate = \App\Models\ExchangeRate::whereIn('currency_code', ['BS', 'VES', 'BCV', 'USD_VES'])
                ->orderByDesc('id')
                ->value('rate');
        }

        $finalRate = ($rate && (float) $rate > 0) ? (float) $rate : 1.0;
        $this->bcvRateCache[$dateKey] = $finalRate;

        return $finalRate;
    }

    /**
     * Consulta la tasa BCV histórica en la API externa para una fecha específica.
     */
    protected function fetchHistoricalBcvRateFromApi(Carbon $date): ?float
    {
        try {
            $year = $date->format('Y');
            $month = $date->format('m');
            $day = $date->format('d');

            $url = "https://ve.dolarapi.com/v1/historicos/dolares/oficial/{$year}/{$month}/{$day}";
            $response = Http::timeout(4)->retry(2, 500)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['promedio'] ?? $data['monto'] ?? $data['precio'] ?? null;
                if ($rate && (float) $rate > 0) {
                    return (float) $rate;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[OnboardingSalesOrdersImport] Error consultando API BCV histórica para {$date->toDateString()}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Obtiene o crea el cliente genérico de respaldo.
     */
    protected function getOrCreateGenericClient(): Client
    {
        $client = Client::where('identification', '00000000')->first();
        if ($client) {
            return $client;
        }

        return Client::create([
            'identification_type' => 'V-',
            'identification'      => '00000000',
            'name'                => 'CLIENTE',
            'last_name'           => 'GENERICO',
            'client_type'         => Client::CLIENT_TYPE_OCASIONAL,
            'status'              => 1,
            'balance'             => 0.0,
        ]);
    }

    protected function parseClientIdent(string $rawIdent): ?array
    {
        $clean = strtoupper(trim($rawIdent));
        $clean = preg_replace('/[^A-Z0-9]/', '', $clean);

        if (preg_match('/^([VJEG])(\d+)$/', $clean, $m)) {
            return [
                'type'   => $m[1] . '-',
                'number' => ltrim($m[2], '0') ?: $m[2],
            ];
        }

        if (preg_match('/^\d+$/', $clean)) {
            return [
                'type'   => 'V-',
                'number' => ltrim($clean, '0') ?: $clean,
            ];
        }

        return null;
    }

    protected function splitNameAndLastName(string $fullName, string $type): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        if ($type === 'J-' || $type === 'G-') {
            return ['name' => $fullName ?: 'Empresa', 'last_name' => null];
        }

        $tokens = explode(' ', $fullName);
        if (count($tokens) <= 1) {
            return ['name' => $tokens[0] ?? 'Cliente', 'last_name' => null];
        }
        if (count($tokens) === 2) {
            return ['name' => $tokens[0], 'last_name' => $tokens[1]];
        }
        return [
            'name'      => $tokens[0] . ' ' . $tokens[1],
            'last_name' => implode(' ', array_slice($tokens, 2)),
        ];
    }

    protected function isValidDate(string $str): bool
    {
        $str = trim($str);
        return (bool) preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $str) || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $str);
    }

    protected function parseDateString(string $dateStr): string
    {
        $dateStr = trim($dateStr);
        try {
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
            return Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::now()->format('Y-m-d');
        }
    }

    protected function isNumericFormat(string $val): bool
    {
        $val = trim($val);
        return (bool) preg_match('/^-?\d{1,3}(?:\.\d{3})*(?:,\d+)?$/', $val) || (bool) preg_match('/^-?\d+(?:\.\d+)?$/', $val);
    }

    protected function parseNumericValue(string $val): float
    {
        $val = trim($val);
        if (str_contains($val, ',') && str_contains($val, '.')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } elseif (str_contains($val, ',')) {
            $val = str_replace(',', '.', $val);
        }

        return (float) preg_replace('/[^\d.-]/', '', $val);
    }
}
