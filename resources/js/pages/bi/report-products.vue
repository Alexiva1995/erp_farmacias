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

const defaultDashboardData = {
  quadrant1: { top_volume: [], top_revenue: [], lab_ranking: [], pareto: { percent: 0 } },
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
    console.log("[Dashboard Data]", data);
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

const fetchRankings = async (sortBy = 'total_sold', page = 1) => {
  const isLoading = sortBy === 'total_sold' ? loadingVolume : loadingRevenue;
  isLoading.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
      laboratory_id: selectedLaboratory.value,
      group_id: selectedGroup.value,
      search: search.value,
      sort_by: sortBy,
      page
    };
    const { data } = await axios.get('/bi/products/rankings', { params });
    if (sortBy === 'total_sold') {
      dashboardData.value.quadrant1.top_volume = data;
      volumePage.value = page;
    } else {
      dashboardData.value.quadrant1.top_revenue = data;
      revenuePage.value = page;
    }
  } catch (error) {
    console.error(`Error al cargar ranking ${sortBy}:`, error);
  } finally {
    isLoading.value = false;
  }
};

const fetchCrossSelling = async (page = 1) => {
  loadingCrossSelling.value = true;
  try {
    const params = {
      start_date: startDate.value,
      end_date: endDate.value,
      laboratory_id: selectedLaboratory.value,
      group_id: selectedGroup.value,
      page
    };
    const { data } = await axios.get('/bi/products/cross-selling', { params });
    if (dashboardData.value?.quadrant2) {
      dashboardData.value.quadrant2.cross_selling = data;
      crossSellingPage.value = page;
    }
  } catch (error) {
    console.error("Error al cargar cross-selling:", error);
  } finally {
    loadingCrossSelling.value = false;
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
  crossSellingPage.value = 1;
  volumePage.value = 1;
  revenuePage.value = 1;
});

watch([selectedTrendGroup], () => {
  fetchTrends();
});

// --- COMPROBACIONES DE SEGURIDAD EXTREMAS ---
const toSafeArray = (data) => Array.isArray(data) ? data : (data ? Object.values(data) : []);

const safeTopVolume = computed(() => toSafeArray(dashboardData.value?.quadrant1?.top_volume?.data || dashboardData.value?.quadrant1?.top_volume));
const safeTopRevenue = computed(() => toSafeArray(dashboardData.value?.quadrant1?.top_revenue?.data || dashboardData.value?.quadrant1?.top_revenue));
const volumePage = ref(1);
const revenuePage = ref(1);
const loadingVolume = ref(false);
const loadingRevenue = ref(false);
const safeLabData = computed(() => toSafeArray(dashboardData.value?.quadrant1?.lab_ranking));
const safeAbcData = computed(() => toSafeArray(dashboardData.value?.quadrant2?.abc));
const safeCrossSelling = computed(() => toSafeArray(dashboardData.value?.quadrant2?.cross_selling?.data || dashboardData.value?.quadrant2?.cross_selling));
const crossSellingPage = ref(1);
const loadingCrossSelling = ref(false);
const safeTrendData = computed(() => toSafeArray(trendData.value));

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
  chart: { type: 'donut', toolbar: { show: false } },
  labels: safeAbcData.value.map(a => `Clase ${a?.type || '?'}`),
  colors: ['#28C76F', '#FF9F43', '#EA5455'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
  plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'SKUs' } } } } }
}));

const abcChartSeries = computed(() => safeAbcData.value.map(a => Number(a?.count || 0)));

// 3. (Eliminado: Vencimientos e Integridad)


// 4. Tendencias Comparativas
const trendChartOptions = computed(() => ({
  chart: { 
    type: 'line', 
    toolbar: { show: false },
    zoom: { enabled: false }
  },
  dataLabels: { enabled: false },
  stroke: { width: [3, 3], curve: 'smooth' },
  markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
  colors: ['#7367F0', '#FF9F43'],
  legend: { position: 'top', horizontalAlign: 'right', offsetY: -10 },
  xaxis: { 
    title: { text: 'Semana' },
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: {
      hideOverlappingLabels: true,
      rotate: -45,
      rotateAlways: false
    }
  },
  yaxis: { 
    title: { text: 'Cantidad de Unidades' },
    labels: { formatter: (val) => Number(val).toFixed(0) }
  },
  grid: { strokeDashArray: 5 }
}));

