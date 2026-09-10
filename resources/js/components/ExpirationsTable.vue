<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";
import { formatDateSimple } from "@/utils/formatters";
import { computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { useAbility } from "@casl/vue";

const { can } = useAbility();
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings?.business_type === "restaurant");

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
  lots: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    required: true,
  },
  totalLots: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
  page: {
    type: Number,
    required: true,
  },
  sortBy: {
    type: String,
    default: "expiration_date",
  },
  orderBy: {
    type: String,
    default: "asc",
  },
});

const emit = defineEmits([
  "update:modelValue",
  "update:options",
  "expire-lot",
]);

const headers = computed(() => [
  { 
    title: "ID", 
    key: "product.id", 
    sortable: true,
    cellClass: "font-weight-black text-primary",
  },
  { title: "PRODUCTO", key: "product.name", sortable: true, width: "35%" },
  { title: "# LOTE", key: "lot_number", sortable: true },
  { title: "EXP.", key: "expiration_date", sortable: true },
  { title: "STOCK", key: "quantity", sortable: true, align: "end" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
]);

const selected = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  return [{ key: props.sortBy, order: props.orderBy || "asc" }];
});

const getExpirationColor = (dateString) => {
  if (!dateString) return "text-disabled";
  const expDate = new Date(dateString);
  const today = new Date();
  const diffTime = expDate - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays <= 0) return "text-error font-weight-black";
  if (diffDays <= 30) return "text-error font-weight-bold";
  if (diffDays <= 90) return "text-warning font-weight-bold";
  return "text-medium-emphasis";
};

