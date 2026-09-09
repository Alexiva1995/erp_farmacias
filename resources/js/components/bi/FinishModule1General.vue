<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  auditSummary: {
    type: Object,
    default: () => ({}),
  },
  selectedSnapshot: {
    type: Object,
    default: () => ({}),
  },
  detailSearch: {
    type: String,
    default: '',
  },
  selectedSalesClass: {
    type: [String, null],
    default: null,
  },
  onlyOverstock: {
    type: Boolean,
    default: false,
  },
  items: {
    type: Array,
    default: () => [],
  },
  totalItems: {
    type: Number,
    default: 0,
  },
  page: {
    type: Number,
    default: 1,
  },
  itemsPerPage: {
    type: Number,
    default: 15,
  },
  sortBy: {
    type: Array,
    default: () => [{ key: 'inventory_value_usd', order: 'desc' }],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  headers: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits([
  'update:detailSearch',
  'update:selectedSalesClass',
  'update:onlyOverstock',
  'update:page',
  'update:itemsPerPage',
  'update:sortBy',
]);

const getClassColor = (c) => {
  if (c === 'A') return 'success';
  if (c === 'B') return 'warning';
  if (c === 'C') return 'secondary';
  return 'error';
};
</script>

<template>
  <div>
    <!-- 4 KPIs Exactos del Módulo 1 -->
    <VRow dense class="mb-4">
      <!-- 1. Valor Total del Inventario ($) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Valor Total Inventario</span>
            <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-coin" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-primary mb-0">
            {{ formatCurrency(auditSummary?.total_inventory_value || selectedSnapshot.total_inventory_value || 0) }}
          </h3>
          <span class="text-caption text-medium-emphasis">{{ Number(selectedSnapshot.total_inventory_units || 0).toLocaleString() }} unidades evaluadas</span>
        </VCard>
      </VCol>

      <!-- 2. Capital Congelado en Productos CZ ($) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Capital Congelado (CZ)</span>
            <VAvatar color="error" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-lock-square" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-error mb-0">
            {{ formatCurrency(auditSummary?.frozen_capital_cz || 0) }}
          </h3>
          <span class="text-caption text-error font-weight-bold">Stock parado sin rotación / Clase C</span>
        </VCard>
      </VCol>

      <!-- 3. Capital Retenido en Sobrestock A/B ($) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Sobrestock A/B (>90d)</span>
            <VAvatar color="warning" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-alert-triangle" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black text-warning mb-0">
            {{ formatCurrency(auditSummary?.overstock_capital_ab || 0) }}
          </h3>
          <span class="text-caption text-medium-emphasis">Dinero atrapado en alta prioridad</span>
        </VCard>
      </VCol>

      <!-- 4. Cantidad de SKUs en Quiebre de Stock (Stock = 0) -->
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg border shadow-sm h-full">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis">Quiebre de Stock (Stock = 0)</span>
            <VAvatar :color="(auditSummary?.stockout_skus_count || 0) > 0 ? 'error' : 'success'" variant="tonal" size="36" class="rounded-lg">
              <VIcon icon="tabler-packages" size="20" />
            </VAvatar>
          </div>
          <h3 class="text-h5 font-weight-black" :class="(auditSummary?.stockout_skus_count || 0) > 0 ? 'text-error' : 'text-success'">
            {{ auditSummary?.stockout_skus_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">{{ auditSummary?.stockout_ab_count || 0 }} pertenecen a Clase A/B</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Productos del Snapshot -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface mb-6">
      <VCardText class="pa-4">
        <VRow align="center" dense class="mb-3">
          <VCol cols="12" md="4">
            <AppTextField
              :model-value="detailSearch"
              placeholder="Buscar producto, laboratorio o ID..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              variant="outlined"
              :disabled="loading"
              @update:model-value="emit('update:detailSearch', )"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <AppSelect
              :model-value="selectedSalesClass"
              :items="[
                { title: 'Todas las clases', value: null },
                { title: 'Clase A (Top 80% Ventas)', value: 'A' },
                { title: 'Clase B (15% Ventas)', value: 'B' },
                { title: 'Clase C (5% Ventas)', value: 'C' },
                { title: 'Clase Z (0 Ventas)', value: 'Z' },
              ]"
              placeholder="Clasificación Pareto"
              density="compact"
              hide-details
              variant="outlined"
              prepend-inner-icon="tabler-tags"
              clearable
              :disabled="loading"
              @update:model-value="emit('update:selectedSalesClass', )"
            />
          </VCol>

          <VCol cols="12" sm="6" md="3">
            <VSwitch
              :model-value="onlyOverstock"
              label="Solo Sobrestock (>90 días)"
              color="error"
              density="compact"
              hide-details
              :disabled="loading"
              @update:model-value="emit('update:onlyOverstock', )"
            />
          </VCol>
        </VRow>

        <VDataTableServer
          :items-per-page="itemsPerPage"
          :page="page"
          :sort-by="sortBy"
          :items-length="totalItems"
          :headers="headers"
          :items="items"
          :loading="loading"
          class="premium-table"
          hover
          density="compact"
          @update:items-per-page="emit('update:itemsPerPage', )"
          @update:page="emit('update:page', )"
          @update:sort-by="emit('update:sortBy', )"
        >
          <!-- ID -->
          <template #item.id_producto="{ item }">
            <span class="font-weight-black text-primary">#{{ item.id_producto }}</span>
          </template>

          <!-- Nombre Producto -->
          <template #item.nombre_producto="{ item }">
            <div class="d-flex flex-column py-1">
              <span class="font-weight-black text-sm text-high-emphasis text-uppercase text-truncate" :title="item.nombre_producto">
                {{ item.nombre_producto }}
              </span>
              <span class="text-caption text-primary font-weight-bold">
                {{ item.laboratorio }}
              </span>
            </div>
          </template>

          <!-- Clasificación -->
          <template #item.clasificacion_ventas="{ item }">
            <VChip
              size="small"
              :color="getClassColor(item.clasificacion_ventas)"
              class="font-weight-black"
              variant="elevated"
            >
              Clase {{ item.clasificacion_ventas }}
            </VChip>
          </template>

          <!-- Ventas 30d -->
          <template #item.ventas_unidades_30d="{ item }">
            <span class="font-weight-bold" :class="item.ventas_unidades_30d > 0 ? 'text-success' : 'text-disabled'">
              {{ item.ventas_unidades_30d }} unds
            </span>
          </template>

          <!-- Ventas Totales USD -->
          <template #item.ventas_totales_usd_30d="{ item }">
            <span class="font-weight-bold">{{ formatCurrency(item.ventas_totales_usd_30d) }}</span>
          </template>

          <!-- Stock Actual -->
          <template #item.stock_actual_unidades="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="font-weight-black">{{ item.stock_actual_unidades }}</span>
              <span class="text-super-xs text-medium-emphasis">Costo: {{ formatCurrency(item.costo_unitario_usd) }}</span>
            </div>
          </template>

          <!-- Valor Inventario -->
          <template #item.valor_inventario_usd="{ item }">
            <span class="font-weight-black text-high-emphasis" :class="item.es_sobrestock ? 'text-error' : ''">
              {{ formatCurrency(item.valor_inventario_usd) }}
            </span>
          </template>

          <!-- Margen -->
          <template #item.margen_porcentaje="{ item }">
            <span class="font-weight-bold" :class="item.margen_porcentaje > 0 ? 'text-primary' : 'text-error'">
              {{ item.margen_porcentaje.toFixed(1) }}%
            </span>
          </template>

          <!-- Cobertura Días -->
          <template #item.cobertura_dias="{ item }">
            <VChip
              size="small"
              :color="item.cobertura_dias >= 999 ? 'error' : (item.cobertura_dias > 90 ? 'warning' : 'success')"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ item.cobertura_dias >= 999 ? '999d (Sin Ventas)' : ${Math.round(item.cobertura_dias)} días }}
            </VChip>
          </template>

          <!-- GMROI Anual -->
          <template #item.gmroi_anual_porcentaje="{ item }">
            <span class="font-weight-bold">
              {{ item.gmroi_anual_porcentaje >= 9999 ? 'MAX' : ${Math.round(item.gmroi_anual_porcentaje)}% }}
            </span>
          </template>

          <!-- Días para Vencer -->
          <template #item.dias_para_vencer="{ item }">
            <span v-if="item.dias_para_vencer !== null" class="font-weight-bold" :class="item.dias_para_vencer <= 90 ? 'text-error' : 'text-medium-emphasis'">
              {{ item.dias_para_vencer <= 0 ? 'Vencido' : ${item.dias_para_vencer}d }}
            </span>
            <span v-else class="text-disabled text-caption">S/F</span>
          </template>

          <!-- Es Sobrestock -->
          <template #item.es_sobrestock="{ item }">
            <VChip
              size="x-small"
              :color="item.es_sobrestock ? 'error' : 'success'"
              variant="flat"
              class="font-weight-bold"
            >
              {{ item.es_sobrestock ? 'SOBRESTOCK' : 'ÓPTIMO' }}
            </VChip>
          </template>
        </VDataTableServer>
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
