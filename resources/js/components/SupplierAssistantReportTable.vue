script
<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options"]);

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
    title: "Demanda",
    key: "demanda_ponderada",
    sortable: false,
    align: 'end',
    value: (item) =>
      item.demanda_ponderada != "" && item.demanda_ponderada != null
        ? parseFloat(item.demanda_ponderada).toFixed(1)
        : 0,
  },
  {
    title: "AO",
    key: "totalQuantityInAutoOrder",
    sortable: false,
    align: 'end',
  },
  {
    title: "Análisis",
    key: "solicitar",
    sortable: true,
    align: 'end',
    value: (item) =>
      item.solicitar != "" && item.solicitar != null
        ? parseFloat(item.solicitar).toFixed(1)
        : 0,
  },
];

// Determina el color de fondo por fila (Light mode friendly)
function rowClass(item) {
  const val = parseFloat(item.solicitar);
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
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      :row-props="({ item }) => ({ class: rowClass(item) })"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- Estado vacío -->
      <template #no-data>
        <div class="d-flex flex-column align-center py-12 text-disabled">
          <VIcon icon="tabler-package-off" size="48" class="mb-3" />
          <span class="text-body-1 font-weight-medium">No hay resultados para este reporte</span>
          <span class="text-caption mt-1">Intenta ajustando los filtros de tiempo o laboratorio</span>
        </div>
      </template>

      <!-- Producto -->
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.psychotropic == 1 }"
            >
              {{ item.name }}

              <span v-if="item.is_colombian_origin == 1" class="text-xs text-secondary"> (COL)</span>
            </span>

            <span class="text-xs text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <!-- Costo Actual -->
      <template #item.unit_cost="{ item }">
        <VTooltip location="top">
          <template #activator="{ props: tp }">
            <div v-bind="tp" class="d-flex flex-column align-center cursor-help">
              <span class="text-primary font-weight-bold">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
              <div class="d-flex gap-x-1 mt-n1">
                <span class="text-success" style="font-size: 0.65rem;">${{ Number(item.cost_min || 0).toFixed(2) }}</span>
                <span class="text-disabled" style="font-size: 0.65rem;">/</span>
                <span class="text-error" style="font-size: 0.65rem;">${{ Number(item.cost_max || 0).toFixed(2) }}</span>
              </div>
            </div>
          </template>
          <div class="text-xs pa-2">
            <div class="d-flex justify-space-between gap-x-4 mb-1">
              <span class="text-disabled font-weight-bold">DETALLE DE COSTOS</span>
            </div>
            <div class="d-flex justify-space-between gap-x-4">
              <span>Mínimo Histórico:</span>
              <span class="text-success font-weight-bold">${{ Number(item.cost_min || 0).toFixed(2) }}</span>
            </div>
            <div class="d-flex justify-space-between gap-x-4">
              <span>Máximo Histórico:</span>
              <span class="text-error font-weight-bold">${{ Number(item.cost_max || 0).toFixed(2) }}</span>
            </div>
            <div class="d-flex justify-space-between gap-x-4 border-t mt-1 pt-1">
              <span class="font-weight-medium">Costo en Ficha (Actual):</span>
              <span class="font-weight-bold text-primary">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
            </div>
          </div>
        </VTooltip>
      </template>

      <!-- Mejor Oferta -->
      <template #item.product_suppliers="{ item }">
        <div v-if="item.product_suppliers && item.product_suppliers.length > 0" class="d-flex flex-column align-center">
          <div class="d-flex align-center gap-x-2">
            <span 
              class="font-weight-bold"
              :class="getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd_with_discount) >= 0 ? 'text-success' : 'text-error'"
            >
              ${{ Number(item.product_suppliers[0].unit_cost_usd_with_discount || 0).toFixed(2) }}
            </span>
            
            <VChip
              v-if="Math.abs(getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd_with_discount)) > 0.5"
              size="x-small"
              :color="getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd_with_discount) > 0 ? 'success' : 'error'"
              variant="tonal"
              label
              class="px-1"
            >
              <VIcon 
                start 
                size="10" 
                :icon="getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd_with_discount) > 0 ? 'tabler-trending-down' : 'tabler-trending-up'" 
                class="me-0" 
              />
              {{ Math.abs(getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd_with_discount)).toFixed(0) }}%
            </VChip>
          </div>
          <span class="text-xs text-disabled text-truncate text-center" style="max-inline-size: 130px;">
            {{ item.product_suppliers[0].supplier.name }}
          </span>
        </div>
        <span v-else class="text-caption text-disabled italic">Sin proveedor</span>
      </template>

      <!-- Demanda Ponderada -->
      <template #item.demanda_ponderada="{ item }">
        <VTooltip text="Promedio + Ventas / 2 (antes de restar stock)">
          <template #activator="{ props: tp }">
            <span v-bind="tp" class="font-weight-bold black--text">
              {{ item.demanda_ponderada ? parseFloat(item.demanda_ponderada).toFixed(1) : '0' }}
            </span>
          </template>
        </VTooltip>
      </template>

      <!-- Promedio con Tooltip -->
      <template #item.promedio_calculado="{ item }">
        <VTooltip text="Promedio calculado según el lapso de tiempo">
          <template #activator="{ props: tp }">
            <span v-bind="tp" class="font-weight-medium">
              {{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(1) : '0' }}
            </span>
          </template>
        </VTooltip>
      </template>

      <!-- En Pedido (AO) -->
      <template #item.totalQuantityInAutoOrder="{ item }">
        <VTooltip text="Unidades en órdenes activas (Pendientes/Enviadas)">
          <template #activator="{ props: tp }">
            <VChip
              v-bind="tp"
              :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'"
              variant="tonal"
              size="small"
              class="font-weight-bold"
            >
              {{ item.totalQuantityInAutoOrder || 0 }}
            </VChip>
          </template>
        </VTooltip>
      </template>

      <!-- Análisis -->
      <template #item.solicitar="{ item }">
        <VTooltip :text="parseFloat(item.solicitar) > 0 ? 'Sugerencia de compra' : 'Stock suficiente'">
          <template #activator="{ props: tp }">
            <span
              v-bind="tp"
              class="font-weight-black text-h6"
              :style="parseFloat(item.solicitar) > 0 ? 'color:#28c76f' : parseFloat(item.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'"
            >
              {{ parseFloat(item.solicitar) > 0 ? '+' : '' }}{{ item.solicitar || 0 }}
            </span>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.row-needs td) {
  background-color: rgba(40, 199, 111, 4%) !important;
}

:deep(.row-excess td) {
  background-color: rgba(234, 84, 85, 4%) !important;
}

.text-xs {
  font-size: 0.75rem;
}
</style>

