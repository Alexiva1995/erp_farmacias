<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import axios from '@/plugins/axios';
import { formatCurrency } from '@/utils/currencyFormatter';
const loading = ref(false);
const errorMessage = ref(null);
const items = ref([]);
const totalItems = ref(0);

// Filters
const selectedDateRange = ref('30 days'); // Default 30 days
const selectedLaboratories = ref([]);
const selectedFinalClassification = ref(null);
const selectedAnalysisType = ref('all');
const minGmroi = ref(null);
const stockFilter = ref('all');

// Pagination & Sorting
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'total_sales', order: 'desc' }]);

// Global Search & KPIs
const search = ref('');
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

// Catalogs
const laboratories = ref([]);

const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return selectedLaboratories.value.length > 0 || selectedFinalClassification.value !== null;
});

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

// Mapping quick date ranges
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

const analysisTypeOptions = [
  { title: 'Análisis Completo', value: 'all' },
  { title: 'Stock Muerto (0 Ventas)', value: 'dead_stock' },
  { title: 'Productos Estrella (AA)', value: 'star_products' },
];

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'PRODUCTO', key: 'name', sortable: true },
  { title: 'Desempeño Comercial', key: 'sold_units', align: 'end', sortable: true },
  { title: 'Rentabilidad Bruta', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'GMROI (Retorno)', key: 'gmroi', align: 'center', sortable: true },
  { title: 'Cobertura (Días)', key: 'inventory_days', align: 'end', sortable: true },
  { title: 'Costo Unit.', key: 'last_cost', align: 'end', sortable: true },
  { title: 'Perfil ABC-XYZ', key: 'final_classification', align: 'center', sortable: true },
];

const fetchCatalogs = async () => {
  try {
    const labsRes = await axios.get('/laboratories');
    laboratories.value = labsRes.data;
  } catch (err) {
    console.error('Error loading catalogs:', err);
  }
};

