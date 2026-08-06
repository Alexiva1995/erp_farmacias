<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from '@/plugins/axios';
import { formatCurrency } from '@/utils/currencyFormatter';
import AbcReportFilters from './components/AbcReportFilters.vue';
import AbcReportKpiCards from './components/AbcReportKpiCards.vue';
import AbcReportMobileView from './components/AbcReportMobileView.vue';

const loading = ref(false);
const errorMessage = ref(null);
const items = ref([]);
const totalItems = ref(0);

// Filters
const selectedDateRange = ref('30 days');
const selectedLaboratories = ref([]);
const selectedFinalClassification = ref(null);
const selectedAnalysisType = ref('all');
const minGmroi = ref(null);
const stockFilter = ref('all');
const search = ref('');
const isAdvancedFiltersVisible = ref(false);

// Pagination & Sorting
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'total_sales', order: 'desc' }]);

// Catalogs & Stats
const laboratories = ref([]);
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
    end_date: end.toISOString().split('T')[0],
  };
};

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'PRODUCTO', key: 'name', sortable: true },
  { title: 'Desempeño Comercial', key: 'sold_units', align: 'end', sortable: true },
  { title: 'Rentabilidad Bruta', key: 'margin_percentage', align: 'end', sortable: true },
  { title: 'GMROI (Retorno)', key: 'gmroi', align: 'center', sortable: true },
  { title: 'Cobertura (Días)', key: 'current_stock', align: 'end', sortable: true },
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
    <!-- Componente Desacoplado: Filtros -->
    <AbcReportFilters
      v-model:search="search"
      v-model:selected-date-range="selectedDateRange"
      v-model:selected-analysis-type="selectedAnalysisType"
      v-model:selected-laboratories="selectedLaboratories"
      v-model:selected-final-classification="selectedFinalClassification"
      v-model:min-gmroi="minGmroi"
      v-model:stock-filter="stockFilter"
      v-model:is-advanced-filters-visible="isAdvancedFiltersVisible"
      :loading="loading"
      :laboratories="laboratories"
      @fetch="fetchReport"
      @clear="handleClearFilters"
    />

    <!-- Banner de error -->
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

    <!-- Componente Desacoplado: KPIs -->
    <AbcReportKpiCards
      :loading="loading"
      :selected-analysis-type="selectedAnalysisType"
      :summary-stats="summaryStats"
    />

    <!-- Contenedor Principal de Resultados -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="d-flex justify-space-between align-center py-3">
        <h2 class="text-h6 font-weight-bold d-flex align-center">
          <VIcon icon="tabler-list-details" class="me-2 text-primary" size="22" />
          Resultados del Análisis
        </h2>
      </VCardText>
      <VDivider class="border-opacity-10" />

      <!-- Vista Tabla Desktop -->
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
          <!-- Empty State Personalizado -->
          <template #no-data>
            <div class="py-8 text-center text-medium-emphasis">
              <VIcon icon="tabler-database-off" size="48" class="mb-3 opacity-40" />
              <p class="text-body-1 font-weight-medium mb-1">No se encontraron productos para los criterios aplicados</p>
              <p class="text-caption text-disabled mb-4">Intente ajustar o borrar los filtros de búsqueda</p>
              <VBtn size="small" color="primary" variant="outlined" @click="handleClearFilters">
                <VIcon icon="tabler-eraser" size="16" class="me-1" />
                Limpiar Filtros
              </VBtn>
            </div>
          </template>

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

          <template #item.sold_units="{ item }">
            <div class="d-flex flex-column align-end">
               <span class="font-weight-bold text-success">{{ formatCurrency(item.total_sales) }}</span>
               <div class="d-flex align-center gap-1">
                 <span class="text-super-xs text-primary font-weight-bold">Aporte: {{ item.contribution_sales_pct ? item.contribution_sales_pct.toFixed(2) : '0.00' }}%</span>
                 <span class="text-caption text-medium-emphasis"><VIcon icon="tabler-box" size="12" class="me-1"/>{{ item.sold_units }} unds</span>
               </div>
            </div>
          </template>
          
          <template #item.margin_percentage="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="font-weight-bold text-base" :class="item.margin_percentage > 0 ? 'text-primary' : 'text-error'">
                Margen: {{ item.margin_percentage }}%
              </span>
              <div class="d-flex align-center gap-1">
                <span class="text-super-xs text-info font-weight-bold">Aporte: {{ item.contribution_margin_pct ? item.contribution_margin_pct.toFixed(2) : '0.00' }}%</span>
                <span class="text-caption text-medium-emphasis">Ganancia: {{ formatCurrency(item.margin_amount) }}</span>
              </div>
            </div>
          </template>

          <template #item.gmroi="{ item }">
            <div class="d-flex flex-column align-center">
              <span class="font-weight-black text-h6" :class="getGmroiColor(item.gmroi)">
                {{ item.gmroi >= 9999 ? 'MAX' : Math.round(item.gmroi) + '%' }}
              </span>
              <span class="text-super-xs text-disabled font-weight-bold">ROI ANUAL</span>
            </div>
          </template>

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

      <!-- Vista Móvil Desacoplada -->
      <AbcReportMobileView
        v-model:page="page"
        :items="items"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :get-color-class="getColorClass"
        :get-gmroi-color="getGmroiColor"
      />
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
  line-height: 1.2;
}

.report-abc-view {
  min-block-size: 100vh;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
</style>
