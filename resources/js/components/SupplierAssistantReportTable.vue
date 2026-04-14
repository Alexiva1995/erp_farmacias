<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { useDisplay } from 'vuetify';
import { ref, computed } from 'vue';

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  sortBy: { type: Array, default: () => [{"key":"solicitar","order":"desc"}] },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);
const { mobile } = useDisplay();

const headers = computed(() => {
  const baseData = [
    { title: "id", key: "id", sortable: true, width: '80px' },
    { title: "Producto", key: "name", sortable: true, minWidth: '320px' },
    { title: "Laboratorio", key: "laboratory.name", sortable: false, minWidth: '150px' },
  ];

  baseData.push(
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
    }
  );

  return baseData;
});

const roundIaAnalysis = (value) => {
  const number = Number(value);
  return isNaN(number) ? 0 : Math.ceil(number);
};

// Clases de fila condicionales
const rowClass = (item) => {
  return roundIaAnalysis(item.solicitar) > 0 ? 'bg-light-success-50' : '';
};

const getPriceDiff = (current, offer) => {
  if (!current || !offer || current <= 0) return 0;
  return ((current - offer) / current) * 100;
};
</script>

<template>
  <div class="assistant-report-container">
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Escritorio -->
      <div v-if="!mobile" class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.products"
          :items-length="props.totalProduct"
          :loading="props.loading"
          :sort-by="props.sortBy"
          :row-props="({ item }) => ({ class: rowClass(item) })"
          class="premium-table text-no-wrap"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Producto -->
          <template #item.name="{ item }">
            <div class="d-flex align-center py-2">
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.name">
                  {{ item.name.toUpperCase() }}
                  <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="tonal" class="ms-1 px-1 font-weight-black" style="font-size: 0.6rem;">COL</VChip>
                </span>
                <div class="d-flex align-center gap-1 text-super-xs">
                  <span class="text-disabled truncate" style="max-inline-size: 180px;">{{ item.active_ingredient || 'SIN INGREDIENTE' }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                    {{ item.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <!-- ID -->
          <template #item.id="{ item }">
            <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
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
      </div>

      <!-- Vista Móvil (Cards) -->
      <div v-else class="d-block d-md-none pa-2">
        <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
        
        <template v-if="props.products.length > 0">
          <div class="d-flex flex-column gap-2">
            <VCard
              v-for="item in props.products"
              :key="item.id"
              variant="flat"
              class="border mb-1 rounded-lg overflow-hidden"
            >
              <div class="pa-3">
                <div class="d-flex justify-space-between align-start mb-2">
                  <div class="flex-grow-1 pr-2">
                    <div class="text-sm font-weight-black text-primary text-uppercase leading-tight truncate-2-lines mb-1">
                      {{ item.name }}
                    </div>
                    <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs text-disabled">
                      <span>{{ item.laboratory?.name || 'S/L' }}</span>
                      <span>|</span>
                      <span>{{ item.active_ingredient }}</span>
                      <VChip v-if="item.is_colombian_origin == 1" color="info" size="x-small" label class="ml-1 text-super-xs">COL</VChip>
                    </div>
                  </div>
                  <div class="text-right d-flex flex-column align-end">
                    <div 
                      class="analysis-badge pa-1 px-2 rounded d-flex flex-column align-center"
                      :class="roundIaAnalysis(item.solicitar) > 0 ? 'bg-success-subtle' : roundIaAnalysis(item.solicitar) < 0 ? 'bg-error-subtle' : 'bg-secondary-subtle'"
                    >
                      <span class="text-super-xs font-weight-black text-uppercase opacity-70">Análisis</span>
                      <span class="text-sm font-weight-black leading-none mt-1">
                        {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }}
                      </span>
                    </div>
                  </div>
                </div>

                <VDivider class="my-2 border-opacity-10" />

                <div class="grid-mobile-info">
                  <div class="info-item">
                    <span class="label">Stock</span>
                    <span class="value">{{ item.lote_quantity || 0 }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Ventas</span>
                    <span class="value">{{ item.total_sold_completed || 0 }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">Costo Actual</span>
                    <span class="value text-primary font-weight-bold">$ {{ Number(item.unit_cost || 0).toFixed(2) }}</span>
                  </div>
                </div>

                <div v-if="item.product_suppliers?.length" class="mt-3 pa-2 bg-var-theme-background rounded d-flex align-center justify-space-between border-dashed-thin">
                  <div class="d-flex align-center gap-2">
                    <VIcon icon="tabler-tag" size="14" color="success" />
                    <span class="text-super-xs font-weight-black text-success text-truncate" style="max-inline-size: 100px;">{{ item.product_suppliers[0].supplier.name }}</span>
                  </div>
                  <span class="text-xs font-weight-black text-success">$ {{ Number(item.product_suppliers[0].unit_cost_usd_with_discount || 0).toFixed(2) }}</span>
                </div>
              </div>
            </VCard>
          </div>

          <!-- Paginación Móvil -->
          <div class="d-flex justify-center mt-4">
             <AppMobilePagination
            :page="props.page"
            :items-per-page="props.itemsPerPage"
            :total-items="props.totalProduct"
            :loading="props.loading"
            @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
          />
          </div>
        </template>

        <div v-else class="d-flex flex-column align-center py-12 text-disabled text-center">
          <VIcon icon="tabler-package-off" size="48" class="mb-3" />
          <span class="text-body-1 font-weight-medium">No hay productos filtrados</span>
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

:deep(.row-needs td) {
  background-color: rgba(var(--v-theme-success), 3%) !important;
}

:deep(.row-excess td) {
  background-color: rgba(var(--v-theme-error), 3%) !important;
}

.grid-mobile-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.info-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.info-item .label {
  font-size: 0.6rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 800;
  text-align: center;
}

.info-item .value {
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
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

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3);
}

.leading-tight {
  line-height: 1.25;
}

.leading-none {
  line-height: 1;
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.chart-placeholder {
  animation: shimmer 1.5s infinite;
  background: linear-gradient(90deg, rgba(var(--v-border-color), 0.05) 25%, rgba(var(--v-border-color), 0.1) 50%, rgba(var(--v-border-color), 0.05) 75%);
  background-size: 200% 100%;
  block-size: 25px;
  inline-size: 100%;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>

