<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from '@/plugins/axios';
import ProductStatsDialog from '@/components/dialogs/ProductStatsDialog.vue';
import DeadStockFilters from './components/DeadStockFilters.vue';
import DeadStockKpiCards from './components/DeadStockKpiCards.vue';
import DeadStockTable from './components/DeadStockTable.vue';

const loading = ref(false);
const error = ref(null);
const items = ref([]);
const totalItems = ref(0);

// Estado para diálogo de estadísticas de producto
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
const selectedDateRange = ref('30 days');
const selectedLaboratories = ref([]);
const selectedFinalClassification = ref(null);
const minGmroi = ref(null);
const isAdvancedFiltersVisible = ref(false);

// Paginación y Ordenación
const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref([{ key: 'inventory_value', order: 'desc' }]);

// Búsqueda global con Debounce (400ms)
const search = ref('');
const debouncedSearch = ref('');
let searchTimeout = null;

watch(search, (newVal) => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    debouncedSearch.value = newVal;
    page.value = 1;
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

// Catálogo de laboratorios
const laboratories = ref([]);

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
      analysis_type: 'dead_stock',
      min_gmroi: minGmroi.value,
      search: debouncedSearch.value,
    };

    const response = await axios.get('/bi/abc', { params });
    items.value = response.data.data;
    totalItems.value = response.data.meta.total;

    if (response.data.summary) {
      const summary = response.data.summary;
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
      <!-- Mensaje de Error -->
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

      <!-- Filtros Desacoplados -->
      <DeadStockFilters
        v-model:search="search"
        v-model:selected-date-range="selectedDateRange"
        v-model:selected-laboratories="selectedLaboratories"
        v-model:selected-final-classification="selectedFinalClassification"
        v-model:min-gmroi="minGmroi"
        v-model:is-advanced-filters-visible="isAdvancedFiltersVisible"
        :loading="loading"
        :laboratories="laboratories"
        @fetch="fetchReport"
        @clear="handleClearFilters"
      />

      <!-- Tarjetas de KPIs Desacopladas con Skeletons -->
      <DeadStockKpiCards
        :loading="loading"
        :summary-stats="summaryStats"
      />

      <!-- Tabla de Resultados Desacoplada -->
      <DeadStockTable
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        v-model:sort-by="sortBy"
        :loading="loading"
        :items="items"
        :total-items="totalItems"
        :search="search"
        @view-stats="handleViewStats"
        @clear-filters="handleClearFilters"
      />
    </div>

    <!-- Diálogo de Estadísticas -->
    <ProductStatsDialog
      v-model="isStatsDialogVisible"
      :product="selectedProductForStats"
    />
  </div>
</template>

<style scoped>
.report-dead-stock {
  min-block-size: 100vh;
}
.gap-1 { gap: 4px !important; }
</style>
