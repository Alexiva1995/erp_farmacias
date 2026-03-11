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
  { title: "ID / Producto / Lab.", key: "name", sortable: true },
  { title: "Costo Actual", key: "costs", sortable: false },
  { title: "Análisis", key: "solicitar", sortable: true },
  { title: "Acción", key: "actions", sortable: false },
];

const onRowClick = (event, { item }) => {
  if (event.target.closest("input")) return;

  // Toggle selección: si ya está seleccionado, deseleccionar (Solicitud V16.2)
  if (props.modelValue && props.modelValue.id === item.id) {
    emit("update:modelValue", null);
    emit("select-product", null);
  } else {
    emit("update:modelValue", item);
    emit("select-product", item);
  }
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
              class="text-caption font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.psychotropic == 1 }"
            >
              #{{ item.id }} - {{ item.name }}
              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>
            <span class="text-xs text-disabled">
              {{ item.laboratory?.name }}
            </span>
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

      <template #item.cheapest_barcode="{ item }">
        <span
          class="text-caption font-weight-bold text-uppercase"
          style="letter-spacing: 0.5px;"
        >
          {{ item.name?.substring(0, 5) }} {{ item.laboratory?.name?.substring(0, 3) }}
        </span>
      </template>

      </template>

      <template #item.costs="{ item }">
        <div class="d-flex flex-column text-body-2">
          <span class="font-weight-medium">{{ item.current_unit_cost ?? "—" }}</span>
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
  font-size: 0.85rem;
  min-block-size: auto !important;
  padding-block: 4px !important;
  padding-inline: 0 !important;
  text-align: center;
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

.text-xs {
  font-size: 0.68rem !important;
  line-height: 0.8rem !important;
}
</style>
