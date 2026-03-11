<script setup>
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { ref } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  modelValue: { type: Object, default: null },
  searchQuery: { type: String, default: "" },
});

const emit = defineEmits([
  "update:options",
  "select-product",
  "update:modelValue",
  "delete",
  "save-analysis",
  "update:search-query",
  "open-filters"
]);

// Track edited pedido values per item id
const editedValues = ref({});

const getInputValue = (item) => {
  if (item.id in editedValues.value) return editedValues.value[item.id];
  // Precarga con el valor de análisis redondeado
  return roundIaAnalysis(item.solicitar ?? 0);
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
  { title: "Ventas", key: "total_sold_completed", sortable: true },
  { title: "Stock", key: "lote_quantity", sortable: true },
  { title: "AO", key: "totalQuantityInAutoOrder", sortable: true },
  { title: "Costo", key: "costs", sortable: false },
  { title: "Análisis", key: "solicitar", sortable: true },
  { title: "Pedido", key: "pedido", sortable: false },
  { title: "Acción", key: "actions", sortable: false },
];

const onRowClick = (event, { item }) => {
  if (event.target.closest("input")) return;
  emit("update:modelValue", item);
  emit("select-product", item);
};

const isSelected = (item) => props.modelValue && props.modelValue.id === item.id;
</script>

<template>
  <VCard>
    <!-- Header con título y producto seleccionado -->
    <VCardTitle class="d-flex align-center gap-2 pa-4">
      <VIcon icon="tabler-package-search" color="warning" size="20" />
      <span class="text-body-1 font-weight-semibold">Productos sin Asignar en el Pedido</span>
      <VSpacer />
      <VChip
        v-if="modelValue"
        color="info"
        variant="tonal"
        size="small"
      >
        Comparando: {{ modelValue.name }}
      </VChip>
      
      <VBtn
        color="primary"
        variant="tonal"
        size="small"
        prepend-icon="tabler-adjustments-horizontal"
        @click="emit('open-filters')"
      >
        Filtros IA
      </VBtn>
    </VCardTitle>

    <div class="px-4 pb-4">
      <VTextField
        :model-value="searchQuery"
        @update:model-value="(val) => emit('update:search-query', val)"
        placeholder="Buscar por Nombre, Código de Barras o Principio Activo"
        prepend-inner-icon="tabler-search"
        density="compact"
        class="mt-2"
        clearable
      />
    </div>

    <VAlert
      v-if="!modelValue"
      type="warning"
      variant="tonal"
      density="compact"
      class="mx-4 mb-3"
      :icon="false"
    >
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-hand-click" color="warning" size="16" />
        <span class="text-body-2">
          Haz clic en un producto para buscarlo en el catálogo de proveedores arriba
        </span>
      </div>
    </VAlert>

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
          class: isSelected(data.item)
            ? 'selected-row'
            : 'cursor-pointer',
        })
      "
    >
      <!-- Producto -->
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-2">
          <!-- Icono de selección activa -->
          <VIcon
            v-if="isSelected(item)"
            icon="tabler-arrows-exchange"
            color="primary"
            size="16"
            class="flex-shrink-0"
          />
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

      <!-- Análisis: con redondeo IA -->
      <template #item.solicitar="{ item }">
        <VChip
          :color="roundIaAnalysis(item.solicitar ?? 0) > 0 ? 'success' : roundIaAnalysis(item.solicitar ?? 0) < 0 ? 'error' : 'default'"
          size="small"
          variant="tonal"
        >
          {{ roundIaAnalysis(item.solicitar ?? 0) > 0 ? "+" : "" }}{{ roundIaAnalysis(item.solicitar ?? 0) }}
        </VChip>
      </template>

      <!-- Cód. Proveedor: barcode del proveedor con menor precio normal -->
      <template #item.cheapest_barcode="{ item }">
        <span
          v-if="item.cheapest_barcode"
          class="text-caption font-weight-medium text-wrap"
        >
          {{ item.cheapest_barcode }}
        </span>
        <span v-else class="text-disabled text-body-2">—</span>
      </template>

      <!-- Pedido: input editable precargado con el valor de análisis -->
      <template #item.pedido="{ item }">
        <VTextField
          :model-value="getInputValue(item)"
          density="compact"
          variant="underlined"
          hide-details
          type="number"
          class="compact-input-qty"
          style="width: 45px"
          @update:model-value="(val) => onInputChange(item, val)"
          @click.stop
        />
      </template>

      <template #item.costs="{ item }">
        <div class="d-flex flex-column text-body-2">
          <!-- Costo actual -->
          <span class="text-disabled text-xs">Actual</span>
          <span class="font-weight-medium">{{ item.current_unit_cost ?? "—" }}</span>
          <!-- Mejor proveedor -->
          <span class="text-disabled text-xs mt-1">Mejor oferta</span>
          <span
            class="font-weight-bold"
            :class="{
              'text-success':
                item.cheapest_unit_cost &&
                Number(item.cheapest_unit_cost) <
                  Number(item.current_unit_cost),
              'text-error':
                item.cheapest_unit_cost &&
                Number(item.cheapest_unit_cost) >
                  Number(item.current_unit_cost),
            }"
          >
            {{ item.cheapest_unit_cost ?? "—" }}
          </span>
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
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
  border-inline-start: 3px solid rgb(var(--v-theme-primary)) !important;
}

:deep(.selected-row td:first-child) {
  padding-inline-start: 13px !important; /* Compensar el borde izquierdo de 3px */
}

:deep(.cursor-pointer) {
  cursor: pointer;
}


.compact-input-qty :deep(.v-field__input) {
  padding: 4px 0 !important;
  text-align: center;
  font-size: 0.85rem;
  min-height: auto !important;
}

.text-caption {
  font-size: 0.72rem !important;
  line-height: 0.9rem !important;
}

.selected-row {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.cursor-pointer {
  cursor: pointer;
}

:deep(.cursor-pointer:hover) {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
