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
  { title: 'ID / PRODUCTO', key: 'product_name', sortable: true },
  { title: 'LABORATORIO', key: 'laboratory_name', sortable: true },
  { title: 'COSTO UNIT. ($)', key: 'unit_cost_usd', align: 'end', sortable: true },
  { title: 'PRECIO VENTA ($)', key: 'sale_price_usd', align: 'end', sortable: true },
  { title: 'MARGEN REAL (%)', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'STOCK ACTUAL', key: 'current_stock', align: 'end', sortable: true },
  { title: 'CAPITAL EN RIESGO ($)', key: 'inventory_value_usd', align: 'end', sortable: true },
  { title: 'DIAGNÓSTICO', key: 'risk_level', align: 'center', sortable: true },
];
</script>

<template>
  <div>
    <VRow dense class="mb-4">
      <VCol cols="12" sm="4">
        <VCard class="pa-4 rounded-lg border shadow-sm">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Venta con Margen Negativo (<0%)
          </span>
          <h3 class="text-h5 font-weight-black text-error mb-0">
            {{ marginSummary?.negative_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-error font-weight-bold">Productos vendiéndose a pérdida con existencias</span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard class="pa-4 rounded-lg border shadow-sm">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Margen Bajo Crítico (<15%)
          </span>
          <h3 class="text-h5 font-weight-black text-warning mb-0">
            {{ marginSummary?.low_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Por debajo del umbral mínimo de rentabilidad</span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="4">
        <VCard class="pa-4 rounded-lg border shadow-sm">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Márgenes Saludables (>=15%)
          </span>
          <h3 class="text-h5 font-weight-black text-success mb-0">
            {{ marginSummary?.healthy_margin_count || 0 }} SKUs
          </h3>
          <span class="text-caption text-medium-emphasis">Operando dentro de rangos óptimos</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- Tabla de Alertas de Margen con VDataTable Interactivo -->
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
              Total: <strong>{{ alerts.length }}</strong> alertas evaluadas
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

          <!-- Columna Costo Unitario -->
          <template #item.unit_cost_usd="{ item }">
            <span>{{ formatCurrency(item.unit_cost_usd) }}</span>
          </template>

          <!-- Columna Precio Venta -->
          <template #item.sale_price_usd="{ item }">
            <span class="font-weight-bold">{{ formatCurrency(item.sale_price_usd) }}</span>
          </template>

          <!-- Columna Margen Real -->
          <template #item.margin_percentage="{ item }">
            <span class="font-weight-black" :class="item.margin_percentage < 0 ? 'text-error' : 'text-warning'">
              {{ Number(item.margin_percentage || 0).toFixed(2) }}%
            </span>
          </template>

          <!-- Columna Stock Actual -->
          <template #item.current_stock="{ item }">
            <span>{{ item.current_stock }}</span>
          </template>

          <!-- Columna Capital en Riesgo -->
          <template #item.inventory_value_usd="{ item }">
            <span>{{ formatCurrency(item.inventory_value_usd) }}</span>
          </template>

          <!-- Columna Diagnóstico -->
          <template #item.risk_level="{ item }">
            <VChip
              size="small"
              :color="item.severity"
              variant="flat"
              class="font-weight-bold"
            >
              {{ item.risk_level }}
            </VChip>
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
</style>