const trendChartSeries = computed(() => ([
  { name: 'Ventas (Und)', type: 'line', data: safeTrendData.value.map(d => d.sold) },
  { name: 'Compras (Und)', type: 'line', data: safeTrendData.value.map(d => d.purchased) }
]));

const trendChartOptionsComputed = computed(() => ({
  ...trendChartOptions.value,
  labels: safeTrendData.value.map(d => {
    if (!d?.week) return '';
    const parts = d.week.split('-');
    return parts.length > 1 ? `S${parts[1]}` : d.week;
  })
}));

// 5. Analítica Individual de Producto (Nueva Sección)
const productSearchItems = ref([]);
const productSearchLoading = ref(false);
const selectedProductAnalytic = ref(null);
const productStatsData = ref(null);
const loadingProductStats = ref(false);

const searchProducts = async (query) => {
  if (!query || query.length < 1) return;
  productSearchLoading.value = true;
  
  // Si la consulta es puramente numérica, buscamos por ID directo
  const isIdSearch = /^\d+$/.test(query.trim());
  const params = isIdSearch 
    ? { id: query.trim() } 
    : { q: query, itemsPerPage: 10 };

  try {
    const { data } = await axios.get('/products', { params });
    productSearchItems.value = data.data;
  } catch (error) {
    console.error('Error buscando productos:', error);
  } finally {
    productSearchLoading.value = false;
  }
};

const loadProductStats = async (productId) => {
  if (!productId) {
    productStatsData.value = null;
    return;
  }
  loadingProductStats.value = true;
  try {
    const response = await axios.get(`/products/${productId}/stats`);
    productStatsData.value = response.data.data;
  } catch (error) {
    console.error('Error cargando stats del producto:', error);
  } finally {
    loadingProductStats.value = false;
  }
};

watch(selectedProductAnalytic, (newId) => {
  if (newId) loadProductStats(newId);
});

const individualChartOptions = computed(() => ({
  chart: {
    type: "area",
    toolbar: { show: false },
    zoom: { enabled: false },
  },
  dataLabels: { enabled: false },
  stroke: { curve: "smooth", width: 2 },
  xaxis: {
    categories: productStatsData.value?.trend_chart?.labels || [],
    labels: { style: { fontSize: "10px" } },
  },
  yaxis: { labels: { style: { fontSize: "10px" } } },
  grid: { strokeDashArray: 5 },
  colors: ["#7367f0", "#28c76f", "#ea5455", "#ff9f43", "#00cfe8", "#4b4b4b"],
  tooltip: { theme: "dark" },
}));

const marketShareOptions = computed(() => ({
  chart: { type: 'radialBar' },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: { size: '70%' },
      dataLabels: {
        name: { fontSize: '13px', color: 'rgba(var(--v-theme-on-surface), 0.6)', offsetY: -10 },
        value: { offsetY: 5, fontSize: '22px', fontWeight: 800, formatter: val => `${val}%` },
      },
    },
  },
  stroke: { dashArray: 4 },
  labels: ['Preferencia'],
  colors: ['#7367f0'],
}));

const individualSeries = computed(() => productStatsData.value?.trend_chart?.series || []);
const marketShareSeries = computed(() => [productStatsData.value?.market_share || 0]);

