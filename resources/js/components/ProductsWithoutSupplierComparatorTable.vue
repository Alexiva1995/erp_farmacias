<script setup>
import { ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory", sortable: true },
  {
    title: "Stock",
    key: "valid_stock",
    visible: true,
    sortable: true,
    value: (item) => {
      return item.stock_calculado;
    },
  },
  { title: "Exp.", key: "next_expiration", sortable: true },
  { title: "Origen", key: "origin", sortable: true },
];
</script>

<template>
<VCard>
    <VDataTableServer
        :headers="headers"
        :items="products"
        :items-length="totalProducts"
        :items-per-page="itemsPerPage"
        :page="page"
        :loading="loading"
    >
    </VDataTableServer>
  </VCard>
</template>


