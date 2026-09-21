<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Contracts\Repositories\FiscalZReportRepositoryInterface;
use App\Models\FiscalHistory;
use App\Models\FiscalZReport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FiscalZReportService
{
    public const DEFAULT_START_NUMBER = 740;

    public function __construct(
        private FiscalZReportRepositoryInterface $repository
    ) {}

    /**
     * Obtiene el listado paginado con filtros.
     */
    public function getReports(array $filters, int $perPage = 10, ?string $sortBy = 'report_date', string $orderBy = 'desc'): LengthAwarePaginator
    {
        $this->syncTodayReportIfHasInvoices();

        return $this->repository->getFilteredPaginated($filters, $perPage, $sortBy, $orderBy);
    }

    /**
     * Verifica y actualiza en tiempo real el reporte del día si hay facturas hoy.
     */
    public function syncTodayReportIfHasInvoices(): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $hasInvoicesToday = FiscalHistory::whereDate('invoice_date', $today)
            ->orWhere(function ($q) use ($today) {
                $q->whereNull('invoice_date')->whereDate('created_at', $today);
            })
            ->exists();

        if ($hasInvoicesToday) {
            $this->generateForDate($today, null, true);
        }
    }

    /**
     * Alias de getReports para compatibilidad.
     */
    public function getFilteredPaginated(array $filters, int $perPage = 10, ?string $sortBy = 'report_date', string $orderBy = 'desc'): LengthAwarePaginator
    {
        return $this->getReports($filters, $perPage, $sortBy, $orderBy);
    }

    /**
     * Obtiene estadísticas agregadas de los reportes.
     */
    public function getSummaryStats(array $filters): array
    {
        return $this->repository->getSummaryStats($filters);
    }

    /**
     * Obtiene un reporte por ID.
     */
    public function getReportById(int $id): ?FiscalZReport
    {
        return $this->repository->findById($id);
    }

    /**
     * Alias de getReportById para compatibilidad.
     */
    public function findById(int $id): ?FiscalZReport
    {
        return $this->getReportById($id);
    }

    /**
     * Genera o recalcula el Reporte Z de una fecha específica.
     * 
     * @param string $date Fecha en formato Y-m-d
     * @param int|null $customReportNumber Número asignado (opcional)
     * @param bool $force Recalcular si ya existe
     * @return FiscalZReport
     */
    public function generateForDate(string $date, ?int $customReportNumber = null, bool $force = false): FiscalZReport
    {
        $existing = $this->repository->findByDate($date);
        if ($existing && !$force) {
            return $existing;
        }

        // Consultar ventas fiscales del día en fiscal_history
        $records = FiscalHistory::whereDate('invoice_date', $date)
            ->orWhere(function ($q) use ($date) {
                $q->whereNull('invoice_date')->whereDate('created_at', $date);
            })
            ->orderBy('id', 'asc')
            ->get();

        $invoicesCount = $records->count();
        $exemptAmount = round((float) $records->sum('exempt_amount'), 2);
        $base16Amount = round((float) $records->sum('taxable_amount'), 2);
        $ivaAmount = round((float) $records->sum('iva_amount'), 2);

        // IGTF / SPE
        $igtfAmount = round((float) $records->sum('spe_surcharge_amount'), 2);
        $igtfBaseAmount = round((float) $records->where('spe', true)->sum('taxable_amount'), 2);
        if ($igtfBaseAmount === 0.0 && $igtfAmount > 0) {
            $igtfBaseAmount = round((float) $records->where('spe_surcharge_amount', '>', 0)->sum(function ($r) {
                return (float)$r->exempt_amount + (float)$r->taxable_amount + (float)$r->iva_amount;
            }), 2);
        }

        // Total en Bs: Exento + Base 16% + IVA 16% + IGTF 3%
        $calculatedTotal = round($exemptAmount + $base16Amount + $ivaAmount + $igtfAmount, 2);
        $totalAmount = round((float) ($records->sum('total_amount') ?: $calculatedTotal), 2);

        // Facturas de inicio y fin
        $firstInvoice = $records->first();
        $lastInvoice = $records->last();
        $firstNumber = $firstInvoice ? ($firstInvoice->invoice_number ?: ('#' . ($firstInvoice->fiscal_id ?: $firstInvoice->id))) : null;
        $lastNumber = $lastInvoice ? ($lastInvoice->invoice_number ?: ('#' . ($lastInvoice->fiscal_id ?: $lastInvoice->id))) : null;

        // Determinar número consecutivo de Reporte Z
        $reportNumber = $existing?->report_number;
        if (!$reportNumber) {
            if ($customReportNumber !== null) {
                $reportNumber = $customReportNumber;
            } else {
                $lastNumberInDb = $this->repository->getLastReportNumber();
                $reportNumber = $lastNumberInDb ? ($lastNumberInDb + 1) : self::DEFAULT_START_NUMBER;
            }
        }

        $openingTime = $firstInvoice && $firstInvoice->created_at ? Carbon::parse($firstInvoice->created_at)->format('H:i:s') : '08:00:00';
        $closingTime = $lastInvoice && $lastInvoice->created_at ? Carbon::parse($lastInvoice->created_at)->format('H:i:s') : '23:59:59';

        $isToday = ($date === Carbon::today()->format('Y-m-d'));
        $status = $isToday ? 'open' : 'closed';

        $data = [
            'report_number'        => $reportNumber,
            'report_date'          => $date,
            'opening_time'         => $openingTime,
            'closing_time'         => $closingTime,
            'first_invoice_number' => $firstNumber,
            'last_invoice_number'  => $lastNumber,
            'invoices_count'       => $invoicesCount,
            'exempt_amount'        => $exemptAmount,
            'base_16_amount'       => $base16Amount,
            'iva_amount'           => $ivaAmount,
            'igtf_base_amount'     => $igtfBaseAmount,
            'igtf_amount'          => $igtfAmount,
            'total_amount'         => $totalAmount,
            'status'               => $status,
        ];

        return $this->repository->updateOrCreateByDate($date, $data);
    }

    /**
     * Elimina un reporte Z por número.
     */
    public function deleteByNumber(int $number): bool
    {
        return $this->repository->deleteByNumber($number);
    }

    /**
     * Elimina un reporte Z por fecha.
     */
    public function deleteByDate(string $date): bool
    {
        return $this->repository->deleteByDate($date);
    }

    /**
     * Genera todos los reportes Z para los días de un mes y año dados.
     * Si backward es true, el día más reciente (ayer o fin de mes) tendrá el targetNumber (ej. 739 o 740)
     * y los días anteriores se numerarán hacia atrás (738, 737...).
     * 
     * @param int $year
     * @param int $month
     * @param int $targetNumber Número asignado para el último día procesado
     * @param bool $force Recalcular si ya existen
     * @param bool $backward Si es true, numera hacia atrás desde el último día
     * @param bool $includeToday Si es false (por defecto), solo genera hasta el día de ayer
     * @return \Illuminate\Support\Collection
     */
    public function generateForMonth(
        int $year,
        int $month,
        int $targetNumber = self::DEFAULT_START_NUMBER,
        bool $force = true,
        bool $backward = true,
        bool $includeToday = false
    ): \Illuminate\Support\Collection {
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->startOfDay();
        $today = Carbon::today()->startOfDay();
        $yesterday = Carbon::yesterday()->startOfDay();

        // Si es el mes actual y no se incluye hoy, solo generar hasta ayer
        if ($endDate->isAfter($today)) {
            $endDate = $includeToday ? $today : $yesterday;
        } elseif ($endDate->equalTo($today) && !$includeToday) {
            $endDate = $yesterday;
        }

        // Si la fecha de inicio es posterior a la de fin (ej. día 1 del mes cuando hoy es día 1 y no se incluye hoy)
        if ($startDate->isAfter($endDate)) {
            return collect();
        }

        // Construir la lista de fechas en orden cronológico (del día 1 al día N)
        $dates = [];
        $tempDate = $startDate->copy();
        while ($tempDate->lte($endDate)) {
            $dates[] = $tempDate->copy()->format('Y-m-d');
            $tempDate->addDay();
        }

        $totalDays = count($dates);
        $generated = collect();

        if ($totalDays === 0) {
            return $generated;
        }

        if ($backward) {
            // El último día (índice $totalDays - 1) recibe $targetNumber
            // El día con índice $i recibe $targetNumber - ($totalDays - 1 - $i)
            foreach ($dates as $index => $dateStr) {
                $assignedNumber = $targetNumber - ($totalDays - 1 - $index);
                $report = $this->generateForDate($dateStr, $assignedNumber, $force);
                $generated->push($report);
            }
        } else {
            // Numeración hacia adelante a partir de $targetNumber
            foreach ($dates as $index => $dateStr) {
                $assignedNumber = $targetNumber + $index;
                $report = $this->generateForDate($dateStr, $assignedNumber, $force);
                $generated->push($report);
            }
        }

        return $generated;
    }

    /**
     * Sube y verifica la imagen del reporte Z contra los datos del sistema usando Gemini.
     */
    public function verifyImageWithAi(int $id, \Illuminate\Http\UploadedFile $file): FiscalZReport
    {
        $report = $this->repository->findById($id);
        if (!$report) {
            throw new \Exception("Reporte Z no encontrado.");
        }

        // Guardar imagen
        $path = $file->store('z_reports', 'public');
        $report->image_path = $path;
        $report->save();

        // Obtener clave de Gemini de config o env
        $apiKey = config('services.gemini.api_key') 
            ?: config('services.telegram.gemini_api_key') 
            ?: env('GEMINI_API_KEY');

        if (!$apiKey) {
            throw new \Exception("La clave de API de Gemini (GEMINI_API_KEY) no está configurada en el servidor.");
        }

        $base64 = base64_encode(file_get_contents($file->path()));
        $mimeType = $file->getMimeType() ?: 'image/jpeg';

        $prompt = "Analiza minuciosamente la imagen del comprobante / ticket físico de corte fiscal Reporte Z adjunto y compáralo con los datos registrados en el sistema.\n"
            . "Datos registrados en el sistema:\n"
            . "- Nro Reporte Z: {$report->report_number}\n"
            . "- Fecha de emisión: {$report->report_date}\n"
            . "- Cantidad de facturas/documentos: {$report->invoices_count}\n"
            . "- Total Exento (Bs): {$report->exempt_amount}\n"
            . "- Base Imponible 16% (Bs): {$report->base_16_amount}\n"
            . "- Total IVA 16% (Bs): {$report->iva_amount}\n"
            . "- Total IGTF (Bs): {$report->igtf_amount}\n"
            . "- Gran Total (Bs): {$report->total_amount}\n\n"
            . "Compara número de reporte, fecha, totales gravados, exentos, impuestos y gran total.\n"
            . "Debes responder estrictamente en formato JSON plano con esta estructura:\n"
            . "{\n"
            . "  \"match\": true o false,\n"
            . "  \"notes\": \"Explicación clara y concisa en español indicando si los valores coinciden o detallando las discrepancias exactas encontradas.\"\n"
            . "}";

        // Modelos soportados en orden de prioridad
        $candidateModels = ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash'];
        $lastError = null;
        $response = null;

        foreach ($candidateModels as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            try {
                $res = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-goog-api-key' => $apiKey,
                ])->timeout(35)->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $base64,
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                if ($res->successful()) {
                    $response = $res;
                    break;
                }

                $lastError = $res->body();
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        if (!$response || !$response->successful()) {
            throw new \Exception("Error al comunicarse con la IA de Gemini: " . ($lastError ?: 'Sin respuesta del modelo.'));
        }

        $aiData = $response->json();
        $text = $aiData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        
        // Limpiar JSON si viene con backticks de markdown
        $text = preg_replace('/```json\s*(.*?)\s*```/is', '$1', $text);
        $text = trim($text);

        $result = json_decode($text, true);
        $isMatch = (bool) ($result['match'] ?? false);
        $notes = $result['notes'] ?? 'Comprobación finalizada sin observaciones.';

        $report->ai_verification_notes = $notes;
        $report->status = $isMatch ? 'COMPROBADO' : 'DISCREPANCIA';
        $report->save();

        return $report;
    }
}
