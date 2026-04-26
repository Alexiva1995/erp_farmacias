<script setup>
import AppFilterBase from '@/components/AppFilterBase.vue';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';
import { computed, onMounted, ref, watch } from 'vue';
import axios from '@/plugins/axios';
import { formatPrice, formatDateSimple } from "@/utils/formatters";
import VueApexCharts from 'vue3-apexcharts';

const { formatCurrency } = useCurrencyConverter();

// --- ESTADOS Y FILTROS ---
const loading = ref(false);
const groupByCorporate = ref(false);

const dashboardData = ref({
  rankings: { by_units: { data: [] }, by_revenue: { data: [] } },
  trends: [],
  stock_on_hand: [],
  profitability: []
});

const startDate = ref('2026-04-01');
const endDate = ref(new Date().toISOString().split('T')[0]);

// Catálogos
const laboratories = ref([]);

// Paginación Rankings
const pageUnits = ref(1);
const pageRevenue = ref(1);
const loadingUnits = ref(false);
const loadingRevenue = ref(false);

// Benchmarking (Lab A vs Lab B)
const labA = ref(null);
const labB = ref(null);
const benchmarkingData = ref(null);
const loadingBenchmarking = ref(false);

// Deep Dive
const selectedLabId = ref(null);
const deepDiveData = ref(null);
const loadingDeepDive = ref(false);

// --- CARGA DE DATOS ---
const fetchCatalogs = async () => {
  try {
    const { data } = await axios.get('/laboratories');
    laboratories.value = Array.isArray(data) ? data : [];
  } catch (error) {
    console.error("Error cargando catálogos:", error);
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
    dashboardData.value = data;
  } catch (error) {
    console.error("Error al cargar dashboard:", error);
  } finally {
    loading.value = false;
  }
};

const fetchRankings = async (metric = 'total_units', page = 1) => {
  const isLoading = metric === 'total_units' ? loadingUnits : loadingRevenue;
  const pageRef = metric === 'total_units' ? pageUnits : pageRevenue;
  
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
    if (metric === 'total_units') {
      dashboardData.value.rankings.by_units = data;
      pageUnits.value = page;
    } else {
      dashboardData.value.rankings.by_revenue = data;
      pageRevenue.value = page;
    }
  } catch (error) {
    console.error(`Error cargando ranking ${metric}:`, error);
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
      end_date: endDate.value
    };
    const { data } = await axios.get('/bi/laboratories/benchmarking', { params });
    benchmarkingData.value = data;
  } catch (error) {
    console.error("Error en benchmarking:", error);
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
      end_date: endDate.value
    };
    const { data } = await axios.get(`/bi/laboratories/${id}/deep-dive`, { params });
    deepDiveData.value = data;
    selectedLabId.value = id;
  } catch (error) {
    console.error("Error en deep dive:", error);
  } finally {
    loadingDeepDive.value = false;
  }
};

// --- CONFIGURACIÓN DE GRÁFICOS ---
const trendChartOptions = computed(() => {
  const months = [...new Set(dashboardData.value.trends.map(t => t.month))].sort();
  
  return {
    chart: { 
      type: 'line', 
      toolbar: { show: false },
      dropShadow: { enabled: true, top: 3, left: 2, blur: 4, opacity: 0.1 }
    },
    stroke: { curve: 'smooth', width: 3 },
    markers: { size: 4, hover: { size: 7 } },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 5 },
    xaxis: { 
      categories: months,
      labels: { style: { colors: '#616161', fontSize: '11px', fontWeight: 600 } }
    },
    yaxis: {
      labels: {
        formatter: (val) => formatCurrency(val),
        style: { colors: '#616161', fontWeight: 600 }
      }
    },
    colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8'],
    legend: { position: 'top', horizontalAlign: 'right', fontWeight: 600 },
    tooltip: {
      theme: 'dark',
      y: { formatter: (val) => formatCurrency(val) }
    }
  };
});

