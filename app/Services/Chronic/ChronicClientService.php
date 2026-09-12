<?php

declare(strict_types=1);

namespace App\Services\Chronic;

use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChronicClientService
{
    /**
     * Obtener listado de clientes con compras de medicamentos crónicos y cálculo de fin de tratamiento.
     */
    public function getChronicPatients(Request $request): LengthAwarePaginator
    {
        $search = $request->input("search");
        $status = $request->input("status", "all"); // all, urgent (<= 5 dias), expired, active
        $productId = $request->input("product_id");
        $hasPhone = $request->input("has_phone"); // "yes", "no"
        $perPage = (int) $request->input("itemsPerPage", 15);
        $page = (int) $request->input("page", 1);

        // Subquery para obtener la última orden completada por cliente y producto crónico
        $latestDetailsQuery = OrderDetail::select(
                "orders.client_id",
                "order_details.product_id",
                DB::raw("MAX(orders.order_date) as last_order_date")
            )
            ->join("orders", "orders.id", "=", "order_details.order_id")
            ->join("products", "products.id", "=", "order_details.product_id")
            ->where("products.is_chronic", true)
            ->where("orders.status", Order::COMPLETED)
            ->whereNotNull("orders.client_id")
            ->groupBy("orders.client_id", "order_details.product_id");

        // Query principal combinando cliente, producto y la última compra
        $query = DB::table(DB::raw("({$latestDetailsQuery->toSql()}) as latest_purchases"))
            ->mergeBindings($latestDetailsQuery->getQuery())
            ->join("clients", "clients.id", "=", "latest_purchases.client_id")
            ->join("products", "products.id", "=", "latest_purchases.product_id")
            ->leftJoin("laboratories", "laboratories.id", "=", "products.laboratory_id")
            ->whereNull("clients.deleted_at")
            ->where("products.is_deleted", false);

        // Join para obtener la cantidad comprada en esa última orden
        $query->join("orders", function ($join) {
            $join->on("orders.client_id", "=", "latest_purchases.client_id")
                ->on("orders.order_date", "=", "latest_purchases.last_order_date")
                ->where("orders.status", "=", Order::COMPLETED);
        })->join("order_details", function ($join) {
            $join->on("order_details.order_id", "=", "orders.id")
                ->on("order_details.product_id", "=", "latest_purchases.product_id");
        });

        // Filtros de búsqueda
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where("clients.name", "like", "%{$search}%")
                    ->orWhere("clients.last_name", "like", "%{$search}%")
                    ->orWhere("clients.identification", "like", "%{$search}%")
                    ->orWhere("clients.phone", "like", "%{$search}%")
                    ->orWhere("products.name", "like", "%{$search}%")
                    ->orWhere("products.barcode", "like", "%{$search}%")
                    ->orWhere("laboratories.name", "like", "%{$search}%");
            });
        }

        if (!empty($productId)) {
            $query->where("products.id", $productId);
        }

        if ($hasPhone === "yes") {
            $query->whereNotNull("clients.phone")->where("clients.phone", "!=", "");
        } elseif ($hasPhone === "no") {
            $query->where(function ($q) {
                $q->whereNull("clients.phone")->orWhere("clients.phone", "=", "");
            });
        }

        $query->select([
            "clients.id as client_id",
            "clients.identification_type",
            "clients.identification",
            "clients.name as client_name",
            "clients.last_name as client_last_name",
            "clients.phone as client_phone",
            "clients.email as client_email",
            "products.id as product_id",
            "products.name as product_name",
            "products.barcode as product_barcode",
            "products.sale_price as current_sale_price",
            "products.treatment_duration_days",
            "laboratories.name as laboratory_name",
            "latest_purchases.last_order_date",
            "order_details.quantity as purchased_quantity",
            "order_details.unit_price_usd",
        ]);

        $query->groupBy(
            "clients.id",
            "clients.identification_type",
            "clients.identification",
            "clients.name",
            "clients.last_name",
            "clients.phone",
            "clients.email",
            "products.id",
            "products.name",
            "products.barcode",
            "products.sale_price",
            "products.treatment_duration_days",
            "laboratories.name",
            "latest_purchases.last_order_date",
            "order_details.quantity",
            "order_details.unit_price_usd"
        );

        // Obtenemos los registros para calcular las fechas dinámicas y aplicar filtros de estado si aplica
        $allRecords = $query->get();

        // Obtener tasas de cambio para calcular precio en moneda local (Bs y COP)
        $rateBs = ExchangeRate::whereIn("currency_code", ["VES", "BS", "BCV", "EUR"])->orderByDesc("id")->value("rate") ?? 0;
        $rateCop = ExchangeRate::where("currency_code", "COP")->orderByDesc("id")->value("rate") ?? 0;

        $processed = $allRecords->map(function ($row) use ($rateBs, $rateCop) {
            $lastDate = Carbon::parse($row->last_order_date);
            $durationDaysPerUnit = (int) ($row->treatment_duration_days ?: 30);
            $totalTreatmentDays = (int) round((float) $row->purchased_quantity * $durationDaysPerUnit);
            
            $treatmentEndDate = $lastDate->copy()->addDays($totalTreatmentDays);
            $reminderDate = $treatmentEndDate->copy()->subDays(5);

            $now = Carbon::now();
            $daysUntilEnd = (int) ceil($now->floatDiffInDays($treatmentEndDate, false));

            // Estados:
            // urgent: faltan 5 o menos días para acabarse (o se venció en los últimos 30 días)
            // expired: vencido hace más de 30 días
            // active: faltan más de 5 días
            $isUrgent = ($daysUntilEnd <= 5 && $daysUntilEnd >= -30);
            $isExpired = ($daysUntilEnd < -30);
            $isActive = ($daysUntilEnd > 5);

            $currentPriceUsd = (float) $row->current_sale_price;
            $currentPriceBs = $rateBs > 0 ? round($currentPriceUsd * (float) $rateBs, 2) : 0;
            $currentPriceCop = $rateCop > 0 ? ceil($currentPriceUsd * (float) $rateCop / 100) * 100 : 0;

            // Formatear teléfono para WhatsApp (Quitar caracteres no numéricos)
            $cleanPhone = preg_replace("/[^0-9]/", "", (string) $row->client_phone);
            // Si empieza con 0 (ej: 0414), convertir a 58414 (código de Venezuela por defecto)
            if (str_starts_with($cleanPhone, "0")) {
                $cleanPhone = "58" . substr($cleanPhone, 1);
            } elseif (strlen($cleanPhone) === 10 && !str_starts_with($cleanPhone, "58")) {
                $cleanPhone = "58" . $cleanPhone;
            }

            $fullName = trim("{$row->client_name} {$row->client_last_name}");
            $treatmentDateFormatted = $treatmentEndDate->format("d/m/Y");
            $priceFormattedUsd = "$" . number_format($currentPriceUsd, 2);
            $priceFormattedBs = $currentPriceBs > 0 ? " (Bs. " . number_format($currentPriceBs, 2) . ")" : "";
            $priceFormattedCop = $currentPriceCop > 0 ? " / COP " . number_format($currentPriceCop, 0, ',', '.') : "";
            
            $whatsappMessage = "¡Hola, {$fullName}! 👋 Te saludamos de Farmacia Barrio Sucre 💚\n\n" .
                "Nos pasamos por aquí para recordarte que ya se acerca la fecha de renovar tu *{$row->product_name}* (estimado: {$treatmentDateFormatted}).\n\n" .
                "💵 Precio actual: *{$priceFormattedUsd}*{$priceFormattedBs}{$priceFormattedCop}\n" .
                "📦 ¡Te lo enviamos HOY mismo con DELIVERY GRATIS hasta tu puerta!\n\n" .
                "Cuidamos tu salud y economía en un solo corazón. ¿Te dejamos el pedido listo? Escríbenos y con gusto te lo llevamos. 🚚💨";

            $whatsappUrl = !empty($cleanPhone) 
                ? "https://wa.me/{$cleanPhone}?text=" . urlencode($whatsappMessage)
                : null;

            return [
                "client_id" => $row->client_id,
                "client_name" => $fullName,
                "identification" => "{$row->identification_type}{$row->identification}",
                "phone" => $row->client_phone,
                "email" => $row->client_email,
                "product_id" => $row->product_id,
                "product_name" => $row->product_name,
                "product_barcode" => $row->product_barcode,
                "laboratory_name" => $row->laboratory_name ?: "Sin Laboratorio",
                "last_order_date" => $lastDate->format("Y-m-d H:i:s"),
                "last_order_date_formatted" => $lastDate->format("d/m/Y"),
                "purchased_quantity" => (float) $row->purchased_quantity,
                "treatment_duration_days" => $durationDaysPerUnit,
                "total_treatment_days" => $totalTreatmentDays,
                "treatment_end_date" => $treatmentEndDate->format("Y-m-d"),
                "treatment_end_date_formatted" => $treatmentDateFormatted,
                "reminder_date" => $reminderDate->format("Y-m-d"),
                "days_until_end" => $daysUntilEnd,
                "is_urgent" => $isUrgent,
                "is_expired" => $isExpired,
                "is_active" => $isActive,
                "status_label" => $this->getStatusLabel($daysUntilEnd),
                "status_color" => $this->getStatusColor($daysUntilEnd),
                "price_usd" => $currentPriceUsd,
                "price_bs" => $currentPriceBs,
                "price_cop" => $currentPriceCop,
                "whatsapp_url" => $whatsappUrl,
                "whatsapp_message" => $whatsappMessage,
            ];
        });

        // Filtrar por estado solicitado si aplica
        if ($status === "urgent") {
            $processed = $processed->filter(fn($item) => $item["is_urgent"]);
        } elseif ($status === "expired") {
            $processed = $processed->filter(fn($item) => $item["is_expired"]);
        } elseif ($status === "active") {
            $processed = $processed->filter(fn($item) => $item["is_active"]);
        }

        // Ordenar: primero los que están por agotarse / más urgentes
        $sorted = $processed->sortBy("days_until_end")->values();

        // Paginación manual para devolver estructura estándar LengthAwarePaginator
        $total = $sorted->count();
        $items = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            "path" => $request->url(),
            "query" => $request->query(),
        ]);
    }

    /**
     * Obtener estadísticas y resumen de clientes crónicos.
     */
    public function getStats(): array
    {
        $allPatients = $this->getChronicPatients(new Request(["itemsPerPage" => 99999, "page" => 1]))->items();
        $collection = collect($allPatients);

        $totalPatients = $collection->pluck("client_id")->unique()->count();
        $totalTreatments = $collection->count();
        $urgentReminders = $collection->where("is_urgent", true)->count();
        $expiredTreatments = $collection->where("is_expired", true)->count();
        $activeTreatments = $collection->where("is_active", true)->count();
        $patientsWithPhone = $collection->filter(fn($p) => !empty($p["phone"]))->count();

        return [
            "total_patients" => $totalPatients,
            "total_treatments" => $totalTreatments,
            "urgent_reminders" => $urgentReminders,
            "expired_treatments" => $expiredTreatments,
            "active_treatments" => $activeTreatments,
            "patients_with_phone" => $patientsWithPhone,
        ];
    }

    private function getStatusLabel(int $daysUntilEnd): string
    {
        if ($daysUntilEnd > 5) {
            return "En tratamiento ({$daysUntilEnd} días restantes)";
        }
        if ($daysUntilEnd > 0) {
            return "Por agotarse en {$daysUntilEnd} " . ($daysUntilEnd === 1 ? "día" : "días");
        }
        if ($daysUntilEnd === 0) {
            return "Tratamiento culmina hoy";
        }
        $abs = abs($daysUntilEnd);
        return "Agotado hace {$abs} " . ($abs === 1 ? "día" : "días");
    }

    private function getStatusColor(int $daysUntilEnd): string
    {
        if ($daysUntilEnd > 5) {
            return "info";
        }
        if ($daysUntilEnd >= 0) {
            return "warning";
        }
        if ($daysUntilEnd >= -30) {
            return "error";
        }
        return "secondary";
    }
}
