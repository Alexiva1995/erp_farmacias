<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';

import LaboratoryReportFilters from './components/LaboratoryReportFilters.vue';
import LaboratoryReportRankings from './components/LaboratoryReportRankings.vue';
import LaboratoryReportCharts from './components/LaboratoryReportCharts.vue';
import LaboratoryReportBenchmarking from './components/LaboratoryReportBenchmarking.vue';
import LaboratoryReportDeepDive from './components/LaboratoryReportDeepDive.vue';

// --- ESTADOS Y FILTROS ---
const loading = ref(false);
const groupByCorporate = ref(false);

const dashboardData = reactive({
  rankings: { 
    by_units: { data: [] }, 
    by_revenue: { data: [] }, 
    by_stock: { data: [] } 
  },
  trends: [],
  stock_on_hand: [],
  profitability: []
});

const startDate = ref('2026-04-01');
const endDate = ref(new Date().toISOString().split('T')[0]);

// Catálogos
const laboratories = reactive([]);

// Paginación Rankings
const pageUnits = ref(1);
const pageRevenue = ref(1);
const pageStock = ref(1);
const loadingUnits = ref(false);
const loadingRevenue = ref(false);
const loadingStock = ref(false);

// Benchmarking
const labA = ref(null);
const labB = ref(null);
const benchmarkingData = reactive({
  lab_a: null,
  lab_b: null,
  shared_groups: []
});
const loadingBenchmarking = ref(false);

// Deep Dive
const selectedLabId = ref(null);
const deepDiveData = reactive({
  top_products: [],
  group_performance: [],
  stats: null
});
const loadingDeepDive = ref(false);

// --- CARGA DE DATOS ---
const fetchCatalogs = async () => {
  try {
    const { data } = await axios.get('/bi/laboratories/catalogs', {
      params: { group_by_corporate: groupByCorporate.value }
    });
    laboratories.splice(0, laboratories.length, ...(Array.isArray(data) ? data : []));
  } catch (error) {
    console.error('Error cargando catálogos:', error);
    toast.error('Error cargando catálogos de laboratorios');
  }
};

const fetchDashboard = async () => {
  loading.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
      group_by_corporate: groupByCorporate.value
    };
    const { data } = await axios.get('/bi/laboratories/dashboard', { params });
    dashboardData.rankings = data.rankings || { by_units: { data: [] }, by_revenue: { data: [] }, by_stock: { data: [] } };
    dashboardData.trends = data.trends || [];
    dashboardData.stock_on_hand = data.stock_on_hand || [];
    dashboardData.profitability = data.profitability || [];
  } catch (error) {
    console.error('Error al cargar dashboard:', error);
    toast.error('Error al cargar los datos del dashboard');
  } finally {
    loading.value = false;
  }
};

const fetchRankings = async (metric = 'total_units', page = 1) => {
  let isLoading, pageRef, dataKey;
  
  if (metric === 'total_units') {
    isLoading = loadingUnits;
    pageRef = pageUnits;
    dataKey = 'by_units';
  } else if (metric === 'total_revenue') {
    isLoading = loadingRevenue;
    pageRef = pageRevenue;
    dataKey = 'by_revenue';
  } else {
    isLoading = loadingStock;
    pageRef = pageStock;
    dataKey = 'by_stock';
  }
  
  isLoading.value = true;
  try {
    const params = {
      metric,
      page,
      start_date: startDate.value,
      end_date: endDate.value,
      group_by_corporate: groupByCorporate.value
    };
    const { data } = await axios.get('/bi/laboratories/rankings', { params });
    dashboardData.rankings[dataKey] = data;
    pageRef.value = page;
  } catch (error) {
    console.error(`Error cargando ranking ${metric}:`, error);
    toast.error('Error al cargar los rankings');
  } finally {
    isLoading.value = false;
  }
};

