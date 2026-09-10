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
const quickFilter = ref('released'); // 'released' | 'all' | 'no_movement' | 'stock_increase'

const headers = [
  { title: 'PRODUCTO / LABORATORIO', key: 'product_name', sortable: true },
  { title: 'STOCK I', key: 'snapshot_stock', align: 'end', sortable: true },
  { title: 'STOCK F', key: 'current_stock', align: 'end', sortable: true },
  { title: 'VALOR I ($)', key: 'snapshot_value_usd', align: 'end', sortable: true },
  { title: 'VALOR F ($)', key: 'current_value_usd', align: 'end', sortable: true },
  { title: 'LIBERADO ($)', key: 'cash_released_usd', align: 'end', sortable: true },
];

const filteredItems = computed(() => {
  let list = props.items || [];
  if (quickFilter.value === 'released') {
    list = list.filter(i => Number(i.cash_released_usd) > 0);
  } else if (quickFilter.value === 'no_movement') {
    list = list.filter(i => Number(i.cash_released_usd) === 0 && Number(i.current_stock) === Number(i.snapshot_stock));
  } else if (quickFilter.value === 'stock_increase') {
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
        <!-- Filtros Rápidos y Buscador -->
        <VRow align="center" dense class="mb-3">
          <VCol cols="12" md="4">
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

          <VCol cols="12" md="8" class="d-flex align-center justify-md-end flex-wrap gap-2">
            <VBtnToggle
              v-model="quickFilter"
              mandatory
              density="compact"
              color="primary"
              variant="outlined"
              class="rounded-lg"
            >
              <VBtn value="released" size="small" class="font-weight-bold" color="success">
                <VIcon icon="tabler-sparkles" size="16" class="me-1 text-success" />
                Solo Liberados (> $0)
              </VBtn>
              <VBtn value="all" size="small" class="font-weight-bold">
                Todos ({{ items.length }})
              </VBtn>
              <VBtn value="no_movement" size="small" class="font-weight-bold">
                Sin Movimiento
              </VBtn>
            </VBtnToggle>

            <span class="text-caption text-medium-emphasis ms-2">
              Mostrando: <strong>{{ filteredItems.length }}</strong> productos
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
          <!-- Columna Producto / ID unificada al estilo del Módulo 1 -->
          <template #item.product_name="{ item }">
            <div class="d-flex flex-column py-1">
              <div class="d-flex align-center gap-1">
                <RouterLink
                  v-if="item.product_id"
                  :to="`/inventory/traceability?q=${item.product_id}`"
                  target="_blank"
                  class="font-weight-black text-primary text-decoration-none text-sm cursor-pointer id-link"
                  title="Ver trazabilidad de movimientos"
                >
                  {{ item.product_id }}
                </RouterLink>
                <span v-if="item.product_id" class="text-sm text-medium-emphasis font-weight-bold">-</span>
                <span class="font-weight-black text-sm text-high-emphasis text-uppercase text-truncate" :title="item.product_name" style="max-width: 320px;">
                  {{ item.product_name }}
                </span>
              </div>
              <span class="text-caption text-secondary font-weight-medium">
                {{ item.laboratory_name }}
              </span>
            </div>
          </template>

          <!-- Columna Stock Inicial (Foto) -->
          <template #item.snapshot_stock="{ item }">
            <span class="font-weight-bold">{{ Number(item.snapshot_stock || 0).toLocaleString() }}</span>
          </template>

          <!-- Columna Stock Final (Actual) -->
          <template #item.current_stock="{ item }">
            <span class="font-weight-bold" :class="item.current_stock < item.snapshot_stock ? 'text-success' : (item.current_stock > item.snapshot_stock ? 'text-warning' : '')">
              {{ Number(item.current_stock || 0).toLocaleString() }}
            </span>
          </template>

          <!-- Columna Valor Inicial (Foto) -->
          <template #item.snapshot_value_usd="{ item }">
            <span class="text-medium-emphasis font-weight-medium">{{ formatCurrency(item.snapshot_value_usd) }}</span>
          </template>

          <!-- Columna Valor Final (Actual) -->
          <template #item.current_value_usd="{ item }">
            <span class="font-weight-bold text-high-emphasis">{{ formatCurrency(item.current_value_usd) }}</span>
          </template>

          <!-- Columna Liberado ($): Verde brillante para valores desinmovilizados -->
          <template #item.cash_released_usd="{ item }">
            <div class="d-flex align-center justify-end">
              <VChip
                v-if="item.cash_released_usd > 0"
                color="success"
                size="small"
                variant="flat"
                class="font-weight-black shadow-sm"
              >
                +{{ formatCurrency(item.cash_released_usd) }}
              </VChip>
              <span v-else class="text-disabled font-weight-medium">
                {{ formatCurrency(0) }}
              </span>
            </div>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="text-center py-6 text-medium-emphasis">
              No se encontraron productos CZ registrados o no coinciden con el filtro aplicado.
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
