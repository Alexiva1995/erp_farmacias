<script setup>
import ReturnsOrderTable from "@/components/ReturnsOrderTable.vue";

const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'N° Order', key: 'id', sortable: true },
  { title: 'Usuario', key: 'id', sortable: true },
  { title: 'Identificación', key: 'id', sortable: true },
  { title: 'Monto', key: 'sale_price', sortable: true },
  { title: 'Fecha', key: 'sale_price', sortable: true },
  { title: 'Producto', key: 'name',  sortable: true},
];

</script>

<template>

      <ReturnsOrderTable
      :orders="orders"
      :loading="loading"
      :total-order="totalOrder"
      :items-per-page="itemsPerPage"
      :page="page"
      :headers="headers"
      @update:options="updateTableOptions"
    />

</template>