const fetchBenchmarking = async () => {
  if (!labA.value || !labB.value) return;
  
  loadingBenchmarking.value = true;
  try {
    const params = {
      lab_a: labA.value,
      lab_b: labB.value,
      start_date: startDate.value,
      end_date: endDate.value,
      group_by_corporate: groupByCorporate.value
    };
    const { data } = await axios.get('/bi/laboratories/benchmarking', { params });
    benchmarkingData.lab_a = data.lab_a;
    benchmarkingData.lab_b = data.lab_b;
    benchmarkingData.shared_groups = data.shared_groups || [];
  } catch (error) {
    console.error('Error en benchmarking:', error);
    toast.error('Error al realizar la comparativa de laboratorios');
  } finally {
    loadingBenchmarking.value = false;
  }
};

const fetchDeepDive = async (id) => {
  if (!id) return;
  loadingDeepDive.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
      group_by_corporate: groupByCorporate.value
    };
    const { data } = await axios.get(`/bi/laboratories/${id}/deep-dive`, { params });
    deepDiveData.top_products = data.top_products || [];
    deepDiveData.group_performance = data.group_performance || [];
    deepDiveData.stats = data.stats || null;
    selectedLabId.value = id;
  } catch (error) {
    console.error('Error en deep dive:', error);
    toast.error('Error al obtener detalle del laboratorio');
  } finally {
    loadingDeepDive.value = false;
  }
};

// --- WATCHERS Y LIFECYCLE ---
watch(groupByCorporate, () => {
  labA.value = null;
  labB.value = null;
  benchmarkingData.lab_a = null;
  benchmarkingData.lab_b = null;
  benchmarkingData.shared_groups = [];
  fetchCatalogs();
  fetchDashboard();
});

watch([startDate, endDate], () => {
  fetchDashboard();
  if (labA.value && labB.value) fetchBenchmarking();
  if (selectedLabId.value) fetchDeepDive(selectedLabId.value);
});

onMounted(() => {
  fetchCatalogs();
  fetchDashboard();
});
</script>

<template>
  <VContainer fluid class="report-laboratories-container pa-0">
    <div class="bi-report-grid">
      <!-- FILTROS PRINCIPALES -->
      <LaboratoryReportFilters
        v-model:group-by-corporate="groupByCorporate"
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :loading="loading"
        @refresh="fetchDashboard"
      />

      <!-- RANKINGS -->
      <LaboratoryReportRankings
        :rankings="dashboardData.rankings"
        :page-units="pageUnits"
        :page-revenue="pageRevenue"
        :page-stock="pageStock"
        :loading="loading"
        :loading-units="loadingUnits"
        :loading-revenue="loadingRevenue"
        :loading-stock="loadingStock"
        @fetch-rankings="fetchRankings"
        @select-lab="fetchDeepDive"
      />

      <!-- TENDENCIAS, CUOTA Y GRÁFICOS -->
      <LaboratoryReportCharts
        :trends="dashboardData.trends"
        :rankings-by-revenue="dashboardData.rankings.by_revenue?.data || []"
        :profitability="dashboardData.profitability"
        :stock-on-hand="dashboardData.stock_on_hand"
        :loading="loading"
      />

      <!-- BENCHMARKING -->
      <LaboratoryReportBenchmarking
        v-model:lab-a="labA"
        v-model:lab-b="labB"
        :laboratories="laboratories"
        :benchmarking-data="benchmarkingData"
        :loading="loading"
        :loading-benchmarking="loadingBenchmarking"
        @fetch-benchmarking="fetchBenchmarking"
      />

      <!-- DEEP DIVE -->
      <LaboratoryReportDeepDive
        :selected-lab-id="selectedLabId"
        :laboratories="laboratories"
        :deep-dive-data="deepDiveData"
        :loading-deep-dive="loadingDeepDive"
      />
    </div>
  </VContainer>
</template>

<style scoped>
.bi-report-grid :deep(.v-row) {
  margin: -6px !important;
}

.bi-report-grid :deep(.v-col) {
  padding: 6px !important;
}

.bi-report-grid :deep(.v-row + .v-row) {
  margin-top: 6px !important;
}
</style>
