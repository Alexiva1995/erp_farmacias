<?php

namespace App\Services\Promotions;

use App\Models\CategoryOffer;
use App\Models\Dish;
use App\Models\ExpirationOffer;
use App\Models\GeneralPromotion;
use App\Models\IndividualOffer;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionResolverService
{
    /**
     * Determina el canal de venta de la orden ('dine_in' o 'takeaway_delivery').
     */
    public function resolveOrderChannel(?Order $order): string
    {
        if (!$order) {
            return 'takeaway_delivery';
        }

        return !empty($order->dining_table_id) ? 'dine_in' : 'takeaway_delivery';
    }

    /**
     * Calcula el mejor descuento individual o por categoría para un Producto o Plato.
     *
     * @return array{pct: float, type: ?string, source: ?int, individual_offer: ?IndividualOffer}
     */
    public function resolveItemBaseDiscount(
        ?Product $product = null,
        ?Dish $dish = null,
        ?Carbon $moment = null,
        ?string $channel = null
    ): array {
        $now = $moment ?? Carbon::now();
        $result = ['pct' => 0.0, 'type' => null, 'source' => null, 'individual_offer' => null];

        $isExcluded = $product ? (bool) ($product->exclude_from_promotions ?? false) : (bool) ($dish->exclude_from_promotions ?? false);
        if ($isExcluded) {
            return $result;
        }

        $categoryId = $product ? $product->category_id : ($dish?->category_id ?? null);

        // 1. Oferta por Categoría (vigente en fecha, día, hora y canal)
        if ($categoryId && Schema::hasTable('category_offers')) {
            $categoryOffers = CategoryOffer::where('category_id', $categoryId)
                ->where('is_active', true)
                ->orderBy('discount_percentage', 'desc')
                ->get();

            foreach ($categoryOffers as $catOffer) {
                if ($catOffer->isCurrentlyValid($now, $channel)) {
                    $result = [
                        'pct' => (float) $catOffer->discount_percentage,
                        'type' => 'category',
                        'source' => $catOffer->id,
                        'individual_offer' => null,
                    ];
                    break;
                }
            }
        }

        // 2. Oferta Individual (Producto o Plato) — mayor prioridad si supera el % de categoría
        if (Schema::hasTable('individual_offers')) {
            $query = IndividualOffer::query()->orderBy('discount_percent', 'desc');
            if ($product) {
                $query->where('product_id', $product->id);
            } elseif ($dish && Schema::hasColumn('individual_offers', 'dish_id')) {
                $query->where('dish_id', $dish->id);
            } else {
                $query = null;
            }

            if ($query) {
                $individualOffers = $query->get();
                foreach ($individualOffers as $indOffer) {
                    if ($indOffer->isCurrentlyValid($now, $channel)) {
                        $result['individual_offer'] = $indOffer;
                        if ((float) $indOffer->discount_percent > $result['pct']) {
                            $result['pct'] = (float) $indOffer->discount_percent;
                            $result['type'] = 'individual';
                            $result['source'] = $indOffer->id;
                        }
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Obtiene las ofertas de caducidad activas excluyendo productos bloqueados.
     */
    public function getActiveExpirationOffersForProduct(int $productId): Collection
    {
        if (!Schema::hasTable('expiration_offers')) {
            return collect();
        }

        $offers = ExpirationOffer::where('is_active', true)
            ->orderBy('months_to_expiration', 'asc')
            ->get();

        if ($offers->isEmpty() || !Schema::hasTable('expiration_offer_excluded_products')) {
            return $offers;
        }

        $excludedOfferIds = DB::table('expiration_offer_excluded_products')
            ->where('product_id', $productId)
            ->pluck('expiration_offer_id')
            ->all();

        if (empty($excludedOfferIds)) {
            return $offers;
        }

        return $offers->reject(fn ($offer) => in_array($offer->id, $excludedOfferIds, true))->values();
    }

    /**
     * Filtra las promociones generales aplicables al momento, canal, monto mínimo y cupón de la orden.
     */
    public function getApplicableGeneralPromotions(Order $order, float $orderSubtotalUsd, ?Carbon $moment = null): Collection
    {
        if (!Schema::hasTable('general_promotions')) {
            return collect();
        }

        $now = $moment ?? Carbon::now();
        $channel = $this->resolveOrderChannel($order);
        $orderCoupon = strtoupper(trim((string) ($order->applied_coupon_code ?? '')));

        $query = GeneralPromotion::where('is_active', true);
        if (Schema::hasColumn('general_promotions', 'priority')) {
            $query->orderBy('priority', 'asc')->orderBy('id', 'asc');
        }

        return $query->get()->filter(function (GeneralPromotion $promo) use ($now, $channel, $orderSubtotalUsd, $orderCoupon) {
            if (!$promo->isCurrentlyValid($now, $channel)) {
                return false;
            }

            if (!empty($promo->min_order_amount) && $orderSubtotalUsd < (float) $promo->min_order_amount) {
                return false;
            }

            if (!empty($promo->coupon_code)) {
                $promoCoupon = strtoupper(trim((string) $promo->coupon_code));
                if ($orderCoupon === '' || $orderCoupon !== $promoCoupon) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Verifica si un ítem está excluido de promociones generales.
     */
    public function isDetailExcludedFromGeneralPromotions($detail): bool
    {
        if (!empty($detail->pack_id)) {
            return true;
        }

        if (in_array($detail->discount_type, ['expiration', 'individual', 'category'], true)) {
            return true;
        }

        if ($detail->product) {
            if (
                str_starts_with($detail->product->barcode ?? '', 'ZOOM-SERVICE')
                || str_starts_with($detail->product->name ?? '', 'SERVICIO ZOOM')
                || !empty($detail->product->exclude_from_promotions)
            ) {
                return true;
            }
        }

        if ($detail->dish && !empty($detail->dish->exclude_from_promotions)) {
            return true;
        }

        return false;
    }

    /**
     * Evalúa si el precio final tras descuento protege el costo del producto/plato.
     *
     * @return array{below_cost: bool, margin_pct: float, unit_cost: float, final_price: float}
     */
    public function checkMarginSafety(float $finalPriceUsd, float $unitCostUsd): array
    {
        $belowCost = $unitCostUsd > 0 && $finalPriceUsd < $unitCostUsd;
        $marginPct = $finalPriceUsd > 0
            ? round((($finalPriceUsd - $unitCostUsd) / $finalPriceUsd) * 100, 2)
            : 0.0;

        return [
            'below_cost' => $belowCost,
            'margin_pct' => $marginPct,
            'unit_cost' => round($unitCostUsd, 2),
            'final_price' => round($finalPriceUsd, 2),
        ];
    }

    /**
     * Calcula métricas accionables (usos, ventas con promo, margen bruto) para el Centro de Promociones.
     */
    public function getPromotionMetrics(): array
    {
        if (!Schema::hasTable('order_details') || !Schema::hasTable('orders')) {
            return [
                'summary' => ['total_uses' => 0, 'total_sales_usd' => 0, 'gross_margin_usd' => 0],
                'by_type' => [],
            ];
        }

        $rows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'Completed')
            ->where(function ($q) {
                $q->whereNotNull('order_details.discount_type')
                    ->orWhereNotNull('order_details.pack_id');
            })
            ->selectRaw("
                COALESCE(order_details.discount_type, CASE WHEN order_details.pack_id IS NOT NULL THEN 'pack' ELSE 'other' END) as promo_type,
                COUNT(DISTINCT order_details.order_id) as uses_count,
                COALESCE(SUM(order_details.quantity * order_details.unit_price_usd), 0) as sales_usd,
                COALESCE(SUM(order_details.quantity * (order_details.unit_price_usd - COALESCE(order_details.unit_cost, 0))), 0) as margin_usd
            ")
            ->groupBy('promo_type')
            ->get();

        $totalUses = 0;
        $totalSales = 0.0;
        $totalMargin = 0.0;
        $byType = [];

        foreach ($rows as $row) {
            $uses = (int) $row->uses_count;
            $sales = round((float) $row->sales_usd, 2);
            $margin = round((float) $row->margin_usd, 2);
            $totalUses += $uses;
            $totalSales += $sales;
            $totalMargin += $margin;

            $byType[$row->promo_type] = [
                'uses_count' => $uses,
                'sales_usd' => $sales,
                'margin_usd' => $margin,
            ];
        }

        return [
            'summary' => [
                'total_uses' => $totalUses,
                'total_sales_usd' => round($totalSales, 2),
                'gross_margin_usd' => round($totalMargin, 2),
            ],
            'by_type' => $byType,
        ];
    }
}