const fetchReport = async () => {
  loading.value = true;
  errorMessage.value = null;
  try {
    const dates = getDateRange(selectedDateRange.value);
    
    const params = {
      page: page.value,
      itemsPerPage: itemsPerPage.value,
      sortBy: sortBy.value[0]?.key || 'total_sales',
      orderBy: sortBy.value[0]?.order || 'desc',
      start_date: dates.start_date,
      end_date: dates.end_date,
      laboratory_id: selectedLaboratories.value,
      final_classification: selectedFinalClassification.value,
      analysis_type: selectedAnalysisType.value,
      min_gmroi: minGmroi.value,
      stock_filter: stockFilter.value !== 'all' ? stockFilter.value : null,
    };

    const response = await axios.get('/bi/abc', { params });
    const responseData = response.data.data;
    const summary = response.data.summary;
    
    items.value = responseData;
    totalItems.value = response.data.meta.total;

    // Usar KPIs calculados globalmente por el servidor
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
    
  } catch (error) {
    console.error('Error fetching ABC report:', error);
    errorMessage.value = 'No se pudo cargar el reporte ABC. Verifique la conexión de datos e intente nuevamente.';
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

const getGmroiColor = (gmroi) => {
  if (gmroi >= 500) return 'text-success';
  if (gmroi >= 200) return 'text-primary';
  if (gmroi > 0) return 'text-warning';
  return 'text-error';
};

watch([page, itemsPerPage, sortBy, selectedDateRange, selectedLaboratories, selectedFinalClassification, selectedAnalysisType, minGmroi, stockFilter], () => {
  fetchReport();
}, { deep: true });

onMounted(() => {
  fetchCatalogs();
  fetchReport();
});

const handleClearFilters = () => {
  search.value = '';
  selectedDateRange.value = '30 days';
  selectedLaboratories.value = [];
  selectedFinalClassification.value = null;
  selectedAnalysisType.value = 'all';
  minGmroi.value = null;
  stockFilter.value = 'all';
  isAdvancedFiltersVisible.value = false;
};
</script>

<template>
  <div class="report-abc-view pb-12">
    <div class="d-flex flex-column gap-1 mt-1">    <!-- Filtros Estandarizados -->
    <VCard class="mb-4 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-3">

        <!-- Fila 1: Filtros principales (siempre visible) -->
        <VRow align="center" dense>
          <!-- Buscador -->
          <VCol cols="12" md="3">
            <AppTextField
              v-model="search"
              placeholder="Buscar producto, ID..."
              prepend-inner-icon="tabler-search"
              clearable
              density="compact"
              hide-details
              variant="outlined"
              :disabled="loading"
            />
          </VCol>

          <!-- Período -->
          <VCol cols="12" md="3">
            <AppSelect
              v-model="selectedDateRange"
              :items="dateRangeOptions"
              placeholder="Período de Análisis"
              density="compact"
              hide-details
              variant="outlined"
              prepend-inner-icon="tabler-calendar-stats"
              :disabled="loading"
            />
          </VCol>

          <!-- Modo de Análisis -->
          <VCol cols="12" md="3">
            <AppSelect
              v-model="selectedAnalysisType"
              :items="analysisTypeOptions"
              placeholder="Modo de Análisis"
              density="compact"
              hide-details
              variant="outlined"
              prepend-inner-icon="tabler-analyze"
              :disabled="loading"
            />
          </VCol>

          <!-- Botones de acción -->
          <VCol cols="12" md="auto" class="d-flex align-center gap-1 ms-auto">
            <!-- Toggle filtros avanzados -->
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

            <!-- Ejecutar -->
            <VBtn icon variant="flat" color="primary" size="36" class="rounded-circle" :loading="loading" :disabled="loading" @click="fetchReport">
              <VIcon icon="tabler-player-play" size="18" />
              <VTooltip activator="parent" location="top">Aplicar Filtros</VTooltip>
            </VBtn>

            <!-- Limpiar -->
            <VBtn icon variant="text" color="secondary" size="36" class="rounded-circle" :disabled="loading" @click="handleClearFilters">
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

              <!-- Laboratorio -->
              <VCol cols="12" md="3">
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
                  :disabled="loading"
                />
              </VCol>

              <!-- Clasificación + Ícono info -->
              <VCol cols="12" md="3">
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
                    :disabled="loading"
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
              <VCol cols="12" md="2">
                <AppTextField
                  v-model="minGmroi"
                  type="number"
                  placeholder="ROI mínimo (%)"
                  density="compact"
                  hide-details
                  variant="outlined"
                  prepend-inner-icon="tabler-chart-line"
                  :disabled="loading"
                />
              </VCol>

              <!-- Estado de Stock -->
              <VCol cols="12" md="2">
                <AppSelect
                  v-model="stockFilter"
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
                />
              </VCol>

            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>
    </div>    <!-- Banner de error si la carga falla -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      class="mb-4 rounded-lg"
      closable
      @click:close="errorMessage = null"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ errorMessage }}</span>
        <VBtn size="small" color="error" variant="flat" @click="fetchReport">
          <VIcon icon="tabler-refresh" size="14" class="me-1" />
          Reintentar
        </VBtn>
      </div>
    </VAlert>

    <!-- Dashboard KPIs Estilo Premium -->
    <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
      <!-- Skeletons durante carga -->
      <template v-if="loading">
        <VCol v-for="n in 4" :key="'skeleton-kpi-' + n" cols="6" class="pa-1 abc-kpi-col">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full pa-5">
            <VSkeletonLoader type="list-item-avatar-two-line" class="bg-transparent" />
          </VCard>
        </VCol>
        <VCol cols="12" class="pa-1 abc-kpi-col-dist">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full pa-5">
            <VSkeletonLoader type="list-item-two-line" class="bg-transparent" />
          </VCard>
        </VCol>
      </template>

      <!-- KPIs reales -->
      <template v-else>
        <VCol v-for="(kpi, index) in [
          {
            title: selectedAnalysisType === 'dead_stock' ? 'Capital Inmovilizado' : 'Ventas Globales',
            value: formatCurrency(selectedAnalysisType === 'dead_stock' ? summaryStats.frozen_capital : summaryStats.total_volume),
            color: selectedAnalysisType === 'dead_stock' ? 'error' : 'primary',
            icon: selectedAnalysisType === 'dead_stock' ? 'tabler-lock-square' : 'tabler-coin',
            desc: selectedAnalysisType === 'dead_stock' ? 'Dinero atrapado en stock' : 'Total facturado en el periodo'
          },
          {
            title: 'Prod. Estrella',
            value: summaryStats.aax_products,
            color: 'success',
            icon: 'tabler-star',
            desc: 'Clasificación AAX/AAY'
          },
          {
            title: 'Margen Global',
            value: summaryStats.avg_margin.toFixed(2) + '%',
            color: summaryStats.avg_margin > 0 ? 'primary' : 'error',
            icon: 'tabler-percentage',
            desc: 'Rentabilidad promedio'
          },
          {
            title: 'Quiebre Crítico',
            value: summaryStats.critical_stockouts,
            color: summaryStats.critical_stockouts > 0 ? 'error' : 'success',
            icon: 'tabler-alert-triangle',
            desc: 'Productos A/B sin stock'
          }
        ]" :key="index" cols="6" class="pa-1 abc-kpi-col">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
            <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)` }"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-4">
                <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon :icon="kpi.icon" size="26" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">{{ kpi.title }}</span>
                  <h4 class="text-h4 font-weight-black mt-1">{{ kpi.value }}</h4>
                </div>
              </div>
              <VDivider class="mb-3 opacity-20" />
              <div class="d-flex align-center justify-space-between">
                <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
                <VIcon icon="tabler-chart-pie" size="16" :color="kpi.color" class="opacity-50" />
              </div>
            </VCardText>
            <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"></div>
          </VCard>
        </VCol>

        <!-- KPI: Distribución A/B/C -->
        <VCol cols="12" class="pa-1 abc-kpi-col-dist">
          <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
            <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-secondary), 0.08), transparent)"></div>
            <VCardText class="pa-5 relative-content">
              <div class="d-flex align-center justify-space-between mb-3">
                <VAvatar color="secondary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                  <VIcon icon="tabler-chart-bar" size="26" />
                </VAvatar>
                <div class="text-right">
                  <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Distribución</span>
                  <h4 class="text-h4 font-weight-black mt-1">{{ summaryStats.total_products }}</h4>
                </div>
              </div>

              <!-- Barra de distribución A/B/C -->
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
      </template>
    </VRow>

    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Head de Tabla -->
      <VCardText class="d-flex justify-space-between align-center py-3">
        <h2 class="text-h6 font-weight-bold d-flex align-center">
          <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
          Resultados del Análisis
        </h2>
      </VCardText>
      <VDivider class="border-opacity-10" />

      <!-- Vista Tabla: solo desktop -->
      <div class="d-none d-md-block">
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

          <!-- Combinación: Producto y Laboratorio -->
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

          <!-- Combinación: Unidades y Ventas Monetarias -->
          <template #item.sold_units="{ item }">
            <div class="d-flex flex-column align-end">
               <span class="font-weight-bold text-success">{{ formatCurrency(item.total_sales) }}</span>
               <div class="d-flex align-center gap-1">
                 <span class="text-super-xs text-primary font-weight-bold">Aporte: {{ item.contribution_sales_pct.toFixed(2) }}%</span>
                 <span class="text-caption text-medium-emphasis"><VIcon icon="tabler-box" size="12" class="me-1"/>{{ item.sold_units }} unds</span>
               </div>
            </div>
          </template>
          
          <!-- Combinación: Rentabilidad % y Costos Absolutos -->
          <template #item.margin_percentage="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="font-weight-bold text-base" :class="item.margin_percentage > 0 ? 'text-primary' : 'text-error'">
                Margen: {{ item.margin_percentage }}%
              </span>
              <div class="d-flex align-center gap-1">
                <span class="text-super-xs text-info font-weight-bold">Aporte: {{ item.contribution_margin_pct.toFixed(2) }}%</span>
                <span class="text-caption text-medium-emphasis">Ganancia: {{ formatCurrency(item.margin_amount) }}</span>
              </div>
            </div>
          </template>

          <!-- GMROI -->
          <template #item.gmroi="{ item }">
            <div class="d-flex flex-column align-center">
              <span class="font-weight-black text-h6" :class="getGmroiColor(item.gmroi)">
                {{ item.gmroi >= 9999 ? 'MAX' : Math.round(item.gmroi) + '%' }}
              </span>
              <span class="text-super-xs text-disabled font-weight-bold">ROI ANUAL</span>
            </div>
          </template>

          <!-- Combinación: Stock Estático y Días Restantes -->
          <template #item.current_stock="{ item }">
             <div class="d-flex flex-column align-end">
              <span class="font-weight-bold">{{ item.current_stock }} unds</span>
              <span class="text-caption text-medium-emphasis mt-1">
                <VIcon icon="tabler-calendar-time" size="12" class="me-1" :class="item.inventory_days < 10 ? 'text-error' : ''"/>
                <span v-if="item.inventory_days === 9999" class="text-warning">Incalculable</span>
                <span v-else :class="item.inventory_days < 10 ? 'text-error' : ''">{{ Math.round(item.inventory_days) }} días d/inv</span>
              </span>
            </div>
          </template>
          
          <template #item.last_cost="{ item }">
            <span class="font-weight-medium">{{ formatCurrency(item.last_cost) }}</span>
          </template>

          <!-- BADGES INTERACTIVOS -->
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
                <span><strong>R</strong>otación Dem.: {{ item.class_rotation === 'X' ? 'Constante (Seguro)' : (item.class_rotation === 'Y' ? 'Fluctuante (Normal)' : 'Esporádica (Riesgo)') }}</span>
              </div>
            </VTooltip>
          </template>

        </VDataTableServer>
      </div>

      <!-- Vista Cards: solo móvil -->
      <div class="d-md-none">
        <VProgressLinear v-if="loading" indeterminate color="primary" />
        <div v-if="items.length === 0 && !loading" class="text-center pa-8 text-medium-emphasis">
          <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
          <p>Sin resultados para los filtros aplicados</p>
        </div>
        <div v-for="item in items" :key="item.id" class="px-2 py-1">
          <VCard variant="flat" class="product-mobile-card border mb-2">
            <div class="pa-3">
              <!-- Cabecera -->
              <div class="d-flex align-start justify-space-between gap-2">
                <div class="flex-grow-1 min-width-0">
                  <div class="d-flex align-center gap-1 mb-1">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                      <span class="text-primary text-xs">#{{ item.id }}</span>
                      <span class="mx-1 text-disabled">|</span>
                      {{ item.name }}
                    </h3>
                  </div>
                  <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs">
                    <span class="text-medium-emphasis font-weight-medium text-truncate" style="max-inline-size: 150px;">
                      {{ item.laboratory_name || 'S/L' }}
                    </span>
                    <span class="text-disabled">|</span>
                    <span class="text-disabled truncate" style="max-inline-size: 120px;">
                      {{ item.active_ingredient || 'Sin ingrediente' }}
                    </span>
                  </div>
                </div>
                <VChip
                  :color="getColorClass(item.final_classification)"
                  class="text-uppercase font-weight-black flex-shrink-0"
                  variant="elevated"
                  size="x-small"
                  label
                >
                  {{ item.final_classification }}
                </VChip>
              </div>

              <VDivider class="my-3 border-opacity-10" />

              <!-- Métricas en grilla (Estilo Inventory) -->
              <div class="metrics-grid rounded border-dashed-thin bg-var-theme-background">
                <VRow dense class="ma-0">
                  <VCol cols="6" class="pa-2 border-r border-b border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Ventas ({{ item.contribution_sales_pct.toFixed(1) }}%)</div>
                    <div class="text-sm font-weight-black text-success">{{ formatCurrency(item.total_sales) }}</div>
                    <div class="text-super-xs text-disabled">{{ item.sold_units }} uds</div>
                  </VCol>
                  <VCol cols="6" class="pa-2 border-b border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Margen ({{ item.contribution_margin_pct.toFixed(1) }}%)</div>
                    <div class="text-sm font-weight-black" :class="item.margin_percentage > 0 ? 'text-primary' : 'text-error'">
                      {{ item.margin_percentage }}%
                    </div>
                    <div class="text-super-xs text-disabled">{{ formatCurrency(item.margin_amount) }}</div>
                  </VCol>
                  <VCol cols="6" class="pa-2 border-r border-opacity-10">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">ROI Anual</div>
                    <div class="text-sm font-weight-black" :class="getGmroiColor(item.gmroi)">
                      {{ item.gmroi >= 9999 ? 'MAX' : Math.round(item.gmroi) + '%' }}
                    </div>
                  </VCol>
                  <VCol cols="6" class="pa-2">
                    <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Stock / Cobertura</div>
                    <div class="text-sm font-weight-black" :class="item.current_stock === 0 ? 'text-error' : ''">{{ item.current_stock }} uds</div>
                    <div class="text-super-xs font-weight-bold" :class="item.inventory_days < 10 ? 'text-error' : 'text-disabled'">
                      {{ item.inventory_days === 9999 ? 'Sin rotación' : Math.round(item.inventory_days) + ' días' }}
                    </div>
                  </VCol>
                </VRow>
              </div>
            </div>

            <!-- Acciones (Estilo Inventory) -->
            <div class="d-flex border-t border-opacity-10">
              <VBtn 
                :href="'/inventory/traceability?q=' + item.id" 
                target="_blank"
                block 
                color="primary" 
                variant="text" 
                class="rounded-0 text-caption font-weight-bold" 
                height="40"
              >
                <VIcon icon="tabler-history" size="18" class="me-2" />
                Ver Trazabilidad
              </VBtn>
            </div>
          </VCard>
        </div>

        <!-- Paginación móvil -->
        <div class="d-flex justify-center align-center pa-3 gap-3">
          <VBtn icon variant="text" size="32" :disabled="page <= 1" @click="page--">
            <VIcon icon="tabler-chevron-left" size="18" />
          </VBtn>
          <span class="text-caption text-medium-emphasis">Pág. {{ page }}</span>
          <VBtn icon variant="text" size="32" :disabled="items.length < itemsPerPage" @click="page++">
            <VIcon icon="tabler-chevron-right" size="18" />
          </VBtn>
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

.metric-box {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.border-b {
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1);
}

.abc-kpi-col {
  flex: 0 0 50%;
  max-width: 50%;
}

.abc-kpi-col-dist {
  flex: 0 0 100%;
  max-width: 100%;
}

@media (min-width: 960px) {
  .abc-kpi-col {
    flex: 0 0 20% !important;
    max-width: 20% !important;
  }
  .abc-kpi-col-dist {
    flex: 0 0 20% !important;
    max-width: 20% !important;
  }
}

.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.metrics-grid {
  background-color: rgba(var(--v-border-color), 0.05);
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

.report-abc-view {
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

.letter-spacing-tight { letter-spacing: -0.02em; }
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
