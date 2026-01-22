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

const emit = defineEmits(['update:options','select-product', 'update:modelValue', 'delete',]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true },
  { title: "Laboratorio", key: "laboratory.name", sortable: true },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Ventas", key: "total_sold_completed", sortable: true },
  { title: "Stock", key: "lote_quantity", sortable: true },
  {
    title: "Pref",
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
    title: "AO",
    key: "totalQuantityInAutoOrder",
    sortable: true,
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
  { title: "Acción", key: "actions", sortable: false },
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
      
      <template #item.actions="{ item }">
        <div class="d-flex gap-2">
             <IconBtn @click="emit('delete', item)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
        </div>
      </template>
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


