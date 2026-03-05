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
  { title: "ID", key: "id", sortable: true, width: '60px' },
  { title: "Producto", key: "name", sortable: true, minWidth: '220px' },
  { title: "Laboratorio", key: "laboratory.name", sortable: false, width: '140px' },
  { title: "Costo", key: "unit_cost", sortable: true, align: 'end', width: '100px' },
  { title: "Ventas", key: "total_sold_completed", sortable: true, align: 'end', width: '90px' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'end', width: '90px' },
  { title: "Preferencia", key: "preferencia_product", sortable: true, align: 'end', width: '110px' },
  { title: "Promedio", key: "promedio_calculado", sortable: true, align: 'end', width: '100px' },
  { title: "En Pedido", key: "totalQuantityInAutoOrder", sortable: true, align: 'end', width: '100px' },
  { title: "Análisis (u)", key: "solicitar", sortable: true, align: 'end', width: '110px' },
];

// Determina el color de fondo por fila
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
          <span class="text-body-1 font-weight-medium">No hay productos que coincidan con los filtros</span>
          <span class="text-caption mt-1">Ajusta los filtros de laboratorio, grupo o lapso de tiempo</span>
        </div>
      </template>

      <!-- Producto -->
      <template #item.name="{ item }">
        <div class="d-flex flex-column py-1" style="max-inline-size: 220px;">
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

      <!-- Costo -->
      <template #item.unit_cost="{ item }">
        <span class="font-weight-medium">
          ${{ Number(item.unit_cost || 0).toFixed(2) }}
        </span>
      </template>

      <!-- Preferencia con tooltip -->
      <template #item.preferencia_product="{ item }">
        <VTooltip text="Unidades de preferencia histórica del proveedor">
          <template #activator="{ props: tp }">
            <span v-bind="tp">{{ item.preferencia_product ? parseFloat(item.preferencia_product).toFixed(2) : '—' }}</span>
          </template>
        </VTooltip>
      </template>

      <!-- Promedio con tooltip -->
      <template #item.promedio_calculado="{ item }">
        <VTooltip text="Promedio de ventas en el lapso de tiempo seleccionado">
          <template #activator="{ props: tp }">
            <span v-bind="tp">{{ item.promedio_calculado ? parseFloat(item.promedio_calculado).toFixed(2) : '—' }}</span>
          </template>
        </VTooltip>
      </template>

      <!-- En Pedido con tooltip -->
      <template #item.totalQuantityInAutoOrder="{ item }">
        <VTooltip text="Unidades ya incluidas en otras órdenes de compra activas">
          <template #activator="{ props: tp }">
            <VChip
              v-bind="tp"
              :color="item.totalQuantityInAutoOrder > 0 ? 'info' : 'default'"
              variant="tonal"
              size="small"
            >
              {{ item.totalQuantityInAutoOrder || 0 }}
            </VChip>
          </template>
        </VTooltip>
      </template>

      <!-- Análisis -->
      <template #item.solicitar="{ item }">
        <VTooltip :text="parseFloat(item.solicitar) > 0 ? 'Unidades sugeridas a reponer' : parseFloat(item.solicitar) < 0 ? 'Exceso: no se necesita comprar' : 'Stock suficiente'">
          <template #activator="{ props: tp }">
            <span
              v-bind="tp"
              class="font-weight-black"
              :style="parseFloat(item.solicitar) > 0 ? 'color:#28c76f' : parseFloat(item.solicitar) < 0 ? 'color:#ea5455' : 'color:inherit'"
            >
              {{ parseFloat(item.solicitar) > 0 ? '+' : '' }}{{ item.solicitar || 0 }} u.
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

.legend-dot {
  display: inline-block;
  border-radius: 50%;
  block-size: 10px;
  inline-size: 10px;
}
.legend-needs { background: rgba(40, 199, 111, 40%); }
.legend-excess { background: rgba(234, 84, 85, 40%); }
</style>
