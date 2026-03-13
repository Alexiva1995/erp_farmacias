<script setup>
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
  <VCard class="mb-6 overflow-visible" variant="flat" border>
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="ID, Producto, C. Activo..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <!-- Búsqueda Estricta (Ahora afuera) -->
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

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <!-- Exportar (Menú Icono) -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                color="success"
                variant="tonal"
                size="38"
              >
                <VIcon icon="tabler-file-export" />
                <VTooltip activator="parent" location="top">Exportar Stock</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="emit('export-excel', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-csv" size="18" color="success" />
                </template>
                <VListItemTitle>Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export-pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" size="18" color="error" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros (Solo Icono) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense class="mb-2">
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
          </VRow>

          <VRow dense align="center">
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

            <VCol cols="12" sm="6" md="2">
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
                label="Próximos a Expirar"
                color="error"
                density="compact"
                hide-details
                @update:model-value="emit('update:expProd', $event)"
              />
              <VCheckbox
                :model-value="props.isColombian"
                label="Solo COL"
                color="info"
                density="compact"
                hide-details
                @update:model-value="emit('update:isColombian', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-x-4 { column-gap: 16px; }
</style>
</template>
