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
  { title: "Costo", key: "sale_price", sortable: true },
  { title: "Ventas", key: "total_sold_completed", sortable: true },
  { title: "Stock", key: "stock", sortable: true },
  {
    title: "Preferencia",
    key: "preferencia_product",
    sortable: true,
    value: (item) =>
      item.preferencia_product != "" && item.preferencia_product != null
        ? parseFloat(item.preferencia_product).toFixed(2)
        : 0,
  },
  {
    title: "Promedio",
    key: "promedio_calculado",
    sortable: true,
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "Diferencia ",
    key: "diferencia_product",
    sortable: true,
    value: (item) =>
      item.diferencia_product != "" && item.diferencia_product != null
        ? parseFloat(item.diferencia_product).toFixed(2)
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
    </VDataTableServer>
  </VCard>
</template>
