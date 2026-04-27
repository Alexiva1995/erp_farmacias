<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

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

watch([startDate, endDate], () => {
  fetchDashboard();
});

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
    categories: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'], // Traducido
    axisBorder: { show: false },
    axisTicks: { show: false }
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
  stroke: { curve: 'smooth', width: 3 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.5,
      opacityTo: 0.1,
      stops: [0, 90, 100]
    }
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${val}%`,
    style: { fontSize: '9px' }
  },
  xaxis: {
    labels: { style: { fontSize: '10px' } },
    axisBorder: { show: false }
  },
  yaxis: { show: false },
  colors: ['#7367F0'],
  grid: { borderColor: '#f1f1f1', strokeDashArray: 4 },
  tooltip: {
    y: {
      formatter: (val) => `${val}% de las ventas diarias`
    }
  }
}));

// 3. Segmentación por Unidades (Dona)
const unitsDonutOptions = computed(() => ({
  labels: dashboardData.value?.segmentation.units.labels || [],
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Tickets',
            formatter: () => dashboardData.value?.kpis.completed_sales
          }
        }
      }
    }
  },
  colors: ['#054D95', '#FF9F43', '#28C76F', '#EA5455'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: false },
  tooltip: {
    y: { formatter: (val) => `${val} tickets` }
  }
}));

// 4. Segmentación por Valor (Barras Horizontales)
const monetaryChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: {
    bar: {
      borderRadius: 4,
      horizontal: true,
      barHeight: '60%'
    }
  },
  colors: ['#28C76F'],
  dataLabels: {
    enabled: true,
    textAnchor: 'start',
    formatter: (val, opt) => `${opt.w.globals.labels[opt.dataPointIndex]}: ${val} vent.`,
    offsetX: 10,
    style: { colors: ['#fff'] }
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
  <VContainer fluid class="page-bi-pos pb-10">
    <!-- Header simple align con sistema -->
    <div class="d-flex align-center mb-6">
      <VIcon icon="tabler-device-desktop-analytics" class="me-3 text-primary" size="32" />
      <h1 class="text-h5 font-weight-black">Analíticas <span class="text-primary">TPV</span></h1>
    </div>

    <!-- Filtros Estandarizados (Max 8px radius) -->
    <VCard border class="rounded-lg mb-6 shadow-sm">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters>
          <VCol cols="12" md="4" class="d-flex align-center">
             <div class="me-4 text-caption font-weight-bold grey-text text-uppercase">Periodo:</div>
             <div class="d-flex gap-2 flex-grow-1">
                <AppTextField v-model="startDate" type="date" density="compact" hide-details />
                <AppTextField v-model="endDate" type="date" density="compact" hide-details />
             </div>
          </VCol>
          <VSpacer />
          <VCol cols="12" md="2" class="text-right">
            <VBtn variant="tonal" color="primary" @click="fetchDashboard" :loading="loading" block>
              <VIcon icon="tabler-refresh" class="me-2" />
              Sincronizar
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div v-if="loading && !dashboardData" class="d-flex justify-center align-center h-[60vh]">
      <VProgressCircular indeterminate color="primary" size="64" />
    </div>

    <div v-else-if="dashboardData">
      <!-- KPIs GRID (Max 8px radius) -->
      <VRow>
        <!-- Ventas Completadas -->
        <VCol cols="12" sm="6" md="2">
          <VCard border class="kpi-card rounded-lg pt-2 h-100">
            <VCardText class="pa-4">
              <VAvatar color="primary" variant="tonal" rounded="lg" size="38" class="mb-3"><VIcon icon="tabler-shopping-cart-check" size="20" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ dashboardData.kpis.completed_sales }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Ventas Exitosas</div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ventas Abandonadas -->
        <VCol cols="12" sm="6" md="2">
          <VCard border class="kpi-card rounded-lg pt-2 h-100">
            <VCardText class="pa-4">
              <VAvatar color="error" variant="tonal" rounded="lg" size="38" class="mb-3"><VIcon icon="tabler-shopping-cart-off" size="20" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ dashboardData.kpis.abandoned_sales }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Abandonos / Canc.</div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Cotizaciones -->
        <VCol cols="12" sm="6" md="2">
          <VCard border class="kpi-card rounded-lg pt-2 h-100">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <VAvatar color="warning" variant="tonal" rounded="lg" size="38"><VIcon icon="tabler-file-invoice" size="20" /></VAvatar>
                <VChip size="x-small" color="success" class="font-weight-black">{{ dashboardData.kpis.conversion_rate }}%</VChip>
              </div>
              <div class="text-h5 font-weight-black mb-0">{{ dashboardData.kpis.quotations_generated }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Cotizaciones</div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ticket Promedio -->
        <VCol cols="12" sm="6" md="3">
          <VCard border class="kpi-card pos-gradient text-white rounded-lg pt-2 h-100">
            <VCardText class="pa-4">
              <VAvatar color="white" variant="tonal" rounded="lg" size="38" class="mb-3"><VIcon icon="tabler-cash" color="white" size="20" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ formatCurrency(dashboardData.kpis.avg_ticket) }}</div>
              <div class="text-[10px] font-weight-bold text-uppercase opacity-70">Ticket Promedio</div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Promedio Diario -->
        <VCol cols="12" sm="6" md="3">
          <VCard border class="kpi-card rounded-lg pt-2 border-primary border-opacity-25 h-100">
            <VCardText class="pa-4">
              <VAvatar color="success" variant="tonal" rounded="lg" size="38" class="mb-3"><VIcon icon="tabler-calendar-stats" size="20" /></VAvatar>
              <div class="text-h5 font-weight-black mb-0">{{ formatCurrency(dashboardData.kpis.avg_daily_sales) }}</div>
              <div class="text-[10px] font-weight-bold grey-text text-uppercase">Venta Diaria Promedio</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- ANALISIS TEMPORAL -->
      <VRow class="mt-4">
        <!-- Foco de Venta Diario -->
        <VCol cols="12" lg="7">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b bg-light-primary d-flex align-center">
              <VIcon icon="tabler-chart-bar" class="me-2 text-primary" />
              <span class="text-subtitle-2 font-weight-black">FOCO DE VENTA POR DÍA</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="320" :options="dailyChartOptions" :series="dashboardData.charts.daily_focus.series" />
            </VCardText>
          </VCard>
        </VCol>

        <!-- Franjas Horarias -->
        <VCol cols="12" lg="5">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b d-flex align-center">
              <VIcon icon="tabler-clock-2" class="me-2 text-primary" />
              <span class="text-subtitle-2 font-weight-black">DISTRIBUCIÓN HORARIA (%)</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="320" :options="hourlyChartOptions" :series="dashboardData.charts.hourly_distribution.series" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- SEGMENTACIÓN DEL CARRITO -->
      <VRow class="mt-4">
        <!-- Unidades -->
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b d-flex align-center">
              <VIcon icon="tabler-package" class="me-2 text-primary" />
              <span class="text-subtitle-2 font-weight-black">SEGMENTACIÓN POR VOLUMEN (UNIDADES)</span>
            </VCardTitle>
            <VRow no-gutters class="pa-4">
              <VCol cols="12" sm="7">
                <VueApexCharts height="260" :options="unitsDonutOptions" :series="dashboardData.segmentation.units.series" type="donut" />
              </VCol>
              <VCol cols="12" sm="5" class="d-flex flex-column justify-center gap-2 ps-4">
                <div v-for="(label, idx) in dashboardData.segmentation.units.labels" :key="label" class="d-flex justify-space-between align-center p-2 border-b">
                   <span class="text-xs font-weight-bold">{{ label }}</span>
                   <VChip density="comfortable" size="x-small" variant="tonal" class="font-weight-black">{{ dashboardData.segmentation.units.series[idx] }} Tks</VChip>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </VCol>

        <!-- Valor Monetario -->
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b d-flex align-center">
              <VIcon icon="tabler-bulb" class="me-2 text-success" />
              <span class="text-subtitle-2 font-weight-black">TIPOLOGÍA POR VALOR DEL TICKET</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="250" :options="monetaryChartOptions" :series="[{ data: dashboardData.segmentation.monetary.series }]" />
              
              <div class="mt-4 p-4 bg-light-success rounded border border-success border-opacity-10 d-flex align-top">
                <VIcon icon="tabler-bulb" class="me-3 text-success mt-1" size="24" />
                <div>
                   <div class="text-xs font-weight-black text-success uppercase">Insight Estratégico</div>
                   <div class="text-[10px] text-success font-weight-bold">Estimular las "Ventas Mayores" mediante cross-selling para superar el ticket promedio de {{ formatCurrency(dashboardData.kpis.avg_ticket) }}.</div>
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
  background-color: #f8fafc;
}

.kpi-card {
  background: #fff;
  transition: transform 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
}

.pos-gradient {
  background: linear-gradient(135deg, #054D95 0%, #007bff 100%) !important;
}

.bg-light-primary {
  background-color: rgb(241, 248, 255);
}

.bg-light-success {
  background-color: rgb(240, 253, 244);
}

.grey-text {
  color: #64748b;
  letter-spacing: 0.5px;
}

.font-weight-black {
  font-weight: 900 !important;
}

.rounded-lg {
  border-radius: 8px !important;
}

.gap-2 {
  gap: 8px;
}
</style>
