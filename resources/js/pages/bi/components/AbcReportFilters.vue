<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  loading: { type: Boolean, default: false },
  search: { type: String, default: '' },
  selectedDateRange: { type: String, default: '30 days' },
  selectedAnalysisType: { type: String, default: 'all' },
  selectedLaboratories: { type: Array, default: () => [] },
  selectedLaboratoryGroups: { type: Array, default: () => [] },
  selectedFinalClassification: { type: String, default: null },
  minGmroi: { type: [Number, String], default: null },
  stockFilter: { type: String, default: 'all' },
  laboratories: { type: Array, default: () => [] },
  laboratoryGroups: { type: Array, default: () => [] },
  isAdvancedFiltersVisible: { type: Boolean, default: false },
  exporting: { type: Boolean, default: false },
  navigatingAssistant: { type: Boolean, default: false },
});

const emit = defineEmits([
  'update:search',
  'update:selectedDateRange',
  'update:selectedAnalysisType',
  'update:selectedLaboratories',
  'update:selectedLaboratoryGroups',
  'update:selectedFinalClassification',
  'update:minGmroi',
  'update:stockFilter',
  'update:isAdvancedFiltersVisible',
  'fetch',
  'clear',
  'export',
  'go-to-assistant',
  'filter-critical',
]);

// Orden de laboratorios por cantidad de productos: 'desc' (el que más productos tiene primero) o 'asc'
const labSortOrder = ref('desc');

