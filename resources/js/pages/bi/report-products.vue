<script setup>
import AppFilterBase from '@/components/AppFilterBase.vue';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';
import axios from '@/plugins/axios';
import { computed, onMounted, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const { formatCurrency } = useCurrencyConverter();

// --- ESTADOS Y FILTROS ---
const loading = ref(false);

const defaultDashboardData = {
  quadrant1: { top_volume: [], top_margin: [], lab_ranking: [], pareto: { percent: 0 } },
  quadrant2: { abc: [], expirations: [], inventory_loss: [] },
  quadrant4: { out_of_stock: 0, critical_stock: 0, avg_inventory_days: 0 }
};

const dashboardData = ref(JSON.parse(JSON.stringify(defaultDashboardData)));
const trendData = ref([]);

const search = ref('');
const startDate = ref('2026-04-01');
const endDate = ref(new Date().toISOString().split('T')[0]);
const selectedLaboratory = ref(null);
const selectedGroup = ref(null);

const laboratories = ref([]);
const groups = ref([]);

// Para el Cuadrante 1 (Top 10)
const performanceMetric = ref('margin');

// Para el Cuadrante 3 (Tendencias)
const selectedTrendProduct = ref(null);
const selectedTrendGroup = ref(null);
const products = ref([]);

const hasActiveAdvancedFilters = computed(() => {
  return startDate.value !== '2026-03-01' || selectedLaboratory.value || selectedGroup.value;
});

// --- CARGA DE DATOS ---
const fetchCatalogs = async () => {
  try {
    const [labRes, grpRes] = await Promise.all([
      axios.get('/laboratories').catch(() => ({ data: [] })),
      axios.get('/groups/consult-all').catch(() => ({ data: { data: [] } }))
    ]);
    laboratories.value = Array.isArray(labRes.data) ? labRes.data : [];
    groups.value = Array.isArray(grpRes.data?.data) ? grpRes.data.data : [];
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
      laboratory_id: selectedLaboratory.value,
      group_id: selectedGroup.value,
      search: search.value
    };
    const { data } = await axios.get('/bi/products/dashboard', { params });
    if (data && data.quadrant1 && data.quadrant2 && data.quadrant4) {
      dashboardData.value = data;
    }
  } catch (error) {
    console.error("Error al cargar dashboard:", error);
  } finally {
    loading.value = false;
  }
};

const fetchTrends = async () => {
  try {
    const params = {
      product_id: selectedTrendProduct.value,
      group_id: selectedTrendGroup.value,
      start_date: startDate.value,
      end_date: endDate.value
    };
    const { data } = await axios.get('/bi/products/trends', { params });
    // Garantizar que siempre se reciba un array
    trendData.value = Array.isArray(data) ? data : (data ? Object.values(data) : []);
  } catch (error) {
    console.error("Error al cargar tendencias:", error);
  }
};

onMounted(() => {
  fetchCatalogs();
  fetchDashboard();
  fetchTrends();
});

watch([search, startDate, endDate, selectedLaboratory, selectedGroup], () => {
  fetchDashboard();
  fetchTrends();
});

watch([selectedTrendGroup], () => {
  fetchTrends();
});

// --- COMPROBACIONES DE SEGURIDAD EXTREMAS ---
const toSafeArray = (data) => Array.isArray(data) ? data : (data ? Object.values(data) : []);

const safeTopVolume = computed(() => toSafeArray(dashboardData.value?.quadrant1?.top_volume));
const safeTopRevenue = computed(() => toSafeArray(dashboardData.value?.quadrant1?.top_revenue));
const safeLabData = computed(() => toSafeArray(dashboardData.value?.quadrant1?.lab_ranking));
const safeAbcData = computed(() => toSafeArray(dashboardData.value?.quadrant2?.abc));
const safeExpData = computed(() => toSafeArray(dashboardData.value?.quadrant2?.expirations));
const safeTrendData = computed(() => toSafeArray(trendData.value));
const safeInvLossData = computed(() => toSafeArray(dashboardData.value?.quadrant2?.inventory_loss));

