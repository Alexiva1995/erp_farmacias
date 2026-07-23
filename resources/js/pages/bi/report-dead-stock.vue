<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import axios from '@/plugins/axios';
import { formatCurrency } from '@/utils/currencyFormatter';
import ProductStatsDialog from "@/components/dialogs/ProductStatsDialog.vue";

const loading = ref(false);
const error = ref(null);
const items = ref([]);
const totalItems = ref(0);

// Estados para diálogo de estadísticas
const isStatsDialogVisible = ref(false);
const selectedProductForStats = ref(null);

const handleViewStats = (product) => {
  selectedProductForStats.value = {
    id: product.id,
    name: product.name,
  };
  isStatsDialogVisible.value = true;
};

// Filtros
const selectedDateRange = ref('30 days'); // Por defecto 30 días
const selectedLaboratories = ref([]);
const selectedFinalClassification = ref(null);
const minGmroi = ref(null);

// Paginación y Ordenación
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'inventory_value', order: 'desc' }]); // Ordenar por capital inmovilizado por defecto

// Búsqueda global y KPIs (Debounced search value)
const search = ref('');
const debouncedSearch = ref('');
let searchTimeout = null;

watch(search, (newVal) => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    debouncedSearch.value = newVal;
    page.value = 1; // Reiniciar a la primera página al buscar
  }, 400);
});

const summaryStats = ref({
  total_volume: 0,
  aax_products: 0,
  avg_margin: 0,
  frozen_capital: 0,
  count_a: 0,
  count_b: 0,
  count_c: 0,
  critical_stockouts: 0,
  total_products: 0,
});

// Catálogos
const laboratories = ref([]);
const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return selectedLaboratories.value.length > 0 || selectedFinalClassification.value !== null;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

// Mapeo de rangos de fecha rápidos
const getDateRange = (rangeType) => {
  const end = new Date();
  const start = new Date();
  
  if (rangeType === '30 days') {
    start.setDate(end.getDate() - 30);
  } else if (rangeType === '90 days') {
    start.setDate(end.getDate() - 90);
  } else if (rangeType === '12 months') {
    start.setMonth(end.getMonth() - 12);
  }

  return {
    start_date: start.toISOString().split('T')[0],
    end_date: end.toISOString().split('T')[0]
  };
};

const dateRangeOptions = [
  { title: 'Últimos 30 días', value: '30 days' },
  { title: 'Últimos 90 días', value: '90 days' },
  { title: 'Últimos 12 meses', value: '12 months' },
];

const classificationOptions = [
  'AAX', 'AAY', 'AAZ', 'ABX', 'ABY', 'ABZ', 'ACX', 'ACY', 'ACZ',
  'BAX', 'BAY', 'BAZ', 'BBX', 'BBY', 'BBZ', 'BCX', 'BCY', 'BCZ',
  'CAX', 'CAY', 'CAZ', 'CBX', 'CBY', 'CBZ', 'CCX', 'CCY', 'CCZ'
];

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'PRODUCTO', key: 'name', sortable: true },
  { title: 'STOCK ACTUAL', key: 'current_stock', align: 'end', sortable: true },
  { title: 'COSTO UNIT.', key: 'last_cost', align: 'end', sortable: true },
  { title: 'CAPITAL INMOVILIZADO', key: 'inventory_value', align: 'end', sortable: true },
  { title: 'ÚLTIMA VENTA', key: 'last_sale_date', align: 'center', sortable: true },
  { title: 'VENTAS (PROM. / 12M)', key: 'sales_average', align: 'end', sortable: true },
  { title: 'PERFIL ABC-XYZ', key: 'final_classification', align: 'center', sortable: true },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '100px' },
];

