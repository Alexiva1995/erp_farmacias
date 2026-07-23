<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from '@/plugins/axios';
import VueApexCharts from 'vue3-apexcharts';

// --- ESTADO ---
const loading = ref(false);
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10));
const endDate = ref(new Date().toISOString().substr(0, 10));
const dashboardData = ref(null);
const errorMessage = ref('');

// --- FORMATEO ---
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
};

const formatNumber = (value) => {
  return new Intl.NumberFormat('en-US').format(value);
};

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

// Reactividad de filtros
watch([startDate, endDate], () => {
  fetchDashboard();
});

// --- PROPIEDADES COMPUTADAS Y CONFIGURACIÓN DE GRÁFICOS ---

const hasData = computed(() => {
  return dashboardData.value && dashboardData.value.kpis && dashboardData.value.kpis.completed_sales > 0;
});

const kpisList = computed(() => {
  if (!dashboardData.value?.kpis) return [];
  const k = dashboardData.value.kpis;
  return [
    { 
      title: 'Ventas Exitosas', 
      mainValue: formatNumber(k.completed_sales || 0), 
      subValue: formatCurrency((k.avg_ticket || 0) * (k.completed_sales || 0)),
      icon: 'tabler-shopping-cart-check', color: 'primary', desc: 'Volumen total' 
    },
    { 
      title: 'Tks. Abandonados', 
      mainValue: formatNumber(k.abandoned_sales || 0), 
      subValue: 'Bajas en caja',
      icon: 'tabler-shopping-cart-off', color: 'error', desc: 'Pérdida operativa' 
    },
    { 
      title: 'Ventas Cruzadas', 
      mainValue: (k.cross_selling_rate || 0) + '%', 
      subValue: formatNumber(k.cross_selling_count || 0) + ' tickets',
      icon: 'tabler-arrows-cross', color: 'info', desc: 'Penetración' 
    },
    { 
      title: 'Cotizaciones', 
      mainValue: formatNumber(k.quotations_generated || 0), 
      subValue: 'Tasa: ' + (k.conversion_rate || 0) + '%',
      icon: 'tabler-file-invoice', color: 'warning', desc: 'Conversión' 
    },
    { 
      title: 'Ticket Medio', 
      mainValue: formatCurrency(k.avg_ticket || 0), 
      subValue: 'Valor por factura',
      icon: 'tabler-cash', color: 'success', desc: 'Ticket Medio' 
    },
    { 
      title: 'Venta Diaria', 
      mainValue: formatCurrency(k.avg_daily_sales || 0), 
      subValue: 'Ticket estimado',
      icon: 'tabler-calendar-stats', color: 'info', desc: 'Ingreso Diario' 
    }
  ];
});

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
  colors: ['#EA5455', '#E20074', '#7A0099', '#28c76f', '#ff9f43', '#00cfe8', '#607d8b'],
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
  colors: ['#E20074'],
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)', strokeDashArray: 4 },
  tooltip: { 
    theme: 'dark',
    y: {
      formatter: (val, { series, seriesIndex, dataPointIndex, w }) => {
        const revenue = w.config.series[seriesIndex].data[dataPointIndex].revenue;
        return `${val}% (Facturado: $${new Intl.NumberFormat('en-US').format(revenue)})`;
      }
    }
  }
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
  colors: ['#E20074', '#7A0099', '#ff9f43', '#28c76f'],
  legend: { position: 'bottom', labels: { colors: '#a3a3a3' }, fontSize: '11px', fontWeight: 600 },
  dataLabels: { enabled: false }
}));

