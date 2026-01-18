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
  modelValue: { type: Object, default: null }
});

const emit = defineEmits(['update:options','select-product', 'update:modelValue']);

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
    title: "Precio Venta", 
    key: "sale_price", 
    align: 'center',
    value: item => `$${parseFloat(item.sale_price || 0).toFixed(2)}`
  },
];

const onRowClick = (event, { item }) => {
  emit('update:modelValue', item);
  emit('select-product', item);
};
</script>

<template>
<VCard :subtitle="modelValue ? 'Producto seleccionado: ' + modelValue.name : 'Selecciona un producto de esta lista para comparar'">
    <VDataTableServer
        :headers="headers"
        :items="products"
        :items-length="totalProducts"
        :items-per-page="itemsPerPage"
        :page="page"
        :loading="loading"
        @update:options="emit('update:options', $event)"
        @click:row="onRowClick"
        :row-props="(data) => ({
        class: modelValue && modelValue.id === data.item.id ? 'bg-primary-lighten-4 selected-row' : 'cursor-pointer'
      })"
    >
    </VDataTableServer>
  </VCard>
</template>
<style scoped>
:deep(.selected-row) {
  background-color: rgba(var(--v-theme-primary), 0.15) !important;
  font-weight: bold;
  box-shadow: inset 0 0 0 2px rgb(var(--v-theme-primary)) !important;
}

:deep(.cursor-pointer) {
  cursor: pointer;
}
:deep(.cursor-pointer:hover) {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}
</style>


