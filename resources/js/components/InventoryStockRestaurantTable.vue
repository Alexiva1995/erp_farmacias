<script setup>
import { computed } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  products: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, default: 0 },
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
  sortBy: { type: [String, Array], default: () => [] },
  orderBy: { type: String, default: "asc" },
  days: { type: [Number, String], default: 30 },
});

const emit = defineEmits(["update:options"]);
const brandingStore = useBrandingStore();

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  const key = Array.isArray(props.sortBy) ? props.sortBy[0] : props.sortBy;
  return key ? [{ key, order: props.orderBy || "asc" }] : [];
});

const headers = [
  { title: "ID", key: "id", sortable: true, cellClass: "font-weight-black text-primary", width: "70px" },
  { title: "INSUMO / MARCA", key: "name", sortable: true, width: "320px" },
  { title: "COSTO LOGÍSTICO", key: "unit_cost", sortable: true, align: "end" },
  { title: "CONSUMO", key: "total_sold_completed", sortable: true, align: "center" },
  { title: "STOCK ACTUAL", key: "lote_quantity", sortable: true, align: "center" },
  { title: "DEMANDA (PROM.)", key: "promedio_calculado", sortable: true, align: "center" },
  { title: "COBERTURA", key: "coverage", sortable: false, align: "center" },
  { title: "REABASTECER (A PEDIR)", key: "diferencia_product", sortable: true, align: "center" },
];

const formatMoney = (val) => {
  const num = Number(val);
  if (isNaN(num)) return "—";
  const currency = brandingStore.settings?.default_currency || "COP";
  return formatCurrency(num, currency);
};

// Formatea el stock en 3 niveles: Compra -> Almacén -> Receta
const getLogisticsStockBreakdown = (item) => {
  const stock = Number(item.lote_quantity ?? item.stock ?? 0);
  const presentation = Number(item.presentation) || 1;
  const unit = item.unit_of_measure || "und";
  const packName = item.pack_unit_name || "paq";
  const purchaseUnit = item.purchase_unit_name || "Display";
  const innerUnits = Number(item.units_per_inner_pack) || 1;

  // En base de datos el stock de lotes representa paquetes/almacén
  const totalPackages = stock;
  const totalRecipeUnits = Math.round(stock * presentation);

  if (innerUnits > 1) {
    const fullPurchaseUnits = Math.floor(totalPackages / innerUnits);
    const remainderPacks = Number((totalPackages % innerUnits).toFixed(2));

    let mainText = "";
    if (fullPurchaseUnits > 0 && remainderPacks > 0) {
      mainText = `${fullPurchaseUnits} ${purchaseUnit} + ${remainderPacks} ${packName}`;
    } else if (fullPurchaseUnits > 0) {
      mainText = `${fullPurchaseUnits} ${purchaseUnit}${fullPurchaseUnits === 1 ? '' : 's'}`;
    } else {
      mainText = `${remainderPacks} ${packName}`;
    }

    const subText = `${totalPackages} ${packName}s · ${totalRecipeUnits.toLocaleString("es-CO")} ${unit}`;
    return { mainText, subText, totalPackages, isWarning: stock <= 0, isLow: stock > 0 && fullPurchaseUnits < 1 };
  }

  // Sin unidad de empaque mayorista (1 nivel compra = 1 paquete)
  const formattedPacks = stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
  return {
    mainText: `${formattedPacks} ${packName}${stock === 1 ? '' : 's'}`,
    subText: presentation > 1 ? `${totalRecipeUnits.toLocaleString("es-CO")} ${unit} en cocina` : null,
    totalPackages,
    isWarning: stock <= 0,
    isLow: stock > 0 && stock < 2,
  };
};

// Costo de compra mayorista vs costo por almacén/receta
const getLogisticsCostBreakdown = (item) => {
  const packCost = Number(item.unit_cost || 0);
  const innerUnits = Number(item.units_per_inner_pack) || 1;
  const purchaseUnit = item.purchase_unit_name || "Display";
  const packName = item.pack_unit_name || "Paq";
  const presentation = Number(item.presentation) || 1;
  const unit = item.unit_of_measure || "und";

  if (innerUnits > 1) {
    const purchaseCost = item.purchase_cost ? Number(item.purchase_cost) : packCost * innerUnits;
    return {
      mainCost: `${formatMoney(purchaseCost)}`,
      mainLabel: `/ ${purchaseUnit}`,
      subCost: `${formatMoney(packCost)} / ${packName}`,
      recipeCost: presentation > 1 ? `${formatMoney(packCost / presentation)} / ${unit}` : null,
    };
  }

  return {
    mainCost: `${formatMoney(packCost)}`,
    mainLabel: `/ ${packName}`,
    subCost: presentation > 1 ? `${formatMoney(packCost / presentation)} / ${unit}` : null,
    recipeCost: null,
  };
};

