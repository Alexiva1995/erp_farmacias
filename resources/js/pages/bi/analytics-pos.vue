<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import AppFilterBase from "@/components/AppFilterBase.vue";

// --- ESTADO ---
const loading = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const dashboardData = ref(null);

// --- FORMATEO ---
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

// --- CARGA DE DATOS ---
const fetchDashboard = async () => {
  loading.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value
    };
    const { data } = await axios.get('/api/bi/pos/dashboard', { params });
    dashboardData.value = data;
  } catch (error) {
    console.error("Error al cargar dashboard de TPV:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchDashboard();
});

// Reactividad de filtros
watch([startDate, endDate], () => {
  fetchDashboard();
});

const handleClearFilters = () => {
  startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10);
  endDate.value = new Date().toISOString().substr(0, 10);
  fetchDashboard();
};

// --- CONFIGURACIÓN DE GRÁFICOS (TODOS EN ESPAÑOL) ---

// 1. Foco de Venta Diario (Barras)
const dailyChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, zoom: { enabled: false } },
  plotOptions: {
    bar: {
      borderRadius: 4,
      columnWidth: '45%',
      distributed: true,
      dataLabels: { position: 'top' }
    }
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `$${val}`,
    offsetY: -20,
    style: { fontSize: '10px', colors: ['#304758'] }
  },
  xaxis: {
    categories: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: { style: { fontSize: '11px', fontWeight: 600 } }
  },
  yaxis: { show: false },
  colors: ['#EA5455', '#054D95', '#28C76F', '#FF9F43', '#7367F0', '#00CFE8', '#4B4B4B'],
  grid: { show: false },
  legend: { show: false },
  tooltip: {
    y: { title: { formatter: () => 'Venta Promedio:' } }
  }
}));

