<script setup>
import { ref } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  modelValue: { type: Object, default: null },
});

const emit = defineEmits([
  "update:options",
  "select-product",
  "update:modelValue",
  "delete",
  "save-analysis",
  "mark-scarce",
]);

// Track edited pedido values per item id
const editedValues = ref({});

const getInputValue = (item) => {
  if (item.id in editedValues.value) return editedValues.value[item.id];
  // Precarga con el valor de análisis
  return item.solicitar ?? 0;
};

const onInputChange = (item, val) => {
  editedValues.value[item.id] = val === "" || val === null ? 0 : Number(val);
};

const isDirty = (item) => {
  if (!(item.id in editedValues.value)) return false;
  return editedValues.value[item.id] !== (item.solicitar ?? 0);
};

const handleSave = (item) => {
  emit("save-analysis", { item, newValue: editedValues.value[item.id] });
  delete editedValues.value[item.id];
};

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
      item.preferencia_product != null && item.preferencia_product !== ""
        ? parseFloat(item.preferencia_product).toFixed(2)
        : 0,
  },
  {
    title: "Promedio",
    key: "promedio_calculado",
    sortable: true,
    value: (item) =>
      item.promedio_calculado != null && item.promedio_calculado !== ""
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  { title: "AO", key: "totalQuantityInAutoOrder", sortable: true },
  { title: "Costo (Inv - Prov)", key: "costs", sortable: false },
  { title: "Cód. Proveedor", key: "cheapest_barcode", sortable: false },
  { title: "Análisis", key: "solicitar", sortable: true },
  { title: "Pedido", key: "pedido", sortable: false },
  { title: "Acción", key: "actions", sortable: false },
];

const onRowClick = (event, { item }) => {
  if (event.target.closest("input")) return;
  emit("update:modelValue", item);
  emit("select-product", item);
};
</script>

<template>
  <VCard
    :subtitle="
      modelValue
        ? 'Producto seleccionado: ' + modelValue.name
        : 'Selecciona un producto de esta lista para comparar'
    "
  >
    <VDataTableServer
      :headers="headers"
      :items="products"
      :items-length="totalProducts"
      :items-per-page="itemsPerPage"
      :page="page"
      :loading="loading"
      @update:options="emit('update:options', $event)"
      @click:row="onRowClick"
      :row-props="
        (data) => ({
          class:
            modelValue && modelValue.id === data.item.id
              ? 'bg-primary-lighten-4 selected-row'
              : 'cursor-pointer',
        })
      "
    >
      <!-- Producto -->
      <template #item.name="{ item }">
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
      </template>

      <!-- Análisis: solo lectura con color -->
      <template #item.solicitar="{ item }">
        <span :style="item.solicitar > 0 ? 'color:#28c76f' : 'color:#dd4d4f'">
          {{ item.solicitar > 0 ? "+" : "" }}{{ item.solicitar }}
        </span>
      </template>

      <!-- Cód. Proveedor: barcode del proveedor con menor precio normal -->
      <template #item.cheapest_barcode="{ item }">
        <span
          v-if="item.cheapest_barcode"
          class="text-body-2 font-weight-medium"
        >
          {{ item.cheapest_barcode }}
        </span>
        <span v-else class="text-disabled text-body-2">—</span>
      </template>

      <!-- Pedido: input editable precargado con el valor de análisis -->
      <template #item.pedido="{ item }">
        <VTextField
          :model-value="getInputValue(item)"
          type="number"
          density="compact"
          variant="outlined"
          hide-details
          style="min-width: 90px; max-width: 110px"
          @update:model-value="onInputChange(item, $event)"
          @click.stop
        />
      </template>

      <template #item.costs="{ item }">
        <div class="text-body-2">
          {{ item.current_unit_cost ?? "0" }} -
          <span class="font-weight-bold">{{
            item.cheapest_unit_cost ?? "—"
          }}</span>
        </div>
      </template>

      <!-- Acciones -->
      <template #item.actions="{ item }">
        <div class="d-flex gap-1 align-center">
          <!-- Check: Solo si hay código de barras -->
          <IconBtn
            v-if="item.cheapest_barcode"
            color="success"
            @click.stop="
              emit('save-analysis', {
                item,
                newValue: getInputValue(item),
              })
            "
          >
            <VIcon icon="tabler-check" />
            <VTooltip activator="parent" location="top">Pedir Directo</VTooltip>
          </IconBtn>

          <!-- Escaso -->
          <IconBtn
            :color="item.is_scarce ? 'error' : 'warning'"
            @click.stop="emit('mark-scarce', item)"
          >
            <VIcon
              :icon="
                item.is_scarce ? 'tabler-alert-circle' : 'tabler-alert-triangle'
              "
            />
            <VTooltip activator="parent" location="top">
              {{
                item.is_scarce ? "Quitar marca de escaso" : "Marcar como escaso"
              }}
            </VTooltip>
          </IconBtn>

          <!-- Borrar -->
          <IconBtn @click.stop="emit('delete', item)">
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent" location="top"
              >Quitar de la lista</VTooltip
            >
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
