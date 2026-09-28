<script setup>
import { onMounted, watch } from 'vue';
import { useLaboratoryReport } from '@/composables/useLaboratoryReport';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

import LaboratoryReportFilters from './components/LaboratoryReportFilters.vue';
import LaboratoryReportRankings from './components/LaboratoryReportRankings.vue';
import LaboratoryReportCharts from './components/LaboratoryReportCharts.vue';
import LaboratoryReportBenchmarking from './components/LaboratoryReportBenchmarking.vue';
import LaboratoryReportDeepDive from './components/LaboratoryReportDeepDive.vue';

const { formatCurrency } = useCurrencyConverter();

// --- COMPOSABLE DE ESTADO Y CONSULTAS ---
const {
  loading,
  groupByCorporate,
  startDate,
  endDate,
  laboratories,
  dashboardData,
  summaryKpis,
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
  resetComparisons,
  exportExecutivePdf,
  exportExecutiveCsv
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
    <!-- CABECERA PRINCIPAL -->
    <div class="d-flex flex-wrap align-center justify-space-between mb-4 ga-2">
      <div>
        <h4 class="text-h4 font-weight-bold text-high-emphasis">
          Inteligencia de Laboratorios
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          Auditoría y análisis multidimensional de rendimiento comercial, rotación y rentabilidad por fabricante
        </div>
      </div>
    </div>

    <!-- TARJETAS DE KPIS RESUMIDOS (SCORECARDS) -->
    <VRow class="mb-2">
      <!-- Total Facturación -->
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg h-100">
          <VCardText class="pa-4 d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                Facturación Total (USD)
              </div>
              <div class="text-h5 font-weight-black text-success mt-1">
                {{ formatCurrency(summaryKpis.totalRevenue) }}
              </div>
              <div class="text-xs text-medium-emphasis mt-1">
                Top facturación en el período
              </div>
            </div>
            <VAvatar color="success" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-currency-dollar" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Total Unidades -->
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg h-100">
          <VCardText class="pa-4 d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                Unidades Comercializadas
              </div>
              <div class="text-h5 font-weight-black text-primary mt-1">
                {{ Math.round(summaryKpis.totalUnits).toLocaleString() }} Unds
              </div>
              <div class="text-xs text-medium-emphasis mt-1">
                Volumen consolidado vendido
              </div>
            </div>
            <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-shopping-cart" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Margen Promedio -->
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg h-100">
          <VCardText class="pa-4 d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                Margen Promedio
              </div>
              <div class="text-h5 font-weight-black text-secondary mt-1">
                {{ summaryKpis.avgMargin.toFixed(1) }}%
              </div>
              <div class="text-xs text-medium-emphasis mt-1">
                Rendimiento bruto ponderado
              </div>
            </div>
            <VAvatar color="secondary" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-percentage" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Inversión en Stock -->
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg h-100">
          <VCardText class="pa-4 d-flex align-center justify-space-between">
            <div>
              <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">
                Inventario en Stock
              </div>
              <div class="text-h5 font-weight-black text-warning mt-1">
                {{ formatCurrency(summaryKpis.totalStockValue) }}
              </div>
              <div class="text-xs text-medium-emphasis mt-1">
                Valorización física disponible
              </div>
            </div>
            <VAvatar color="warning" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-packages" size="24" />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- FILTROS PRINCIPALES -->
    <LaboratoryReportFilters
      v-model:group-by-corporate="groupByCorporate"
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      @refresh="fetchDashboard"
      @export-pdf="exportExecutivePdf(formatCurrency)"
      @export-csv="exportExecutiveCsv"
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
  </VContainer>
</template>

<style scoped>
.text-xs {
  font-size: 0.75rem;
}
</style>
