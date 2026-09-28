<script setup>
// Vista principal de Reporte de Productos BI
// Orquestador: solo estado global, fetch de datos y composición de sub-componentes.
import { computed, onMounted, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import axios from '@/plugins/axios';
import { toast } from '@/plugins/sweetalert';
import AppFilterBase from '@/components/AppFilterBase.vue';
import ProductReportKpiCards from './components/ProductReportKpiCards.vue';
import ProductReportRankings from './components/ProductReportRankings.vue';
import ProductReportAbc from './components/ProductReportAbc.vue';
import ProductReportCrossSelling from './components/ProductReportCrossSelling.vue';
import ProductReportTrendChart from './components/ProductReportTrendChart.vue';
import ProductReportLabChart from './components/ProductReportLabChart.vue';
import ProductReportAnalytic from './components/ProductReportAnalytic.vue';
import { generateBiProductExecutivePdf } from '@/utils/pdfBiProductReportGenerator';

// ─────────────────────────────────────────────
// Estado de la UI
// ─────────────────────────────────────────────
const loading      = ref(false);
const errorMessage = ref('');

const defaultDashboardData = {
  quadrant1: { top_volume: [], top_revenue: [], lab_ranking: [], pareto: { percent: 0 } },
  quadrant2: { abc: [], cross_selling: [] },
  quadrant4: { out_of_stock: 0, critical_stock: 0, avg_inventory_days: 0 },
};

const dashboardData = ref(JSON.parse(JSON.stringify(defaultDashboardData)));
const trendData     = ref([]);
const loadingTrends = ref(false);
const selectedSkuId = ref(null);

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
const volumePage                  = ref(1);
const revenuePage                 = ref(1);
const loadingVolume               = ref(false);
const loadingRevenue              = ref(false);
const crossSellingPage            = ref(1);
const loadingCrossSelling         = ref(false);
const selectedCrossSellingProductId   = ref(null);
const selectedCrossSellingProductName = ref('');
const selectedTrendGroup          = ref(null);

// ─────────────────────────────────────────────
// Parámetros comunes — computed
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
  (Array.isArray(data) ? data : (data ? Object.values(data) : []));

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
      axios.get('/groups/consult-all').catch(() => axios.get('/groups')).catch(() => ({ data: { data: [] } })),
    ]);
    laboratories.value = Array.isArray(labRes.data) ? labRes.data : (Array.isArray(labRes.data?.data) ? labRes.data.data : []);
    const grpData = grpRes.data;
    groups.value = Array.isArray(grpData) ? grpData : (Array.isArray(grpData?.data) ? grpData.data : []);
  } catch {
    // Catálogos auxiliares opcionales
  }
};

