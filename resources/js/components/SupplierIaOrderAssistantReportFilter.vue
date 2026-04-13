<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  checkColombia: { type: Boolean, required: true },
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  laboratories: { type: Array, default: () => [] },
  selectedLaboratory: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  selectProducts: { type: Array, default: () => [] },
  showIgnored: { type: Boolean, default: false },
  showGraphs: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:selectedLaboratory",
  "update:selectProducts",
  "update:checkColombia",
  "clear",
  "clear-ignore",
  "export-excel",
  "export-pdf",
  "update:showIgnored",
  "update:showGraphs",
]);

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratory?.length > 0 ||
    props.checkColombia ||
    props.lapso_de_tiempo !== '1 month' ||
    props.tipo_de_filtracion !== 'combinado'
  );
});

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "12 Meses", value: "12 month" },
  { title: "18 Meses", value: "18 month" },
  { title: "24 Meses", value: "24 month" },
];
</script>

<template>
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-4">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal (Autocomplete de Productos) -->
        <VCol cols="12" md="4" lg="5">
          <VAutocomplete
            :model-value="props.selectProducts"
            :items="props.products"
            placeholder="Buscar por productos..."
            item-title="name"
            item-value="id"
            clearable
            chips
            multiple
            closable-chips
            hide-details
            density="compact"
            prepend-inner-icon="tabler-search"
            @update:model-value="emit('update:selectProducts', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-2">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="toggleAdvancedFilters"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
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
                <VIcon icon="tabler-file-download" />
                <VTooltip activator="parent" location="top">Exportar Reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" class="rounded-lg shadow-lg border">
              <VListItem @click="emit('export-excel', 'xlsx')" class="py-2">
                <template #prepend>
                  <VIcon icon="tabler-file-spreadsheet" class="me-2" color="success" />
                </template>
                <VListItemTitle class="font-weight-bold text-success">Excel (.xlsx)</VListItemTitle>
              </VListItem>
              <VDivider />
              <VListItem @click="emit('export-pdf')" class="py-2">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" class="me-2" color="error" />
                </template>
                <VListItemTitle class="font-weight-bold text-error">PDF (.pdf)</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Mostrar Ignorados (Toggle) -->
          <VBtn
            icon
            variant="tonal"
            :color="props.showIgnored ? 'success' : 'secondary'"
            size="38"
            @click="emit('update:showIgnored', !props.showIgnored)"
          >
            <VIcon :icon="props.showIgnored ? 'tabler-eye' : 'tabler-eye-off'" />
            <VTooltip activator="parent" location="top">{{ props.showIgnored ? 'Ocultar Ignorados' : 'Mostrar Ignorados' }}</VTooltip>
          </VBtn>

          <!-- Mostrar Gráficos (Toggle) -->
          <VBtn
            icon
            variant="tonal"
            :color="props.showGraphs ? 'primary' : 'secondary'"
            size="38"
            @click="emit('update:showGraphs', !props.showGraphs)"
          >
            <VIcon icon="tabler-chart-line" />
            <VTooltip activator="parent" location="top">{{ props.showGraphs ? 'Ocultar Gráficos' : 'Mostrar Gráficos' }}</VTooltip>
          </VBtn>

          <!-- Limpiar Filtros (Solo Icono) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>

          <!-- Limpiar Ignore (Nuevo) -->
          <VBtn
            icon
            variant="text"
            color="warning"
            size="38"
            @click="emit('clear-ignore')"
          >
            <VIcon icon="tabler-eye-check" />
            <VTooltip activator="parent" location="top">Restaurar Ocultos (Ignore)</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <VCol cols="12" md="6" lg="3">
              <VAutocomplete
                :model-value="props.selectedLaboratory"
                :items="props.laboratories"
                placeholder="Laboratorios"
                item-title="name"
                item-value="id"
                clearable
                chips
                multiple
                closable-chips
                hide-details
                density="compact"
                prepend-inner-icon="tabler-flask"
                @update:model-value="emit('update:selectedLaboratory', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3" lg="2">
              <VSelect
                :model-value="props.tipo_de_filtracion"
                :items="tipoFiltracionOpcion"
                placeholder="Calcular por"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-calculator"
                @update:model-value="emit('update:tipo_de_filtracion', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3" lg="2">
              <VSelect
                :model-value="props.lapso_de_tiempo"
                :items="lapsoDeTiempoOpciones"
                placeholder="Lapso de tiempo"
                hide-details
                density="compact"
                prepend-inner-icon="tabler-calendar-time"
                @update:model-value="emit('update:lapso_de_tiempo', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3" lg="2" class="d-flex align-center">
              <VCheckbox
                :model-value="props.checkColombia"
                label="Origen Colombia"
                color="primary"
                density="compact"
                hide-details
                class="mt-0"
                @update:model-value="emit('update:checkColombia', $event)"
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
.gap-4 { gap: 16px; }

.text-xs {
  font-size: 0.7rem !important;
  letter-spacing: 0.5px;
}
</style>
