<script setup>
import { computed } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  selectedLaboratory: { type: Array, default: () => [] },
  selectProducts: { type: Array, default: () => [] },
  excludeSupplierIds: { type: Array, default: () => [] },
  tipoFiltracion: { type: String, default: "combinado" },
  lapsoTiempo: { type: String, default: "3 month" },
  stockFilter: { type: String, default: "all" },
  withDiscount: { type: Boolean, default: false },
  hideRedundant: { type: Boolean, default: true },
  hideDuplicates: { type: Boolean, default: true },
  laboratories: { type: Array, default: () => [] },
  productosSelect: { type: Array, default: () => [] },
  suppliers: { type: Array, default: () => [] },
  exportLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectProducts",
  "update:excludeSupplierIds",
  "update:tipoFiltracion",
  "update:lapsoTiempo",
  "update:stockFilter",
  "update:withDiscount",
  "update:hideRedundant",
  "update:hideDuplicates",
  "clear",
  "export",
]);

const searchModel = computed({
  get: () => props.searchQuery,
  set: (val) => emit("update:searchQuery", val),
});

const selectedLabModel = computed({
  get: () => props.selectedLaboratory,
  set: (val) => emit("update:selectedLaboratory", val),
});

const selectProdModel = computed({
  get: () => props.selectProducts,
  set: (val) => emit("update:selectProducts", val),
});

const excludeSupplierModel = computed({
  get: () => props.excludeSupplierIds,
  set: (val) => emit("update:excludeSupplierIds", val),
});

const tipoFiltracionModel = computed({
  get: () => props.tipoFiltracion,
  set: (val) => emit("update:tipoFiltracion", val),
});

const lapsoTiempoModel = computed({
  get: () => props.lapsoTiempo,
  set: (val) => emit("update:lapsoTiempo", val),
});

const stockFilterModel = computed({
  get: () => props.stockFilter,
  set: (val) => emit("update:stockFilter", val),
});

const withDiscountModel = computed({
  get: () => props.withDiscount,
  set: (val) => emit("update:withDiscount", val),
});

const hideRedundantModel = computed({
  get: () => props.hideRedundant,
  set: (val) => emit("update:hideRedundant", val),
});

const hideDuplicatesModel = computed({
  get: () => props.hideDuplicates,
  set: (val) => emit("update:hideDuplicates", val),
});

const hasActiveAdvancedFilters = computed(
  () =>
    props.selectedLaboratory?.length > 0 ||
    props.selectProducts?.length > 0 ||
    props.excludeSupplierIds?.length > 0 ||
    props.tipoFiltracion !== "combinado" ||
    props.lapsoTiempo !== "3 month" ||
    props.stockFilter !== "all",
);
</script>

<template>
  <AppFilterBase
    v-model:search="searchModel"
    :has-advanced-filters="hasActiveAdvancedFilters"
    search-placeholder="Buscar por nombre o barcode..."
    show-export
    :export-loading="exportLoading"
    class="py-1 mb-4"
    @clear="emit('clear')"
    @export="emit('export')"
  >
    <template #advanced-filters>
      <!-- Selección de Producto(s) -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          v-model="selectProdModel"
          :items="productosSelect"
          placeholder="Seleccionar Producto(s)"
          item-title="name"
          item-value="id"
          multiple
          chips
          closable-chips
          clearable
          hide-details
          density="compact"
          variant="outlined"
          class="premium-select-compact"
          prepend-inner-icon="tabler-package"
        />
      </VCol>

      <!-- Selección de Laboratorio(s) -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          v-model="selectedLabModel"
          :items="laboratories"
          placeholder="Seleccionar Laboratorio(s)"
          item-title="name"
          item-value="id"
          multiple
          chips
          closable-chips
          clearable
          hide-details
          density="compact"
          variant="outlined"
          class="premium-select-compact"
          prepend-inner-icon="tabler-flask"
        />
      </VCol>

      <!-- Excluir Proveedor(es) -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          v-model="excludeSupplierModel"
          :items="suppliers"
          placeholder="Excluir Proveedor(es)"
          item-title="name"
          item-value="id"
          multiple
          chips
          closable-chips
          clearable
          hide-details
          density="compact"
          variant="outlined"
          class="premium-select-compact"
          prepend-inner-icon="tabler-user-minus"
        />
      </VCol>

      <!-- Filtro Calcular Por -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          v-model="tipoFiltracionModel"
          label="Calcular por"
          :items="[
            { title: 'Combinado', value: 'combinado' },
            { title: 'Solo Promedio', value: 'average' },
            { title: 'Solo Ventas', value: 'sales' },
          ]"
          hide-details
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-calculator"
        />
      </VCol>

      <!-- Lapso de Tiempo -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          v-model="lapsoTiempoModel"
          label="Lapso de tiempo"
          :items="[
            { title: '15 Días', value: '15 days' },
            { title: '1 Mes', value: '1 month' },
            { title: '3 Meses', value: '3 month' },
            { title: '6 Meses', value: '6 month' },
            { title: '12 Meses', value: '12 month' },
            { title: '18 Meses', value: '18 month' },
            { title: '24 Meses', value: '24 month' },
          ]"
          hide-details
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-calendar-time"
        />
      </VCol>

      <!-- Estado Stock -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          v-model="stockFilterModel"
          label="Estado Stock"
          :items="[
            { title: 'Todo', value: 'all' },
            { title: 'Fallas (Necesitan)', value: 'fallas' },
            { title: 'Exceso', value: 'exceso' },
          ]"
          hide-details
          density="compact"
          variant="outlined"
          prepend-inner-icon="tabler-box"
        />
      </VCol>

      <!-- Toggles -->
      <VCol cols="12" md="6" class="d-flex align-center flex-wrap ga-4 py-0">
        <VSwitch
          v-model="withDiscountModel"
          label="Desc."
          hide-details
          density="compact"
          color="primary"
          inset
        />
        <VSwitch
          v-model="hideRedundantModel"
          label="Redundantes"
          hide-details
          density="compact"
          color="primary"
          inset
        />
        <VSwitch
          v-model="hideDuplicatesModel"
          label="Mejor Oferta"
          hide-details
          density="compact"
          color="primary"
          inset
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
