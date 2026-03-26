<script setup>
import { roundIaAnalysis } from "@/utils/iaAnalysisRounding";
import { ref, computed } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProducts: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  modelValue: { type: Object, default: null },
  searchQuery: { type: String, default: "" },
  // Filtros avanzados
  laboratories: { type: Array, default: () => [] },
  selectedLaboratory: { type: Array, default: () => [] },
  groups: { type: Array, default: () => [] },
  selectedGroup: { type: Array, default: () => [] },
  tipo_de_filtracion: String,
  lapso_de_tiempo: String,
  stock: String,
  selectConDescuento: Boolean,
});

const emit = defineEmits([
  "update:modelValue",
  "select-product",
  "update:options",
  "update:search-query",
  "update:selectedLaboratory",
  "update:selectedGroup",
  "update:tipo_de_filtracion",
  "update:lapso_de_tiempo",
  "update:stock",
  "update:selectConDescuento",
  "delete",
  "delete-old",
  "save-analysis",
]);

const precioOpciones = [
  { title: "Full", value: true },
  { title: "Descuento", value: false },
];

const tipoFiltracionOpciones = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

const lapsoDeTiempoOpciones = [
  { title: "7 Dias", value: "7 days" },
  { title: "15 Dias", value: "15 days" },
  { title: "1 Mes", value: "1 month" },
  { title: "3 Meses", value: "3 month" },
  { title: "6 Meses", value: "6 month" },
  { title: "1 Año", value: "1 year" },
];

const stockOpciones = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "Todos", value: "all" },
];

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedLaboratory?.length > 0;
});

const { mdAndUp } = useDisplay();

// Track edited pedido values per item id
const editedValues = ref({});

const getInputValue = (item) => {
  if (item.id in editedValues.value) return editedValues.value[item.id];
  // Precarga con el valor de análisis redondeado
  return roundIaAnalysis(item.solicitar ?? 0);
};

