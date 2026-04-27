<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import VueApexCharts from 'vue3-apexcharts';

// --- ESTADO ---
const loading = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const dashboardData = ref(null);
const isAdvancedFiltersVisible = ref(false);

// --- FORMATEO ---
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

const formatNumber = (value) => {
  return new Intl.NumberFormat('en-US').format(value);
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

const resetFilters = () => {
  startDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10);
  endDate.value = new Date().toISOString().substr(0, 10);
  fetchDashboard();
};

onMounted(() => {
  fetchDashboard();
});

// Reactividad de filtros
watch([startDate, endDate], () => {
  fetchDashboard();
});

// --- CONFIGURACIÓN DE GRÁFICOS ---

// 1. Foco de Venta Diario (Barras)
const dailyChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'Inter, sans-serif' },
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
    labels: { style: { fontSize: '11px', fontWeight: 600, colors: '#a3a3a3' } }
  },
  yaxis: { labels: { style: { colors: '#a3a3a3' } } },
  colors: ['#EA5455', '#7367f0', '#28c76f', '#ff9f43', '#00cfe8', '#00bbd4', '#607d8b'],
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)', show: false },
  legend: { show: false },
  tooltip: { theme: 'dark' }
}));

// 2. Franjas Horarias (Área)
const hourlyChartOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false }, fontFamily: 'Inter, sans-serif' },
  stroke: { curve: 'smooth', width: 3 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1, stops: [0, 90, 100] } },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${val}%`,
    style: { fontSize: '9px', fontWeight: 900 }
  },
  xaxis: {
    labels: { style: { fontSize: '10px', colors: '#a3a3a3' } },
    axisBorder: { show: false }
  },
  yaxis: { show: false },
  colors: ['#7367F0'],
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)', strokeDashArray: 4 },
  tooltip: { theme: 'dark' }
}));

// 3. Segmentación por Unidades (Dona)
const unitsDonutOptions = computed(() => ({
  labels: dashboardData.value?.segmentation?.units?.labels || [],
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Tickets',
            fontSize: '12px',
            fontWeight: 900,
            formatter: () => dashboardData.value?.kpis?.completed_sales || 0
          }
        }
      }
    }
  },
  colors: ['#7367f0', '#ff9f43', '#28c76f', '#ea5455'],
  legend: { position: 'bottom', labels: { colors: '#a3a3a3' }, fontSize: '11px', fontWeight: 600 },
  dataLabels: { enabled: false }
}));

// 4. Segmentación por Valor (Barras Horizontales)
const monetaryChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: {
    bar: { borderRadius: 4, horizontal: true, barHeight: '60%' }
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
    categories: dashboardData.value?.segmentation?.monetary?.labels || [],
    labels: { show: false }
  },
  yaxis: { labels: { show: false } },
  grid: { show: false },
  tooltip: { theme: 'dark' }
}));

</script>

<template>
  <VContainer fluid class="analytics-dashboard pa-0">
    
    <!-- Filtros Estandarizados (Estilo report-expiry) -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-2">
          <VCol cols="12" md="4">
            <div class="d-flex align-center py-1">
              <VAvatar color="primary" variant="tonal" size="32" class="me-3" rounded="lg">
                <VIcon icon="tabler-chart-pie" size="20" />
              </VAvatar>
              <div>
                <h4 class="text-subtitle-1 font-weight-black mb-0 text-uppercase" style="letter-spacing: 0.5px">Analytics TPV</h4>
                <p class="text-[10px] text-disabled mb-0 font-weight-bold uppercase">Métricas de Punto de Venta</p>
              </div>
            </div>
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <!-- Toggle Filtros Avanzados -->
            <VBtn
              icon
              variant="tonal"
              :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
              size="38"
              class="rounded-circle shadow-sm"
              @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
              <VTooltip activator="parent" location="top">Filtros de Fecha</VTooltip>
            </VBtn>

            <!-- Sincronizar -->
            <VBtn
              icon
              variant="flat"
              color="primary"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="loading"
              @click="fetchDashboard"
            >
              <VIcon icon="tabler-refresh" size="20" />
              <VTooltip activator="parent" location="top">Sincronizar Datos</VTooltip>
            </VBtn>

            <VDivider vertical class="mx-1 my-2 border-opacity-10" />

            <!-- Limpiar -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              class="rounded-circle shadow-sm"
              @click="resetFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Restablecer Periodo</VTooltip>
            </VBtn>
          </div>
        </VRow>

        <!-- Filtros Avanzados (Fechas) -->
        <VExpandTransition>
          <div v-show="isAdvancedFiltersVisible">
            <VDivider class="my-3 border-opacity-10" />
            <VRow dense>
              <VCol cols="12" sm="6" md="3">
                <AppTextField v-model="startDate" type="date" label="Fecha Inicio" density="compact" hide-details prepend-inner-icon="tabler-calendar-event" class="premium-input-compact" />
              </VCol>
              <VCol cols="12" sm="6" md="3">
                <AppTextField v-model="endDate" type="date" label="Fecha Fin" density="compact" hide-details prepend-inner-icon="tabler-calendar-event" class="premium-input-compact" />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>
      </VCardText>
    </VCard>

    <div v-if="loading && !dashboardData" class="d-flex justify-center align-center h-[60vh]">
      <VProgressCircular indeterminate color="primary" size="40" />
    </div>

    <div v-else-if="dashboardData" class="px-1">
      <!-- Row 1: KPI Cards (Estilo report-expiry) -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="2" sm="6" v-for="(kpi, idx) in [
          { 
            title: 'Ventas Exitosas', 
            mainValue: formatNumber(dashboardData?.kpis?.completed_sales || 0), 
            subValue: formatCurrency((dashboardData?.kpis?.avg_ticket || 0) * (dashboardData?.kpis?.completed_sales || 0)),
            icon: 'tabler-shopping-cart-check', color: 'primary', desc: 'Volumen total' 
          },
          { 
            title: 'Tickets Abandonados', 
            mainValue: formatNumber(dashboardData?.kpis?.abandoned_sales || 0), 
            subValue: 'Cancelaciones en caja',
            icon: 'tabler-shopping-cart-off', color: 'error', desc: 'Pérdida operativa' 
          },
          { 
            title: 'Cotizaciones', 
            mainValue: formatNumber(dashboardData?.kpis?.quotations_generated || 0), 
            subValue: 'Tasa: ' + (dashboardData?.kpis?.conversion_rate || 0) + '%',
            icon: 'tabler-file-invoice', color: 'warning', desc: 'Conversión' 
          },
          { 
            title: 'Ticket Promedio', 
            mainValue: formatCurrency(dashboardData?.kpis?.avg_ticket || 0), 
            subValue: 'Valor por factura',
            icon: 'tabler-cash', color: 'success', desc: 'Ticket Medio' 
          },
          { 
            title: 'Venta Diaria Est.', 
            mainValue: formatCurrency(dashboardData?.kpis?.avg_daily_sales || 0), 
            subValue: 'Promedio periodo',
            icon: 'tabler-calendar-stats', color: 'info', desc: 'Ingreso Diario' 
          }
        ]" :key="idx">
          <VCard border class="rounded-lg shadow-sm h-100 bg-surface">
            <VCardText class="pa-4 d-flex align-center">
              <VAvatar :color="kpi.color" variant="tonal" size="42" rounded="lg" class="me-3 font-weight-bold">
                <VIcon :icon="kpi.icon" size="20" />
              </VAvatar>
              <div class="overflow-hidden">
                <p class="text-[10px] text-disabled mb-0 font-weight-bold truncate text-uppercase">{{ kpi.title }}</p>
                <h3 class="text-h5 font-weight-black">{{ kpi.mainValue }}</h3>
                <p class="text-xs font-weight-bold text-medium-emphasis mb-0 truncate">
                  {{ kpi.subValue }}
                </p>
                <p class="text-super-xs text-disabled mb-0 uppercase">{{ kpi.desc }}</p>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 2: Distribución Temporal -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="7">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b bg-light-primary">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-chart-bar-stacked" class="me-2 text-primary" size="20" />
                Foco de Venta por Día (Semanal)
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="dailyChartOptions" :series="dashboardData?.charts?.daily_focus?.series || []" />
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" md="5">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-chart-area" class="me-2 text-success" size="20" />
                Distribución Horaria (%)
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="hourlyChartOptions" :series="dashboardData?.charts?.hourly_distribution?.series || []" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 3: Segmentación -->
      <VRow dense>
        <VCol cols="12" md="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-package" class="me-2 text-primary" size="20" />
                Segmentación por Volumen (Unidades)
              </VCardTitle>
            </VCardItem>
            <VRow no-gutters class="pa-4 align-center">
              <VCol cols="12" sm="7">
                <VueApexCharts height="240" :options="unitsDonutOptions" :series="dashboardData?.segmentation?.units?.series || []" type="donut" />
              </VCol>
              <VCol cols="12" sm="5" class="d-flex flex-column justify-center gap-1 ps-4">
                <div v-for="(label, idx) in (dashboardData?.segmentation?.units?.labels || [])" :key="label" class="d-flex justify-space-between align-center py-1 border-b">
                   <span class="text-[10px] font-weight-bold uppercase opacity-60">{{ label }}</span>
                   <VChip density="comfortable" size="x-small" variant="tonal" color="primary" class="font-weight-black">{{ dashboardData?.segmentation?.units?.series?.[idx] || 0 }} Tks</VChip>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </VCol>

        <VCol cols="12" md="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-currency-dollar" class="me-2 text-success" size="20" />
                Tipología por Valor del Ticket
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="220" :options="monetaryChartOptions" :series="[{ data: dashboardData?.segmentation?.monetary?.series || [] }]" />
              
              <!-- Refactorizado Estilo Expiry Insight -->
              <div class="mt-4 p-3 bg-light-success rounded-lg border border-success border-opacity-10 d-flex align-top">
                <VAvatar color="success" variant="tonal" size="32" rounded="lg" class="me-3">
                    <VIcon icon="tabler-bulb" size="18" />
                </VAvatar>
                <div>
                   <div class="text-[11px] font-weight-black text-success uppercase">Recomendación Estratégica</div>
                   <div class="text-[10px] text-success font-weight-bold opacity-80">
                     Fomenta el cross-selling en el mostrador para tickets de baja unidad. Un incremento del 5% en el valor medio elevaría el ticket a {{ formatCurrency((dashboardData?.kpis?.avg_ticket || 0) * 1.05) }}.
                   </div>
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
.analytics-dashboard {
  background-color: transparent;
}

.bg-surface {
  background-color: #fff !important;
}

.kpi-card {
  transition: transform 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
}

.bg-light-primary {
  background-color: #f7fbff;
}

.bg-light-success {
  background-color: #f0fdf4;
}

.text-super-xs {
  font-size: 9px;
  line-height: 1;
}

.font-weight-black {
  font-weight: 900 !important;
}

.uppercase {
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.truncate {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }

:deep(.v-card-title) {
  font-size: 0.8rem !important;
  font-family: 'Inter', sans-serif;
}

.premium-input-compact :deep(.v-field__input) {
  font-size: 0.85rem !important;
}
</style>