const sortedAndFilteredLaboratories = computed(() => {
  let list = Array.isArray(props.laboratories) ? [...props.laboratories] : [];

  // Filtrar si hay grupos de laboratorios seleccionados
  if (props.selectedLaboratoryGroups && props.selectedLaboratoryGroups.length > 0) {
    const groupIds = props.selectedLaboratoryGroups.map((id) => Number(id));
    list = list.filter((lab) => lab.group_id && groupIds.includes(Number(lab.group_id)));
  }

  // Ordenar por cantidad de productos (el que más productos tiene)
  list.sort((a, b) => {
    const countA = Number(a.products_count ?? 0);
    const countB = Number(b.products_count ?? 0);
    return labSortOrder.value === 'asc' ? countA - countB : countB - countA;
  });

  return list.map((lab) => ({
    id: lab.id,
    name: lab.name,
    group_id: lab.group_id,
    products_count: lab.products_count ?? 0,
    displayName: `${lab.name} (${lab.products_count ?? 0} prods)`,
  }));
});

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedLaboratories.length > 0 ||
    props.selectedLaboratoryGroups.length > 0 ||
    props.selectedFinalClassification !== null ||
    props.minGmroi !== null ||
    props.stockFilter !== 'all'
  );
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
  { title: 'Análisis Completo (Catálogo total)', value: 'all' },
  { title: 'Capital Congelado (CZ / Sin Rotación)', value: 'frozen_capital' },
  { title: 'Stock Muerto (0 Ventas en el período)', value: 'dead_stock' },
  { title: 'Capital Propenso a Vencer (Algoritmo FEFO)', value: 'expiring_risk' },
  { title: 'Quiebres y Riesgo de Stock (Prioridad A/B)', value: 'critical_stock' },
  { title: 'Margen Negativo / Pérdida (< 0%)', value: 'negative_margin' },
  { title: 'Productos Estrella (Clase AA)', value: 'star_products' },
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

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <!-- Menú Desplegable de Exportación Excel -->
          <VMenu location="bottom end" :close-on-content-click="true">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="tonal"
                color="success"
                size="36"
                class="rounded-circle"
                :loading="exporting"
                :disabled="loading || exporting"
              >
                <VIcon icon="tabler-download" size="18" />
                <VTooltip activator="parent" location="top">Exportar a Excel</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" class="py-1 shadow-md border rounded-lg" min-width="270">
              <VListSubheader class="text-uppercase text-caption font-weight-bold tracking-wider opacity-75">
                Exportar a Excel (.xlsx)
              </VListSubheader>
              
              <VListItem
                prepend-icon="tabler-file-spreadsheet"
                title="Vista / Filtros Actuales"
                subtitle="Datos según los filtros seleccionados"
                @click="emit('export', 'all')"
              />
              
              <VDivider class="my-1 opacity-10" />
              
              <VListItem
                prepend-icon="tabler-truck-delivery"
                title="1. Prioridad Compras (AX / AY)"
                subtitle="Clase A en riesgo de quiebre"
                @click="emit('export', 'ax_ay')"
              />
              
              <VListItem
                prepend-icon="tabler-lock-square"
                title="2. Capital Congelado (CZ / Muerto)"
                subtitle="Stock inmovilizado sin rotación"
                @click="emit('export', 'frozen_capital')"
              />
              
              <VListItem
                prepend-icon="tabler-chart-arrows-vertical"
                title="3. Matriz Rentabilidad GMROI"
                subtitle="Ranking por retorno sobre stock"
                @click="emit('export', 'gmroi')"
              />
              
              <VListItem
                prepend-icon="tabler-trending-down"
                title="4. Margen Negativo (<0%)"
                subtitle="Productos con margen o GMROI en pérdida"
                @click="emit('export', 'negative_margin')"
              />

              <VListItem
                prepend-icon="tabler-clock-exclamation"
                title="5. Riesgo de Expiración (FEFO)"
                subtitle="Capital próximo a vencer (<= 180 días)"
                @click="emit('export', 'expiring_risk')"
              />
            </VList>
          </VMenu>

          <!-- Botón de Asistente IA de Pedidos / Compras Críticas -->
          <VMenu location="bottom end" :close-on-content-click="true">
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                variant="tonal"
                color="warning"
                size="36"
                class="rounded-circle"
                :loading="navigatingAssistant"
                :disabled="loading || navigatingAssistant"
              >
                <VIcon icon="tabler-robot" size="18" />
                <VTooltip activator="parent" location="top">Asistente IA de Pedidos (Quiebres Críticos)</VTooltip>
              </VBtn>
            </template>
            <VList density="compact" class="py-1 shadow-md border rounded-lg" min-width="310">
              <VListSubheader class="text-uppercase text-caption font-weight-bold tracking-wider opacity-75">
                Reposición Automática IA
              </VListSubheader>
              
              <VListItem
                prepend-icon="tabler-sparkles"
                title="1. Pedir Automático con IA"
                subtitle="Buscar mejor proveedor y cotizar con IA lo disponible"
                @click="emit('go-to-assistant', true)"
              />
              
              <VListItem
                prepend-icon="tabler-shopping-cart-plus"
                title="2. Revisar Manual en Asistente IA"
                subtitle="Abrir asistente para ajustar cantidades y proveedores"
                @click="emit('go-to-assistant', false)"
              />
              
              <VDivider class="my-1 opacity-10" />
              
              <VListItem
                prepend-icon="tabler-alert-triangle"
                title="3. Filtrar Quiebres en esta Tabla"
                subtitle="Ver los productos críticos en el reporte ABC"
                @click="emit('filter-critical')"
              />
            </VList>
          </VMenu>
        </VCol>
      </VRow>

      <!-- Fila 2: Filtros avanzados (colapsable) -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow align="center" dense>
            <!-- Grupo de Laboratorios -->
            <VCol cols="12" sm="6" md="3">
              <AppAutocomplete
                :model-value="selectedLaboratoryGroups"
                :items="laboratoryGroups"
                item-title="name"
                item-value="id"
                placeholder="Grupo de Laboratorios"
                multiple
                chips
                closable-chips
                clearable
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-folders"
                :disabled="loading"
                @update:model-value="emit('update:selectedLaboratoryGroups', $event)"
              />
            </VCol>

            <!-- Laboratorio (Ordenado por cantidad de productos) -->
            <VCol cols="12" sm="6" md="3">
              <div class="d-flex align-center gap-1">
                <AppAutocomplete
                  :model-value="selectedLaboratories"
                  :items="sortedAndFilteredLaboratories"
                  item-title="displayName"
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
                  class="flex-grow-1"
                  :disabled="loading"
                  @update:model-value="emit('update:selectedLaboratories', $event)"
                />
                <VBtn
                  icon
                  variant="text"
                  size="28"
                  :color="labSortOrder === 'desc' ? 'primary' : 'secondary'"
                  class="flex-shrink-0"
                  :disabled="loading"
                  @click="labSortOrder = labSortOrder === 'desc' ? 'asc' : 'desc'"
                >
                  <VIcon :icon="labSortOrder === 'desc' ? 'tabler-sort-descending-numbers' : 'tabler-sort-ascending-numbers'" size="18" />
                  <VTooltip activator="parent" location="top">
                    {{ labSortOrder === 'desc' ? 'Orden: Más productos primero' : 'Orden: Menos productos primero' }}
                  </VTooltip>
                </VBtn>
              </div>
            </VCol>

            <!-- Clasificación ABC-XYZ -->
            <VCol cols="12" sm="6" md="2">
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

            <!-- ROI Mínimo -->
            <VCol cols="12" sm="6" md="2">
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

            <!-- Estado de Stock -->
            <VCol cols="12" sm="6" md="2">
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