// Días de cobertura de inventario actual
const getCoverageInfo = (item) => {
  const stock = Number(item.lote_quantity ?? 0);
  const promedioMensual = Number(item.promedio_calculado ?? 0);
  const daysWindow = Number(props.days) || 30;

  if (stock <= 0) {
    return { text: "0 días", color: "error", icon: "tabler-alert-octagon", label: "Agotado" };
  }

  const dailyDemand = promedioMensual > 0 ? (promedioMensual / daysWindow) : 0;
  if (dailyDemand <= 0) {
    return { text: "+90 días", color: "secondary", icon: "tabler-clock", label: "Sin demanda reciente" };
  }

  const daysCoverage = Math.round(stock / dailyDemand);
  if (daysCoverage <= 3) {
    return { text: `${daysCoverage}d`, color: "error", icon: "tabler-alert-triangle", label: "Crítico (<3d)" };
  }
  if (daysCoverage <= 7) {
    return { text: `${daysCoverage}d`, color: "warning", icon: "tabler-clock-exclamation", label: "Alerta (<7d)" };
  }
  if (daysCoverage <= 30) {
    return { text: `${daysCoverage}d`, color: "success", icon: "tabler-circle-check", label: "Óptimo" };
  }
  return { text: `${daysCoverage}d`, color: "info", icon: "tabler-box", label: "Sobre-stock" };
};

// Cálculo de unidades de reabastecimiento en unidades de compra
const getReplenishmentSuggestion = (item) => {
  const diff = Number(item.diferencia_product ?? 0);
  const innerUnits = Number(item.units_per_inner_pack) || 1;
  const purchaseUnit = item.purchase_unit_name || "Display";
  const packName = item.pack_unit_name || "paq";
  const packCost = Number(item.unit_cost || 0);

  // Superávit (stock suficiente)
  if (diff >= 0) {
    if (innerUnits > 1) {
      const displays = (diff / innerUnits).toFixed(1);
      return {
        isDeficit: false,
        text: `+${diff} ${packName}`,
        subText: `+${displays} ${purchaseUnit}`,
        color: "success",
      };
    }
    return {
      isDeficit: false,
      text: `+${diff} ${packName}`,
      subText: "Stock cubierto",
      color: "success",
    };
  }

  // Déficit (requiere compra)
  const deficitPacks = Math.abs(diff);
  if (innerUnits > 1) {
    const purchaseUnitsToOrder = Math.ceil(deficitPacks / innerUnits);
    const totalPacksOrdered = purchaseUnitsToOrder * innerUnits;
    const purchaseCost = item.purchase_cost ? Number(item.purchase_cost) : packCost * innerUnits;
    const estimatedCost = purchaseUnitsToOrder * purchaseCost;

    return {
      isDeficit: true,
      text: `PEDIR ${purchaseUnitsToOrder} ${purchaseUnit}${purchaseUnitsToOrder === 1 ? '' : 's'}`,
      subText: `Faltan ${deficitPacks} ${packName} (Recibes ${totalPacksOrdered})`,
      costText: estimatedCost > 0 ? `Est. ${formatMoney(estimatedCost)}` : null,
      color: "error",
    };
  }

  // Sin empaque mayorista: se piden paquetes exactos
  const packsToOrder = Math.ceil(deficitPacks);
  const estimatedCost = packsToOrder * packCost;
  return {
    isDeficit: true,
    text: `PEDIR ${packsToOrder} ${packName}${packsToOrder === 1 ? '' : 's'}`,
    subText: `Faltan ${deficitPacks} ${packName}`,
    costText: estimatedCost > 0 ? `Est. ${formatMoney(estimatedCost)}` : null,
    color: "error",
  };
};
</script>