const formatUsd = (amount) => {
  return (
    new Intl.NumberFormat("es-VE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount)
  );
};

const headers = [
  { title: "PRODUCTO / LAB.", key: "name", sortable: true },
  { title: "COSTO", key: "costs", sortable: false, width: "100px" },
  { title: "ANÁLISIS", key: "solicitar", sortable: true, width: "80px" },
  { title: "ACCIÓN", key: "actions", sortable: false, width: "110px", align: "end" },
];

const onRowClick = (event, { item }) => {
  if (event.target.closest("input") || event.target.closest("button")) return;

  // Toggle selección: si ya está seleccionado, deseleccionar
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
  <div class="comparator-needs-container">
    <!-- Header de Sección y Búsqueda (Estandarizado) -->
    <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-2">
          <!-- Título/Icono -->
          <div class="d-flex align-center gap-2 mr-4">
            <VIcon icon="tabler-brain" color="primary" size="20" />
            <span class="text-subtitle-2 font-weight-bold text-uppercase d-none d-sm-inline">Necesidades IA</span>
          </div>

          <!-- Buscador Principal -->
          <VCol cols="12" sm="5" md="4" lg="4">
            <VTextField
              :model-value="searchQuery"
              @update:model-value="(val) => emit('update:search-query', val)"
              placeholder="Buscar faltantes..."
              prepend-inner-icon="tabler-search"
              density="compact"
              hide-details
              clearable
            />
          </VCol>

          <VSpacer />

          <!-- Acciones (Solo Iconos) -->
          <div class="d-flex align-center gap-1">
            <!-- Toggle Filtros -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
              @click="toggleAdvancedFilters"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
              <VBadge
                v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
                color="error"
                dot
                offset-x="2"
                offset-y="-2"
              />
            </VBtn>

            <!-- Borrar Productos Antiguos (Movido desde el panel avanzado) -->
            <VBtn
              icon
              variant="tonal"
              color="error"
              size="38"
              class="rounded-circle shadow-sm"
              @click="emit('delete-old')"
            >
              <VIcon icon="tabler-trash" size="20" />
              <VTooltip activator="parent" location="top">Borrar Productos Antiguos</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar Filtros -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="emit('update:search-query', '')"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Panel de Filtros Avanzados -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible" class="pt-4 mt-4 border-t">
            <VRow dense>
              <VCol cols="12" md="6">
                <VAutocomplete
                  :model-value="props.selectedLaboratory"
                  :items="props.laboratories"
                  placeholder="Laboratorios"
                  item-title="name"
                  item-value="id"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-flask"
                  @update:model-value="emit('update:selectedLaboratory', $event)"
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <VAutocomplete
                  :model-value="props.selectedGroup"
                  :items="props.groups"
                  placeholder="Grupos"
                  item-title="name"
                  item-value="id"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-tags"
                  @update:model-value="emit('update:selectedGroup', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <VSelect
                  :model-value="props.tipo_de_filtracion"
                  :items="tipoFiltracionOpciones"
                  placeholder="Calcular Por"
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-calculator"
                  @update:model-value="emit('update:tipo_de_filtracion', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <VSelect
                  :model-value="props.lapso_de_tiempo"
                  :items="lapsoDeTiempoOpciones"
                  placeholder="Lapso"
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-calendar-time"
                  @update:model-value="emit('update:lapso_de_tiempo', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <VSelect
                  :model-value="props.stock"
                  :items="stockOpciones"
                  placeholder="Stock"
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-package"
                  @update:model-value="emit('update:stock', $event)"
                />
              </VCol>

              <VCol cols="12" sm="6" md="3">
                <VSelect
                  :model-value="props.selectConDescuento"
                  :items="precioOpciones"
                  placeholder="Precio"
                  hide-details
                  density="compact"
                  prepend-inner-icon="tabler-tag"
                  @update:model-value="emit('update:selectConDescuento', $event)"
                />
              </VCol>


            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <!-- Tabla Principal (Unified VCard) -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Desktop -->
      <div v-if="mdAndUp" class="d-none d-md-block">
        <VDataTableServer
          :headers="headers"
          :items="products"
          :items-length="totalProducts"
          :items-per-page="itemsPerPage"
          :page="page"
          :loading="loading"
          hover
          density="compact"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
          @click:row="onRowClick"
          :row-props="
            (data) => ({
              class: isSelected(data.item) ? 'selected-row' : 'cursor-pointer',
            })
          "
        >
          <template #item.name="{ item }">
            <div class="d-flex align-center py-2 gap-2">
              <VIcon
                v-if="isSelected(item)"
                icon="tabler-arrows-exchange"
                color="primary"
                size="16"
                class="flex-shrink-0"
              />
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.name">
                  <a
                    :href="'/inventory/traceability?q=' + item.id"
                    target="_blank"
                    class="text-decoration-none text-primary mr-2"
                  >
                    {{ item.id }}
                  </a>
                  <span class="text-disabled mr-2">|</span>
                  {{ item.name.toUpperCase() }}
                  <span v-if="item.is_colombian_origin == 1" class="text-xs opacity-60 ml-1">(COL)</span>
                </span>
                <div class="d-flex align-center gap-1 text-super-xs">
                  <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                    {{ item.laboratory?.name || 'S/L' }}
                  </span>
                  <template v-if="item.psychotropic == 1">
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-error font-weight-bold">PSICOTRÓPICO</span>
                  </template>
                </div>
              </div>
            </div>
          </template>

          <template #item.solicitar="{ item }">
            <VChip
              :color="roundIaAnalysis(item.solicitar ?? 0) > 0 ? 'success' : 'error'"
              size="x-small"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ roundIaAnalysis(item.solicitar ?? 0) > 0 ? "+" : "" }}{{ roundIaAnalysis(item.solicitar ?? 0) }}
            </VChip>
          </template>

          <template #item.costs="{ item }">
            <span class="text-sm font-weight-medium">${{ formatUsd(item.unit_cost ?? 0) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end ga-1">
              <VTooltip text="Pedir Directo" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-if="item.cheapest_barcode"
                    v-bind="tooltipProps"
                    icon="tabler-check"
                    variant="text"
                    color="success"
                    size="small"
                    @click.stop="emit('save-analysis', { item, newValue: getInputValue(item) })"
                  />
                </template>
              </VTooltip>

              <VTooltip text="Quitar" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-trash"
                    variant="tonal"
                    color="secondary"
                    size="small"
                    class="rounded-circle shadow-sm"
                    @click.stop="emit('delete', item)"
                  />
                </template>
              </VTooltip>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-disabled text-sm">No hay necesidades detectadas</div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div v-else class="d-md-none pa-4 bg-var-theme-background">
        <div v-if="loading" class="d-flex justify-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        <div v-else-if="products.length === 0" class="text-center py-8 text-disabled text-sm">
          No hay productos disponibles
        </div>
        <div v-else class="d-flex flex-column gap-3">
          <VCard
            v-for="item in products"
            :key="item.id"
            class="mobile-card border shadow-none"
            :class="{ 'selected-card': isSelected(item) }"
            @click="onRowClick($event, { item })"
          >
            <VCardText class="pa-4">
              <div class="d-flex align-start gap-2 mb-3">
                <VIcon
                  v-if="isSelected(item)"
                  icon="tabler-arrows-exchange"
                  color="primary"
                  size="18"
                  class="mt-1"
                />
                <div class="flex-grow-1">
                  <span class="text-sm font-weight-black text-high-emphasis text-uppercase d-block leading-tight">
                    {{ item.name }}
                    <span v-if="item.is_colombian_origin == 1" class="text-xs opacity-60 ml-1">(COL)</span>
                  </span>
                  <span class="text-xs text-disabled d-block mt-1">
                    #<a
                      :href="'/inventory/traceability?q=' + item.id"
                      target="_blank"
                      class="text-decoration-none text-primary font-weight-bold"
                    >
                      {{ item.id }}
                    </a>
                    • {{ item.laboratory?.name }}
                  </span>
                </div>
                <VChip
                  :color="roundIaAnalysis(item.solicitar ?? 0) > 0 ? 'success' : 'error'"
                  size="x-small"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  {{ roundIaAnalysis(item.solicitar ?? 0) > 0 ? "+" : "" }}{{ roundIaAnalysis(item.solicitar ?? 0) }}
                </VChip>
              </div>

              <div class="d-flex justify-space-between align-center mb-4">
                <div class="d-flex flex-column">
                  <span class="text-xs text-disabled uppercase font-weight-bold">Costo Actual</span>
                  <span class="text-sm font-weight-bold">${{ formatUsd(item.unit_cost ?? 0) }}</span>
                </div>
                <div class="d-flex ga-1">
                  <VBtn
                    v-if="item.cheapest_barcode"
                    icon="tabler-check"
                    variant="tonal"
                    color="success"
                    size="small"
                    @click.stop="emit('save-analysis', { item, newValue: getInputValue(item) })"
                  />
                  <VBtn
                    icon="tabler-trash"
                    variant="tonal"
                    color="secondary"
                    size="small"
                    @click.stop="emit('delete', item)"
                  />
                </div>
              </div>
              
              <div v-if="isSelected(item)" class="text-center pt-2 border-t mt-1">
                <span class="text-xs font-weight-bold text-primary text-uppercase">Seleccionado para comparar</span>
              </div>
            </VCardText>
          </VCard>

          <VPagination
            v-model="props.page"
            :length="Math.ceil(totalProducts / itemsPerPage)"
            :total-visible="3"
            density="compact"
            @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: itemsPerPage })"
          />
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.selected-row {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
  border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important;
}

.selected-card {
  border: 1px solid rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.04) !important;
}

.text-xs { font-size: 0.75rem !important; }

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.mobile-card { transition: all 0.2s ease; cursor: pointer; }
.leading-tight { line-height: 1.25; }
.ga-1 { gap: 4px !important; }
</style>
