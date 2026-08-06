<script setup>
// Componente: Analítica Individual de Producto (búsqueda + gráficos + KPIs)
import { ref, computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from '@/plugins/axios';
import { formatPrice, formatDateSimple } from '@/utils/formatters';

const props = defineProps({
  groups: { type: Array, default: () => [] },
});

// --- Estado local ---
const productSearchItems   = ref([]);
const productSearchLoading = ref(false);
const selectedProduct      = ref(null);
const productStatsData     = ref(null);
const loadingStats         = ref(false);

// --- Búsqueda de productos con debounce implícito por min-length ---
const searchProducts = async (query) => {
  if (!query || query.length < 1) return;
  productSearchLoading.value = true;
  const isIdSearch = /^\d+$/.test(query.trim());
  const params = isIdSearch ? { id: query.trim() } : { q: query, itemsPerPage: 10 };
  try {
    const { data } = await axios.get('/products', { params });
    productSearchItems.value = data.data ?? [];
  } catch {
    productSearchItems.value = [];
  } finally {
    productSearchLoading.value = false;
  }
};

// --- Carga de stats al seleccionar un producto ---
const loadProductStats = async (productId) => {
  if (!productId) {
    productStatsData.value = null;
    return;
  }
  loadingStats.value = true;
  try {
    const { data } = await axios.get(`/products/${productId}/stats`);
    productStatsData.value = data.data ?? null;
  } catch {
    productStatsData.value = null;
  } finally {
    loadingStats.value = false;
  }
};

watch(selectedProduct, (id) => loadProductStats(id));

// --- Opciones de gráficos ---
const individualChartOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: {
    categories: productStatsData.value?.trend_chart?.labels ?? [],
    labels: { style: { fontSize: '10px' } },
  },
  yaxis: { labels: { style: { fontSize: '10px' } } },
  grid: { strokeDashArray: 5 },
  colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8', '#4b4b4b'],
  tooltip: { theme: 'dark' },
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

const individualSeries  = computed(() => productStatsData.value?.trend_chart?.series ?? []);
const marketShareSeries = computed(() => [productStatsData.value?.market_share ?? 0]);
</script>

<template>
  <VCard border class="rounded-lg overflow-hidden shadow-md">
    <!-- Header con buscador -->
    <header class="pa-4 bg-primary d-flex align-center flex-wrap gap-4">
      <div class="d-flex align-center text-white">
        <VIcon icon="tabler-chart-dots" class="me-2" color="white" />
        <span class="text-h6 font-weight-bold text-white">Analítica Individual de Producto</span>
      </div>
      <VSpacer />
      <div style="width: 400px; max-width: 100%;">
        <VAutocomplete
          v-model="selectedProduct"
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
          <template #item="{ props: itemProps, item }">
            <VListItem v-bind="itemProps">
              <template #prepend>
                <VChip size="x-small" color="primary" label class="me-2">ID: {{ item.raw.id }}</VChip>
              </template>
            </VListItem>
          </template>
        </VAutocomplete>
      </div>
    </header>

    <!-- Estado: cargando (sin datos aún seleccionados o tras seleccionar) -->
    <VCardText v-if="loadingStats" class="pa-6">
      <VProgressLinear indeterminate color="primary" class="mb-6" />
      <VRow>
        <VCol cols="12" md="4">
          <VSkeleton type="card" height="280" />
        </VCol>
        <VCol cols="12" md="8">
          <VSkeleton type="card" height="280" />
        </VCol>
      </VRow>
    </VCardText>

    <!-- Estado: datos cargados -->
    <VCardText v-else-if="productStatsData" class="pa-6">
      <VRow>
        <!-- Market Share -->
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

        <!-- Tendencia histórica -->
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

        <!-- Última operación -->
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

    <!-- Estado: sin selección -->
    <VCardText v-else class="pa-16 text-center text-medium-emphasis">
      <VIcon icon="tabler-search" size="64" class="mb-4 opacity-20" />
      <div class="text-h6">Analítica de SKU Específico</div>
      <p>Busca y selecciona un producto arriba para cargar sus estadísticas detalladas</p>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs { font-size: 0.65rem !important; line-height: 1; }
.text-xs      { font-size: 0.75rem !important; }
</style>
