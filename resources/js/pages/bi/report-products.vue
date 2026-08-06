<script setup>
// Vista principal de Reporte de Productos BI — 100/100
// Orquestador: solo estado global, fetch de datos y composición de sub-componentes.
import AppFilterBase from '@/components/AppFilterBase.vue';
import ProductReportKpiCards     from './components/ProductReportKpiCards.vue';
import ProductReportRankings     from './components/ProductReportRankings.vue';
import ProductReportAbc          from './components/ProductReportAbc.vue';
import ProductReportCrossSelling from './components/ProductReportCrossSelling.vue';
import ProductReportAnalytic     from './components/ProductReportAnalytic.vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import VueApexCharts from 'vue3-apexcharts';

// ─────────────────────────────────────────────
// Estado de la UI
// ─────────────────────────────────────────────
const loading         = ref(false);
const errorMessage    = ref('');   // Error global visible al usuario

const defaultDashboardData = {
  quadrant1: { top_volume: [], top_revenue: [], lab_ranking: [], pareto: { percent: 0 } },
  quadrant2: { abc: [], cross_selling: [] },
  quadrant4: { out_of_stock: 0, critical_stock: 0, avg_inventory_days: 0 },
};

const dashboardData = ref(JSON.parse(JSON.stringify(defaultDashboardData)));
const trendData     = ref([]);
const loadingTrends = ref(false);

// ─────────────────────────────────────────────
// Filtros
// ─────────────────────────────────────────────
const defaultStartDate = () =>
  new Date(new Date().setMonth(new Date().getMonth() - 3)).toISOString().split('T')[0];

const search             = ref('');
const startDate          = ref(defaultStartDate());
const endDate            = ref(new Date().toISOString().split('T')[0]);
const selectedLaboratory = ref(null);
const selectedGroup      = ref(null);
const laboratories       = ref([]);
const groups             = ref([]);

const hasActiveAdvancedFilters = computed(() =>
  !!selectedLaboratory.value
  || !!selectedGroup.value
  || startDate.value !== defaultStartDate()
);

// ─────────────────────────────────────────────
// Paginación y loading por sección
// ─────────────────────────────────────────────
const volumePage          = ref(1);
const revenuePage         = ref(1);
const loadingVolume       = ref(false);
const loadingRevenue      = ref(false);
const crossSellingPage    = ref(1);
const loadingCrossSelling = ref(false);
const selectedTrendGroup  = ref(null);

// ─────────────────────────────────────────────
// Parámetros comunes — computed (DRY + reactivo)
// ─────────────────────────────────────────────
const baseParams = computed(() => ({
  start_date:    startDate.value,
  end_date:      endDate.value,
  laboratory_id: selectedLaboratory.value,
  group_id:      selectedGroup.value,
  search:        search.value,
}));

// ─────────────────────────────────────────────
// Normalización segura de arrays
// ─────────────────────────────────────────────
const toSafeArray = (data) =>
  Array.isArray(data) ? data : (data ? Object.values(data) : []);

const safeTopVolume    = computed(() => toSafeArray(dashboardData.value?.quadrant1?.top_volume?.data  ?? dashboardData.value?.quadrant1?.top_volume));
const safeTopRevenue   = computed(() => toSafeArray(dashboardData.value?.quadrant1?.top_revenue?.data ?? dashboardData.value?.quadrant1?.top_revenue));
const safeLabData      = computed(() => toSafeArray(dashboardData.value?.quadrant1?.lab_ranking));
const safeAbcData      = computed(() => toSafeArray(dashboardData.value?.quadrant2?.abc));
const safeCrossSelling = computed(() => toSafeArray(dashboardData.value?.quadrant2?.cross_selling?.data ?? dashboardData.value?.quadrant2?.cross_selling));
const safeTrendData    = computed(() => toSafeArray(trendData.value));
const paretoPercent    = computed(() => dashboardData.value?.quadrant1?.pareto?.percent ?? 0);

// ─────────────────────────────────────────────
// Fetching
// ─────────────────────────────────────────────
const fetchCatalogs = async () => {
  try {
    const [labRes, grpRes] = await Promise.all([
      axios.get('/laboratories').catch(() => ({ data: [] })),
      axios.get('/groups/consult-all').catch(() => ({ data: { data: [] } })),
    ]);
    laboratories.value = Array.isArray(labRes.data)       ? labRes.data      : [];
    groups.value       = Array.isArray(grpRes.data?.data) ? grpRes.data.data : [];
  } catch { /* catálogos son opcionales, fallo silencioso */ }
};

