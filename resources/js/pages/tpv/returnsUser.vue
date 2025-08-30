<script setup>
import ReturnsClientCard from "@/components/cards/ReturnsClientCard.vue";
import ReturnsOrderTable from "@/components/ReturnsOrderTable.vue";


const headers = [
  { title: 'N° Order', key: 'id', sortable: true },
  { title: 'Monto', key: 'sale_price', sortable: true },
  { title: 'Producto', key: 'name',  sortable: true},
  { title: 'Acción', key: 'action', sortable: false, width: '150px'},
];

</script>

<template>
 <ReturnsClientCard
        v-model="clientIdentification"
        @verify-client-order="verifyClientOrder"
      />

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
