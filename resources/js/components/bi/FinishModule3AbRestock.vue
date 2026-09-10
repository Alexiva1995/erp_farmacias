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
  { title: 'ID / PRODUCTO', key: 'product_name', sortable: true },
  { title: 'LABORATORIO', key: 'laboratory_name', sortable: true },
  { title: 'CLASIFICACIÓN', key: 'sales_class', align: 'center', sortable: true },
  { title: 'STOCK EN FOTO', key: 'snapshot_stock', align: 'end', sortable: true },
  { title: 'COBERTURA CORTE', key: 'snapshot_coverage_days', align: 'end', sortable: true },
  { title: 'STOCK ACTUAL', key: 'current_stock', align: 'end', sortable: true },
  { title: 'ESTADO DE REABASTECIMIENTO', key: 'restock_status', align: 'center', sortable: true },
];

const getClassColor = (c) => {
  if (c === 'A') return 'success';
  if (c === 'B') return 'warning';
  if (c === 'C') return 'secondary';
  return 'error';
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
          <VCol cols="12" md="5">
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

          <VCol cols="12" md="7" class="text-end">
            <span class="text-caption text-medium-emphasis">
              Total: <strong>{{ items.length }}</strong> productos en riesgo
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
          <!-- Columna Producto / ID -->
          <template #item.product_name="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="font-weight-black text-sm text-uppercase text-truncate" style="max-width: 260px;">
                {{ item.product_name }}
              </span>
              <span class="text-caption text-primary">#{{ item.product_id }}</span>
            </div>
          </template>

          <!-- Columna Laboratorio -->
          <template #item.laboratory_name="{ item }">
            <span class="font-weight-medium text-caption">{{ item.laboratory_name }}</span>
          </template>

          <!-- Columna Clasificación -->
          <template #item.sales_class="{ item }">
            <VChip size="x-small" :color="getClassColor(item.sales_class)" class="font-weight-bold">
              Clase {{ item.sales_class }}
            </VChip>
          </template>

          <!-- Columna Stock Foto -->
          <template #item.snapshot_stock="{ item }">
            <span class="font-weight-bold" :class="item.snapshot_stock <= 0 ? 'text-error' : ''">
              {{ item.snapshot_stock <= 0 ? '0 (Agotado)' : `${item.snapshot_stock} unds` }}
            </span>
          </template>

          <!-- Columna Cobertura Corte -->
          <template #item.snapshot_coverage_days="{ item }">
            <span class="text-caption">
              {{ item.snapshot_coverage_days < 10 ? `${item.snapshot_coverage_days} días (Riesgo)` : `${item.snapshot_coverage_days}d` }}
            </span>
          </template>

          <!-- Columna Stock Actual -->
          <template #item.current_stock="{ item }">
            <span class="font-weight-black text-primary">
              {{ item.current_stock }} unds
            </span>
          </template>

          <!-- Columna Estado Reabastecimiento -->
          <template #item.restock_status="{ item }">
            <VChip
              size="small"
              :color="item.status_color"
              variant="flat"
              class="font-weight-bold"
            >
              {{ item.restock_status }}
            </VChip>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="text-center py-6 text-medium-emphasis">
              No hubo productos A/B en quiebre o riesgo en la fecha de corte evaluada.
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
</style>
