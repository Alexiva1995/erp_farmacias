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
  { title: "Costo Min", key: "cost_min", sortable: true },
  { title: "Costo Max", key: "cost_max", sortable: true },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Ventas", key: "total_sold_completed", sortable: true },
  { title: "Stock", key: "lote_quantity", sortable: true },
  {
    title: "Prom.",
    key: "promedio_calculado",
    sortable: true,
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "AO",
    key: "totalQuantityInAutoOrder",
    sortable: false,
  },
  {
    title: "Análisis",
    key: "solicitar",
    sortable: true,
    value: (item) =>
      item.solicitar != "" && item.solicitar != null
        ? parseFloat(item.solicitar).toFixed(2)
        : 0,
  },
];
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
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.psychotropic == 1 }"
            >
              {{ item.name }}

              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>

            <span class="text-sm text-disabled">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>
      <template #item.solicitar="{ item }">
        <span :style="item.solicitar > 0 ? 'color:#28c76f;' : 'color:#dd4d4f;'"
          >{{ item.solicitar > 0 ? "+" : "" }}{{ item.solicitar }}</span
        >
      </template>
    </VDataTableServer>
  </VCard>
</template>
