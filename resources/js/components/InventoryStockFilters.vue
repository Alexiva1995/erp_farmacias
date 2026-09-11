<script setup>
import AppFilterBase from "@/components/AppFilterBase.vue";
import { useAuthStore } from "@/stores/auth";
import { computed, ref } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Array, Number, String, null],
  stockStatusFilter: [Boolean, null],
  expProd: [Boolean, null],
  viewType: { type: String, default: "individual" },
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  days: [String, Number, null],
  stock: [String, null],
  isStrictSearch: { type: Boolean, default: false },
  tipoFiltracion: { type: String, default: "weighted" },
  isColombian: { type: Boolean, default: false },
  isExportingPdf: { type: Boolean, default: false },
  isExportingExcel: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:stockStatusFilter",
  "update:viewType",
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

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => false);

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

const tipoFiltracionOpcion = computed(() => [
  { title: "Ponderado (Óptimo ROP)", value: "weighted" },
  { title: "Promedio", value: "average" },
  { title: isRestaurant.value ? "Consumido" : "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
]);

const viewTypeOptions = [
  { title: "Individual", value: "individual" },
  { title: "Grupal", value: "group" },
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
    :has-advanced-filters="isAdvancedFiltersVisible || !!(props.selectedLaboratory || props.stockStatusFilter !== null || props.stock || props.days || props.tipoFiltracion !== 'weighted' || props.expProd || props.isColombian || props.isStrictSearch)"
    :show-export="true"
    :export-loading="props.isExportingPdf || props.isExportingExcel"
    search-placeholder="ID, Producto, C. Activo..."
    class="py-1"
    @update:search="emit('update:searchQuery', $event)"
    @clear="handleClear"
    @export="ext => ext === 'xlsx' ? emit('export-excel', ext) : emit('export-pdf')"
  >
    <template #search-extra>
      <!-- Búsqueda Estricta Rápida en barra principal -->
      <VCol cols="auto" class="d-none d-lg-flex">
        <VChip
          filter
          :color="props.isStrictSearch ? 'primary' : undefined"
          :variant="props.isStrictSearch ? 'flat' : 'tonal'"
          size="small"
          class="cursor-pointer font-weight-medium"
          @click="emit('update:isStrictSearch', !props.isStrictSearch)"
        >
          <VIcon start icon="tabler-zoom-check" size="14" />
          Estricta
          <VTooltip activator="parent" location="top">Búsqueda exacta por coincidencia estricta</VTooltip>
        </VChip>
      </VCol>
    </template>

    <template #advanced-filters>
      <!-- ── FILA 1: Jerarquía y Estado de Stock ────────────────────────── -->
      <!-- Laboratorio / Marca -->
      <VCol cols="12" sm="6" md="4">
        <VAutocomplete
          :model-value="props.selectedLaboratory"
          :items="props.laboratories"
          :loading="props.loading"
          :placeholder="isRestaurant ? 'Marca' : 'Laboratorio'"
          item-title="name"
          item-value="id"
          clearable
          multiple
          chips
          closable-chips
          density="compact"
          hide-details
          :prepend-inner-icon="isRestaurant ? 'tabler-tags' : 'tabler-flask'"
          @update:model-value="emit('update:selectedLaboratory', $event)"
        />
      </VCol>

      <!-- Estado Stock (Con / Sin Stock) -->
      <VCol cols="12" sm="6" md="4">
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

      <!-- Nivel Stock (Exceso / Fallas / Todos) -->
      <VCol cols="12" sm="6" md="4">
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

      <!-- ── FILA 2: Parámetros de Visualización y Cálculo ──────────────── -->
      <!-- Vista (Individual / Grupal) -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.viewType"
          :items="viewTypeOptions"
          placeholder="Vista"
          density="compact"
          hide-details
          prepend-inner-icon="tabler-layout-grid"
          @update:model-value="emit('update:viewType', $event)"
        />
      </VCol>

      <!-- Días Proyección -->
      <VCol cols="12" sm="6" md="4">
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

      <!-- Método de Cálculo -->
      <VCol cols="12" sm="6" md="4">
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

      <!-- ── FILA 3: Filtros Rápidos (Chips Interactivos) ──────────────── -->
      <VCol cols="12">
        <div class="d-flex align-center flex-wrap gap-2 pt-1">
          <span class="text-caption text-medium-emphasis font-weight-medium me-1">Filtros Rápidos:</span>

          <!-- Chip Búsqueda Estricta (Móvil y Desktop) -->
          <VChip
            filter
            :color="props.isStrictSearch ? 'primary' : undefined"
            :variant="props.isStrictSearch ? 'flat' : 'tonal'"
            size="small"
            class="cursor-pointer font-weight-medium"
            @click="emit('update:isStrictSearch', !props.isStrictSearch)"
          >
            <VIcon start icon="tabler-zoom-check" size="14" />
            Búsqueda Estricta
            <VTooltip activator="parent" location="top">Búsqueda exacta de producto</VTooltip>
          </VChip>

          <!-- Chip Próximos a Vencer -->
          <VChip
            filter
            :color="props.expProd ? 'error' : undefined"
            :variant="props.expProd ? 'flat' : 'tonal'"
            size="small"
            class="cursor-pointer font-weight-medium"
            @click="emit('update:expProd', !props.expProd)"
          >
            <VIcon start icon="tabler-calendar-time" size="14" />
            Próximos a Vencer
            <VTooltip activator="parent" location="top">Filtrar lotes y productos próximos a expirar</VTooltip>
          </VChip>

          <!-- Chip Origen Colombia (COL) -->
          <VChip
            filter
            :color="props.isColombian ? 'info' : undefined"
            :variant="props.isColombian ? 'flat' : 'tonal'"
            size="small"
            class="cursor-pointer font-weight-medium"
            @click="emit('update:isColombian', !props.isColombian)"
          >
            <VIcon start icon="tabler-flag" size="14" />
            Colombia (COL)
            <VTooltip activator="parent" location="top">Filtrar solo productos de origen Colombia</VTooltip>
          </VChip>
        </div>
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-x-4 { column-gap: 16px; }
</style>
