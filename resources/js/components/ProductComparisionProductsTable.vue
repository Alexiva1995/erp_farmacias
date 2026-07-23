<script setup>
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { computed, reactive, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  quantityErrors: { type: Object, default: () => ({}) },
  enableUsdAmountCol: { type: Boolean, default: false },
  enableDiscountCol: { type: Boolean, default: false },
  // Props de búsqueda
  searchQuery: { type: String, default: "" },
  isStrictSearch: { type: Boolean, default: false },
  // Producto seleccionado desde la tabla inferior (para calcular diferencia de precio)
  selectedProduct: { type: Object, default: null },
  sortBy: { type: Array, default: () => [] },
  // Filtros avanzados
  laboratories: { type: Array, default: () => [] },
  selectedLaboratory: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  selectedOrigin: [Number, String, null],
  suppliers: { type: Array, default: () => [] },
  selectedSupplier: [Number, String, null],
  // Switches
  enableDiscounts: Boolean,
  enableUsdAmountCol: Boolean,
  enableDiscountCol: Boolean,
});

const emit = defineEmits([
  "update:options",
  "send-product",
  "update:searchQuery",
  "update:isStrictSearch",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:selectedOrigin",
  "update:selectedSupplier",
  "update:enableDiscounts",
  "update:enableUsdAmountCol",
  "update:enableDiscountCol",
  "update:sortBy",
  "sync-apis",
]);

const { mdAndUp } = useDisplay();
const localSearch = ref(props.searchQuery);
const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedLaboratory?.length > 0;
});

// Sincronizar localSearch si cambia desde fuera
watch(
  () => props.searchQuery,
  (newVal) => {
    if (newVal !== localSearch.value) {
      localSearch.value = newVal;
    }
  },
);

const rows = reactive({});
const getQty = (id) => {
  if (id in rows) return rows[id];
  // Si no se ha modificado manualmente, traer la sugerencia de la IA si existe un producto seleccionado
  if (props.selectedProduct && props.selectedProduct.solicitar) {
    const suggested = roundIaAnalysis(props.selectedProduct.solicitar);
    return suggested > 0 ? suggested : 1;
  }
  return 1;
};

const formatBs = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount) + " Bs."
  );
};
const formatUsd = (amount) => {
  const num = parseFloat(amount);
  if (isNaN(num)) return "0.00";
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
};

const getPriceDiff = (item) => {
  if (!props.selectedProduct) return null;
  const currentCost = parseFloat(props.selectedProduct.current_unit_cost ?? props.selectedProduct.unit_cost ?? 0);
  if (!currentCost || currentCost === 0) return null;

  const supplierCost = parseFloat(
    (props.enableDiscounts && parseFloat(item.final_cost_usd) > 0) 
      ? item.final_cost_usd 
      : item.unit_cost_usd
  );
  if (!supplierCost || isNaN(supplierCost)) return null;

  const diff = ((currentCost - supplierCost) / currentCost) * 100;
  const absDiff = Math.abs(diff).toFixed(1);

  if (diff > 0.5) {
    return { diff, label: `${absDiff}% más barato`, color: "success", icon: 'tabler-trending-down' };
  } else if (diff < -0.5) {
    return { diff, label: `${absDiff}% más caro`, color: "error", icon: 'tabler-trending-up' };
  }
  return { diff: 0, label: "Precio igual", color: "warning", icon: 'tabler-minus' };
};

const isProcessing = ref({});

const onActionClick = (item) => {
  const qty = getQty(item.id);
  isProcessing.value[item.id] = true;
  emit('send-product', { id: item.id, quantity: qty, item: item });
};

const allHeaders = [
  { title: "PRODUCTO / PROVEEDOR", key: "name", sortable: true, width: "175px" },
  { title: "NUESTRO COSTO", key: "our_cost", sortable: false, width: "90px", align: 'end' },
  { title: "COSTO USD", key: "unit_cost_usd", sortable: true, width: "90px", align: 'end' },
  { title: "AHORRO", key: "price_diff", sortable: false, width: "110px", align: 'center' },
  { title: "ACCIÓN", key: "actions", sortable: false, width: "110px", align: "end" },
];

const headers = computed(() =>
  allHeaders.filter((h) => {
    if (h.key === "price_diff" && !props.selectedProduct) return false;
    if (props.enableUsdAmountCol && h.key.includes("bs")) return false;
    if (!props.enableUsdAmountCol && h.key.includes("usd")) return false;
    if (h.key.includes("final_cost") && !props.enableDiscountCol) return false;
    return true;
  }),
);
</script>