const formatStock = (quantity) => {
  const stock = Number(quantity ?? 0);
  return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-sm-block">
      <VDataTableServer
        v-model="selected"
        :show-select="can('manage', 'admin') || can('manage', 'supervisor')"
        item-value="id"
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.lots"
        :items-length="props.totalLots"
        :loading="props.loading"
        :sort-by="sortByModel"
        class="text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="¡Todo al Día!"
            message="No se encontraron productos por caducar con los filtros aplicados."
            icon="tabler-circle-check"
          />
        </template>

        <!-- ID con enlace a trazabilidad sin # -->
        <template #item.product.id="{ item }">
          <a
            :href="'/inventory/traceability?q=' + item.product?.id"
            target="_blank"
            class="text-decoration-none font-weight-black text-primary"
          >
            {{ item.product?.id }}
          </a>
        </template>

        <!-- PRODUCTO: idéntico a inventario -->
        <template #item.product.name="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <div class="d-flex flex-column min-width-0">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate product-title-max"
                :class="{ 
                  'text-warning': item.product?.psychotropic == 1 || item.product?.psychotropic === true 
                }"
                :title="item.product?.name"
              >
                {{ item.product?.name?.toUpperCase() || "—" }}
                <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-xs text-disabled"> (G)</span>
                <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-xs text-disabled"> (COL)</span>
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span v-if="!isRestaurant" class="text-disabled truncate active-ingredient-max">
                  {{ item.product?.active_ingredient || item.product?.presentation || "Sin Especificación" }}
                </span>
                <span v-else class="text-disabled truncate active-ingredient-max">
                  {{ item.product?.presentation || "S/P" }}{{ item.product?.unit_of_measure ? ` (${item.product?.unit_of_measure})` : '' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate lab-name-max">
                  {{ item.product?.laboratory?.name || 'S/L' }}
                </span>
              </div>
              <div v-if="item.has_overstock_risk" class="mt-1">
                <VChip
                  size="x-small"
                  color="error"
                  variant="tonal"
                  prepend-icon="tabler-clock-exclamation"
                  class="font-weight-black px-1.5 text-super-xs"
                >
                  {{ item.risk_label || 'Sobrestock en Riesgo' }}
                </VChip>
              </div>
            </div>
          </div>
        </template>

        <!-- # LOTE -->
        <template #item.lot_number="{ item }">
          <span class="text-xs font-weight-black text-high-emphasis">{{ item.lot_number || "—" }}</span>
        </template>

        <!-- EXP. / VENCIMIENTO -->
        <template #item.expiration_date="{ item }">
          <span class="text-xs font-weight-medium" :class="getExpirationColor(item.expiration_date)">
            {{ formatDateSimple(item.expiration_date) }}
          </span>
        </template>

        <!-- STOCK -->
        <template #item.quantity="{ item }">
          <div class="text-end">
            <VChip
              :color="(item.quantity ?? 0) > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ formatStock(item.quantity) }}
            </VChip>
          </div>
        </template>

        <!-- ACCIONES -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <VTooltip v-if="can('manage', 'admin') || can('manage', 'supervisor')" location="top" text="Marcar como Caducado">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  color="error"
                  size="small"
                  @click="emit('expire-lot', item)"
                >
                  <VIcon icon="tabler-calendar-off" size="18" />
                </IconBtn>
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista Móvil (Tarjetas) -->
    <div class="d-block d-sm-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.lots.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        <AppEmptyState
          title="¡Todo al Día!"
          message="No se encontraron productos por caducar con los filtros aplicados."
          icon="tabler-circle-check"
        />
      </div>

      <div v-else class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.lots"
          :key="item.id"
          class="product-mobile-card border rounded-lg bg-surface pa-3 shadow-none position-relative"
        >
          <div class="d-flex align-center justify-space-between mb-1">
            <div class="d-flex align-center gap-1 min-width-0">
              <a
                :href="'/inventory/traceability?q=' + item.product?.id"
                target="_blank"
                class="text-xs font-weight-black text-primary text-decoration-none"
              >
                {{ item.product?.id }}
              </a>
              <span class="text-disabled">|</span>
              <span class="text-xs font-weight-black text-primary uppercase truncate mobile-lab-truncate">
                {{ item.product?.laboratory?.name || 'S/L' }}
              </span>
            </div>
            <VChip
              :color="(item.quantity ?? 0) > 0 ? 'success' : 'error'"
              label
              size="x-small"
              variant="tonal"
              class="font-weight-black"
            >
              {{ formatStock(item.quantity) }} UNDS
            </VChip>
          </div>

          <h4 class="text-xs font-weight-black text-high-emphasis uppercase leading-tight mb-1 text-truncate">
            {{ item.product?.name }}
            <span v-if="item.product?.iva == 1 || item.product?.iva === true" class="text-super-xs text-disabled"> (G)</span>
            <span v-if="item.product?.is_colombian_origin == 1 || item.product?.is_colombian_origin === true" class="text-super-xs text-disabled"> (COL)</span>
          </h4>

          <div class="d-flex align-center justify-space-between text-super-xs text-disabled mb-2">
            <span>Lote: <strong class="text-high-emphasis font-weight-black">{{ item.lot_number || 'S/L' }}</strong></span>
            <span :class="getExpirationColor(item.expiration_date)">
              Vence: <strong>{{ formatDateSimple(item.expiration_date) }}</strong>
            </span>
          </div>

          <div v-if="item.has_overstock_risk" class="mb-2">
            <VChip
              size="x-small"
              color="error"
              variant="tonal"
              prepend-icon="tabler-clock-exclamation"
              class="font-weight-black px-1.5 text-super-xs"
            >
              {{ item.risk_label_short || item.risk_label || 'Sobrestock en Riesgo' }}
            </VChip>
          </div>

          <div v-if="can('manage', 'admin') || can('manage', 'supervisor')" class="pt-2 border-t d-flex justify-end">
            <VBtn
              size="small"
              color="error"
              variant="tonal"
              class="font-weight-bold flex-grow-1"
              prepend-icon="tabler-calendar-off"
              @click="emit('expire-lot', item)"
            >
              Marcar como Caducado
            </VBtn>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="mt-4">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalLots"
          :loading="props.loading"
          :sort-by="props.sortBy"
          :order-by="props.orderBy"
          @change="(options) => emit('update:options', options)"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.product-title-max {
  max-inline-size: 320px;
}

.active-ingredient-max {
  max-inline-size: 180px;
}

.lab-name-max {
  max-inline-size: 140px;
}

.mobile-lab-truncate {
  max-inline-size: 160px;
}

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
