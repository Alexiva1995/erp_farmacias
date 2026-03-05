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
  { title: "ID", key: "id", sortable: true, width: '70px' },
  { title: "Producto", key: "name", sortable: true, minWidth: '250px' },
  { title: "Laboratorio", key: "laboratory.name", sortable: false, width: '150px' },
  { title: "Costos (Min-Max-U)", key: "unit_cost", sortable: false, align: 'center', width: '200px' },
  { title: "Mejor Oferta", key: "product_suppliers", sortable: false, width: '180px' },
  { title: "Ventas", key: "total_sold_completed", sortable: true, align: 'end', width: '100px' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'end', width: '100px' },
  { 
    title: "Promedio", 
    key: "promedio_calculado", 
    sortable: true, 
    align: 'end', 
    width: '110px' 
  },
  { title: "En Pedido", key: "totalQuantityInAutoOrder", sortable: true, align: 'end', width: '110px' },
  { title: "Análisis (u)", key: "solicitar", sortable: true, align: 'end', width: '120px' },
];

// Determina el color de fondo por fila (Light mode friendly)
function rowClass(item) {
  const val = parseFloat(item.solicitar);
  if (val > 0) return 'row-needs';
  if (val < 0) return 'row-excess';
  return '';
}
</script>

<template>
  <VCard variant="outlined" class="rounded-lg">
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
        <div class="d-flex flex-column py-1" style="max-inline-size: 250px;">
          <span
            class="text-body-2 font-weight-medium text-high-emphasis text-truncate"
            :class="{ 'text-primary': item.psychotropic == 1 }"
            :title="item.name"
          >
            {{ item.name }}
          </span>
          <span class="text-caption text-disabled text-truncate">
            {{ item.active_ingredient }}
            <span v-if="item.is_colombian_origin == 1" class="text-info font-weight-bold ml-1">(COL)</span>
          </span>
        </div>
      </template>

      <!-- Costos (Min - Max - Unit) -->
      <template #item.unit_cost="{ item }">
        <div class="d-flex align-center justify-center gap-1">
          <VTooltip text="Costo Mínimo">
            <template #activator="{ props: tp }">
              <span v-bind="tp" class="text-success text-xs font-weight-medium">${{ Number(item.cost_min || 0).toFixed(1) }}</span>
            </template>
          </VTooltip>
          <span class="text-disabled">/</span>
          <VTooltip text="Costo Máximo">
            <template #activator="{ props: tp }">
              <span v-bind="tp" class="text-error text-xs font-weight-medium">${{ Number(item.cost_max || 0).toFixed(1) }}</span>
            </template>
          </VTooltip>
          <span class="text-disabled">/</span>
          <VTooltip text="Costo Último">
            <template #activator="{ props: tp }">
              <span v-bind="tp" class="text-primary font-weight-bold">${{ Number(item.unit_cost || 0).toFixed(2) }}</span>
            </template>
          </VTooltip>
        </div>
      </template>

      <!-- Mejor Oferta -->
      <template #item.product_suppliers="{ item }">
        <div v-if="item.product_suppliers && item.product_suppliers.length > 0" class="d-flex flex-column">
          <span class="text-body-2 font-weight-bold text-success">
            ${{ Number(item.product_suppliers[0].unit_cost_usd_with_discount || 0).toFixed(2) }}
          </span>
          <span class="text-xs text-disabled text-truncate" style="max-inline-size: 150px;">
            {{ item.product_suppliers[0].supplier.name }}
          </span>
        </div>
        <span v-else class="text-caption text-disabled italic">Sin proveedor</span>
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