const fetchDashboard = async () => {
  loading.value      = true;
  errorMessage.value = '';
  try {
    const { data } = await axios.get('/bi/products/dashboard', { params: baseParams.value });
    const payload = (data?.data && (data.data.quadrant1 || data.data.quadrant4)) ? data.data : data;
    if (payload?.quadrant1 || payload?.quadrant4) {
      dashboardData.value = payload;
    }
  } catch (err) {
    errorMessage.value = 'Error al cargar el dashboard. Verifica tu conexión con el servidor.';
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
    const params = {
      ...baseParams.value,
      page,
      product_id: selectedCrossSellingProductId.value || undefined,
    };
    const { data } = await axios.get('/bi/products/cross-selling', { params });
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

const handleSelectCrossSellingProduct = (item) => {
  if (!item) {
    selectedCrossSellingProductId.value = null;
    selectedCrossSellingProductName.value = '';
  } else if (typeof item === 'object') {
    selectedCrossSellingProductId.value = item.id;
    selectedCrossSellingProductName.value = item.name || `ID #${item.id}`;
  } else {
    selectedCrossSellingProductId.value = item;
    selectedCrossSellingProductName.value = `ID #${item}`;
  }
  crossSellingPage.value = 1;
  fetchCrossSelling(1);
};

// Exportación ejecutiva a PDF
const handleExport = () => {
  try {
    generateBiProductExecutivePdf(dashboardData.value, baseParams.value);
    toast.success('Reporte ejecutivo generado correctamente.');
  } catch (err) {
    toast.error('Ocurrió un error al generar el reporte en PDF.');
  }
};

// Drill-down: Selección de SKU desde los rankings
const handleInspectProduct = (productId) => {
  if (!productId) return;
  selectedSkuId.value = productId;
  const targetElement = document.getElementById('analytic-sku-card');
  if (targetElement) {
    targetElement.scrollIntoView({ behavior: 'smooth' });
  }
};

const resetFilters = () => {
  search.value             = '';
  startDate.value          = defaultStartDate();
  endDate.value            = new Date().toISOString().split('T')[0];
  selectedLaboratory.value = null;
  selectedGroup.value      = null;
};

// ─────────────────────────────────────────────
// Debounce en búsqueda
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
watch(search, debouncedFetchAll);

watch([startDate, endDate, selectedLaboratory, selectedGroup], () => {
  crossSellingPage.value = 1;
  volumePage.value       = 1;
  revenuePage.value      = 1;
  fetchDashboard();
  fetchTrends();
  fetchCrossSelling(1);
});

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
</script>

<template>
  <VContainer fluid class="pa-0">
    <!-- Banner de error global -->
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

    <!-- Barra de Filtros y Acciones -->
    <AppFilterBase
      v-model:search="search"
      placeholder="Buscar producto por nombre o principio activo..."
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
          <VTooltip activator="parent" location="top">Sincronizar Datos</VTooltip>
        </VBtn>
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-pill"
          :disabled="loading"
          @click="handleExport"
        >
          <VIcon icon="tabler-file-export" />
          <VTooltip activator="parent" location="top">Generar Reporte Ejecutivo PDF</VTooltip>
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
            label="Grupo Terapéutico"
            clearable
            density="compact"
            hide-details
            prepend-inner-icon="tabler-tags"
          />
        </VCol>
      </template>
    </AppFilterBase>

    <!-- Fila 1: KPIs de Abastecimiento y Rendimiento -->
    <ProductReportKpiCards
      :quadrant4="dashboardData.quadrant4"
      :pareto-percent="paretoPercent"
      :loading="loading"
    />

    <!-- Fila 2: Rankings TOP Volumen y TOP Ingresos con Drill-Down a SKU -->
    <ProductReportRankings
      :top-volume="safeTopVolume"
      :volume-page="volumePage"
      :loading-volume="loadingVolume"
      :top-revenue="safeTopRevenue"
      :revenue-page="revenuePage"
      :loading-revenue="loadingRevenue"
      class="mb-4"
      @page-volume="fetchRankings('total_sold', $event)"
      @page-revenue="fetchRankings('total_revenue', $event)"
      @inspect-product="handleInspectProduct"
    />

    <!-- Fila 3: Matriz ABC y Venta Cruzada (Market Basket) -->
    <VRow class="match-height mb-4">
      <VCol cols="12" md="4">
        <ProductReportAbc :abc-data="safeAbcData" :loading="loading" />
      </VCol>
      <VCol cols="12" md="8">
        <ProductReportCrossSelling
          :cross-selling="safeCrossSelling"
          :page="crossSellingPage"
          :loading="loadingCrossSelling"
          :selected-product-id="selectedCrossSellingProductId"
          :selected-product-name="selectedCrossSellingProductName"
          @page-change="fetchCrossSelling($event)"
          @select-product="handleSelectCrossSellingProduct"
        />
      </VCol>
    </VRow>

    <!-- Fila 4: Tendencias Semanales Compras vs Ventas -->
    <VRow class="mb-4">
      <VCol cols="12">
        <ProductReportTrendChart
          :trend-data="safeTrendData"
          :loading="loadingTrends"
          :groups="groups"
          v-model:selected-group="selectedTrendGroup"
        />
      </VCol>
    </VRow>

    <!-- Fila 5: Rentabilidad por Laboratorio / Fabricante -->
    <VRow class="mb-4">
      <VCol cols="12">
        <ProductReportLabChart
          :lab-data="safeLabData"
          :loading="loading"
        />
      </VCol>
    </VRow>

    <!-- Fila 6: Analítica Individual Profunda por SKU -->
    <VRow>
      <VCol cols="12">
        <ProductReportAnalytic
          v-model="selectedSkuId"
          :groups="groups"
        />
      </VCol>
    </VRow>
  </VContainer>
</template>
