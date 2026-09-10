<script setup>
import { ref, computed } from 'vue';
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  czSummary: {
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
const sortBy = ref([{ key: 'cash_released_usd', order: 'desc' }]);
const statusFilter = ref('all');

const headers = [
  { title: 'ID / PRODUCTO', key: 'product_name', sortable: true },
  { title: 'LABORATORIO', key: 'laboratory_name', sortable: true },
  { title: 'STOCK FOTO', key: 'snapshot_stock', align: 'end', sortable: true },
  { title: 'VALOR FOTO ($)', key: 'snapshot_value_usd', align: 'end', sortable: true },
  { title: 'STOCK ACTUAL', key: 'current_stock', align: 'end', sortable: true },
  { title: 'VALOR ACTUAL ($)', key: 'current_value_usd', align: 'end', sortable: true },
  { title: 'DINERO LIBERADO ($)', key: 'cash_released_usd', align: 'end', sortable: true },
  { title: 'ESTADO', key: 'status', align: 'center', sortable: true },
];

const filteredItems = computed(() => {
  let list = props.items || [];
  if (statusFilter.value === 'released') {
    list = list.filter(i => Number(i.cash_released_usd) > 0);
  } else if (statusFilter.value === 'no_movement') {
    list = list.filter(i => Number(i.cash_released_usd) === 0 && Number(i.current_stock) === Number(i.snapshot_stock));
  } else if (statusFilter.value === 'stock_increase') {
    list = list.filter(i => Number(i.current_stock) > Number(i.snapshot_stock));
  }
  return list;
});
</script>

<template>
  <div>
    <!-- Hero Card Métrica Automática de Dinero Liberado -->
    <VCard class="pa-5 mb-4 rounded-lg border shadow-sm bg-surface">
      <VRow align="center" dense>
        <VCol cols="12" md="4" class="text-center text-md-start">
          <span class="text-caption font-weight-bold text-uppercase text-medium-emphasis d-block mb-1">
            Métrica Automática de Desinmovilización
          </span>
          <div class="d-flex align-baseline gap-2 justify-center justify-md-start">
            <h2 class="text-h4 font-weight-black text-success mb-0">
              {{ formatCurrency(czSummary?.total_cash_released || 0) }}
            </h2>
            <VChip size="small" color="success" class="font-weight-black">
              {{ czSummary?.recovery_percentage || 0 }}% Liberado
            </VChip>
          </div>
          <span class="text-caption text-success font-weight-bold">
            [Capital Inicial CZ: {{ formatCurrency(czSummary?.total_initial_cz_capital || 0) }}] - [Capital Actual CZ: {{ formatCurrency(czSummary?.total_current_cz_capital || 0) }}] = Dinero Liberado a Caja
          </span>
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <div class="pa-3 bg-light-primary rounded-lg border">
            <span class="text-caption font-weight-bold text-medium-emphasis d-block">Unidades Descongeladas Vendidas</span>
            <span class="text-h6 font-weight-black text-primary">{{ Number(czSummary?.total_units_released || 0).toLocaleString() }} unidades</span>
            <span class="text-super-xs text-medium-emphasis d-block">Reducción efectiva de inventario parado</span>
          </div>
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <div class="pa-3 bg-light-warning rounded-lg border">
            <span class="text-caption font-weight-bold text-medium-emphasis d-block">Capital Pendiente por Liberar</span>
            <span class="text-h6 font-weight-black text-warning">{{ formatCurrency(czSummary?.total_current_cz_capital || 0) }}</span>
            <span class="text-super-xs text-medium-emphasis d-block">En {{ czSummary?.items_count || 0 }} SKUs CZ monitoreados</span>
          </div>
        </VCol>
      </VRow>
    </VCard>

    <!-- Tabla Comparativa de Productos CZ con VDataTable Interactivo -->
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

          <VCol cols="12" md="4">
            <AppSelect
              v-model="statusFilter"
              :items="[
                { title: 'Todos los estados', value: 'all' },
                { title: 'Solo con Dinero Liberado (> $0)', value: 'released' },
                { title: 'Sin Movimiento (Stock Inmóvil)', value: 'no_movement' },
                { title: 'Aumento de Stock (Entradas)', value: 'stock_increase' },
              ]"
              density="compact"
              hide-details
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" md="3" class="text-end">
            <span class="text-caption text-medium-emphasis">
              Total: <strong>{{ filteredItems.length }}</strong> productos
            </span>
          </VCol>
        </VRow>

        <VDataTable
          v-model:page="page"
          v-model:items-per-page="itemsPerPage"
          v-model:sort-by="sortBy"
          :headers="headers"
          :items="filteredItems"
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

          <!-- Columna Stock Foto -->
          <template #item.snapshot_stock="{ item }">
            <span class="font-weight-bold">{{ item.snapshot_stock }}</span>
          </template>

          <!-- Columna Valor Foto -->
          <template #item.snapshot_value_usd="{ item }">
            <span>{{ formatCurrency(item.snapshot_value_usd) }}</span>
          </template>

          <!-- Columna Stock Actual -->
          <template #item.current_stock="{ item }">
            <span class="font-weight-bold" :class="item.current_stock < item.snapshot_stock ? 'text-success' : ''">
              {{ item.current_stock }}
            </span>
          </template>

          <!-- Columna Valor Actual -->
          <template #item.current_value_usd="{ item }">
            <span>{{ formatCurrency(item.current_value_usd) }}</span>
          </template>

          <!-- Columna Dinero Liberado -->
          <template #item.cash_released_usd="{ item }">
            <span class="font-weight-black" :class="item.cash_released_usd > 0 ? 'text-success' : 'text-medium-emphasis'">
              {{ item.cash_released_usd > 0 ? `+${formatCurrency(item.cash_released_usd)}` : formatCurrency(item.cash_released_usd) }}
            </span>
          </template>

          <!-- Columna Estado -->
          <template #item.status="{ item }">
            <VChip
              size="x-small"
              :color="item.cash_released_usd > 0 ? 'success' : (item.current_stock > item.snapshot_stock ? 'warning' : 'secondary')"
              variant="flat"
              class="font-weight-bold"
            >
              {{ item.status }}
            </VChip>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="text-center py-6 text-medium-emphasis">
              No se encontraron productos CZ registrados en esta Foto Finish.
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