// 4. Segmentación por Valor (Barras Horizontales)
const monetaryChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: {
    bar: {
      borderRadius: 4,
      horizontal: true,
      barHeight: '70%',
      distributed: true
    }
  },
  colors: ['#E20074', '#7A0099', '#28c76f', '#ff9f43', '#ea5455', '#00cfe8', '#161616', '#a8aaad'],
  dataLabels: {
    enabled: true,
    style: { fontSize: '10px', fontWeight: 900, colors: ['#fff'] },
    formatter: (val) => val
  },
  xaxis: {
    categories: dashboardData.value?.segmentation?.monetary?.labels?.map(l => `$ ${l}`) || [],
    labels: { style: { fontSize: '10px' } }
  },
  yaxis: {
    labels: { style: { fontSize: '11px', fontWeight: 700 } }
  },
  grid: { borderColor: 'rgba(144, 164, 174, 0.05)' },
  legend: { show: false },
  tooltip: { theme: 'dark' }
}));

const trafficHourlyData = computed(() => {
  const data = dashboardData.value?.charts?.hourly_distribution?.series?.[0]?.data || [];
  return [...data].sort((a, b) => b.y - a.y);
});

const revenueHourlyData = computed(() => {
  const data = dashboardData.value?.charts?.hourly_distribution?.series?.[0]?.data || [];
  return [...data].sort((a, b) => b.revenue - a.revenue);
});

const sellersHourlyData = computed(() => {
  const data = dashboardData.value?.charts?.hourly_distribution?.series?.[0]?.data || [];
  return [...data].sort((a, b) => parseInt(a.x) - parseInt(b.x));
});

</script>