const trendSeries = computed(() => {
  const months = [...new Set(dashboardData.value.trends.map(t => t.month))].sort();
  const seriesNames = [...new Set(dashboardData.value.trends.map(t => t.lab_name))];

  return seriesNames.map(name => ({
    name,
    data: months.map(m => {
      const match = dashboardData.value.trends.find(t => t.lab_name === name && t.month === m);
      return match ? parseFloat(match.revenue) : 0;
    })
  }));
});

// Gráfico de Ventas (Pie Chart)
const marketShareChartOptions = computed(() => ({
  chart: { type: 'donut' },
  labels: dashboardData.value.rankings.by_revenue.data.map(l => l.name),
  colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8', '#00bbd4', '#607d8b', '#9c27b0', '#3f51b5', '#e91e63'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'TOTAL USD', formatter: () => formatCurrency(dashboardData.value.rankings.by_revenue.data.reduce((a, b) => a + parseFloat(b.total_revenue), 0)) } } } } }
}));

const marketShareSeries = computed(() => dashboardData.value.rankings.by_revenue.data.map(l => parseFloat(l.total_revenue)));

// Gráfico de Rentabilidad (Bar Chart)
const profitabilityChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '70%', distributed: true } },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  xaxis: { categories: dashboardData.value.profitability.map(l => l.name) },
  colors: ['#28c76f'],
  legend: { show: false }
}));

const profitabilitySeries = computed(() => [{
  name: 'Margen %',
  data: dashboardData.value.profitability.map(l => parseFloat(l.margin_percent))
}]);

const stockTreemapOptions = computed(() => ({
  legend: { show: false },
  chart: { height: 350, type: 'treemap', toolbar: { show: false } },
  colors: ['#7367f0'],
  plotOptions: {
    treemap: {
      enableShades: true,
      shadeIntensity: 0.5,
      distributed: true
    }
  },
  tooltip: {
    y: { formatter: (val) => formatCurrency(val) }
  }
}));

const stockSeries = computed(() => ([{
  data: dashboardData.value.stock_on_hand.map(item => ({
    x: item.name,
    y: parseFloat(item.inventory_value)
  }))
}]));

// --- WATCHERS ---
watch([startDate, endDate, groupByCorporate], () => {
  fetchDashboard();
  if (labA.value && labB.value) fetchBenchmarking();
  if (selectedLabId.value) fetchDeepDive(selectedLabId.value);
});

onMounted(() => {
  fetchCatalogs();
  fetchDashboard();
});

const formatPercent = (val) => `${parseFloat(val || 0).toFixed(1)}%`;
</script>

