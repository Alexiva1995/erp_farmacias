<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  loading: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
  totalItems: { type: Number, default: 0 },
  itemsPerPage: { type: Number, default: 10 },
  page: { type: Number, default: 1 },
  sortBy: { type: Array, default: () => [{ key: 'inventory_value', order: 'desc' }] },
  search: { type: String, default: '' },
});

const emit = defineEmits([
  'update:itemsPerPage',
  'update:page',
  'update:sortBy',
  'view-stats',
  'clear-filters',
]);

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'PRODUCTO', key: 'name', sortable: true },
  { title: 'STOCK ACTUAL', key: 'current_stock', align: 'end', sortable: true },
  { title: 'COSTO UNIT.', key: 'last_cost', align: 'end', sortable: true },
  { title: 'CAPITAL INMOVILIZADO', key: 'inventory_value', align: 'end', sortable: true },
  { title: 'ÚLTIMA VENTA', key: 'last_sale_date', align: 'center', sortable: true },
  { title: 'VENTAS (PROM. / 12M)', key: 'sales_average', align: 'end', sortable: true },
  { title: 'PERFIL ABC-XYZ', key: 'final_classification', align: 'center', sortable: true },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '100px' },
];

const formatDate = (dateStr) => {
  if (!dateStr) return 'Nunca vendido';
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return 'Nunca vendido';
    return d.toLocaleDateString('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (e) {
    return 'Nunca vendido';
  }
};

const getColorClass = (classification) => {
  if (!classification) return 'default';
  
  if (['AAX', 'AAY', 'BAX', 'CAX'].includes(classification)) return 'success';
  if (['CCZ', 'CBZ', 'ACX'].includes(classification)) return 'error';
  if (['ABX', 'BBX'].includes(classification)) return 'warning';
  
  return 'secondary';
};
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VCardText class="d-flex justify-space-between align-center py-3">
      <h2 class="text-h6 font-weight-bold d-flex align-center">
        <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
        Productos en Stock Muerto
      </h2>
    </VCardText>
    <VDivider class="border-opacity-10" />

    <VDataTableServer
      :items-per-page="itemsPerPage"
      :page="page"
      :sort-by="sortBy"
      :items-length="totalItems"
      :headers="headers"
      :items="items"
      :search="search"
      :loading="loading"
      class="premium-table"
      hover
      density="compact"
      @update:items-per-page="val => emit('update:itemsPerPage', val)"
      @update:page="val => emit('update:page', val)"
      @update:sort-by="val => emit('update:sortBy', val)"
    >
      <template #item.id="{ item }">
        <a
          :href="'/inventory/traceability?q=' + item.id"
          target="_blank"
          class="text-decoration-none font-weight-black text-primary"
        >
          {{ item.id }}
        </a>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex flex-column py-2">
          <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.name">
            {{ item.name.toUpperCase() }}
          </span>
          <div class="d-flex align-center gap-1 text-super-xs">
            <span class="text-disabled truncate" style="max-inline-size: 200px;">
              {{ item.active_ingredient || item.active_ingredient_inventory || 'SIN INGREDIENTE' }}
            </span>
            <span class="text-disabled mx-1">|</span>
            <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
              {{ item.laboratory_name || 'S/L' }}
            </span>
          </div>
        </div>
      </template>

      <template #item.current_stock="{ item }">
        <span class="font-weight-black text-high-emphasis">{{ item.current_stock }} uds</span>
      </template>

      <template #item.last_cost="{ item }">
        <span>{{ formatCurrency(item.last_cost) }}</span>
      </template>

      <template #item.inventory_value="{ item }">
        <span class="font-weight-black text-error">{{ formatCurrency(item.inventory_value) }}</span>
      </template>

      <template #item.last_sale_date="{ item }">
        <span class="font-weight-medium text-high-emphasis">{{ formatDate(item.last_sale_date) }}</span>
      </template>

      <template #item.sales_average="{ item }">
        <div class="d-flex flex-column align-end">
          <span class="font-weight-bold text-primary">{{ item.sales_average }} uds/mes</span>
          <span class="text-caption text-medium-emphasis">{{ Math.round(item.sales_average * 12) }} uds (12m)</span>
        </div>
      </template>

      <template #item.final_classification="{ item }">
        <VTooltip location="top" content-class="bg-grey-900 border-opacity-100">
          <template #activator="{ props }">
            <VChip
              size="large"
              v-bind="props"
              :color="getColorClass(item.final_classification)"
              class="text-uppercase font-weight-black elevation-1"
              variant="elevated"
            >
              {{ item.final_classification }}
            </VChip>
          </template>
          <div class="d-flex flex-column gap-1 text-caption text-left text-white pa-1">
            <span><strong>A</strong>porte Ventas: {{ item.class_sales === 'A' ? 'Alto (80%)' : (item.class_sales === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
            <span><strong>M</strong>argen Cbción: {{ item.class_margin === 'A' ? 'Alto (80%)' : (item.class_margin === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
            <span><strong>R</strong>otación Dem.: {{ item.class_rotation === 'X' ? 'Constante' : (item.class_rotation === 'Y' ? 'Fluctuante' : 'Esporádica (Z)') }}</span>
          </div>
        </VTooltip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex align-center justify-center">
          <IconBtn @click="emit('view-stats', item)" color="primary" size="small">
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent" location="top">Ver Estadísticas</VTooltip>
          </IconBtn>
        </div>
      </template>

      <!-- Estado Vacío -->
      <template #no-data>
        <div class="text-center py-8">
          <VIcon icon="tabler-box-off" size="48" color="secondary" class="mb-2" />
          <h3 class="text-h6 font-weight-bold text-high-emphasis">Sin productos inmovilizados</h3>
          <p class="text-body-2 text-medium-emphasis mb-4">No se encontraron productos en stock muerto con los filtros seleccionados.</p>
          <VBtn color="primary" variant="tonal" size="small" prepend-icon="tabler-eraser" @click="emit('clear-filters')">
            Restablecer Filtros
          </VBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.gap-1 { gap: 4px !important; }
</style>