const formatPercent = (val) => {
  return Number(val || 0).toFixed(2) + '%';
};

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
  <VContainer fluid class="report-products-container pa-0">
    <div class="bi-report-grid">
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

      <VRow class="match-height">
        <!-- CUADRANTE 1: RENDIMIENTO INDEPENDIENTE -->
        <VCol cols="12" md="6">
          <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b d-flex align-center bg-light-primary">
              <VIcon icon="tabler-package" class="me-2 text-primary" />
              <span class="text-h6 font-weight-bold text-high-emphasis">TOP Productos (Volumen)</span>
            </VCardTitle>
            <VCardText class="pa-0">
              <div :class="{ 'opacity-50 transition-opacity': loadingVolume }">
                <VList lines="one" class="px-0">
                  <VListItem 
                    v-for="(item, idx) in safeTopVolume" 
                    :key="item?.id ? `vol-${item.id}` : `idxv-${idx}`"
                    class="border-b px-2"
                  >
                    <template #prepend><VAvatar color="primary" variant="tonal" size="32" class="me-3 font-weight-black">{{ (volumePage - 1) * 10 + (idx + 1) }}</VAvatar></template>
                    <div class="d-flex flex-column min-width-0 py-2">
                      <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" style="max-width: 200px;" :title="item?.name">
                        {{ item?.name || 'Desconocido' }}
                      </span>
                      <div class="d-flex align-center gap-1 text-super-xs">
                        <span class="text-primary font-weight-black">ID: {{ item?.id }}</span>
                        <span class="text-disabled mx-1">|</span>
                        <span class="text-disabled text-truncate" style="max-width: 150px;">{{ item?.active_ingredient || 'Sin principio activo' }}</span>
                        <span class="text-disabled mx-1">|</span>
                        <span class="text-primary font-weight-black text-uppercase text-truncate" style="max-width: 120px;">
                          {{ item?.laboratory_name || 'S/L' }}
                        </span>
                      </div>
                    </div>
                    <template #append>
                       <div class="text-right">
                         <div class="text-body-2 font-weight-bold text-primary">{{ item?.total_sold || 0 }} Unds</div>
                         <div class="text-super-xs text-medium-emphasis">Ventas realizadas</div>
                       </div>
                    </template>
                  </VListItem>
                </VList>
              </div>
              
              <VDivider />
              <div class="pa-2 d-flex align-center justify-space-between bg-light-primary">
                <span class="text-xs font-weight-medium ms-2">Página {{ volumePage }}</span>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-chevron-left" size="small" variant="text" :disabled="volumePage <= 1 || loadingVolume" @click="fetchRankings('total_sold', volumePage - 1)" />
                  <VBtn icon="tabler-chevron-right" size="small" variant="text" :disabled="safeTopVolume.length < 10 || loadingVolume" @click="fetchRankings('total_sold', volumePage + 1)" />
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="6">
          <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b d-flex align-center bg-light-success">
              <VIcon icon="tabler-currency-dollar" class="me-2 text-success" />
              <span class="text-h6 font-weight-bold text-high-emphasis">TOP Productos (Venta Bruta)</span>
            </VCardTitle>
            <VCardText class="pa-0">
              <div :class="{ 'opacity-50 transition-opacity': loadingRevenue }">
                <VList lines="one" class="px-0">
                  <VListItem 
                    v-for="(item, idx) in safeTopRevenue" 
                    :key="item?.id ? `rev-${item.id}` : `idxr-${idx}`"
                    class="border-b px-2"
                  >
                    <template #prepend><VAvatar color="success" variant="tonal" size="32" class="me-3 font-weight-black">{{ (revenuePage - 1) * 10 + (idx + 1) }}</VAvatar></template>
                    <div class="d-flex flex-column min-width-0 py-2">
                      <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" style="max-width: 200px;" :title="item?.name">
                        {{ item?.name || 'Desconocido' }}
                      </span>
                      <div class="d-flex align-center gap-1 text-super-xs">
                        <span class="text-success font-weight-black">ID: {{ item?.id }}</span>
                        <span class="text-disabled mx-1">|</span>
                        <span class="text-disabled text-truncate" style="max-width: 150px;">{{ item?.active_ingredient || 'Sin principio activo' }}</span>
                        <span class="text-disabled mx-1">|</span>
                        <span class="text-success font-weight-black text-uppercase text-truncate" style="max-width: 120px;">
                          {{ item?.laboratory_name || 'S/L' }}
                        </span>
                      </div>
                    </div>
                    <template #append>
                       <div class="text-right">
                         <div class="text-body-2 font-weight-bold text-success">{{ formatCurrency(item?.total_revenue || 0) }}</div>
                         <div class="text-super-xs text-medium-emphasis">Total recaudado</div>
                       </div>
                    </template>
                  </VListItem>
                </VList>
              </div>

              <VDivider />
              <div class="pa-2 d-flex align-center justify-space-between bg-light-success">
                <span class="text-xs font-weight-medium ms-2">Página {{ revenuePage }}</span>
                <div class="d-flex gap-1">
                  <VBtn icon="tabler-chevron-left" size="small" variant="text" :disabled="revenuePage <= 1 || loadingRevenue" @click="fetchRankings('total_revenue', revenuePage - 1)" />
                  <VBtn icon="tabler-chevron-right" size="small" variant="text" :disabled="safeTopRevenue.length < 10 || loadingRevenue" @click="fetchRankings('total_revenue', revenuePage + 1)" />
                </div>
              </div>
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
                <div v-for="abc in safeAbcData" :key="abc?.type" class="d-flex justify-space-between mb-2 border-b pa-1 align-center">
                  <div class="d-flex flex-column">
                    <span class="text-sm font-weight-bold">Clase {{ abc?.type }}</span>
                    <span class="text-xs text-medium-emphasis">{{ formatCurrency(abc?.revenue || 0) }} acum.</span>
                  </div>
                  <div class="text-right">
                    <div class="text-sm font-weight-black">{{ abc?.count }} SKUs</div>
                    <div class="text-super-xs text-primary">{{ formatPercent((abc?.count / safeAbcData.reduce((acc, curr) => acc + curr.count, 0)) * 100) }} del total</div>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12" md="8">
          <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b d-flex align-center">
              <span class="text-h6 font-weight-bold">Venta Cruzada (Cross-selling)</span>
              <VChip color="success" size="x-small" label class="ms-2">Parejas Frecuentes</VChip>
            </VCardTitle>
            <VCardText class="pa-0">
              <VTable v-if="safeCrossSelling.length" density="compact" class="cross-selling-table">
                <thead>
                  <tr>
                    <th class="text-left font-weight-black uppercase">Vínculo de Productos (A + B)</th>
                    <th class="text-right font-weight-black uppercase">Frecuencia</th>
                  </tr>
                </thead>
                <tbody :class="{ 'opacity-50 transition-opacity': loadingCrossSelling }">
                  <tr v-for="(pair, idx) in safeCrossSelling" :key="idx">
                    <td class="py-3 px-2">
                      <div class="d-flex align-center gap-3">
                        <!-- Producto A -->
                        <div class="d-flex flex-column min-width-0" style="flex: 1;">
                          <span class="text-sm font-weight-black text-uppercase text-truncate mb-1" :title="pair.product_a">{{ pair.product_a }}</span>
                          <div class="d-flex align-center gap-1 text-super-xs font-weight-bold">
                            <span class="text-primary">ID: {{ pair.product_id_a }}</span>
                            <span class="opacity-30">|</span>
                            <span class="text-medium-emphasis uppercase text-truncate" style="max-width: 120px;">{{ pair.ingredient_a || 'S/PA' }}</span>
                            <span class="opacity-30">|</span>
                            <span class="text-primary uppercase text-truncate" style="max-width: 100px;">{{ pair.lab_a || 'S/L' }}</span>
                          </div>
                        </div>

                        <div class="d-flex align-center justify-center" style="width: 30px;">
                          <VIcon icon="tabler-plus" size="18" color="primary" />
                        </div>

                        <!-- Producto B -->
                        <div class="d-flex flex-column min-width-0" style="flex: 1;">
                          <span class="text-sm font-weight-black text-uppercase text-truncate mb-1" :title="pair.product_b">{{ pair.product_b }}</span>
                          <div class="d-flex align-center gap-1 text-super-xs font-weight-bold">
                            <span class="text-primary">ID: {{ pair.product_id_b }}</span>
                            <span class="opacity-30">|</span>
                            <span class="text-medium-emphasis uppercase text-truncate" style="max-width: 120px;">{{ pair.ingredient_b || 'S/PA' }}</span>
                            <span class="opacity-30">|</span>
                            <span class="text-primary uppercase text-truncate" style="max-width: 100px;">{{ pair.lab_b || 'S/L' }}</span>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="text-right px-2">
                      <VChip color="primary" class="font-weight-black" size="small">{{ pair.frequency }}</VChip>
                      <div class="text-super-xs text-medium-emphasis mt-1">Juntos</div>
                    </td>
                  </tr>
                </tbody>
              </VTable>
              <div v-else class="pa-10 text-center text-medium-emphasis">No se han detectado asociaciones frecuentes en este periodo</div>
              
              <VDivider />
              <div class="pa-2 d-flex align-center justify-space-between bg-light-primary">
                <span class="text-xs font-weight-medium ms-2">Página {{ crossSellingPage }}</span>
                <div class="d-flex gap-1">
                  <VBtn 
                    icon="tabler-chevron-left" 
                    size="small" 
                    variant="text" 
                    :disabled="crossSellingPage <= 1 || loadingCrossSelling"
                    @click="fetchCrossSelling(crossSellingPage - 1)" 
                  />
                  <VBtn 
                    icon="tabler-chevron-right" 
                    size="small" 
                    variant="text" 
                    :disabled="safeCrossSelling.length < 8 || loadingCrossSelling"
                    @click="fetchCrossSelling(crossSellingPage + 1)" 
                  />
                </div>
              </div>
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
            <VCardText class="pa-4">
              <VueApexCharts height="280" :options="trendChartOptionsComputed" :series="trendChartSeries" />
            </VCardText>
          </VCard>
        </VCol>
        <!-- SECCIÓN 4: ANALÍTICA INDIVIDUAL DE PRODUCTO -->
        <VCol cols="12">
          <VCard border class="rounded-lg overflow-hidden shadow-md">
            <header class="pa-4 bg-primary d-flex align-center flex-wrap gap-4">
              <div class="d-flex align-center text-white">
                <VIcon icon="tabler-chart-dots" class="me-2" color="white" />
                <span class="text-h6 font-weight-bold text-white">Analítica Individual de Producto</span>
              </div>
              <VSpacer />
              <div style="width: 400px; max-width: 100%;">
                <VAutocomplete
                  v-model="selectedProductAnalytic"
                  :items="productSearchItems"
                  :loading="productSearchLoading"
                  item-title="name"
                  item-value="id"
                  placeholder="Escribe el nombre o el ID del producto..."
                  variant="solo"
                  density="compact"
                  hide-details
                  clearable
                  no-filter
                  class="bg-white rounded custom-search-analytic"
                  @update:search="searchProducts"
                >
                  <template #item="{ props, item }">
                    <VListItem v-bind="props">
                      <template #prepend>
                        <VChip size="x-small" color="primary" label class="me-2">ID: {{ item.raw.id }}</VChip>
                      </template>
                    </VListItem>
                  </template>
                </VAutocomplete>
              </div>
            </header>

            <VCardText class="pa-6" v-if="productStatsData">
              <VProgressLinear v-if="loadingProductStats" indeterminate color="primary" class="mb-6" />
              
              <VRow>
                <!-- KPI de Market Share -->
                <VCol cols="12" md="4">
                  <VCard variant="outlined" class="pa-4 rounded-lg d-flex flex-column align-center justify-center h-100">
                    <div class="text-xs font-weight-black text-disabled uppercase mb-4">Dominancia del SKU</div>
                    <VueApexCharts type="radialBar" height="240" :options="marketShareOptions" :series="marketShareSeries" />
                    <div class="text-center mt-2">
                      <div class="text-h4 font-weight-black">{{ productStatsData.market_share }}%</div>
                      <div class="text-caption text-medium-emphasis">Participación en su grupo competitivo</div>
                    </div>
                  </VCard>
                </VCol>

                <!-- Tendencia y Resumen -->
                <VCol cols="12" md="8">
                  <VCard variant="outlined" class="pa-4 rounded-lg h-100">
                    <div class="d-flex align-center justify-space-between mb-4">
                      <span class="text-xs font-weight-black text-high-emphasis uppercase">Tendencia Histórica</span>
                      <div class="d-flex gap-4">
                        <div class="text-right">
                          <div class="text-caption text-disabled uppercase font-weight-black">Ventas Totales</div>
                          <div class="text-h6 font-weight-black text-primary">{{ productStatsData.total_units_sold }} Unidades</div>
                        </div>
                        <div class="text-right">
                          <div class="text-caption text-disabled uppercase font-weight-black">Promedio Mes</div>
                          <div class="text-h6 font-weight-black text-success">{{ productStatsData.monthly_average }} / mes</div>
                        </div>
                      </div>
                    </div>
                    <VueApexCharts type="area" height="250" :options="individualChartOptions" :series="individualSeries" />
                  </VCard>
                </VCol>

                <!-- Footer: Última Operación -->
                <VCol cols="12">
                  <VCard variant="tonal" color="primary" class="pa-4 rounded-lg d-flex align-center justify-space-between">
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-history" size="20" />
                      <span class="font-weight-bold">Detalle de la última operación:</span>
                    </div>
                    <div v-if="productStatsData.last_sale" class="d-flex gap-8">
                      <div class="d-flex flex-column">
                        <span class="text-super-xs uppercase font-weight-black opacity-70">Fecha</span>
                        <span class="text-subtitle-2 font-weight-black">{{ formatDateSimple(productStatsData.last_sale.date) }}</span>
                      </div>
                      <div class="d-flex flex-column">
                        <span class="text-super-xs uppercase font-weight-black opacity-70">Precio</span>
                        <span class="text-subtitle-2 font-weight-black">{{ formatPrice(productStatsData.last_sale.price) }}</span>
                      </div>
                      <div class="d-flex flex-column">
                        <span class="text-super-xs uppercase font-weight-black opacity-70">Cantidad</span>
                        <span class="text-subtitle-2 font-weight-black text-right">{{ productStatsData.last_sale.quantity }} Und</span>
                      </div>
                    </div>
                    <span v-else class="text-subtitle-2 italic opacity-70">Sin operaciones recientes</span>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>

            <VCardText v-else class="pa-16 text-center text-medium-emphasis">
              <VIcon icon="tabler-search" size="64" class="mb-4 opacity-20" />
              <div class="text-h6">Analítica de SKU Específico</div>
              <p>Busca y selecciona un producto arriba para cargar sus estadísticas detalladas</p>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </VContainer>
</template>

<style scoped>
/* Aislamiento del Dashboard: Los ajustes solo afectan a .bi-report-grid */
.bi-report-grid :deep(.v-row) {
  margin: -6px !important;
}

.bi-report-grid :deep(.v-col) {
  padding: 6px !important;
}

/* Espaciado vertical entre filas exacto */
.bi-report-grid :deep(.v-row + .v-row) {
  margin-top: 6px !important;
}

.bg-light-primary { background-color: rgba(115, 103, 240, 0.15); }

/* Ajuste de padding horizontal interno para tablas y listas */
:deep(.v-table .v-table__wrapper > table > thead > tr > th),
:deep(.v-table .v-table__wrapper > table > tbody > tr > td) {
  padding-inline: 6px !important;
}

:deep(.v-card-text.pa-4) {
  padding-inline: 6px !important;
}
.bg-light-success { background-color: rgba(40, 199, 111, 0.15); }
.bg-light-error { background-color: rgba(234, 84, 85, 0.15); }
.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}
</style>