<template>
  <VCard variant="flat" border class="restaurant-stock-table">
    <!-- Desktop Table -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        :sort-by="sortByModel"
        class="text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- ID con enlace a trazabilidad -->
        <template #item.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            #{{ item.id }}
          </a>
        </template>

        <!-- Insumo y equivalencia logística -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2 truncate" style="max-inline-size: 340px;">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase truncate">
              {{ item.name || 'N/A' }}
            </span>
            <div class="d-flex align-center gap-1 text-super-xs mt-1 text-disabled flex-wrap">
              <span class="text-primary font-weight-black text-uppercase">
                {{ item.laboratory?.name || 'S/M' }}
              </span>
              <span>•</span>
              <span class="font-weight-medium">
                1 {{ item.purchase_unit_name || 'Display' }} = {{ item.units_per_inner_pack || 1 }} {{ item.pack_unit_name || 'paq' }}
                <template v-if="Number(item.presentation) > 1">
                  = {{ ((Number(item.units_per_inner_pack) || 1) * (Number(item.presentation) || 1)).toLocaleString() }} {{ item.unit_of_measure || 'und' }}
                </template>
              </span>
            </div>
          </div>
        </template>

        <!-- Costo Logístico (Compra -> Almacén) -->
        <template #item.unit_cost="{ item }">
          <div class="d-flex flex-column align-end py-1">
            <span class="font-weight-black text-sm text-high-emphasis">
              {{ getLogisticsCostBreakdown(item).mainCost }}
              <span class="text-super-xs text-disabled">{{ getLogisticsCostBreakdown(item).mainLabel }}</span>
            </span>
            <span v-if="getLogisticsCostBreakdown(item).subCost" class="text-super-xs text-disabled">
              {{ getLogisticsCostBreakdown(item).subCost }}
            </span>
            <span v-if="getLogisticsCostBreakdown(item).recipeCost" class="text-super-xs text-success font-weight-bold">
              {{ getLogisticsCostBreakdown(item).recipeCost }}
            </span>
          </div>
        </template>

        <!-- Consumo en período -->
        <template #item.total_sold_completed="{ item }">
          <div class="d-flex flex-column align-center py-1">
            <span class="font-weight-black text-sm text-high-emphasis">
              {{ Math.round(Number(item.total_sold_completed || 0)) }} {{ item.pack_unit_name || 'paq' }}
            </span>
            <span v-if="Number(item.units_per_inner_pack) > 1" class="text-super-xs text-disabled">
              {{ (Number(item.total_sold_completed || 0) / Number(item.units_per_inner_pack)).toFixed(1) }} {{ item.purchase_unit_name || 'disp' }}
            </span>
          </div>
        </template>

        <!-- Stock Actual en 3 Niveles -->
        <template #item.lote_quantity="{ item }">
          <div class="d-flex flex-column align-center py-1">
            <VChip
              :color="getLogisticsStockBreakdown(item).isWarning ? 'error' : (getLogisticsStockBreakdown(item).isLow ? 'warning' : 'success')"
              size="small"
              variant="flat"
              class="font-weight-black px-2 shadow-xs"
            >
              {{ getLogisticsStockBreakdown(item).mainText }}
            </VChip>
            <span v-if="getLogisticsStockBreakdown(item).subText" class="text-super-xs text-disabled mt-0-5 font-weight-bold">
              {{ getLogisticsStockBreakdown(item).subText }}
            </span>
          </div>
        </template>

        <!-- Demanda / Promedio Calculado -->
        <template #item.promedio_calculado="{ item }">
          <div class="d-flex flex-column align-center py-1">
            <span class="font-weight-black text-sm text-medium-emphasis">
              {{ parseFloat(item.promedio_calculado || 0).toFixed(1) }} {{ item.pack_unit_name || 'paq' }}
            </span>
            <span v-if="Number(item.units_per_inner_pack) > 1" class="text-super-xs text-disabled">
              {{ (Number(item.promedio_calculado || 0) / Number(item.units_per_inner_pack)).toFixed(1) }} {{ item.purchase_unit_name || 'disp' }}
            </span>
          </div>
        </template>

        <!-- Cobertura de días -->
        <template #item.coverage="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VChip
              :color="getCoverageInfo(item).color"
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              <VIcon start :icon="getCoverageInfo(item).icon" size="13" />
              {{ getCoverageInfo(item).text }}
              <VTooltip activator="parent" location="top">
                {{ getCoverageInfo(item).label }}
              </VTooltip>
            </VChip>
          </div>
        </template>

        <!-- Reabastecer (A Pedir al Proveedor) -->
        <template #item.diferencia_product="{ item }">
          <div class="d-flex flex-column align-center py-1">
            <VChip
              :color="getReplenishmentSuggestion(item).color"
              size="small"
              :variant="getReplenishmentSuggestion(item).isDeficit ? 'flat' : 'tonal'"
              class="font-weight-black px-2.5 shadow-xs"
            >
              {{ getReplenishmentSuggestion(item).text }}
            </VChip>
            <span class="text-super-xs font-weight-bold mt-0-5" :class="getReplenishmentSuggestion(item).isDeficit ? 'text-error' : 'text-disabled'">
              {{ getReplenishmentSuggestion(item).subText }}
            </span>
            <span v-if="getReplenishmentSuggestion(item).costText" class="text-super-xs text-disabled">
              {{ getReplenishmentSuggestion(item).costText }}
            </span>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista Móvil (Tarjetas de Reabastecimiento) -->
    <div class="d-block d-md-none pa-2 bg-var-theme-background-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

      <div v-if="props.products.length === 0 && !props.loading" class="text-center py-12 text-disabled border rounded-lg bg-surface mx-1">
        <VIcon icon="tabler-package-off" size="48" class="mb-2" />
        <p>No se encontraron insumos.</p>
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-2 overflow-hidden bg-surface shadow-xs rounded-xl pa-3"
        >
          <!-- Header Tarjeta -->
          <div class="d-flex justify-space-between align-start mb-2">
            <div class="d-flex flex-column flex-grow-1 min-width-0">
              <div class="d-flex align-center gap-1">
                <a :href="'/inventory/traceability?q=' + item.id" target="_blank" class="text-xs font-weight-black text-primary text-decoration-none">
                  #{{ item.id }}
                </a>
                <span class="text-sm font-weight-black text-uppercase text-high-emphasis truncate">
                  {{ item.name }}
                </span>
              </div>
              <span class="text-super-xs text-disabled text-uppercase">
                {{ item.laboratory?.name || 'S/M' }} • 1 {{ item.purchase_unit_name || 'Disp' }} = {{ item.units_per_inner_pack || 1 }} {{ item.pack_unit_name || 'paq' }}
              </span>
            </div>
            <VChip :color="getCoverageInfo(item).color" size="x-small" variant="tonal" class="font-weight-black flex-shrink-0">
              {{ getCoverageInfo(item).text }}
            </VChip>
          </div>

          <!-- Métricas Resumidas -->
          <div class="d-flex align-center justify-space-between bg-light pa-2 rounded-lg border mb-2 text-xs">
            <div class="text-center flex-1">
              <span class="text-super-xs text-disabled d-block uppercase font-weight-bold">Stock</span>
              <span class="font-weight-black text-primary">{{ getLogisticsStockBreakdown(item).mainText }}</span>
            </div>
            <VDivider vertical class="mx-1" />
            <div class="text-center flex-1">
              <span class="text-super-xs text-disabled d-block uppercase font-weight-bold">Consumo</span>
              <span class="font-weight-bold text-high-emphasis">{{ Math.round(Number(item.total_sold_completed || 0)) }} {{ item.pack_unit_name || 'paq' }}</span>
            </div>
            <VDivider vertical class="mx-1" />
            <div class="text-center flex-1">
              <span class="text-super-xs text-disabled d-block uppercase font-weight-bold">Costo Compra</span>
              <span class="font-weight-bold text-high-emphasis">{{ getLogisticsCostBreakdown(item).mainCost }}</span>
            </div>
          </div>

          <!-- Acción de Reabastecimiento -->
          <div class="d-flex align-center justify-space-between pt-1">
            <span class="text-super-xs font-weight-bold text-disabled">Reabastecimiento sugerido:</span>
            <VChip
              :color="getReplenishmentSuggestion(item).color"
              size="x-small"
              :variant="getReplenishmentSuggestion(item).isDeficit ? 'flat' : 'tonal'"
              class="font-weight-black"
            >
              {{ getReplenishmentSuggestion(item).text }}
            </VChip>
          </div>
        </VCard>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.68rem !important;
  line-height: normal;
}
.shadow-xs {
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}
.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
