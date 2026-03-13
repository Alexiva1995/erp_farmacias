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
    <VCardText class="pa-4">
      <VRow align="center" no-gutters class="gap-3">
        <!-- Buscador Principal (Siempre visible) -->
        <VCol cols="12" md="4" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Producto, C. Activo..."
            prepend-inner-icon="tabler-search"
            clearable
            persistent-placeholder
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VCol class="d-flex gap-2 flex-wrap flex-md-nowrap align-center">
          <!-- Botón de Filtros Avanzados -->
          <VBtn
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            :prepend-icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'"
            @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
          >
            Filtros
          </VBtn>

          <VSpacer class="d-none d-md-block" />

          <!-- Acciones de Exportación -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                color="success"
                variant="flat"
                prepend-icon="tabler-file-export"
                v-bind="menuProps"
              >
                Exportar
              </VBtn>
            </template>
            <VList>
              <VListItem @click="emit('export-excel', 'xlsx')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
                </template>
                <VListItemTitle class="text-success">Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="emit('export-pdf')">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" class="me-2" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </VCol>
      </VRow>

      <!-- Panel de Filtros Avanzados (Colapsable) -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-4 border-opacity-10" />
          
          <VRow>
            <!-- Grupos de Filtros -->
            <VCol cols="12" sm="6" md="3">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                :loading="props.loading"
                label="Laboratorio"
                placeholder="Seleccionar"
                item-title="name"
                item-value="id"
                clearable
                density="compact"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.stockStatusFilter"
                label="Estado de Stock"
                :items="stockOptions"
                placeholder="Todos"
                clearable
                density="compact"
                @update:model-value="emit('update:stockStatusFilter', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.startDate"
                label="Desde"
                placeholder="YYYY-MM-DD"
                clearable
                density="compact"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.endDate"
                label="Hasta"
                placeholder="YYYY-MM-DD"
                clearable
                density="compact"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>

            <!-- Filtros de Proyección de Stock -->
            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.stock"
                label="Stock Nivel"
                :items="stockOptionsList"
                clearable
                density="compact"
                @update:model-value="emit('update:stock', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.days"
                label="Días Proyección"
                :items="diasVencimientos"
                density="compact"
                @update:model-value="emit('update:days', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.tipoFiltracion"
                label="Cálculo Por"
                :items="tipoFiltracionOpcion"
                density="compact"
                @update:model-value="emit('update:tipoFiltracion', $event)"
              />
            </VCol>

            <!-- Checkboxes de Configuración -->
            <VCol cols="12" md="6" class="d-flex flex-wrap align-center gap-x-4">
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
              <VCheckbox
                :model-value="props.isStrictSearch"
                label="Búsqueda Estricta"
                color="primary"
                density="compact"
                hide-details
                @update:model-value="emit('update:isStrictSearch', $event)"
              />
            </VCol>

            <VCol cols="12" class="d-flex justify-end gap-2 mt-2">
              <VBtn 
                color="secondary" 
                variant="outlined" 
                size="small" 
                prepend-icon="tabler-eraser"
                @click="handleClear"
              >
                Limpiar
              </VBtn>
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
