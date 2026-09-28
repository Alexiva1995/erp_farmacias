<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import PosAnalyticsFilters from './components/PosAnalyticsFilters.vue';
import PosAnalyticsKpis from './components/PosAnalyticsKpis.vue';
import PosAnalyticsTemporalCharts from './components/PosAnalyticsTemporalCharts.vue';
import PosAnalyticsSegmentation from './components/PosAnalyticsSegmentation.vue';
import PosAnalyticsHourlyTables from './components/PosAnalyticsHourlyTables.vue';

// --- ESTADO REACTIVO ---
const loading = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10));
const endDate = ref(new Date().toISOString().substring(0, 10));
const dashboardData = ref(null);
const errorMessage = ref('');

let abortController = null;

// --- CARGA DE DATOS ASÍNCRONA ---
const fetchDashboard = async () => {
  if (abortController) {
    abortController.abort();
  }
  abortController = new AbortController();

  loading.value = true;
  errorMessage.value = '';
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
    };
    const { data } = await axios.get('/bi/pos/dashboard', {
      params,
      signal: abortController.signal,
    });
    
    // Soporte para respuesta estandarizada de API Resource
    dashboardData.value = data.data || data;
  } catch (error) {
    if (error.name !== 'CanceledError') {
      console.error('Error al cargar dashboard de TPV:', error);
      errorMessage.value = error.response?.data?.message || 'Error al cargar los datos del dashboard. Por favor intente de nuevo.';
    }
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10);
  endDate.value = new Date().toISOString().substring(0, 10);
};

onMounted(() => {
  fetchDashboard();
});

watch([startDate, endDate], () => {
  fetchDashboard();
});

const hasData = computed(() => {
  return Boolean(dashboardData.value?.kpis && dashboardData.value.kpis.completed_sales > 0);
});
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Filtros de Consulta -->
    <PosAnalyticsFilters
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :loading="loading"
      @fetch="fetchDashboard"
      @reset="resetFilters"
    />

    <!-- Estado de Error -->
    <VAlert
      v-if="errorMessage"
      type="error"
      variant="tonal"
      closable
      class="mb-6 rounded-lg"
      @click:close="errorMessage = ''"
    >
      {{ errorMessage }}
    </VAlert>

    <!-- Estado de Carga (Skeleton) -->
    <div v-if="loading && !dashboardData" class="px-1">
      <VRow class="mb-6" dense>
        <VCol cols="12" sm="6" md="4" lg="2" v-for="i in 6" :key="i">
          <VSkeletonLoader type="card" height="90" class="rounded-lg" />
        </VCol>
      </VRow>
      <VRow class="mb-6" dense>
        <VCol cols="12" md="6" v-for="i in 2" :key="i">
          <VSkeletonLoader type="card" height="350" class="rounded-lg" />
        </VCol>
      </VRow>
      <VRow dense>
        <VCol cols="12" md="4" v-for="i in 3" :key="i">
          <VSkeletonLoader type="table" height="250" class="rounded-lg" />
        </VCol>
      </VRow>
    </div>

    <!-- Estado Vacío -->
    <VCard
      v-else-if="!loading && !hasData"
      variant="outlined"
      class="rounded-lg text-center pa-10 mb-6"
    >
      <VAvatar color="warning" variant="tonal" size="64" class="mb-4">
        <VIcon icon="tabler-shopping-cart-off" size="32" />
      </VAvatar>
      <h3 class="text-h6 font-weight-bold mb-2">No se encontraron ventas</h3>
      <p class="text-medium-emphasis text-body-2 mb-0">
        No existen registros de ventas completadas para el rango de fechas seleccionado.
      </p>
    </VCard>

    <!-- Contenido del Dashboard -->
    <div v-else-if="dashboardData" class="px-1">
      <!-- Tarjetas de KPI -->
      <PosAnalyticsKpis :kpis="dashboardData.kpis || {}" />

      <!-- Gráficos Temporales -->
      <PosAnalyticsTemporalCharts :charts="dashboardData.charts || {}" />

      <!-- Segmentación por Volumen y Valor -->
      <PosAnalyticsSegmentation
        :segmentation="dashboardData.segmentation || {}"
        :kpis="dashboardData.kpis || {}"
      />

      <!-- Tablas de Clasificación Horaria -->
      <PosAnalyticsHourlyTables
        :hourly-distribution="dashboardData.charts?.hourly_distribution || {}"
        :completed-sales="dashboardData.kpis?.completed_sales || 0"
        :total-revenue="dashboardData.kpis?.total_revenue || 0"
      />
    </div>
  </VContainer>
</template>