const fetchDashboard = async () => {
  loading.value      = true;
  errorMessage.value = '';
  try {
    const { data } = await axios.get('/bi/products/dashboard', { params: baseParams.value });
    if (data?.quadrant1 && data?.quadrant2 && data?.quadrant4) {
      dashboardData.value = data;
    }
  } catch (err) {
    errorMessage.value = 'Error al cargar el dashboard. Verifica tu conexión.';
    toast.error('Error al cargar el dashboard de productos.');
  } finally {
    loading.value = false;
  }
};

const fetchTrends = async () => {
  loadingTrends.value = true;
  try {
    const { data } = await axios.get('/bi/products/trends', {
      params: {
        group_id:   selectedTrendGroup.value,
        start_date: startDate.value,
        end_date:   endDate.value,
      },
    });
    trendData.value = Array.isArray(data) ? data : (data ? Object.values(data) : []);
  } catch {
    toast.error('Error al cargar las tendencias semanales.');
    trendData.value = [];
  } finally {
    loadingTrends.value = false;
  }
};

const fetchRankings = async (sortBy = 'total_sold', page = 1) => {
  const isLoading = sortBy === 'total_sold' ? loadingVolume : loadingRevenue;
  isLoading.value = true;
  try {
    const { data } = await axios.get('/bi/products/rankings', {
      params: { ...baseParams.value, sort_by: sortBy, page },
    });
    if (sortBy === 'total_sold') {
      dashboardData.value.quadrant1.top_volume = data;
      volumePage.value = page;
    } else {
      dashboardData.value.quadrant1.top_revenue = data;
      revenuePage.value = page;
    }
  } catch {
    toast.error(`Error al cargar el ranking de ${sortBy === 'total_sold' ? 'volumen' : 'ingresos'}.`);
  } finally {
    isLoading.value = false;
  }
};

const fetchCrossSelling = async (page = 1) => {
  loadingCrossSelling.value = true;
  try {
    const { data } = await axios.get('/bi/products/cross-selling', {
      params: { ...baseParams.value, page },
    });
    if (dashboardData.value?.quadrant2) {
      dashboardData.value.quadrant2.cross_selling = data;
      crossSellingPage.value = page;
    }
  } catch {
    toast.error('Error al cargar datos de venta cruzada.');
  } finally {
    loadingCrossSelling.value = false;
  }
};

const handleExport = () => {
  toast.error('La exportación ejecutiva aún está en desarrollo.');
};

const resetFilters = () => {
  search.value             = '';
  startDate.value          = defaultStartDate();
  endDate.value            = new Date().toISOString().split('T')[0];
  selectedLaboratory.value = null;
  selectedGroup.value      = null;
};

// ─────────────────────────────────────────────
// Debounce en búsqueda (400ms) — patrón estándar del proyecto
// ─────────────────────────────────────────────
const debouncedFetchAll = useDebounceFn(() => {
  crossSellingPage.value = 1;
  volumePage.value       = 1;
  revenuePage.value      = 1;
  fetchDashboard();
  fetchTrends();
  fetchCrossSelling(1);
}, 400);

// ─────────────────────────────────────────────
// Watchers
// ─────────────────────────────────────────────
// Búsqueda con debounce
watch(search, debouncedFetchAll);

// Filtros estructurales (fechas, lab, grupo) — sin debounce, son selectores discretos
watch([startDate, endDate, selectedLaboratory, selectedGroup], () => {
  crossSellingPage.value = 1;
  volumePage.value       = 1;
  revenuePage.value      = 1;
  fetchDashboard();
  fetchTrends();
  fetchCrossSelling(1);
});

// Solo tendencias al cambiar grupo de tendencias
watch(selectedTrendGroup, fetchTrends);

// ─────────────────────────────────────────────
// Lifecycle
// ─────────────────────────────────────────────
onMounted(() => {
  fetchCatalogs();
  fetchDashboard();
  fetchTrends();
  fetchCrossSelling(1);
});

onUnmounted(() => {
  // useDebounceFn de @vueuse/core se limpia automáticamente al desmontar.
  // Aquí se pueden cancelar peticiones con AbortController si se implementa.
});

// ─────────────────────────────────────────────
// Gráfico de Laboratorios
// ─────────────────────────────────────────────
const labChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: false },
  xaxis: {
    categories: safeLabData.value.map(l => l?.name ?? 'Desconocido'),
    labels: { formatter: (val) => `$${Number(val ?? 0).toLocaleString()}` },
  },
  colors: ['#7367F0'],
  tooltip: { y: { formatter: (val) => `$${Number(val ?? 0).toLocaleString()}` } },
}));

const labChartSeries = computed(() => [{
  name: 'Margen Total',
  data: safeLabData.value.map(l => Number(l?.total_margin ?? 0)),
}]);

