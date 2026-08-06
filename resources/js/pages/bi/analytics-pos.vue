<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import PosAnalyticsFilters from './components/PosAnalyticsFilters.vue';
import PosAnalyticsKpis from './components/PosAnalyticsKpis.vue';
import PosAnalyticsTemporalCharts from './components/PosAnalyticsTemporalCharts.vue';
import PosAnalyticsSegmentation from './components/PosAnalyticsSegmentation.vue';
import PosAnalyticsHourlyTables from './components/PosAnalyticsHourlyTables.vue';

// --- ESTADO ---
const loading = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const dashboardData = ref(null);
const errorMessage = ref('');

// --- CARGA DE DATOS ---
const fetchDashboard = async () => {
  if (loading.value) return;
  loading.value = true;
  errorMessage.value = '';
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value
    };
    const { data } = await axios.get('/bi/pos/dashboard', { params });
    dashboardData.value = data;
  } catch (error) {
    console.error("Error al cargar dashboard de TPV:", error);
    errorMessage.value = 'Error al cargar los datos del dashboard. Por favor intente de nuevo.';
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10);
  endDate.value = new Date().toISOString().substr(0, 10);
  fetchDashboard();
};

onMounted(() => {
  fetchDashboard();
});

watch([startDate, endDate], () => {
  fetchDashboard();
});

const hasData = computed(() => {
  return dashboardData.value && dashboardData.value.kpis && dashboardData.value.kpis.completed_sales > 0;
});
</script>

<template>
  <VContainer fluid class="analytics-dashboard pa-0">
    <!-- Filtros Modularizados -->
    <PosAnalyticsFilters
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      @fetch="fetchDashboard"
      @reset="resetFilters"
    />

    <!-- Estado de Error -->
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-6 rounded-lg">
      {{ errorMessage }}
    </VAlert>

    <!-- Estado de Carga (Skeleton) -->
    <div v-if="loading && !dashboardData" class="px-1">
      <VRow class="mb-6" dense>
        <VCol cols="12" sm="6" md="4" lg="2" v-for="i in 6" :key="i">
          <VSkeletonLoader type="card" height="90" class="border rounded-lg" />
        </VCol>
      </VRow>
      <VRow class="mb-6" dense>
        <VCol cols="12" md="6" v-for="i in 2" :key="i">
          <VSkeletonLoader type="card" height="350" class="border rounded-lg" />
        </VCol>
      </VRow>
      <VRow dense>
        <VCol cols="12" md="4" v-for="i in 3" :key="i">
          <VSkeletonLoader type="table" height="250" class="border rounded-lg" />
        </VCol>
      </VRow>
    </div>

    <!-- Estado Vacío -->
    <VCard v-else-if="!loading && !hasData" class="rounded-lg border shadow-sm text-center pa-10 bg-surface mb-6">
      <VAvatar color="warning" variant="tonal" size="64" class="mb-4">
        <VIcon icon="tabler-shopping-cart-off" size="32" />
      </VAvatar>
      <h3 class="text-h6 font-weight-black mb-2">No se encontraron ventas</h3>
      <p class="text-disabled text-subtitle-2 mb-0">No existen registros de ventas completadas para el rango de fechas seleccionado.</p>
    </VCard>

    <!-- Contenido del Dashboard -->
    <div v-else-if="dashboardData" class="px-1">
      <!-- Tarjetas de KPI -->
      <PosAnalyticsKpis :kpis="dashboardData.kpis || {}" />

      <!-- Gráficos Temporales -->
      <PosAnalyticsTemporalCharts :charts="dashboardData.charts || {}" />

      <!-- Segmentación por Volumen y Valor -->
      <PosAnalyticsSegmentation :segmentation="dashboardData.segmentation || {}" :kpis="dashboardData.kpis || {}" />

      <!-- Tablas de Clasificación Horaria -->
      <PosAnalyticsHourlyTables
        :hourly-distribution="dashboardData.charts?.hourly_distribution || {}"
        :completed-sales="dashboardData.kpis?.completed_sales || 0"
        :total-revenue="dashboardData.kpis?.total_revenue || 0"
      />
    </div>
  </VContainer>
</template>

<style scoped>
.analytics-dashboard {
  background-color: transparent;
}
.bg-surface {
  background-color: #fff !important;
}
.font-weight-black {
  font-weight: 900 !important;
}
</style>