<template>
  <VContainer fluid class="analytics-dashboard pa-0">
    
    <!-- Filtros Estandarizados (Estilo report-expiry) -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-2">
          <!-- Filtros de Fecha Directos (Minimalistas) -->
          <VCol cols="12" md="6">
             <VRow dense align="center">
                <VCol cols="4">
                   <AppTextField v-model="startDate" type="date" :disabled="loading" density="compact" hide-details prepend-inner-icon="tabler-calendar" class="premium-input-compact" />
                </VCol>
                <VCol cols="1" class="text-center text-disabled font-weight-bold">al</VCol>
                <VCol cols="4">
                   <AppTextField v-model="endDate" type="date" :disabled="loading" density="compact" hide-details prepend-inner-icon="tabler-calendar" class="premium-input-compact" />
                </VCol>
             </VRow>
          </VCol>

          <VSpacer />

          <div class="d-flex align-center gap-2">
            <!-- Sincronizar -->
            <VBtn
              icon
              variant="flat"
              color="primary"
              size="38"
              class="rounded-circle shadow-sm"
              :loading="loading"
              :disabled="loading"
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
              :disabled="loading"
              @click="resetFilters"
            >
              <VIcon icon="tabler-eraser" size="20" />
              <VTooltip activator="parent" location="top">Restablecer Periodo</VTooltip>
            </VBtn>
          </div>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Error State -->
    <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-6 rounded-lg">
      {{ errorMessage }}
    </VAlert>

    <!-- Loading Skeleton State -->
    <div v-if="loading && !dashboardData" class="px-1">
      <VRow class="mb-6" dense>
        <VCol cols="12" md="2" sm="6" v-for="i in 6" :key="i">
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

    <!-- Empty State -->
    <VCard v-else-if="!loading && !hasData" class="rounded-lg border shadow-sm text-center pa-10 bg-surface mb-6">
      <VAvatar color="warning" variant="tonal" size="64" class="mb-4">
        <VIcon icon="tabler-shopping-cart-off" size="32" />
      </VAvatar>
      <h3 class="text-h6 font-weight-black mb-2">No se encontraron ventas</h3>
      <p class="text-disabled text-subtitle-2 mb-0">No existen registros de ventas completadas para el rango de fechas seleccionado.</p>
    </VCard>

    <div v-else-if="dashboardData" class="px-1">
      <!-- Row 1: KPI Cards -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="2" sm="6" v-for="(kpi, idx) in kpisList" :key="idx">
          <VCard border class="rounded-lg shadow-sm h-100 bg-surface">
            <VCardText class="pa-4 d-flex align-center">
              <VAvatar :color="kpi.color" variant="tonal" size="38" rounded="lg" class="me-3 font-weight-bold">
                <VIcon :icon="kpi.icon" size="18" />
              </VAvatar>
              <div class="overflow-hidden">
                <p class="text-[12px] text-disabled mb-0 font-weight-bold truncate">{{ kpi.title }}</p>
                <h3 class="text-h6 font-weight-black leading-tight">{{ kpi.mainValue }}</h3>
                <p class="text-[10px] text-medium-emphasis mb-0 truncate opacity-70">
                  {{ kpi.subValue }}
                </p>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 2: Distribución Temporal (50/50) -->
      <VRow class="mb-6" dense>
        <VCol cols="12" md="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b bg-light-primary">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-coin" class="me-2 text-primary" size="20" />
                Ventas Totales por Día (Semanal)
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="dailyChartOptions" :series="dashboardData.charts?.daily_focus?.series || []" />
            </VCardText>
          </VCard>
        </VCol>
        
        <VCol cols="12" md="6">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-chart-area" class="me-2 text-success" size="20" />
                Distribución Horaria (% y USD)
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="300" :options="hourlyChartOptions" :series="dashboardData.charts?.hourly_distribution?.series || []" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 3: Segmentación (60/40) -->
      <VRow dense class="mb-6">
        <VCol cols="12" md="7">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-package" class="me-2 text-primary" size="20" />
                Segmentación por Volumen (Unidades)
              </VCardTitle>
            </VCardItem>
            <VRow no-gutters class="pa-4 align-center">
              <VCol cols="12" sm="7">
                <VueApexCharts height="260" :options="unitsDonutOptions" :series="dashboardData.segmentation?.units?.series || []" type="donut" />
              </VCol>
              <VCol cols="12" sm="5" class="ps-4">
                <div class="mb-4">
                   <div class="d-flex align-center mb-1">
                      <VIcon icon="tabler-arrows-cross" size="14" class="me-1 text-info" />
                      <span class="text-[11px] font-weight-black uppercase">Penetración V. Cruzada</span>
                   </div>
                   <h4 class="text-h6 font-weight-black text-info">{{ dashboardData.kpis?.cross_selling_rate || 0 }}%</h4>
                   <VProgressLinear :model-value="dashboardData.kpis?.cross_selling_rate || 0" color="info" height="6" rounded class="mt-1" />
                </div>

                <div v-for="(label, idx) in (dashboardData.segmentation?.units?.labels || [])" :key="label" class="d-flex justify-space-between align-center py-1 border-b">
                   <span class="text-[10px] font-weight-bold uppercase opacity-60">{{ label }}</span>
                   <VChip density="comfortable" size="x-small" variant="tonal" color="primary" class="font-weight-black">{{ dashboardData.segmentation?.units?.series?.[idx] || 0 }} Tks</VChip>
                </div>
              </VCol>
            </VRow>
          </VCard>
        </VCol>

        <VCol cols="12" md="5">
          <VCard class="rounded-lg border shadow-sm h-100">
            <VCardItem class="py-3 border-b">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-currency-dollar" class="me-2 text-success" size="20" />
                Tipología por Valor del Ticket
              </VCardTitle>
            </VCardItem>
            <VCardText class="pa-4">
              <VueApexCharts height="220" :options="monetaryChartOptions" :series="[{ data: dashboardData.segmentation?.monetary?.series || [] }]" />
              
              <!-- Refactorizado Estilo Expiry Insight con Venta Cruzada -->
              <div class="mt-4 p-3 bg-light-info rounded-lg border border-info border-opacity-10 d-flex align-top">
                <VAvatar color="info" variant="tonal" size="32" rounded="lg" class="me-3">
                    <VIcon icon="tabler-trending-up" size="18" />
                </VAvatar>
                <div>
                   <div class="text-[11px] font-weight-black text-info uppercase">Oportunidad de Venta Cruzada</div>
                   <div class="text-[10px] text-info font-weight-bold opacity-80">
                     Un incremento al 40% en esta métrica generaría un ingreso adicional estimado de {{ formatCurrency((dashboardData.kpis?.total_revenue || 0) * 0.15) }}.
                   </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Row 4: Clasificación por Horas (3 Tables) -->
      <VRow dense>
        <!-- Tabla 1: Tráfico -->
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm overflow-hidden h-100">
            <VCardItem class="py-3 border-b bg-light-primary">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-clock-up" class="me-2 text-primary" size="20" />
                Top Tráfico (Frecuencia)
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-no-wrap analytics-table">
              <thead>
                <tr>
                  <th class="text-uppercase text-[10px] font-weight-black">Hora</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-center">Tks</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-center">% Part.</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="slot in trafficHourlyData" :key="slot.x">
                  <td class="font-weight-black text-primary">{{ slot.x }}</td>
                  <td class="text-center font-weight-bold">{{ Math.round((slot.y * (dashboardData.kpis?.completed_sales || 0)) / 100) }}</td>
                  <td class="text-center">
                    <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-black">{{ slot.y }}%</VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>

        <!-- Tabla 2: Facturación -->
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm overflow-hidden h-100">
            <VCardItem class="py-3 border-b bg-light-success">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-cash-banknote" class="me-2 text-success" size="20" />
                Mayor Facturación (USD)
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-no-wrap analytics-table">
              <thead>
                <tr>
                  <th class="text-uppercase text-[10px] font-weight-black">Hora</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-right">Monto</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-center">% Part.</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="slot in revenueHourlyData" :key="slot.x">
                  <td class="font-weight-black text-success">{{ slot.x }}</td>
                  <td class="text-right font-weight-black text-success">{{ formatCurrency(slot.revenue) }}</td>
                  <td class="text-center">
                    <VChip size="x-small" label color="success" variant="tonal" class="font-weight-black">
                      {{ ((slot.revenue / (dashboardData.kpis?.total_revenue || 1)) * 100).toFixed(1) }}%
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>

        <!-- Tabla 3: Vendedores por Hora -->
        <VCol cols="12" md="4">
          <VCard class="rounded-lg border shadow-sm overflow-hidden h-100">
            <VCardItem class="py-3 border-b bg-light-info">
              <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
                <VIcon icon="tabler-users" class="me-2 text-info" size="20" />
                Vendedor Estrella por Hora
              </VCardTitle>
            </VCardItem>
            <VTable density="compact" class="text-no-wrap analytics-table">
              <thead>
                <tr>
                  <th class="text-uppercase text-[10px] font-weight-black">Hora</th>
                  <th class="text-uppercase text-[10px] font-weight-black">Vendedor</th>
                  <th class="text-uppercase text-[10px] font-weight-black text-right">Venta USD</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="slot in sellersHourlyData" :key="slot.x">
                  <td class="font-weight-black text-info">{{ slot.x }}</td>
                  <td>
                    <div class="d-flex align-center" v-if="slot.top_seller">
                      <span class="text-[11px] font-weight-bold truncate">{{ slot.top_seller.seller_name }}</span>
                    </div>
                    <span v-else class="text-disabled text-[10px]">Sin ventas</span>
                  </td>
                  <td class="text-right font-weight-black text-info" v-if="slot.top_seller">
                    {{ formatCurrency(slot.top_seller.revenue) }}
                  </td>
                  <td v-else class="text-right text-disabled">-</td>
                </tr>
              </tbody>
            </VTable>
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
  background-color: #fff0f6;
}

.bg-light-success {
  background-color: #f0fdf4;
}

.bg-light-info {
  background-color: #f0f9ff;
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

.analytics-table :deep(th) {
  background-color: #f8fafc !important;
  color: #64748b !important;
  border-bottom: 2px solid #e2e8f0 !important;
}

.analytics-table :deep(td) {
  font-size: 0.75rem !important;
  border-bottom: 1px solid #f1f5f9 !important;
}
</style>