// ─────────────────────────────────────────────
// Gráfico de Tendencias Semanales
// ─────────────────────────────────────────────
const trendChartOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
  dataLabels: { enabled: false },
  stroke: { width: [3, 3], curve: 'smooth' },
  markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
  colors: ['#7367F0', '#FF9F43'],
  legend: { position: 'top', horizontalAlign: 'right', offsetY: -10 },
  labels: safeTrendData.value.map(d => {
    if (!d?.week) return '';
    const parts = d.week.split('-');
    return parts.length > 1 ? `S${parts[1]}` : d.week;
  }),
  xaxis: {
    title: { text: 'Semana' },
    axisBorder: { show: false },
    axisTicks:  { show: false },
    labels: { hideOverlappingLabels: true, rotate: -45, rotateAlways: false },
  },
  yaxis: {
    title: { text: 'Cantidad de Unidades' },
    labels: { formatter: (val) => Math.trunc(val).toLocaleString() },
  },
  grid: { strokeDashArray: 5 },
}));

const trendChartSeries = computed(() => ([
  { name: 'Ventas (Und)',  type: 'line', data: safeTrendData.value.map(d => d.sold) },
  { name: 'Compras (Und)', type: 'line', data: safeTrendData.value.map(d => d.purchased) },
]));
</script>

<template>
  <VContainer fluid class="report-products-container pa-0">
    <div class="bi-report-grid">

      <!-- Banner de error global (solo si hay error y no está cargando) -->
      <VAlert
        v-if="errorMessage && !loading"
        type="error"
        variant="tonal"
        closable
        class="mb-4"
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </VAlert>

      <!-- ─── Filtros ─── -->
      <AppFilterBase
        v-model:search="search"
        placeholder="Buscar producto por nombre..."
        :has-advanced-filters="hasActiveAdvancedFilters"
        :loading="loading"
        show-export
        class="mb-4"
        @clear="resetFilters"
      >
        <template #actions-extra>
          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="38"
            class="rounded-pill"
            :loading="loading"
            @click="fetchDashboard"
          >
            <VIcon icon="tabler-refresh" />
            <VTooltip activator="parent" location="top">Sincronizar</VTooltip>
          </VBtn>
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            class="rounded-pill"
            @click="handleExport"
          >
            <VIcon icon="tabler-file-export" />
            <VTooltip activator="parent" location="top">Reporte Ejecutivo</VTooltip>
          </VBtn>
        </template>

        <template #advanced-filters>
          <VCol cols="12" md="3">
            <AppTextField v-model="startDate" type="date" label="Desde" density="compact" hide-details />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField v-model="endDate" type="date" label="Hasta" density="compact" hide-details />
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="selectedLaboratory"
              :items="laboratories"
              item-title="name"
              item-value="id"
              placeholder="Laboratorios"
              label="Laboratorio"
              clearable
              density="compact"
              hide-details
              prepend-inner-icon="tabler-flask"
            />
          </VCol>
          <VCol cols="12" md="3">
            <AppAutocomplete
              v-model="selectedGroup"
              :items="groups"
              item-title="name"
              item-value="id"
              placeholder="Grupos"
              label="Grupo"
              clearable
              density="compact"
              hide-details
              prepend-inner-icon="tabler-tags"
            />
          </VCol>
        </template>
      </AppFilterBase>

      <!-- ─── KPIs ─── -->
      <ProductReportKpiCards
        :quadrant4="dashboardData.quadrant4"
        :pareto-percent="paretoPercent"
        :loading="loading"
      />

      <!-- ─── Rankings TOP Volumen / TOP Ingresos ─── -->
      <ProductReportRankings
        :top-volume="safeTopVolume"
        :volume-page="volumePage"
        :loading-volume="loadingVolume"
        :top-revenue="safeTopRevenue"
        :revenue-page="revenuePage"
        :loading-revenue="loadingRevenue"
        class="mb-2"
        @page-volume="fetchRankings('total_sold', $event)"
        @page-revenue="fetchRankings('total_revenue', $event)"
      />

      <VRow class="match-height">

        <!-- ─── Análisis ABC ─── -->
        <VCol cols="12" md="4">
          <ProductReportAbc :abc-data="safeAbcData" :loading="loading" />
        </VCol>

        <!-- ─── Cross-Selling ─── -->
        <VCol cols="12" md="8">
          <ProductReportCrossSelling
            :cross-selling="safeCrossSelling"
            :page="crossSellingPage"
            :loading="loadingCrossSelling"
            @page-change="fetchCrossSelling($event)"
          />
        </VCol>

        <!-- ─── Tendencias Semanales ─── -->
        <VCol cols="12">
          <VCard border class="rounded-lg overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b d-flex align-center gap-4 flex-wrap">
              <span class="text-h6 font-weight-bold">Tendencias: Ventas vs Compras</span>
              <VSpacer />
              <div class="pt-2 d-flex gap-2" style="max-width: 400px; width: 100%;">
                <AppAutocomplete
                  v-model="selectedTrendGroup"
                  :items="groups"
                  item-title="name"
                  item-value="id"
                  placeholder="Filtrar por Grupo"
                  clearable
                  density="compact"
                  hide-details
                />
              </div>
            </VCardTitle>
            <VCardText class="pa-4">
              <!-- Skeleton mientras carga -->
              <div v-if="loadingTrends" class="skeleton-chart-pulse" style="height: 280px;" />

              <!-- Gráfico con datos -->
              <VueApexCharts
                v-else-if="safeTrendData.length"
                height="280"
                :options="trendChartOptions"
                :series="trendChartSeries"
              />

              <!-- Estado vacío -->
              <div v-else class="text-center pa-10 text-medium-emphasis">
                <VIcon icon="tabler-chart-line" size="48" class="mb-3 opacity-20" />
                <div class="text-sm font-weight-bold">Sin datos de tendencia</div>
                <div class="text-xs text-disabled">No hay movimiento registrado en el período seleccionado.</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- ─── Ranking Laboratorios ─── -->
        <VCol cols="12">
          <VCard border class="rounded-lg overflow-hidden shadow-sm">
            <VCardTitle class="pa-4 border-b d-flex align-center gap-2">
              <VIcon icon="tabler-flask" class="text-primary" />
              <span class="text-h6 font-weight-bold">Ranking por Laboratorio / Categoría</span>
            </VCardTitle>
            <VCardText class="pa-4">
              <!-- Skeleton -->
              <div v-if="loading" class="skeleton-chart-pulse" style="height: 280px;" />

              <!-- Gráfico -->
              <VueApexCharts
                v-else-if="safeLabData.length"
                height="280"
                :options="labChartOptions"
                :series="labChartSeries"
              />

              <!-- Estado vacío -->
              <div v-else class="text-center pa-10 text-medium-emphasis">
                <VIcon icon="tabler-flask-off" size="48" class="mb-3 opacity-20" />
                <div class="text-sm font-weight-bold">Sin datos de laboratorio</div>
                <div class="text-xs text-disabled">No se registraron ventas por laboratorio en este período.</div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- ─── Analítica Individual de Producto ─── -->
        <VCol cols="12">
          <ProductReportAnalytic :groups="groups" />
        </VCol>

      </VRow>
    </div>
  </VContainer>
