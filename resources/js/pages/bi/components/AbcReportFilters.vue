<script setup>
import { computed } from 'vue';

const props = defineProps({
  loading: { type: Boolean, default: false },
  search: { type: String, default: '' },
  selectedDateRange: { type: String, default: '30 days' },
  selectedAnalysisType: { type: String, default: 'all' },
  selectedLaboratories: { type: Array, default: () => [] },
  selectedFinalClassification: { type: String, default: null },
  minGmroi: { type: [Number, String], default: null },
  stockFilter: { type: String, default: 'all' },
  laboratories: { type: Array, default: () => [] },
  isAdvancedFiltersVisible: { type: Boolean, default: false },
});

const emit = defineEmits([
  'update:search',
  'update:selectedDateRange',
  'update:selectedAnalysisType',
  'update:selectedLaboratories',
  'update:selectedFinalClassification',
  'update:minGmroi',
  'update:stockFilter',
  'update:isAdvancedFiltersVisible',
  'fetch',
  'clear',
]);

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedLaboratories.length > 0 || props.selectedFinalClassification !== null;
});

const dateRangeOptions = [
  { title: 'Últimos 30 días', value: '30 days' },
  { title: 'Últimos 90 días', value: '90 days' },
  { title: 'Últimos 12 meses', value: '12 months' },
];

const classificationOptions = [
  'AAX', 'AAY', 'AAZ', 'ABX', 'ABY', 'ABZ', 'ACX', 'ACY', 'ACZ',
  'BAX', 'BAY', 'BAZ', 'BBX', 'BBY', 'BBZ', 'BCX', 'BCY', 'BCZ',
  'CAX', 'CAY', 'CAZ', 'CBX', 'CBY', 'CBZ', 'CCX', 'CCY', 'CCZ',
];

const analysisTypeOptions = [
  { title: 'Análisis Completo', value: 'all' },
  { title: 'Stock Muerto (0 Ventas)', value: 'dead_stock' },
  { title: 'Productos Estrella (AA)', value: 'star_products' },
];

const toggleAdvancedFilters = () => {
  emit('update:isAdvancedFiltersVisible', !props.isAdvancedFiltersVisible);
};
</script>

<template>
  <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
    <VCardText class="pa-3">
      <!-- Fila 1: Filtros principales -->
      <VRow align="center" dense>
        <!-- Buscador -->
        <VCol cols="12" md="3">
          <AppTextField
            :model-value="search"
            placeholder="Buscar producto, ID..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            variant="outlined"
            :disabled="loading"
            @update:model-value="emit('update:search', $event)"
          />
        </VCol>

        <!-- Período -->
        <VCol cols="12" md="3">
          <AppSelect
            :model-value="selectedDateRange"
            :items="dateRangeOptions"
            placeholder="Período de Análisis"
            density="compact"
            hide-details
            variant="outlined"
            prepend-inner-icon="tabler-calendar-stats"
            :disabled="loading"
            @update:model-value="emit('update:selectedDateRange', $event)"
          />
        </VCol>

        <!-- Modo de Análisis -->
        <VCol cols="12" md="3">
          <AppSelect
            :model-value="selectedAnalysisType"
            :items="analysisTypeOptions"
            placeholder="Modo de Análisis"
            density="compact"
            hide-details
            variant="outlined"
            prepend-inner-icon="tabler-analyze"
            :disabled="loading"
            @update:model-value="emit('update:selectedAnalysisType', $event)"
          />
        </VCol>

        <!-- Botones de acción -->
        <VCol cols="12" md="auto" class="d-flex align-center gap-1 ms-auto">
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="36"
            class="rounded-circle"
            :disabled="loading"
            @click="toggleAdvancedFilters"
          >
            <VBadge
              :model-value="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="2"
              offset-y="-2"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="18" />
            </VBadge>
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <VBtn
            icon
            variant="flat"
            color="primary"
            size="36"
            class="rounded-circle"
            :loading="loading"
            :disabled="loading"
            @click="emit('fetch')"
          >
            <VIcon icon="tabler-player-play" size="18" />
            <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
          </VBtn>

          <VBtn
            icon
            variant="text"
            color="secondary"
            size="36"
            class="rounded-circle"
            :disabled="loading"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" size="18" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </VCol>
      </VRow>

      <!-- Fila 2: Filtros avanzados (colapsable) -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow align="center" dense>
            <VCol cols="12" md="3">
              <AppAutocomplete
                :model-value="selectedLaboratories"
                :items="laboratories"
                item-title="name"
                item-value="id"
                placeholder="Laboratorio"
                multiple
                chips
                closable-chips
                clearable
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-flask"
                :disabled="loading"
                @update:model-value="emit('update:selectedLaboratories', $event)"
              />
            </VCol>

            <VCol cols="12" md="3">
              <div class="d-flex align-center gap-1">
                <AppAutocomplete
                  :model-value="selectedFinalClassification"
                  :items="classificationOptions"
                  placeholder="Clasificación (AAX...)"
                  clearable
                  density="compact"
                  hide-details
                  variant="outlined"
                  prepend-inner-icon="tabler-tags"
                  class="flex-grow-1"
                  :disabled="loading"
                  @update:model-value="emit('update:selectedFinalClassification', $event)"
                />
                <VBtn icon variant="text" size="28" color="info" class="flex-shrink-0" :disabled="loading">
                  <VIcon icon="tabler-info-circle" size="18" />
                  <VTooltip activator="parent" location="right" max-width="310">
                    <div style="line-height: 1.8">
                      <div class="text-caption font-weight-bold mb-2" style="font-size:11px;letter-spacing:1px;opacity:.7">GUÍA DE CLASIFICACIÓN ABC-XYZ</div>
                      <div class="text-caption mb-1"><span style="color:#4CAF50;font-weight:bold">A</span> — Genera el 80% de ventas/margen</div>
                      <div class="text-caption mb-1"><span style="color:#FF9800;font-weight:bold">B</span> — Contribuye el siguiente 15%</div>
                      <div class="text-caption mb-2"><span style="color:#9E9E9E;font-weight:bold">C</span> — Representa el 5% restante</div>
                      <div class="text-caption mb-1"><span style="color:#4CAF50;font-weight:bold">X</span> — Demanda predecible y constante</div>
                      <div class="text-caption mb-1"><span style="color:#FF9800;font-weight:bold">Y</span> — Demanda moderada con variaciones</div>
                      <div class="text-caption mb-2"><span style="color:#F44336;font-weight:bold">Z</span> — Demanda irregular o esporádica</div>
                      <div class="text-caption" style="opacity:.6;border-top:1px solid rgba(255,255,255,.1);padding-top:6px">
                         <span style="color:#4CAF50">●</span> AAX = Producto Estrella<br>
                         <span style="color:#9E9E9E">●</span> CCZ = Prescindible
                      </div>
                    </div>
                  </VTooltip>
                </VBtn>
              </div>
            </VCol>

            <VCol cols="12" md="2">
              <AppTextField
                :model-value="minGmroi"
                type="number"
                placeholder="ROI mínimo (%)"
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-chart-line"
                :disabled="loading"
                @update:model-value="emit('update:minGmroi', $event)"
              />
            </VCol>

            <VCol cols="12" md="2">
              <AppSelect
                :model-value="stockFilter"
                :items="[
                  { title: 'Todos los productos', value: 'all' },
                  { title: 'Con stock', value: 'with_stock' },
                  { title: 'Sin stock', value: 'out_of_stock' },
                ]"
                placeholder="Estado de Stock"
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-package"
                :disabled="loading"
                @update:model-value="emit('update:stockFilter', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
</style>
