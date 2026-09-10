<script setup>
import { ref } from 'vue';
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  marginSummary: {
    type: Object,
    default: () => ({}),
  },
  alerts: {
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
const sortBy = ref([{ key: 'margin_percentage', order: 'asc' }]);

const headers = [
  { title: 'PRODUCTO / LABORATORIO', key: 'product_name', sortable: true, cellProps: { class: 'sticky-col' }, headerProps: { class: 'sticky-col' } },
  { title: 'COSTO', key: 'unit_cost_usd', align: 'end', sortable: true },
  { title: 'P.V.P.', key: 'sale_price_usd', align: 'end', sortable: true },
  { title: 'MARGEN', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'STOCK', key: 'current_stock', align: 'end', sortable: true },
  { title: 'CAP. RIESGO', key: 'inventory_value_usd', align: 'end', sortable: true },
  { title: 'ESTADO', key: 'risk_level', align: 'center', sortable: true },
];

const getMarginBadgeClass = (riskLevel) => {
  if (riskLevel === 'Pérdida') return 'badge-margin-loss';
  if (riskLevel === 'Margen Bajo') return 'badge-margin-low';
  return 'badge-margin-healthy';
};
</script>

<template>
  <div>
    <!-- Tarjetas de Resumen Rápido (Alertas de Margen) -->
    <VRow dense class="mb-4">
      <!-- 1. Margen Negativo (<0%) -->
      <VCol cols="12" sm="6" md="4">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Margen Negativo (<0%)</span>
            <VAvatar color="error" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-alert-octagon" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-error mb-0">
            {{ marginSummary?.negative_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-error font-weight-bold">Venta a pérdida con existencias</span>
        </VCard>
      </VCol>

      <!-- 2. Margen Bajo Crítico (<15%) -->
      <VCol cols="12" sm="6" md="4">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Margen Bajo (<15%)</span>
            <VAvatar color="warning" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-alert-triangle" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-warning mb-0">
            {{ marginSummary?.low_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Por debajo del umbral mínimo</span>
        </VCard>
      </VCol>

      <!-- 3. Márgenes Saludables (>=15%) -->
      <VCol cols="12" sm="6" md="4">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Márgenes Saludables</span>
            <VAvatar color="success" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-percentage" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-success mb-0">
            {{ marginSummary?.healthy_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Operando en rangos óptimos</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Alertas de Margen con VDataTable Interactivo -->
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
              Total: <strong class="text-high-emphasis">{{ alerts.length }}</strong> alertas evaluadas
            </span>
          </VCol>
        </VRow>

        <VDataTable
          v-model:page="page"
          v-model:items-per-page="itemsPerPage"
          v-model:sort-by="sortBy"
          :headers="headers"
          :items="alerts"
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

          <!-- 2. Columna Costo -->
          <template #item.unit_cost_usd="{ item }">
            <span class="text-sm font-weight-bold">{{ formatCurrency(item.unit_cost_usd) }}</span>
          </template>

          <!-- 3. Columna P.V.P. -->
          <template #item.sale_price_usd="{ item }">
            <span class="text-sm font-weight-black text-high-emphasis">{{ formatCurrency(item.sale_price_usd) }}</span>
          </template>

          <!-- 4. Columna Margen -->
          <template #item.margin_percentage="{ item }">
            <span class="text-sm font-weight-black" :class="item.margin_percentage < 0 ? 'text-error' : 'text-warning'">
              {{ Number(item.margin_percentage || 0).toFixed(2) }}%
            </span>
          </template>

          <!-- 5. Columna Stock -->
          <template #item.current_stock="{ item }">
            <span class="text-sm font-weight-bold">{{ Number(item.current_stock).toLocaleString() }}</span>
          </template>

          <!-- 6. Columna Cap. Riesgo -->
          <template #item.inventory_value_usd="{ item }">
            <span class="text-sm font-weight-bold" :class="item.margin_percentage < 0 ? 'text-error' : 'text-warning'">
              {{ formatCurrency(item.inventory_value_usd) }}
            </span>
          </template>

          <!-- 7. Columna Estado / Diagnóstico (Badge pill sólido) -->
          <template #item.risk_level="{ item }">
            <div class="d-flex justify-center">
              <span class="badge-pill font-weight-black text-xs" :class="getMarginBadgeClass(item.risk_level)">
                {{ item.risk_level }}
              </span>
            </div>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="text-center py-6 text-success font-weight-bold">
              <VIcon icon="tabler-shield-check" size="24" class="me-2" />
              Excelente: Ningún producto con existencias opera con margen inferior al deseado.
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
  padding: 0 10px;
  white-space: nowrap;
}

/* Diagnósticos de Margen */
.badge-margin-loss {
  background-color: #FFEBEE !important;
  color: #D32F2F !important;
}

.badge-margin-low {
  background-color: #FEF7E0 !important;
  color: #B06000 !important;
}

.badge-margin-healthy {
  background-color: #E6F4EA !important;
  color: #137333 !important;
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