const formatDate = (dateStr) => {
  if (!dateStr) return 'Nunca vendido';
  try {
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return 'Nunca vendido';
    return d.toLocaleDateString('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return 'Nunca vendido';
  }
};

const fetchCatalogs = async () => {
  try {
    const labsRes = await axios.get('/laboratories');
    laboratories.value = labsRes.data;
  } catch (err) {
    console.error('Error al cargar catálogos:', err);
  }
};

const fetchReport = async () => {
  if (loading.value) return;
  loading.value = true;
  error.value = null;
  try {
    const dates = getDateRange(selectedDateRange.value);
    
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key || 'inventory_value',
      orderBy: sortBy.value[0]?.order || 'desc',
      start_date: dates.start_date,
      end_date: dates.end_date,
      laboratory_id: selectedLaboratories.value,
      final_classification: selectedFinalClassification.value,
      analysis_type: 'dead_stock', // Forzar filtro de stock muerto en backend
      min_gmroi: minGmroi.value,
      search: debouncedSearch.value, // Envío correcto del término de búsqueda debounced
    };

    const response = await axios.get('/bi/abc', { params });
    const responseData = response.data.data;
    const summary = response.data.summary;
    
    items.value = responseData;
    totalItems.value = response.data.meta.total;

    if (summary) {
      summaryStats.value = {
        total_volume: summary.total_sales,
        aax_products: summary.aax_products,
        avg_margin: summary.avg_margin,
        frozen_capital: summary.frozen_capital,
        count_a: summary.count_a ?? 0,
        count_b: summary.count_b ?? 0,
        count_c: summary.count_c ?? 0,
        critical_stockouts: summary.critical_stockouts ?? 0,
        total_products: summary.total_products ?? 0,
      };
    }
    
  } catch (err) {
    console.error('Error al obtener reporte de stock muerto:', err);
    error.value = 'Ocurrió un error al cargar el reporte de stock inmovilizado. Por favor, reintente la consulta.';
  } finally {
    loading.value = false;
  }
};

const getColorClass = (classification) => {
  if (!classification) return 'default';
  
  if (['AAX', 'AAY', 'BAX', 'CAX'].includes(classification)) return 'success';
  if (['CCZ', 'CBZ', 'ACX'].includes(classification)) return 'error';
  if (['ABX', 'BBX'].includes(classification)) return 'warning';
  
  return 'secondary';
};

// Observar cambios en filtros, ordenamiento, paginación y búsqueda con debounce
watch([page, itemsPerPage, sortBy, selectedDateRange, selectedLaboratories, selectedFinalClassification, minGmroi, debouncedSearch], () => {
  fetchReport();
}, { deep: true });

onMounted(async () => {
  await fetchCatalogs();
  await fetchReport();
});

const handleClearFilters = () => {
  search.value = '';
  debouncedSearch.value = '';
  selectedDateRange.value = '30 days';
  selectedLaboratories.value = [];
  selectedFinalClassification.value = null;
  minGmroi.value = null;
  isAdvancedFiltersVisible.value = false;
};
</script>