// 1. Ranking Laboratorios
const labChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: {
    categories: safeLabData.value.map(l => l?.name || 'Desconocido'),
    labels: { formatter: (val) => `$${Number(val || 0).toLocaleString()}` }
  },
  colors: ['#7367F0'],
  tooltip: { y: { formatter: (val) => formatCurrency(val || 0) } }
}));

const labChartSeries = computed(() => ([{
  name: 'Margen Total',
  data: safeLabData.value.map(l => Number(l?.total_margin || 0))
}]));

// 2. ABC Summary
const abcChartOptions = computed(() => ({
  labels: safeAbcData.value.map(a => `Clase ${a?.type || '?'}`),
  colors: ['#28C76F', '#FF9F43', '#EA5455'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
  plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'SKUs' } } } } }
}));

const abcChartSeries = computed(() => safeAbcData.value.map(a => Number(a?.count || 0)));

// 3. Vencimientos
const expirationChartOptions = computed(() => ({
  chart: { type: 'bar', stacked: true, toolbar: { show: false } }, 
  plotOptions: { bar: { horizontal: false, columnWidth: '50%' } },
  xaxis: { categories: safeExpData.value.map(e => e?.bucket || '') },
  colors: ['#EA5455', '#FF9F43', '#FFCC00', '#28C76F'],
  dataLabels: { enabled: false },
  yaxis: { labels: { formatter: (val) => `$${Number(val || 0).toLocaleString()}` } }
}));

const expirationChartSeries = computed(() => ([{
  name: 'Valor en Riesgo',
  data: safeExpData.value.map(e => Number(e?.total_value || 0))
}]));

// 4. Tendencias Comparativas
const trendChartOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: true } },
  stroke: { width: [3, 3], curve: 'smooth' },
  labels: safeTrendData.value.map(t => {
    if (!t?.week) return '';
    const parts = String(t.week).split('-');
    return parts.length > 1 ? `S${parts[1]}` : t.week;
  }),
  dataLabels: { enabled: false },
  colors: ['#7367F0', '#FF9F43'],
  legend: { position: 'top', horizontalAlign: 'right' },
  xaxis: { title: { text: 'Semana' } },
  yaxis: [
    { title: { text: 'Ventas (Und)' } },
    { opposite: true, title: { text: 'Compras (Und)' } }
  ]
}));

const trendChartSeries = computed(() => ([
  { name: 'Ventas', type: 'line', data: safeTrendData.value.map(t => Number(t?.sold || 0)) },
  { name: 'Compras', type: 'line', data: safeTrendData.value.map(t => Number(t?.purchased || 0)) }
]));

const formatPercent = (val) => {
  return Number(val || 0).toFixed(2) + '%';
};

const calcLossTotal = computed(() => safeInvLossData.value.reduce((acc, curr) => acc + Number(curr?.money_loss || 0), 0));

// Helpers UI
const resetFilters = () => {
  search.value = '';
  startDate.value = '2026-04-01';
  endDate.value = new Date().toISOString().split('T')[0];
  selectedLaboratory.value = null;
  selectedGroup.value = null;
};
</script>

