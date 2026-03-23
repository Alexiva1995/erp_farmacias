<script setup>
import { useDisplay } from 'vuetify';

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);
const { mobile } = useDisplay();

// ... (headers definition stays same)
const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory.name", sortable: false },
  { title: "Costo Actual", key: "unit_cost", sortable: false, align: 'center' },
  { title: "Mejor Oferta", key: "product_suppliers", sortable: false, align: 'center' },
  { title: "Ventas", key: "total_sold_completed", sortable: true, align: 'end' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'end' },
  {
    title: "Prom.",
    key: "promedio_calculado",
    sortable: true,
    align: 'end',
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "Análisis",
    key: "solicitar",
    sortable: true,
    align: 'end',
    value: (item) =>
      item.solicitar != "" && item.solicitar != null
        ? roundIaAnalysis(item.solicitar)
        : 0,
  },
];

const roundIaAnalysis = (val) => Math.round(val);

function rowClass(item) {
  const val = roundIaAnalysis(item.solicitar);
  if (val > 0) return 'row-needs';
  if (val < 0) return 'row-excess';
  return '';
}

const getPriceDiff = (current, offer) => {
  if (!current || !offer || current <= 0) return 0;
  return ((current - offer) / current) * 100;
};
</script>

<template>
  <div class="assistant-report-container">
    <!-- Vista Escritorio -->
    <VCard v-if="!mobile" class="rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.products"
        :items-length="props.totalProduct"
        :loading="props.loading"
        :row-props="({ item }) => ({ class: rowClass(item) })"
        class="premium-table text-no-wrap"
        @update:options="(options) => emit('update:options', options)"
      >
        <!-- Producto -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-body-2 font-weight-black text-high-emphasis leading-tight">
              {{ item.name }}
              <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="tonal" class="ms-1 px-1" style="block-size: 14px; font-size: 0.6rem;">COL</VChip>
            </span>
            <span class="text-xxs text-disabled font-weight-medium uppercase mt-1">{{ item.active_ingredient || 'Sin ingrediente' }}</span>
          </div>
        </template>

        <!-- Costo Actual -->
        <template #item.unit_cost="{ item }">
          <div class="d-flex flex-column align-center">
            <span class="text-primary font-weight-black">$ {{ Number(item.unit_cost || 0).toFixed(2) }}</span>
            <span class="text-xxs text-disabled mt-n1">Costo Ficha</span>
          </div>
        </template>

        <!-- Mejor Oferta -->
        <template #item.product_suppliers="{ item }">
          <div v-if="item.product_suppliers?.length" class="d-flex flex-column align-center">
            <div class="d-flex align-center gap-1">
              <span class="font-weight-black text-success">$ {{ Number(item.product_suppliers[0].unit_cost_usd_with_discount || 0).toFixed(2) }}</span>
              <VIcon icon="tabler-trending-down" size="14" color="success" class="opacity-70" />
            </div>
            <span class="text-xxs text-disabled text-truncate" style="max-inline-size: 100px;">{{ item.product_suppliers[0].supplier.name }}</span>
          </div>
          <span v-else class="text-xxs text-disabled italic">Sin ofertas</span>
        </template>

        <!-- Ventas y Stock -->
        <template #item.total_sold_completed="{ item }">
          <span class="font-weight-bold">{{ item.total_sold_completed || 0 }}</span>
        </template>
        
        <template #item.lote_quantity="{ item }">
          <VChip :color="item.lote_quantity > 0 ? 'secondary' : 'error'" variant="tonal" size="x-small" class="font-weight-black">
            {{ item.lote_quantity || 0 }}
          </VChip>
        </template>

        <!-- Análisis IA -->
        <template #item.solicitar="{ item }">
          <div class="d-flex align-center justify-end gap-2">
            <VAvatar 
              :color="roundIaAnalysis(item.solicitar) > 0 ? 'success' : roundIaAnalysis(item.solicitar) < 0 ? 'error' : 'secondary'" 
              variant="tonal" 
              size="32" 
              class="rounded-lg"
            >
              <VIcon :icon="roundIaAnalysis(item.solicitar) > 0 ? 'tabler-plus' : roundIaAnalysis(item.solicitar) < 0 ? 'tabler-minus' : 'tabler-check'" size="16" />
            </VAvatar>
            <span 
              class="text-h6 font-weight-black"
              :class="roundIaAnalysis(item.solicitar) > 0 ? 'text-success' : roundIaAnalysis(item.solicitar) < 0 ? 'text-error' : 'text-disabled'"
            >
              {{ Math.abs(roundIaAnalysis(item.solicitar)) }}
            </span>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Vista Móvil -->
    <div v-else class="mobile-cards-view d-flex flex-column gap-4">
      <div v-if="loading" class="d-flex justify-center py-10">
        <VProgressCircular indeterminate color="primary" />
      </div>
      <template v-else>
        <VCard 
          v-for="item in props.products" 
          :key="item.id" 
          class="rounded-xl border-0 shadow-sm overflow-hidden row-decoration"
          :class="rowClass(item)"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-center gap-2 mb-1">
                  <span class="text-xs font-weight-black text-disabled">#{{ item.id }}</span>
                  <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="flat" density="compact" class="px-1 text-xxs leading-none">COL</VChip>
                </div>
                <h3 class="text-h6 font-weight-black leading-tight text-truncate">{{ item.name }}</h3>
                <p class="text-xs text-disabled font-weight-medium mb-0">{{ item.laboratory?.name }}</p>
              </div>

              <div class="d-flex flex-column align-end">
                <div 
                  class="analysis-badge pa-2 rounded-lg d-flex flex-column align-center"
                  :class="roundIaAnalysis(item.solicitar) > 0 ? 'bg-success-subtle' : roundIaAnalysis(item.solicitar) < 0 ? 'bg-error-subtle' : 'bg-secondary-subtle'"
                >
                  <span class="text-xxs font-weight-bold text-uppercase opacity-70">Sugerencia</span>
                  <span class="text-h6 font-weight-black leading-none mt-1">
                    {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }}
                  </span>
                </div>
              </div>
            </div>

            <VDivider class="opacity-10 mb-4" />

            <VRow no-gutters class="gap-y-4">
              <VCol cols="4" class="pe-2">
                <div class="text-xxs font-weight-bold text-disabled text-uppercase mb-1 text-center">Stock</div>
                <div class="d-flex justify-center">
                  <VChip size="small" :color="item.lote_quantity > 0 ? 'secondary' : 'error'" variant="tonal" class="font-weight-black px-3 h-auto py-1">
                    {{ item.lote_quantity || 0 }}
                  </VChip>
                </div>
              </VCol>
              
              <VCol cols="4" class="px-2 border-s border-e opacity-80">
                <div class="text-xxs font-weight-bold text-disabled text-uppercase mb-1 text-center">Ventas</div>
                <div class="text-body-2 font-weight-black text-center">{{ item.total_sold_completed || 0 }}</div>
              </VCol>

              <VCol cols="4" class="ps-2 text-center">
                <div class="text-xxs font-weight-bold text-disabled text-uppercase mb-1">Costo</div>
                <div class="text-body-2 font-weight-black text-primary">$ {{ Number(item.unit_cost || 0).toFixed(2) }}</div>
              </VCol>
            </VRow>

            <div v-if="item.product_suppliers?.length" class="mt-4 pa-2 bg-light rounded-lg d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-tag" size="16" color="success" />
                <span class="text-xs font-weight-bold text-success">{{ item.product_suppliers[0].supplier.name }}</span>
              </div>
              <span class="text-body-2 font-weight-black text-success">$ {{ Number(item.product_suppliers[0].unit_cost_usd_with_discount || 0).toFixed(2) }}</span>
            </div>
          </div>
        </VCard>

        <!-- Paginación móvil simple -->
        <div class="d-flex justify-center mt-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalProduct / props.itemsPerPage)"
            :total-visible="3"
            density="compact"
            @update:model-value="emit('update:options', { page: $event, itemsPerPage: props.itemsPerPage })"
          />
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: rgba(var(--v-theme-on-surface), 2%) !important;
  font-size: 0.7rem !important;
  font-weight: 800 !important;
  letter-spacing: 0.5px !important;
  text-transform: uppercase !important;
}

.row-needs td {
  background-color: rgba(var(--v-theme-success), 3%) !important;
}

.row-excess td {
  background-color: rgba(var(--v-theme-error), 3%) !important;
}

.row-decoration {
  position: relative;
  border-inline-start: 4px solid transparent !important;
}

.row-needs.row-decoration {
  border-inline-start-color: rgb(var(--v-theme-success)) !important;
}

.row-excess.row-decoration {
  border-inline-start-color: rgb(var(--v-theme-error)) !important;
}

.bg-success-subtle {
  background-color: rgba(var(--v-theme-success), 10%);
  color: rgb(var(--v-theme-success));
}

.bg-error-subtle {
  background-color: rgba(var(--v-theme-error), 10%);
  color: rgb(var(--v-theme-error));
}

.bg-secondary-subtle {
  background-color: rgba(var(--v-theme-secondary), 10%);
  color: rgb(var(--v-theme-secondary));
}

.text-xxs {
  font-size: 0.65rem !important;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 4%);
}

.leading-tight {
  line-height: 1.25;
}

.leading-none {
  line-height: 1;
}

.gap-y-4 {
  row-gap: 16px;
}
</style>