<template>
  <VContainer fluid class="report-laboratories-container pa-0">
    <div class="bi-report-grid">
      <!-- FILTROS PRINCIPALES -->
      <VCard border class="mb-4 rounded-lg shadow-sm">
        <VCardText class="pa-4">
          <VRow align="center">
            <VCol cols="12" md="4" class="d-flex align-center gap-2">
              <VBtn 
                :color="!groupByCorporate ? 'primary' : 'secondary'" 
                variant="tonal" 
                @click="groupByCorporate = false"
                class="flex-grow-1"
              >Individual</VBtn>
              <VBtn 
                :color="groupByCorporate ? 'primary' : 'secondary'" 
                variant="tonal" 
                @click="groupByCorporate = true"
                class="flex-grow-1"
              >Corporativo</VBtn>
            </VCol>
            <VSpacer />
            <VCol cols="12" md="3">
              <AppTextField v-model="startDate" type="date" label="Desde" density="compact" hide-details />
            </VCol>
            <VCol cols="12" md="3">
              <AppTextField v-model="endDate" type="date" label="Hasta" density="compact" hide-details />
            </VCol>
            <VCol cols="12" md="1" class="text-right">
              <VBtn icon variant="tonal" color="primary" @click="fetchDashboard" :loading="loading">
                <VIcon icon="tabler-refresh" />
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- RANKINGS -->
      <VRow class="match-height">
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b bg-light-primary d-flex align-center">
              <VIcon icon="tabler-box" class="me-2 text-primary" />
              <span class="font-weight-bold">Top Laboratorios (Unidades)</span>
            </VCardTitle>
            <VCardText class="pa-0">
              <VList lines="one" v-if="dashboardData.rankings.by_units?.data?.length" :class="{ 'opacity-50': loadingUnits }">
                <VListItem v-for="(lab, idx) in dashboardData.rankings.by_units.data" :key="idx" class="border-b px-2 hover-bg" @click="fetchDeepDive(lab.aggregation_id)">
                  <template #prepend>
                    <VAvatar color="primary" variant="tonal" size="30" class="font-weight-black text-xs">{{ idx + 1 }}</VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-bold text-sm uppercase">{{ lab.name }}</VListItemTitle>
                  <template #append>
                    <div class="text-right">
                      <div class="text-sm font-weight-black">{{ lab.total_units }} Unds</div>
                      <div class="text-caption opacity-60">{{ lab.ticket_count }} Ventas</div>
                    </div>
                  </template>
                </VListItem>
              </VList>
              <div v-else class="pa-10 text-center opacity-50">No hay datos de unidades</div>
              
              <div class="pa-2 d-flex justify-space-between align-center bg-light-primary">
                <span class="text-xs opacity-60">Página {{ pageUnits }}</span>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-chevron-left" variant="text" size="small" :disabled="pageUnits <= 1" @click="fetchRankings('total_units', pageUnits - 1)" />
                  <VBtn icon="tabler-chevron-right" variant="text" size="small" :disabled="dashboardData.rankings.by_units?.data?.length < 10" @click="fetchRankings('total_units', pageUnits + 1)" />
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="6">
          <VCard border class="rounded-lg overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b bg-light-success d-flex align-center">
              <VIcon icon="tabler-coins" class="me-2 text-success" />
              <span class="font-weight-bold">Top Laboratorios (Venta Bruta)</span>
            </VCardTitle>
            <VCardText class="pa-0">
              <VList lines="one" v-if="dashboardData.rankings.by_revenue?.data?.length" :class="{ 'opacity-50': loadingRevenue }">
                <VListItem v-for="(lab, idx) in dashboardData.rankings.by_revenue.data" :key="idx" class="border-b px-2 hover-bg" @click="fetchDeepDive(lab.aggregation_id)">
                  <template #prepend>
                    <VAvatar color="success" variant="tonal" size="30" class="font-weight-black text-xs">{{ idx + 1 }}</VAvatar>
                  </template>
                  <VListItemTitle class="font-weight-bold text-sm uppercase">{{ lab.name }}</VListItemTitle>
                  <template #append>
                    <div class="text-right">
                      <div class="text-sm font-weight-black text-success">{{ formatCurrency(lab.total_revenue) }}</div>
                      <div class="text-caption opacity-60">{{ lab.ticket_count }} Ventas</div>
                    </div>
                  </template>
                </VListItem>
              </VList>
              <div v-else class="pa-10 text-center opacity-50">No hay datos de ingresos</div>

              <div class="pa-2 d-flex justify-space-between align-center bg-light-success">
                <span class="text-xs opacity-60">Página {{ pageRevenue }}</span>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-chevron-left" variant="text" size="small" :disabled="pageRevenue <= 1" @click="fetchRankings('total_revenue', pageRevenue - 1)" />
                  <VBtn icon="tabler-chevron-right" variant="text" size="small" :disabled="dashboardData.rankings.by_revenue?.data?.length < 10" @click="fetchRankings('total_revenue', pageRevenue + 1)" />
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- TENDENCIAS Y CUOTA DE MERCADO -->
      <VRow>
        <VCol cols="12" md="8">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b">Tendencia de Venta Bruta (Top 5)</VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="320" :options="trendChartOptions" :series="trendSeries" />
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="4">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b">Cuota de Mercado (% Ventas)</VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="320" :options="marketShareChartOptions" :series="marketShareSeries" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- RENTABILIDAD Y STOCK -->
      <VRow>
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b d-flex align-center">
              <VIcon icon="tabler-trending-up" class="me-2 text-success" />
              <span>Laboratorios Más Rentables (% Margen)</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="350" :options="profitabilityChartOptions" :series="profitabilitySeries" />
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg shadow-sm h-100">
            <VCardTitle class="pa-4 border-b d-flex align-center">
              <VIcon icon="tabler-building-warehouse" class="me-2 text-primary" />
              <span>Valor de Inventario al Costo (Stock Actual)</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <VueApexCharts height="350" :options="stockTreemapOptions" :series="stockSeries" />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- BENCHMARKING -->
      <VCard border class="mt-4 rounded-lg shadow-sm overflow-hidden">
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center">
          <VIcon icon="tabler-arrows-left-right" class="me-2" color="white" />
          <span>Comparativa Lado a Lado (Benchmarking)</span>
        </VCardTitle>
        <VCardText class="pa-4">
          <VRow>
            <VCol cols="12" md="5">
              <AppAutocomplete v-model="labA" :items="laboratories" item-title="name" item-value="id" placeholder="Seleccionar Laboratorio A" density="compact" hide-details @update:model-value="fetchBenchmarking" />
            </VCol>
            <VCol cols="12" md="2" class="text-center">
              <VChip color="primary" class="font-weight-black mt-2">VS</VChip>
            </VCol>
            <VCol cols="12" md="5">
              <AppAutocomplete v-model="labB" :items="laboratories" item-title="name" item-value="id" placeholder="Seleccionar Laboratorio B" density="compact" hide-details @update:model-value="fetchBenchmarking" />
            </VCol>
          </VRow>

          <VRow v-if="benchmarkingData" class="mt-6 border-t pt-6">
            <!-- Lab A -->
            <VCol cols="12" md="6" class="border-e">
              <div class="px-4">
                <div class="d-flex align-center gap-4 mb-6">
                  <VAvatar color="primary" variant="tonal" size="58"><VIcon icon="tabler-flask" size="32" /></VAvatar>
                  <div>
                    <div class="text-h6 font-weight-black text-primary text-uppercase">{{ laboratories.find(l => l.id === labA)?.name }}</div>
                    <div class="text-caption">Desempeño en el periodo</div>
                  </div>
                </div>
                
                <div class="d-flex flex-column gap-3">
                  <div class="d-flex justify-space-between align-center p-2 rounded bg-light-primary px-3">
                    <span class="text-xs font-weight-bold opacity-70">PARTICIPACIÓN (VS B)</span>
                    <span class="text-h5 font-weight-black text-primary">{{ benchmarkingData.lab_a.share_relative }}%</span>
                  </div>
                  <div class="d-flex justify-space-between align-center p-2 border-b px-3">
                    <span class="text-xs opacity-70">TICKET PROMEDIO</span>
                    <span class="text-sm font-weight-bold">{{ formatCurrency(benchmarkingData.lab_a.details.stats.avg_ticket) }}</span>
                  </div>
                  <div class="d-flex justify-space-between align-center p-2 border-b px-3">
                    <span class="text-xs opacity-70">MARGEN ESTIMADO</span>
                    <VChip size="small" color="success" class="font-weight-bold">{{ formatPercent(benchmarkingData.lab_a.details.stats.avg_margin_percent) }}</VChip>
                  </div>
                </div>
              </div>
            </VCol>

            <!-- Lab B -->
            <VCol cols="12" md="6">
              <div class="px-4">
                <div class="d-flex align-center gap-4 mb-6 justify-end">
                  <div class="text-right">
                    <div class="text-h6 font-weight-black text-success text-uppercase">{{ laboratories.find(l => l.id === labB)?.name }}</div>
                    <div class="text-caption">Desempeño en el periodo</div>
                  </div>
                  <VAvatar color="success" variant="tonal" size="58"><VIcon icon="tabler-flask" size="32" /></VAvatar>
                </div>
                
                <div class="d-flex flex-column gap-3">
                  <div class="d-flex justify-space-between align-center p-2 rounded bg-light-success px-3">
                    <span class="text-xs font-weight-bold opacity-70 text-right">PARTICIPACIÓN (VS A)</span>
                    <span class="text-h5 font-weight-black text-success">{{ benchmarkingData.lab_b.share_relative }}%</span>
                  </div>
                  <div class="d-flex justify-space-between align-center p-2 border-b px-3">
                    <span class="text-xs opacity-70">TICKET PROMEDIO</span>
                    <span class="text-sm font-weight-bold">{{ formatCurrency(benchmarkingData.lab_b.details.stats.avg_ticket) }}</span>
                  </div>
                  <div class="d-flex justify-space-between align-center p-2 border-b px-3">
                    <span class="text-xs opacity-70">MARGEN ESTIMADO</span>
                    <VChip size="small" color="success" class="font-weight-bold">{{ formatPercent(benchmarkingData.lab_b.details.stats.avg_margin_percent) }}</VChip>
                  </div>
                </div>
              </div>
            </VCol>

            <!-- CATEGORIAS COMPARTIDAS (HEAD-TO-HEAD) -->
            <VCol v-if="benchmarkingData.shared_groups.length" cols="12" class="mt-8 pt-4 border-t">
              <div class="text-subtitle-1 font-weight-black mb-4 d-flex align-center">
                <VIcon icon="tabler-swords" class="me-2 text-error" />
                <span>Competencia Directa por Categoría</span>
              </div>
              
              <VRow>
                <VCol v-for="group in benchmarkingData.shared_groups" :key="group.group_id" cols="12" md="6">
                  <VCard border flat class="pa-3 bg-light-secondary rounded-lg">
                    <div class="d-flex justify-space-between align-center mb-1">
                      <span class="text-xs font-weight-black text-uppercase">{{ group.name }}</span>
                    </div>
                    <div class="d-flex align-center gap-2">
                       <span class="text-xs font-weight-bold text-primary">{{ group.share_a }}%</span>
                       <VProgressLinear
                         :model-value="group.share_a"
                         height="12"
                         color="primary"
                         class="flex-grow-1"
                         rounded
                       />
                       <span class="text-xs font-weight-bold text-success">{{ group.share_b }}%</span>
                    </div>
                    <div class="d-flex justify-space-between mt-1 opacity-60 text-xs font-weight-bold">
                      <span class="text-primary">{{ formatCurrency(group.revenue_a) }}</span>
                      <span class="text-success">{{ formatCurrency(group.revenue_b) }}</span>
                    </div>
                  </VCard>
                </VCol>
              </VRow>
            </VCol>
          </VRow>
          <div v-else class="pa-10 text-center text-medium-emphasis">Selecciona dos laboratorios para comparar su rendimiento</div>
        </VCardText>
      </VCard>

      <!-- DEEP DIVE SECCION -->
      <VCard v-if="deepDiveData" border class="mt-4 rounded-lg shadow-sm">
        <VCardTitle class="pa-4 border-b d-flex align-center bg-light-warning">
          <VIcon icon="tabler-zoom-in" class="me-2 text-warning" />
          <span class="font-weight-bold text-uppercase">Top 20 Productos de {{ laboratories.find(l => l.id === selectedLabId)?.name }}</span>
        </VCardTitle>
        <VCardText class="pa-0">
          <VTable density="compact">
            <thead>
              <tr>
                <th class="text-left font-weight-black">PRODUCTO</th>
                <th class="text-center font-weight-black">UNIDADES</th>
                <th class="text-right font-weight-black">VENTA BRUTA</th>
                <th class="text-right font-weight-black">MARGEN EST.</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in deepDiveData.top_products" :key="product.id">
                <td class="text-xs font-weight-bold text-uppercase px-2 pa-2">{{ product.name }}</td>
                <td class="text-center font-weight-black">{{ product.units }}</td>
                <td class="text-right font-weight-bold text-success">{{ formatCurrency(product.revenue) }}</td>
                <td class="text-right">
                  <VChip size="x-small" color="primary" variant="flat">{{ formatCurrency(product.estimated_margin) }}</VChip>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
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

.bg-light-primary { background-color: rgba(115, 103, 240, 0.15); }
.bg-light-success { background-color: rgba(40, 199, 111, 0.15); }
.bg-light-warning { background-color: rgba(255, 159, 67, 0.15); }

/* Ajuste de padding horizontal interno para tablas y listas */
:deep(.v-table .v-table__wrapper > table > thead > tr > th),
:deep(.v-table .v-table__wrapper > table > tbody > tr > td) {
  padding-inline: 6px !important;
}

.hover-bg:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  cursor: pointer;
}

.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }
.gap-8 { gap: 32px; }
</style>