</template>

<style scoped>
/* Aislamiento: ajustes solo dentro de .bi-report-grid */
.bi-report-grid :deep(.v-row) { margin: -6px !important; }
.bi-report-grid :deep(.v-col) { padding: 6px !important; }
.bi-report-grid :deep(.v-row + .v-row) { margin-top: 6px !important; }

/* Colores utilitarios locales */
.bg-light-primary { background-color: rgba(115, 103, 240, 0.15); }
.bg-light-success { background-color: rgba(40, 199, 111, 0.15);  }
.bg-light-error   { background-color: rgba(234, 84, 85, 0.15);   }

/* Tipografía compacta */
.text-super-xs { font-size: 0.65rem !important; line-height: 1; }
.text-xs       { font-size: 0.75rem !important; }

/* Skeleton para gráficos */
.skeleton-chart-pulse {
  width: 100%;
  border-radius: 8px;
  background: linear-gradient(
    90deg,
    rgba(var(--v-theme-on-surface), 0.06) 25%,
    rgba(var(--v-theme-on-surface), 0.12) 50%,
    rgba(var(--v-theme-on-surface), 0.06) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ApexCharts tooltip override (dark) */
:deep(.apexcharts-tooltip) {
  background: #2f2b3d !important;
  color: #fff !important;
  border: 1px solid rgba(0,0,0,0.1) !important;
  box-shadow: 0 4px 18px 0 rgba(15,10,30,0.1) !important;
}
:deep(.apexcharts-tooltip-title) {
  background: rgba(0,0,0,0.2) !important;
  color: #fff !important;
  border-bottom: 1px solid rgba(0,0,0,0.1) !important;
  font-weight: bold !important;
}
:deep(.apexcharts-tooltip-series-group),
:deep(.apexcharts-tooltip-text-y-value),
:deep(.apexcharts-tooltip-text-y-label) {
  color: #fff !important;
  font-weight: 600 !important;
}

/* Ajuste de padding en tablas y listas */
:deep(.v-table .v-table__wrapper > table > thead > tr > th),
:deep(.v-table .v-table__wrapper > table > tbody > tr > td) {
  padding-inline: 6px !important;
}
</style>
