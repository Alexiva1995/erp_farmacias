<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  expProd: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  days: [String, Number, null],
  stock: [String, null],
  isStrictSearch: { type: Boolean, default: false },
  tipoFiltracion: { type: String, default: "average" },
  isColombian: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "update:expProd",
  "update:isStrictSearch",
  "update:tipoFiltracion",
  "update:isColombian",
  "update:stock",
  "update:days",
  "clear",
  "add-product",
  "sort",
  "export-pdf",
  "export-excel",
]);

const isAdvancedFiltersVisible = ref(false);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const stockOptionsList = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos", value: "all" },
];

const diasVencimientos = [
  { title: "7 días", value: 7 },
  { title: "15 días", value: 15 },
  { title: "30 días", value: 30 },
  { title: "60 días", value: 60 },
  { title: "90 días", value: 90 },
];

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const handleClear = () => {
  emit("clear");
};
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="isAdvancedFiltersVisible || !!(props.selectedLaboratory || props.stockStatusFilter !== null || props.startDate || props.endDate || props.stock || props.days || props.tipoFiltracion !== 'average' || props.expProd || props.isColombian)"
    :show-export="true"
    search-placeholder="ID, Producto, C. Activo..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="handleClear"
    @export="ext => ext === 'xlsx' ? emit('export-excel', ext) : emit('export-pdf')"
  >
    <template #search-extra>
      <!-- Búsqueda Estricta -->
      <VCol cols="auto" class="d-none d-sm-flex">
        <VCheckbox
          :model-value="props.isStrictSearch"
          label="Estricta"
          color="primary"
          density="compact"
          hide-details
          @update:model-value="emit('update:isStrictSearch', $event)"
        />
      </VCol>
    </template>

    <template #advanced-filters>
      <!-- Filtros Primera Fila -->
      <VCol cols="12" sm="6" md="3">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          placeholder="Laboratorio"
          item-title="name"
          item-value="id"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-flask"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.stockStatusFilter"
          placeholder="Estado Stock"
          :items="stockOptions"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-package"
          @update:model-value="emit('update:stockStatusFilter', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>

      <!-- Filtros Segunda Fila -->
      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.stock"
          placeholder="Nivel Stock"
          :items="stockOptionsList"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-chart-bar"
          @update:model-value="emit('update:stock', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VSelect
          :model-value="props.days"
          placeholder="Días Proyección"
          :items="diasVencimientos"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-clock"
          @update:model-value="emit('update:days', $event)"
        />
      </VCol>

      <VCol cols="12" sm="12" md="2">
        <VSelect
          :model-value="props.tipoFiltracion"
          placeholder="Cálculo Por"
          :items="tipoFiltracionOpcion"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calculator"
          @update:model-value="emit('update:tipoFiltracion', $event)"
        />
      </VCol>

      <VCol cols="12" md="6" class="d-flex flex-wrap align-center gap-x-3 ps-4">
        <VCheckbox
          :model-value="props.expProd"
          label="Prox. Exp."
          color="error"
          density="compact"
          hide-details
          @update:model-value="emit('update:expProd', $event)"
        />
        <VCheckbox
          :model-value="props.isColombian"
          label="COL"
          color="info"
          density="compact"
          hide-details
          @update:model-value="emit('update:isColombian', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-x-4 { column-gap: 16px; }
</style>
