<script setup>
import { ref } from 'vue';

const props = defineProps({
  restockSummary: {
    type: Object,
    default: () => ({}),
  },
  items: {
    type: Array,
    default: () => [],
  },
  search: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['update:search']);

const itemsPerPage = ref(15);
const page = ref(1);
const sortBy = ref([{ key: 'snapshot_stock', order: 'asc' }]);

const headers = [
  { title: 'PRODUCTO / LABORATORIO', key: 'product_name', sortable: true, cellProps: { class: 'sticky-col' }, headerProps: { class: 'sticky-col' } },
  { title: 'CLASIF.', key: 'sales_class', align: 'center', sortable: true },
  { title: 'STOCK I', key: 'snapshot_stock', align: 'end', sortable: true },
  { title: 'COB. I', key: 'snapshot_coverage_days', align: 'center', sortable: true },
  { title: 'STOCK F', key: 'current_stock', align: 'end', sortable: true },
  { title: 'COB. F', key: 'current_coverage_days', align: 'center', sortable: true },
  { title: 'DÍAS QUIEBRE', key: 'days_in_stockout', align: 'center', sortable: true },
  { title: 'ESTADO', key: 'restock_status', align: 'center', sortable: false },
];

const getSalesClassBadgeClass = (c) => {
  if (c === 'A') return 'badge-class-a';
  if (c === 'B') return 'badge-class-b';
  if (c === 'C') return 'badge-class-c';
  return 'badge-class-z';
};

const getCoverageBadgeClass = (days) => {
  const d = Math.round(Number(days) || 0);
  if (d <= 0) return 'badge-coverage-zero';
  if (d <= 7) return 'badge-coverage-warning';
  return 'badge-coverage-healthy';
};
</script>

<template>
  <div>
    <!-- Tarjetas de Resumen Rápido (Reabastecimiento A/B) -->
    <VRow dense class="mb-4">
      <!-- 1. Efectividad de Reabastecimiento A/B -->
      <VCol cols="12" sm="6" md="4">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Efectividad de Reabastecimiento</span>
            <VAvatar :color="restockSummary?.effectiveness_rate >= 80 ? 'success' : 'warning'" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-truck-delivery" size="20" />
            </VAvatar>
          </div>
          <div class="d-flex align-baseline gap-2 mb-1">
            <h3 class="text-h5 font-weight-black mb-0" :class="restockSummary?.effectiveness_rate >= 80 ? 'text-success' : 'text-warning'">
              {{ restockSummary?.effectiveness_rate || 0 }}%
            </h3>
            <VChip size="x-small" :color="restockSummary?.effectiveness_rate >= 80 ? 'success' : 'warning'" class="font-weight-black">
              Tasa de Cumplimiento
            </VChip>
          </div>
          <span class="text-caption text-medium-emphasis">
            {{ restockSummary?.restocked_count || 0 }} de {{ restockSummary?.total_critical_items || 0 }} productos reabastecidos
          </span>
        </VCard>
      </VCol>

      <!-- 2. SKUs Reabastecidos con Éxito -->
      <VCol cols="12" sm="6" md="4">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Reabastecidos con Éxito</span>
            <VAvatar color="success" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-circle-check" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-success mb-0">
            {{ restockSummary?.restocked_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Stock recuperado y saludable</span>
        </VCard>
      </VCol>

      <!-- 3. SKUs Aún en Quiebre Crítico -->
      <VCol cols="12" sm="6" md="4">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Aún en Quiebre Crítico</span>
            <VAvatar color="error" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-alert-triangle" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-error mb-0">
            {{ restockSummary?.still_stockout_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-error font-weight-bold">Requieren compra urgente</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Seguimiento de Compras A/B con VDataTable Interactivo -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
      <VCardText class="pa-4">
        <VRow align="center" dense class="mb-3">
          <VCol cols="12" sm="6" md="5">
            <AppTextField
              :model-value="search"
              placeholder="Buscar por producto, laboratorio o ID..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              variant="outlined"
              @update:model-value="val => emit('update:search', val || '')"
            />
          </VCol>

          <VCol cols="12" sm="6" md="7" class="text-end">
            <span class="text-caption text-medium-emphasis">
              Total: <strong class="text-high-emphasis">{{ items.length }}</strong> productos en riesgo
            </span>
          </VCol>
        </VRow>

        <VDataTable
          v-model:page="page"
          v-model:items-per-page="itemsPerPage"
          v-model:sort-by="sortBy"
          :headers="headers"
          :items="items"
          :items-per-page-options="[10, 15, 25, 50, 100, -1]"
          density="compact"
          hover
          class="text-no-wrap premium-datatable"
        >
          <!-- 1. Columna Producto / Laboratorio (Sticky a la izquierda) -->
          <template #item.product_name="{ item }">
            <div class="d-flex flex-column py-1" style="min-width: 240px; max-width: 320px;">
              <a
                :href="`/inventory/traceability?q=${item.product_id}`"
                target="_blank"
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate text-decoration-none id-link cursor-pointer"
                :title="item.product_name"
              >
                <span class="text-primary font-weight-black me-1">{{ item.product_id }}</span>
                - {{ item.product_name }}
              </a>
              <span class="text-xs font-weight-medium text-primary text-uppercase text-truncate mt-0.5">
                {{ item.laboratory_name || 'SIN LABORATORIO' }}
              </span>
            </div>
          </template>

          <!-- 2. Columna Clasificación Pareto (Badge sólido pill) -->
          <template #item.sales_class="{ item }">
            <div class="d-flex justify-center">
              <span class="badge-pill font-weight-black text-xs" :class="getSalesClassBadgeClass(item.sales_class)">
                {{ item.sales_class }}
              </span>
            </div>
          </template>

          <!-- 3. Columna Stock Inicial -->
          <template #item.snapshot_stock="{ item }">
            <span class="text-sm font-weight-bold" :class="Number(item.snapshot_stock) <= 0 ? 'text-error' : 'text-high-emphasis'">
              {{ Number(item.snapshot_stock).toLocaleString() }}
            </span>
          </template>

          <!-- 4. Columna Cobertura Inicial (Mini Badge Tenue) -->
          <template #item.snapshot_coverage_days="{ item }">
            <div class="d-flex justify-center">
              <span class="badge-pill font-weight-bold text-xs" :class="getCoverageBadgeClass(item.snapshot_coverage_days)">
                {{ Math.round(Number(item.snapshot_coverage_days)) }}D
              </span>
            </div>
          </template>

          <!-- 5. Columna Stock Actual (Stock Final) -->
          <template #item.current_stock="{ item }">
            <span class="text-sm font-weight-black" :class="Number(item.current_stock) > Number(item.snapshot_stock) ? 'text-success' : (Number(item.current_stock) <= 0 ? 'text-error' : 'text-primary')">
              {{ Number(item.current_stock).toLocaleString() }}
            </span>
          </template>

          <!-- 6. Columna Cobertura Actual (Mini Badge Tenue) -->
          <template #item.current_coverage_days="{ item }">
            <div class="d-flex justify-center">
              <span class="badge-pill font-weight-bold text-xs" :class="getCoverageBadgeClass(item.current_coverage_days)">
                {{ Math.round(Number(item.current_coverage_days)) }}D
              </span>
            </div>
          </template>

          <!-- 7. Columna Días en Quiebre (Badge de alerta) -->
          <template #item.days_in_stockout="{ item }">
            <div class="d-flex justify-center">
              <span
                v-if="Number(item.days_in_stockout) > 0"
                class="badge-pill badge-stockout-alert font-weight-black text-xs d-inline-flex align-center gap-1"
              >
                <VIcon icon="tabler-clock" size="13" />
                {{ item.days_in_stockout }}D
              </span>
              <span
                v-else
                class="badge-pill badge-stockout-zero font-weight-medium text-xs"
              >
                0D
              </span>
            </div>
          </template>

          <!-- 8. Columna Estado Reabastecimiento con Avatar e Ícono -->
          <template #item.restock_status="{ item }">
            <div class="d-flex justify-center align-center">
              <!-- Reabastecido con Éxito: Check Verde -->
              <VAvatar
                v-if="Number(item.current_stock) >= 10"
                color="success"
                variant="tonal"
                size="28"
                class="cursor-pointer"
              >
                <VIcon icon="tabler-check" size="18" />
                <VTooltip activator="parent" location="top">
                  Reabastecido con Éxito ({{ item.current_stock }} unids)
                </VTooltip>
              </VAvatar>

              <!-- Reabastecimiento Parcial: Alerta Naranja -->
              <VAvatar
                v-else-if="Number(item.current_stock) > 0"
                color="warning"
                variant="tonal"
                size="28"
                class="cursor-pointer"
              >
                <VIcon icon="tabler-alert-triangle" size="18" />
                <VTooltip activator="parent" location="top">
                  Reabastecimiento Parcial ({{ item.current_stock }} unids)
                </VTooltip>
              </VAvatar>

              <!-- Aún en Quiebre: X Roja -->
              <VAvatar
                v-else
                color="error"
                variant="tonal"
                size="28"
                class="cursor-pointer"
              >
                <VIcon icon="tabler-x" size="18" />
                <VTooltip activator="parent" location="top">
                  Aún en Quiebre Crítico (0 unids)
                </VTooltip>
              </VAvatar>
            </div>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="text-center py-6 text-success font-weight-bold">
              <VIcon icon="tabler-circle-check" size="24" class="me-2" />
              ¡Excelente! No hay productos clase A/B en quiebre de stock o riesgo de cobertura.
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.6875rem !important;
  line-height: 0.875rem !important;
}

.id-link {
  transition: opacity 0.2s ease;
}

.id-link:hover {
  text-decoration: underline !important;
  opacity: 0.85;
}

/* Badges redondeados (pill) */
.badge-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 22px;
  font-size: 0.75rem;
  line-height: 1;
  border-radius: 9999px;
  padding: 0 8px;
  white-space: nowrap;
}

/* Clasificación Pareto */
.badge-class-a {
  background-color: #E6F4EA !important;
  color: #137333 !important;
}

.badge-class-b {
  background-color: #E8F0FE !important;
  color: #1A73E8 !important;
}

.badge-class-c {
  background-color: #FEF7E0 !important;
  color: #B06000 !important;
}

.badge-class-z {
  background-color: #FCE8E6 !important;
  color: #C5221F !important;
}

/* Coberturas */
.badge-coverage-zero {
  background-color: #FCE8E6 !important;
  color: #C5221F !important;
}

.badge-coverage-warning {
  background-color: #FEF7E0 !important;
  color: #B06000 !important;
}

.badge-coverage-healthy {
  background-color: #E6F4EA !important;
  color: #137333 !important;
}

/* Días Quiebre */
.badge-stockout-alert {
  background-color: #FFEBEE !important;
  color: #D32F2F !important;
}

.badge-stockout-zero {
  background-color: #F1F3F4 !important;
  color: #5F6368 !important;
}

:deep(.premium-datatable) table {
  table-layout: auto;
}

:deep(.premium-datatable th:nth-child(1)),
:deep(.premium-datatable td:nth-child(1)) {
  position: sticky;
  left: 0;
  background-color: rgb(var(--v-theme-surface)) !important;
  z-index: 2;
  box-shadow: 2px 0 5px -2px rgba(0, 0, 0, 0.1);
}
</style>