<template>
  <div class="pb-6 px-0">
    <AppFilterBase
      v-model:search="search"
      placeholder="Buscar producto por nombre..."
      :has-advanced-filters="hasActiveAdvancedFilters"
      :loading="loading"
      show-export
      @clear="resetFilters"
      class="mb-4"
    >
      <template #actions-extra>
        <VBtn icon variant="tonal" color="secondary" size="38" class="rounded-pill" @click="fetchDashboard">
          <VIcon icon="tabler-refresh" />
          <VTooltip activator="parent" location="top">Sincronizar</VTooltip>
        </VBtn>
        <VBtn icon color="primary" variant="flat" size="38" class="rounded-pill">
          <VIcon icon="tabler-file-export" />
          <VTooltip activator="parent" location="top">Reporte Ejecutivo</VTooltip>
        </VBtn>
      </template>
      <template #advanced-filters>
        <VCol cols="12" md="3"><AppTextField v-model="startDate" type="date" label="Desde" density="compact" hide-details /></VCol>
        <VCol cols="12" md="3"><AppTextField v-model="endDate" type="date" label="Hasta" density="compact" hide-details /></VCol>
        <VCol cols="12" md="3">
          <AppAutocomplete v-model="selectedLaboratory" :items="laboratories" item-title="name" item-value="id" placeholder="Laboratorios" label="Laboratorio" clearable density="compact" hide-details prepend-inner-icon="tabler-flask" />
        </VCol>
        <VCol cols="12" md="3">
          <AppAutocomplete v-model="selectedGroup" :items="groups" item-title="name" item-value="id" placeholder="Grupos" label="Grupo" clearable density="compact" hide-details prepend-inner-icon="tabler-tags" />
        </VCol>
      </template>
    </AppFilterBase>

    <!-- RESUMEN KPI -->
    <VRow class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg overflow-hidden shadow-sm">
          <div class="pa-4 d-flex align-center gap-4">
            <VAvatar color="error" variant="tonal" rounded="lg" size="48"><VIcon icon="tabler-package-off" size="26" /></VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ dashboardData.quadrant4.out_of_stock || 0 }}</div>
              <div class="text-caption text-medium-emphasis">Out of Stock (SKUs)</div>
            </div>
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg overflow-hidden shadow-sm">
          <div class="pa-4 d-flex align-center gap-4">
            <VAvatar color="warning" variant="tonal" rounded="lg" size="48"><VIcon icon="tabler-hourglass-high" size="26" /></VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ dashboardData.quadrant4.critical_stock || 0 }}</div>
              <div class="text-caption text-medium-emphasis">Suministro Crítico (<7d)</div>
            </div>
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg overflow-hidden shadow-sm">
          <div class="pa-4 d-flex align-center gap-4">
            <VAvatar color="success" variant="tonal" rounded="lg" size="48"><VIcon icon="tabler-chart-pie" size="26" /></VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ dashboardData.quadrant1.pareto.percent || 0 }}%</div>
              <div class="text-caption text-medium-emphasis">Eficiencia Pareto (Utilidad)</div>
            </div>
          </div>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard border class="rounded-lg overflow-hidden shadow-sm">
          <div class="pa-4 d-flex align-center gap-4">
            <VAvatar color="info" variant="tonal" rounded="lg" size="48"><VIcon icon="tabler-calendar-time" size="26" /></VAvatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ Math.round(dashboardData.quadrant4.avg_inventory_days || 0) }}</div>
              <div class="text-caption text-medium-emphasis">Días Prom. de Inventario</div>
            </div>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <!-- CUADRANTE 1: RENDIMIENTO -->
      <VCol cols="12" md="6">
        <VCard border class="rounded-lg h-100 flex-column shadow-sm overflow-hidden">
          <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between">
            <span class="text-h6 font-weight-bold">TOP Productos</span>
            <VBtnToggle v-model="performanceMetric" mandatory density="compact" color="primary">
              <VBtn value="volume" size="small"><VIcon icon="tabler-package" /><VTooltip activator="parent" location="top">Volumen</VTooltip></VBtn>
              <VBtn value="margin" size="small"><VIcon icon="tabler-currency-dollar" /><VTooltip activator="parent" location="top">Venta Bruta</VTooltip></VBtn>
            </VBtnToggle>
          </VCardTitle>
          <VCardText class="pa-0">
            <div class="px-4 py-2 text-caption text-medium-emphasis">Mostrando {{ (performanceMetric === 'volume' ? safeTopVolume : safeTopRevenue).length }} ítems</div>
            <VList lines="one" class="px-0">
              <VListItem 
                v-for="(item, idx) in (performanceMetric === 'volume' ? safeTopVolume : safeTopRevenue)" 
                :key="item?.id || idx"
                class="border-b px-4"
              >
                <template #prepend><VAvatar color="primary" variant="tonal" size="32" class="me-3">{{ idx + 1 }}</VAvatar></template>
                <VListItemTitle class="font-weight-medium text-truncate" style="max-width: 250px;">{{ item?.name || 'Desconocido' }}</VListItemTitle>
                <template #append>
                   <div class="text-right">
                     <div class="text-body-2 font-weight-bold">{{ performanceMetric === 'volume' ? `${item?.total_sold || 0} Und` : formatCurrency(item?.total_revenue || 0) }}</div>
                     <div class="text-caption text-medium-emphasis">{{ formatPercent(((item?.total_margin || 0) / (item?.total_revenue || 1)) * 100) }} margen</div>
                   </div>
                </template>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="6">
        <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
          <VCardTitle class="pa-4 border-b"><span class="text-h6 font-weight-bold">Rentabilidad por Laboratorio</span></VCardTitle>
          <VCardText class="px-0 py-4">
            <VueApexCharts v-if="safeLabData.length" height="400" :options="labChartOptions" :series="labChartSeries" />
            <div v-else class="text-center pa-10 text-medium-emphasis">No hay datos de rentabilidad</div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- CUADRANTE 2: RIESGO Y SALUD -->
      <VCol cols="12" md="4">
        <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
          <VCardTitle class="pa-4 border-b"><span class="text-h6 font-weight-bold">Análisis ABC</span></VCardTitle>
          <VCardText class="px-0 py-4 text-center">
            <VueApexCharts v-if="safeAbcData.length" height="300" :options="abcChartOptions" :series="abcChartSeries" />
            <div v-else class="text-center pa-10 text-medium-emphasis">Sin datos ABC</div>
            <div class="mt-4 text-left px-4">
              <div v-for="abc in safeAbcData" :key="abc?.type" class="d-flex justify-space-between mb-2 border-b pa-1">
                <span>Clase {{ abc?.type }}</span>
                <span class="font-weight-bold">{{ formatCurrency(abc?.revenue || 0) }}</span>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="4">
        <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
          <VCardTitle class="pa-4 border-b"><span class="text-h6 font-weight-bold">Vencimientos ($)</span></VCardTitle>
          <VCardText class="px-0 py-4">
            <VueApexCharts v-if="safeExpData.length" height="350" :options="expirationChartOptions" :series="expirationChartSeries" />
            <div v-else class="text-center pa-10 text-medium-emphasis">Sin vencimientos próximos</div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="4">
        <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
          <VCardTitle class="pa-4 border-b"><span class="text-h6 font-weight-bold">Integridad de Inventario</span></VCardTitle>
          <VCardText class="px-0 py-4">
            <div v-if="safeInvLossData.length" class="text-center px-4">
              <div class="text-h4 font-weight-black text-error">{{ formatCurrency(calcLossTotal) }}</div>
              <div class="text-caption mb-4">Pérdida monetaria acumulada</div>
               <div class="border rounded pa-2 bg-light-error bg-opacity-10">
                 <div v-for="loss in safeInvLossData" :key="loss?.date" class="d-flex justify-space-between text-caption border-b py-1">
                    <span>{{ loss?.date }}</span>
                    <span class="text-error font-weight-bold">- {{ formatCurrency(loss?.money_loss || 0) }}</span>
                 </div>
               </div>
            </div>
            <div v-else class="text-center pa-10 text-medium-emphasis">Sin discrepancias reportadas</div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- CUADRANTE 3: COMPARATIVA Y TENDENCIAS -->
      <VCol cols="12">
        <VCard v-if="safeTrendData.length" border class="rounded-lg overflow-hidden shadow-sm">
          <VCardTitle class="pa-4 border-b d-flex align-center gap-4 flex-wrap">
            <span class="text-h6 font-weight-bold">Tendencias: Ventas vs Compras</span>
            <VSpacer />
             <div class="pt-2 d-flex gap-2" style="max-width: 400px; width: 100%;">
                <AppAutocomplete v-model="selectedTrendGroup" :items="groups" item-title="name" item-value="id" placeholder="Filtrar por Grupo" clearable density="compact" hide-details />
             </div>
          </VCardTitle>
          <VCardText class="px-0 py-10">
            <VueApexCharts height="400" :options="trendChartOptions" :series="trendChartSeries" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.bg-light-primary { background-color: #7367F0; }
.bg-light-error { background-color: #EA5455; }
.bg-opacity-10 { opacity: 0.15; }
.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
</style>