// 2. Franjas Horarias (Área)
const hourlyChartOptions = computed(() => ({
  chart: { 
    type: 'area', 
    toolbar: { show: false },
    sparkline: { enabled: false }
  },
  stroke: { curve: 'smooth', width: 2.5 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [0, 90, 100] }
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${val}%`,
    style: { fontSize: '9px', fontWeight: 900 }
  },
  xaxis: {
    labels: { style: { fontSize: '10px' } },
    axisBorder: { show: false }
  },
  yaxis: { show: false },
  colors: ['#7367F0'],
  grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
  tooltip: {
    y: { formatter: (val) => `${val}% de las ventas diarias` }
  }
}));

// 3. Segmentación por Unidades (Dona)
const unitsDonutOptions = computed(() => ({
  labels: dashboardData.value?.segmentation.units.labels || [],
  plotOptions: {
    pie: {
      donut: {
        size: '72%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Tickets',
            fontSize: '12px',
            fontWeight: 900,
            formatter: () => dashboardData.value?.kpis.completed_sales
          }
        }
      }
    }
  },
  colors: ['#054D95', '#FF9F43', '#28C76F', '#EA5455'],
  legend: { position: 'bottom', fontSize: '11px', fontWeight: 600 },
  dataLabels: { enabled: false },
  tooltip: {
    y: { formatter: (val) => `${val} tickets` }
  }
}));

// 4. Segmentación por Valor (Barras Horizontales)
const monetaryChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: {
    bar: { borderRadius: 4, horizontal: true, barHeight: '55%' }
  },
  colors: ['#28C76F'],
  dataLabels: {
    enabled: true,
    textAnchor: 'start',
    formatter: (val, opt) => `${opt.w.globals.labels[opt.dataPointIndex]}: ${val}`,
    offsetX: 10,
    style: { fontSize: '10px', fontWeight: 900, colors: ['#fff'] }
  },
  xaxis: {
    categories: dashboardData.value?.segmentation.monetary.labels || [],
    labels: { show: false }
  },
  yaxis: { labels: { show: false } },
  grid: { show: false },
  tooltip: {
    y: { title: { formatter: () => 'Tickets:' } }
  }
}));

</script>

<template>
  <VContainer fluid class="page-bi-pos pa-0">
    <!-- FILTROS ESTILO INVENTORY/PRODUCTS -->
    <AppFilterBase
      :show-search="false"
      :show-advanced="true"
      :loading="loading"
      :has-advanced-filters="true"
      class="mb-6 rounded-lg shadow-sm"
      @clear="handleClearFilters"
    >
      <!-- Título de sistema discreto -->
      <template #search>
        <div class="d-flex align-center py-2 px-1">
          <VIcon icon="tabler-chart-pie" class="me-2 text-primary" size="22" />
          <span class="text-subtitle-1 font-weight-bold text-uppercase" style="letter-spacing: 0.5px;">Analytics TPV</span>
        </div>
      </template>

      <template #actions-extra>
        <VBtn
          color="primary"
          variant="tonal"
          size="38"
          class="rounded-pill"
          :loading="loading"
          @click="fetchDashboard"
        >
          <VIcon icon="tabler-refresh" />
          <VTooltip activator="parent" location="top">Sincronizar Datos</VTooltip>
        </VBtn>
      </template>

      <template #advanced-filters>
        <VCol cols="12" sm="6" md="3">
          <AppTextField v-model="startDate" type="date" label="Fecha Inicio" density="compact" hide-details prepend-inner-icon="tabler-calendar" />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <AppTextField v-model="endDate" type="date" label="Fecha Fin" density="compact" hide-details prepend-inner-icon="tabler-calendar" />
        </VCol>
      </template>
    </AppFilterBase>

    <div v-if="loading && !dashboardData" class="d-flex justify-center align-center h-[60vh]">
      <VProgressCircular indeterminate color="primary" size="40" />
    </div>

    <div v-else-if="dashboardData" class="px-1">
      <!-- KPIs GRID (Bordes de 8px) -->
      <VRow dense>
        <VCol cols="12" sm="6" md="2">
          <VCard border class="kpi-card border-opacity-10 h-100">
            <VCardText class="pa-4">
              <VAvatar color="primary" variant="tonal" rounded="lg" size="34" class="mb-3"><VIcon icon="tabler-shopping-cart-check" size="18" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ dashboardData.kpis.completed_sales }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Ventas Exitosas</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard border class="kpi-card border-opacity-10 h-100">
            <VCardText class="pa-4">
              <VAvatar color="error" variant="tonal" rounded="lg" size="34" class="mb-3"><VIcon icon="tabler-shopping-cart-off" size="18" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ dashboardData.kpis.abandoned_sales }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Abandonos / Canc.</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VCard border class="kpi-card border-opacity-10 h-100">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <VAvatar color="warning" variant="tonal" rounded="lg" size="34"><VIcon icon="tabler-file-invoice" size="18" /></VAvatar>
                <div class="text-[10px] font-weight-black text-success">{{ dashboardData.kpis.conversion_rate }}%</div>
              </div>
              <div class="text-h5 font-weight-black mb-0">{{ dashboardData.kpis.quotations_generated }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Cotizaciones</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard border class="kpi-card pos-gradient text-white h-100 shadow-none">
            <VCardText class="pa-4">
              <VAvatar color="white" variant="tonal" rounded="lg" size="34" class="mb-3"><VIcon icon="tabler-cash" color="white" size="18" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ formatCurrency(dashboardData.kpis.avg_ticket) }}</div>
              <div class="text-[10px] font-weight-bold text-uppercase opacity-70">Ticket Promedio</div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <VCard border class="kpi-card border-primary border-opacity-25 h-100">
            <VCardText class="pa-4">
              <VAvatar color="success" variant="tonal" rounded="lg" size="34" class="mb-3"><VIcon icon="tabler-calendar-stats" size="18" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ formatCurrency(dashboardData.kpis.avg_daily_sales) }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Venta Diaria Promedio</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- ANALISIS TEMPORAL -->
      <VRow class="mt-4" dense>
        <VCol cols="12" lg="7">
          <VCard border class="h-100 rounded-lg shadow-none">
            <VCardTitle class="pa-4 border-b bg-light-primary d-flex align-center py-3">
              <VIcon icon="tabler-chart-bar" class="me-2 text-primary" size="18" />
              <span class="text-subtitle-2 font-weight-black uppercase">Foco de Venta por Día</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="280" :options="dailyChartOptions" :series="dashboardData.charts.daily_focus.series" />
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" lg="5">
          <VCard border class="h-100 rounded-lg shadow-none">
            <VCardTitle class="pa-4 border-b d-flex align-center py-3">
              <VIcon icon="tabler-clock-2" class="me-2 text-primary" size="18" />
              <span class="text-subtitle-2 font-weight-black uppercase">Distribución Horaria (%)</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="280" :options="hourlyChartOptions" :series="dashboardData.charts.hourly_distribution.series" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- SEGMENTACIÓN -->
      <VRow class="mt-4" dense>
        <VCol cols="12" md="6">
          <VCard border class="h-100 rounded-lg shadow-none">
            <VCardTitle class="pa-4 border-b d-flex align-center py-3">
              <VIcon icon="tabler-package" class="me-2 text-primary" size="18" />
              <span class="text-subtitle-2 font-weight-black uppercase">Segmentación por Volumen</span>
            </VCardTitle>
            <VRow no-gutters class="pa-4 align-center">
              <VCol cols="12" sm="7">
                <VueApexCharts height="220" :options="unitsDonutOptions" :series="dashboardData.segmentation.units.series" type="donut" />
              </VCol>
              <VCol cols="12" sm="5" class="d-flex flex-column justify-center gap-1 ps-4">
                <div v-for="(label, idx) in dashboardData.segmentation.units.labels" :key="label" class="d-flex justify-space-between align-center py-1 border-b">
                   <span class="text-[10px] font-weight-bold uppercase opacity-60">{{ label }}</span>
                   <VChip density="comfortable" size="x-small" variant="tonal" color="primary" class="font-weight-black">{{ dashboardData.segmentation.units.series[idx] }} Tks</VChip>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </VCol>

        <VCol cols="12" md="6">
          <VCard border class="h-100 rounded-lg shadow-none">
            <VCardTitle class="pa-4 border-b d-flex align-center py-3">
              <VIcon icon="tabler-currency-dollar" class="me-2 text-success" size="18" />
              <span class="text-subtitle-2 font-weight-black uppercase">Tipología por Valor</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="220" :options="monetaryChartOptions" :series="[{ data: dashboardData.segmentation.monetary.series }]" />
              
              <div class="mt-4 p-3 bg-light-success rounded border border-success border-opacity-10 d-flex align-top">
                <VIcon icon="tabler-bulb" class="me-3 text-success mt-1" size="20" />
                <div>
                   <div class="text-[10px] font-weight-black text-success uppercase">Insight Estratégico</div>
                   <div class="text-[10px] text-success font-weight-bold opacity-80">Promover combos bundle para elevar el ticket de {{ formatCurrency(dashboardData.kpis.avg_ticket) }}.</div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style scoped>
.page-bi-pos {
  background-color: transparent;
}

.kpi-card {
  background: #fff;
  border-radius: 8px !important;
  box-shadow: none !important;
}

.pos-gradient {
  background: linear-gradient(135deg, #054D95 0%, #176BBE 100%) !important;
}

.bg-light-primary {
  background-color: #f7fbff;
}

.bg-light-success {
  background-color: #f0fdf4;
}

.grey-text {
  color: #64748b;
}

.font-weight-black {
  font-weight: 900 !important;
}

.uppercase {
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

:deep(.v-card) {
  border-radius: 8px !important;
}

:deep(.v-card-title) {
  font-size: 0.8rem !important;
}

.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
</style>