<template>
  <div class="comparator-products-container">
    <!-- Header de Sección y Búsqueda (Estandarizado) -->
    <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-2">
          <!-- Título/Icono -->
          <div class="d-flex align-center gap-2 mr-4">
            <VIcon icon="tabler-building-store" color="primary" size="20" />
            <span class="text-subtitle-2 font-weight-bold text-uppercase d-none d-sm-inline">Catálogo Proveedores</span>
          </div>

          <!-- Buscador Principal -->
          <VCol cols="12" sm="5" md="4" lg="4">
            <VTextField
              :model-value="localSearch"
              placeholder="Buscar producto o proveedor..."
              clearable
              density="compact"
              hide-details
              prepend-inner-icon="tabler-search"
              @update:model-value="$emit('update:searchQuery', $event)"
            />
          </VCol>

          <VSpacer />

          <!-- Acciones (Solo Iconos) -->
          <div class="d-flex align-center gap-1">
            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
              @click="toggleAdvancedFilters"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
              <VBadge
                v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
                color="error"
                dot
                offset-x="2"
                offset-y="-2"
              />
            </VBtn>

            <!-- Sincronizar APIs (Movido desde el panel avanzado) -->
            <VBtn
              icon
              variant="tonal"
              color="info"
              size="38"
              class="rounded-circle shadow-sm"
              @click="emit('sync-apis')"
            >
              <VIcon icon="tabler-cloud-download" size="20" />
              <VTooltip activator="parent" location="top">Actualizar APIs</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="$emit('update:searchQuery', '')"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzados -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible" class="pt-4 mt-4 border-t">
            <VRow dense>
              <!-- Selecciones Principales -->
              <VCol cols="12" md="6">
                <VAutocomplete
                  :model-value="props.selectedLaboratory"
                  :items="props.laboratories"
                  placeholder="Laboratorios"
                  item-title="name"
                  item-value="id"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-flask"
                  @update:model-value="emit('update:selectedLaboratory', $event)"
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <VAutocomplete
                  :model-value="props.selectedGroup"
                  :items="props.groups"
                  placeholder="Grupos"
                  item-title="name"
                  item-value="id"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-tags"
                  @update:model-value="emit('update:selectedGroup', $event)"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VSelect
                  :model-value="props.selectedOrigin"
                  :items="props.origins"
                  placeholder="Origen"
                  item-title="name"
                  item-value="id"
                  clearable
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-map-pin"
                  @update:model-value="emit('update:selectedOrigin', $event)"
                />
              </VCol>

              <VCol cols="12" md="6">
                <VAutocomplete
                  :model-value="props.selectedSupplier"
                  :items="props.suppliers"
                  placeholder="Proveedor Específico"
                  item-title="name"
                  item-value="id"
                  clearable
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-building-warehouse"
                  @update:model-value="emit('update:selectedSupplier', $event)"
                />
              </VCol>

              <!-- Switches -->
              <VCol cols="12" class="mt-2">
                <div class="d-flex flex-wrap ga-4 align-center">
                  <VSwitch
                    :model-value="props.enableDiscounts"
                    label="Descuentos"
                    color="primary"
                    density="compact"
                    hide-details
                    @update:model-value="emit('update:enableDiscounts', $event)"
                  />
                  <VSwitch
                    :model-value="props.enableUsdAmountCol"
                    label="Ver Divisas"
                    color="success"
                    density="compact"
                    hide-details
                    @update:model-value="emit('update:enableUsdAmountCol', $event)"
                  />
                  <VSwitch
                    :model-value="props.enableDiscountCol"
                    label="Ver % Desc."
                    color="info"
                    density="compact"
                    hide-details
                    @update:model-value="emit('update:enableDiscountCol', $event)"
                  />
                  <VSwitch
                    :model-value="props.isStrictSearch"
                    label="Estricta"
                    color="warning"
                    density="compact"
                    hide-details
                    @update:model-value="emit('update:isStrictSearch', $event)"
                  />
                  
                  <VSpacer />
                </div>
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Alerta de Comparación -->
    <VAlert
      v-if="selectedProduct"
      type="info"
      variant="tonal"
      density="compact"
      class="mb-4 border-0"
      rounded="lg"
      icon="tabler-arrows-exchange"
    >
      <div class="text-xs">
        Comparando: <strong>{{ selectedProduct.name }}</strong>
        <span v-if="selectedProduct.current_unit_cost" class="opacity-70 ml-1">
          (${{ parseFloat(selectedProduct.current_unit_cost).toFixed(2) }})
        </span>
      </div>
    </VAlert>

    <!-- Tabla Principal (Unified VCard) -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Desktop -->
      <div v-if="mdAndUp" class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.products"
          :items-length="props.totalProducts"
          :loading="props.loading"
          :sort-by="props.sortBy"
          @update:sort-by="emit('update:sortBy', $event)"
          hover
          density="compact"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #item.name="{ item }">
            <div class="d-flex flex-column py-2" style="min-inline-size: 175px; white-space: normal !important;">
                <span class="text-xs font-weight-black text-high-emphasis text-uppercase leading-tight mb-1" :title="item.name">
                  {{ item.name.toUpperCase() }}
                </span>
                
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs font-weight-bold">
                  <span v-if="item.laboratory_name" class="text-primary uppercase">
                    {{ item.laboratory_name.toUpperCase() }}
                  </span>
                  <span v-if="item.laboratory_name && item.supplier_name" class="text-disabled">|</span>
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-building-warehouse" size="10" class="text-disabled" />
                    <span class="text-disabled uppercase">{{ item.supplier_name }}</span>
                  </div>
                </div>
            </div>
          </template>

          <template #item.our_cost>
            <span v-if="selectedProduct" class="text-sm font-weight-medium text-disabled">
              ${{ formatUsd(selectedProduct.current_unit_cost ?? selectedProduct.unit_cost ?? 0) }}
            </span>
          </template>

          <template #item.price_diff="{ item }">
            <template v-if="getPriceDiff(item)">
              <VChip
                :color="getPriceDiff(item).color"
                size="x-small"
                variant="tonal"
                :prepend-icon="getPriceDiff(item).icon"
                class="font-weight-bold"
              >
                {{ getPriceDiff(item).label }}
              </VChip>
            </template>
            <span v-else class="text-disabled text-xs">—</span>
          </template>

          <template #item.unit_cost_usd="{ item }">
            <div class="d-flex flex-column align-end">
              <template v-if="props.enableDiscounts && parseFloat(item.final_cost_usd) > 0">
                <span class="text-sm font-weight-black text-primary">
                  ${{ formatUsd(item.final_cost_usd) }}
                </span>
              </template>
              <template v-else>
                <span class="text-sm font-weight-bold text-high-emphasis">
                  ${{ formatUsd(item.unit_cost_usd) }}
                </span>
              </template>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end ga-2">
              <VTextField
                :model-value="getQty(item.id)"
                @update:model-value="(val) => (rows[item.id] = Number(val))"
                type="number"
                variant="outlined"
                density="compact"
                hide-details
                class="compact-qty-input"
                :error="!!quantityErrors[item.id]"
              />
              <VBtn
                icon="tabler-shopping-cart-plus"
                variant="flat"
                color="primary"
                size="small"
                class="rounded-circle shadow-sm"
                :loading="isProcessing[item.id]"
                @click="onActionClick(item)"
              />
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-disabled text-sm">Use el buscador para filtrar productos</div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div v-else class="d-md-none pa-4 bg-var-theme-background">
        <div v-if="loading" class="d-flex justify-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        <div v-else-if="props.products.length === 0" class="text-center py-8 text-disabled text-sm">
          No hay productos disponibles
        </div>
        <div v-else class="d-flex flex-column gap-3">
          <VCard
            v-for="item in props.products"
            :key="item.id"
            class="mobile-card border shadow-none"
          >
            <VCardText class="pa-4">
              <div class="mb-3">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase d-block mb-1 leading-tight">
                  {{ item.name }}
                </span>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs font-weight-bold">
                  <span v-if="item.laboratory_name" class="text-primary uppercase">
                    {{ item.laboratory_name.toUpperCase() }}
                  </span>
                  <span v-if="item.laboratory_name && item.supplier_name" class="text-disabled">|</span>
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-building-warehouse" size="10" class="text-disabled" />
                    <span class="text-disabled uppercase">{{ item.supplier_name }}</span>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-space-between align-center mb-3">
                <div class="d-flex flex-column">
                  <span class="text-xs text-disabled uppercase font-weight-bold">COTP</span>
                  <span class="text-sm font-weight-bold" :class="(props.enableDiscounts && parseFloat(item.final_cost_usd) > 0) ? 'text-primary font-weight-black' : ''">
                    ${{ formatUsd(props.enableDiscounts && parseFloat(item.final_cost_usd) > 0 ? item.final_cost_usd : item.unit_cost_usd) }}
                  </span>
                </div>
              </div>

              <div v-if="getPriceDiff(item)" class="mb-3">
                <VChip
                  :color="getPriceDiff(item).color"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-bold w-full justify-center"
                  :prepend-icon="getPriceDiff(item).icon"
                >
                  {{ getPriceDiff(item).label }}
                </VChip>
              </div>

              <VDivider class="mb-3" />

              <div class="d-flex align-center ga-2">
                <VTextField
                  :model-value="getQty(item.id)"
                  @update:model-value="(val) => (rows[item.id] = Number(val))"
                  type="number"
                  variant="outlined"
                  density="compact"
                  hide-details
                  label="Cant."
                  class="flex-grow-1"
                />
                <VBtn
                  color="primary"
                  icon="tabler-shopping-cart-plus"
                  size="small"
                  @click="emit('send-product', { id: item.id, quantity: getQty(item.id), item: item })"
                />
              </div>
            </VCardText>
          </VCard>

          <VPagination
            v-model="props.page"
            :length="Math.ceil(totalProducts / itemsPerPage)"
            :total-visible="3"
            density="compact"
            @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: itemsPerPage })"
          />
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs { font-size: 0.75rem !important; }

.compact-qty-input {
  max-width: 60px;
}

.compact-qty-input :deep(.v-field__input) {
  padding-block: 4px;
  padding-inline: 4px;
  text-align: center;
  font-size: 0.85rem;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.mobile-card {
  transition: all 0.2s ease;
}

.ga-2 { gap: 8px !important; }
</style>
