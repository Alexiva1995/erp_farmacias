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

const emit = defineEmits(['update:options']);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory.name", sortable: true },
  { 
    title: "Costo Unit.", 
    key: "unit_cost", 
    align: 'center',
    value: item => `$${parseFloat(item.unit_cost || 0).toFixed(2)}`
  },
  { 
    title: "Stock Faltante", 
    key: "stockFaltante", 
    align: 'center',
    value: item => Math.abs(item.stockFaltante || 0) // El valor de 'solicitar' suele venir negativo
  },
  { 
    title: "Precio Venta", 
    key: "sale_price", 
    align: 'center',
    value: item => `$${parseFloat(item.sale_price || 0).toFixed(2)}`
  },
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
        @update:options="emit('update:options', $event)"
    >
    </VDataTableServer>
  </VCard>
</template>


