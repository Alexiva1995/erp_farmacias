<script setup>
import { onMounted, watch } from 'vue';
import { useLaboratoryReport } from '@/composables/useLaboratoryReport';

import LaboratoryReportFilters from './components/LaboratoryReportFilters.vue';
import LaboratoryReportRankings from './components/LaboratoryReportRankings.vue';
import LaboratoryReportCharts from './components/LaboratoryReportCharts.vue';
import LaboratoryReportBenchmarking from './components/LaboratoryReportBenchmarking.vue';
import LaboratoryReportDeepDive from './components/LaboratoryReportDeepDive.vue';

// --- COMPOSABLE DE ESTADO Y CONSULTAS ---
const {
  loading,
  groupByCorporate,
  startDate,
  endDate,
  laboratories,
  dashboardData,
  pageUnits,
  pageRevenue,
  pageStock,
  loadingUnits,
  loadingRevenue,
  loadingStock,
  labA,
  labB,
  benchmarkingData,
  loadingBenchmarking,
  selectedLabId,
  deepDiveData,
  loadingDeepDive,
  fetchCatalogs,
  fetchDashboard,
  fetchRankings,
  fetchBenchmarking,
  fetchDeepDive,
  resetComparisons
} = useLaboratoryReport();

// --- WATCHERS ---
watch(groupByCorporate, () => {
  resetComparisons();
  selectedLabId.value = null;
  deepDiveData.top_products = [];
  fetchCatalogs();
  fetchDashboard();
});

watch([startDate, endDate], () => {
  fetchDashboard();
  if (labA.value && labB.value) fetchBenchmarking();
  if (selectedLabId.value) fetchDeepDive(selectedLabId.value);
});

// --- LIFECYCLE ---
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