<template>
  <div class="report-dead-stock pb-12">
    <div class="d-flex flex-column gap-1 mt-1">
      <!-- Mensaje de Error de API -->
      <VAlert
        v-if="error"
        type="error"
        title="Error de Conexión"
        variant="tonal"
        closable
        class="mb-4"
        @click:close="error = null"
      >
        {{ error }}
      </VAlert>

      <!-- Filtros Estandarizados -->
      <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VCardText class="pa-3">
          <VRow align="center" dense>
            <!-- Buscador -->
            <VCol cols="12" md="4">
              <AppTextField
                v-model="search"
                placeholder="Buscar producto, ID..."
                prepend-inner-icon="tabler-search"
                clearable
                density="compact"
                hide-details
                variant="outlined"
              />
            </VCol>

            <!-- Período -->
            <VCol cols="12" md="4">
              <AppSelect
                v-model="selectedDateRange"
                :items="dateRangeOptions"
                placeholder="Período de Análisis"
                density="compact"
                hide-details
                variant="outlined"
                prepend-inner-icon="tabler-calendar-stats"
              />
            </VCol>

            <!-- Botones de Acción -->
            <VCol cols="12" md="auto" class="d-flex align-center gap-1 ms-auto">
              <VBtn
                icon
                variant="tonal"
                :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
                size="36"
                class="rounded-circle"
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

              <VBtn icon variant="flat" color="primary" size="36" class="rounded-circle" :loading="loading" @click="fetchReport">
                <VIcon icon="tabler-player-play" size="18" />
                <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
              </VBtn>

              <VBtn icon variant="text" color="secondary" size="36" class="rounded-circle" @click="handleClearFilters">
                <VIcon icon="tabler-eraser" size="18" />
                <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
              </VBtn>
            </VCol>
          </VRow>

          <!-- Filtros Avanzados (Colapsable) -->
          <VExpandTransition>
            <div v-show="isAdvancedFiltersVisible">
              <VDivider class="my-3 border-opacity-10" />
              <VRow align="center" dense>
                <!-- Laboratorio -->
                <VCol cols="12" md="4">
                  <AppAutocomplete
                    v-model="selectedLaboratories"
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
                  />
                </VCol>

                <!-- Clasificación -->
                <VCol cols="12" md="4">
                  <div class="d-flex align-center gap-1">
                    <AppAutocomplete
                      v-model="selectedFinalClassification"
                      :items="classificationOptions"
                      placeholder="Clasificación (AAX...)"
                      clearable
                      density="compact"
                      hide-details
                      variant="outlined"
                      prepend-inner-icon="tabler-tags"
                      class="flex-grow-1"
                    />
                    <VBtn icon variant="text" size="28" color="info" class="flex-shrink-0">
                      <VIcon icon="tabler-info-circle" size="18" />
                      <VTooltip activator="parent" location="right" max-width="310">
                        <div style="line-height: 1.8">
                          <div class="text-caption font-weight-bold mb-2" style="font-size:11px;letter-spacing:1px;opacity:.7">GUÍA DE CLASIFICACIÓN</div>
                          <div class="text-caption mb-1"><span style="color:#4CAF50;font-weight:bold">A</span> — Genera el 80% del valor</div>
                          <div class="text-caption mb-1"><span style="color:#FF9800;font-weight:bold">B</span> — Contribuye el siguiente 15%</div>
                          <div class="text-caption mb-2"><span style="color:#9E9E9E;font-weight:bold">C</span> — Representa el 5% restante</div>
                          <div class="text-caption mb-1"><span style="color:#4CAF50;font-weight:bold">X</span> — Demanda constante</div>
                          <div class="text-caption mb-1"><span style="color:#FF9800;font-weight:bold">Y</span> — Demanda variable</div>
                          <div class="text-caption mb-2"><span style="color:#F44336;font-weight:bold">Z</span> — Demanda esporádica</div>
                        </div>
                      </VTooltip>
                    </VBtn>
                  </div>
                </VCol>

                <!-- ROI Mínimo -->
                <VCol cols="12" md="4">
                  <AppTextField
                    v-model="minGmroi"
                    type="number"
                    placeholder="ROI mínimo (%)"
                    density="compact"
                    hide-details
                    variant="outlined"
                    prepend-inner-icon="tabler-chart-line"
                  />
                </VCol>
              </VRow>
            </div>
          </VExpandTransition>
        </VCardText>
      </VCard>

      <!-- KPIs Dashboard -->
      <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
        <VCol cols="12" sm="6" md="4" class="pa-1">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
            <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-error), 0.1), transparent)"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar color="error" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-lock-square" size="26" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Capital Inmovilizado</span>
                  <h4 class="text-h4 font-weight-black mt-1 text-error">{{ formatCurrency(summaryStats.frozen_capital) }}</h4>
                </div>
              </div>
              <VDivider class="mb-3 opacity-20" />
              <span class="text-caption font-weight-medium text-medium-emphasis">Total atrapado en Stock Muerto</span>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-error))"></div>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="4" class="pa-1">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
            <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-primary), 0.1), transparent)"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar color="primary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-box" size="26" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Productos Afectados</span>
                  <h4 class="text-h4 font-weight-black mt-1">{{ summaryStats.total_products }} SKUs</h4>
                </div>
              </div>
              <VDivider class="mb-3 opacity-20" />
              <span class="text-caption font-weight-medium text-medium-emphasis">Items sin ventas y con existencias</span>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-primary))"></div>
          </VCard>
        </VCol>

        <!-- KPI: Distribución A/B/C -->
        <VCol cols="12" sm="12" md="4" class="pa-1">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
            <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-secondary), 0.08), transparent)"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="secondary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-chart-bar" size="26" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Distribución de Ventas</span>
                  <h4 class="text-h4 font-weight-black mt-1">{{ summaryStats.total_products }}</h4>
                </div>
              </div>
              <div class="d-flex rounded overflow-hidden mb-2" style="height:8px;gap:2px">
                <div
                  :style="{ width: summaryStats.total_products > 0 ? (summaryStats.count_a / summaryStats.total_products * 100) + '%' : '0%', background: '#4CAF50' }"
                  class="rounded-s"
                />
                <div
                  :style="{ width: summaryStats.total_products > 0 ? (summaryStats.count_b / summaryStats.total_products * 100) + '%' : '0%', background: '#FF9800' }"
                />
                <div
                  :style="{ width: summaryStats.total_products > 0 ? (summaryStats.count_c / summaryStats.total_products * 100) + '%' : '0%', background: '#9E9E9E' }"
                  class="rounded-e flex-grow-1"
                />
              </div>
              <div class="d-flex justify-space-between">
                <span class="text-caption d-flex align-center gap-1">
                  <span style="width:8px;height:8px;background:#4CAF50;border-radius:50%;display:inline-block"></span>
                  A: <b>{{ summaryStats.count_a }}</b>
                </span>
                <span class="text-caption d-flex align-center gap-1">
                  <span style="width:8px;height:8px;background:#FF9800;border-radius:50%;display:inline-block"></span>
                  B: <b>{{ summaryStats.count_b }}</b>
                </span>
                <span class="text-caption d-flex align-center gap-1">
                  <span style="width:8px;height:8px;background:#9E9E9E;border-radius:50%;display:inline-block"></span>
                  C: <b>{{ summaryStats.count_c }}</b>
                </span>
              </div>
            </VCardText>
            <div class="accent-border" style="background-color: rgb(var(--v-theme-secondary))"></div>
          </VCard>
        </VCol>
      </VRow>

      <!-- Tabla de Resultados -->
      <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VCardText class="d-flex justify-space-between align-center py-3">
          <h2 class="text-h6 font-weight-bold d-flex align-center">
            <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
            Productos en Stock Muerto
          </h2>
        </VCardText>
        <VDivider class="border-opacity-10" />

        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          v-model:sort-by="sortBy"
          :items-length="totalItems"
          :headers="headers"
          :items="items"
          :search="search"
          :loading="loading"
          class="premium-table"
          hover
          density="compact"
        >
          <template #item.id="{ item }">
            <a
              :href="'/inventory/traceability?q=' + item.id"
              target="_blank"
              class="text-decoration-none font-weight-black text-primary"
            >
              {{ item.id }}
            </a>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex flex-column py-2">
              <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.name">
                {{ item.name.toUpperCase() }}
              </span>
              <div class="d-flex align-center gap-1 text-super-xs">
                <span class="text-disabled truncate" style="max-inline-size: 200px;">
                  {{ item.active_ingredient || item.active_ingredient_inventory || 'SIN INGREDIENTE' }}
                </span>
                <span class="text-disabled mx-1">|</span>
                <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 150px;">
                  {{ item.laboratory_name || 'S/L' }}
                </span>
              </div>
            </div>
          </template>

          <template #item.current_stock="{ item }">
            <span class="font-weight-black text-high-emphasis">{{ item.current_stock }} uds</span>
          </template>

          <template #item.last_cost="{ item }">
            <span>{{ formatCurrency(item.last_cost) }}</span>
          </template>

          <template #item.inventory_value="{ item }">
            <span class="font-weight-black text-error">{{ formatCurrency(item.inventory_value) }}</span>
          </template>

          <template #item.last_sale_date="{ item }">
            <span class="font-weight-medium text-high-emphasis">{{ formatDate(item.last_sale_date) }}</span>
          </template>

          <template #item.sales_average="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="font-weight-bold text-primary">{{ item.sales_average }} uds/mes</span>
              <span class="text-caption text-medium-emphasis">{{ Math.round(item.sales_average * 12) }} uds (12m)</span>
            </div>
          </template>

          <template #item.final_classification="{ item }">
            <VTooltip location="top" content-class="bg-grey-900 border-opacity-100">
              <template #activator="{ props }">
                <VChip
                  size="large"
                  v-bind="props"
                  :color="getColorClass(item.final_classification)"
                  class="text-uppercase font-weight-black elevation-1"
                  variant="elevated"
                >
                  {{ item.final_classification }}
                </VChip>
              </template>
              <div class="d-flex flex-column gap-1 text-caption text-left text-white pa-1">
                <span><strong>A</strong>porte Ventas: {{ item.class_sales === 'A' ? 'Alto (80%)' : (item.class_sales === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
                <span><strong>M</strong>argen Cbción: {{ item.class_margin === 'A' ? 'Alto (80%)' : (item.class_margin === 'B' ? 'Medio (15%)' : 'Bajo (5%)') }}</span>
                <span><strong>R</strong>otación Dem.: {{ item.class_rotation === 'X' ? 'Constante' : (item.class_rotation === 'Y' ? 'Fluctuante' : 'Esporádica (Z)') }}</span>
              </div>
            </VTooltip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center">
              <IconBtn @click="handleViewStats(item)" color="primary" size="small">
                <VIcon icon="tabler-eye" />
                <VTooltip activator="parent" location="top">Ver Estadísticas</VTooltip>
              </IconBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Diálogo de Estadísticas del Producto -->
    <ProductStatsDialog
      v-model="isStatsDialogVisible"
      :product="selectedProductForStats"
    />
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

.report-dead-stock {
  min-block-size: 100vh;
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  transition: all 0.3s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
