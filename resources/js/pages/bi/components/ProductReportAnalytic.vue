<script setup>
// Componente: Analítica Individual de Producto (Hero Bar + Diagnóstico Profundo)
import { ref, computed, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from '@/plugins/axios';
import { formatPrice, formatDateSimple } from '@/utils/formatters';
import { useBiThemeColors } from '@/composables/useBiThemeColors';

const props = defineProps({
  groups: { type: Array, default: () => [] },
  modelValue: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:modelValue']);

const { colors } = useBiThemeColors();

// --- Estado local ---
const productSearchItems   = ref([]);
const productSearchLoading = ref(false);
const selectedProduct      = ref(props.modelValue);
const productStatsData     = ref(null);
const loadingStats         = ref(false);

// Sincronización con prop externa
watch(() => props.modelValue, (newVal) => {
  if (newVal !== selectedProduct.value) {
    selectedProduct.value = newVal;
    if (newVal) {
      loadProductStats(newVal);
    }
  }
});

// --- Búsqueda de productos ---
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

watch(selectedProduct, (id) => {
  emit('update:modelValue', id);
  loadProductStats(id);
});

// --- Opciones de gráficos dinámicas con tema ---
const individualChartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: {
    categories: productStatsData.value?.trend_chart?.labels ?? [],
    labels: { style: { fontSize: '11px', colors: colors.value.onSurface } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: { style: { fontSize: '11px', colors: colors.value.onSurface } },
  },
  grid: { strokeDashArray: 4, borderColor: 'rgba(var(--v-theme-on-surface), 0.12)' },
  colors: [colors.value.primary, colors.value.success, colors.value.warning],
  tooltip: { theme: colors.value.isDark ? 'dark' : 'light' },
}));

const marketShareOptions = computed(() => ({
  chart: {
    type: 'radialBar',
    fontFamily: 'inherit',
    background: 'transparent',
  },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: { size: '68%' },
      dataLabels: {
        name: { fontSize: '12px', color: colors.value.onSurface, offsetY: -8 },
        value: { offsetY: 6, fontSize: '20px', fontWeight: 800, color: colors.value.onSurface, formatter: val => `${val}%` },
      },
    },
  },
  stroke: { dashArray: 4 },
  labels: ['Preferencia'],
  colors: [colors.value.primary],
}));

const individualSeries  = computed(() => productStatsData.value?.trend_chart?.series ?? []);
const marketShareSeries = computed(() => [productStatsData.value?.market_share ?? 0]);

defineExpose({
  selectProduct: (id) => {
    selectedProduct.value = id;
    loadProductStats(id);
  },
});
</script>

<template>
  <VCard border class="rounded-lg overflow-hidden shadow-sm analytic-card" id="analytic-sku-card">
    <!-- Hero Bar interactivo con buscador corporativo -->
    <div class="pa-4 pa-sm-5 bg-surface border-b d-flex align-center flex-wrap gap-4">
      <div class="d-flex align-center gap-3">
        <VAvatar size="40" color="primary" variant="tonal" class="rounded-lg">
          <VIcon icon="tabler-chart-dots" size="22" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis">Analítica Individual por SKU</div>
          <div class="text-caption text-medium-emphasis">Explora dominancia competitiva, rotación y trazabilidad de cualquier ítem</div>
        </div>
      </div>
      <VSpacer />
      <div style="width: 440px; max-width: 100%;">
        <VAutocomplete
          v-model="selectedProduct"
          :items="productSearchItems"
          :loading="productSearchLoading"
          item-title="name"
          item-value="id"
          placeholder="Buscar producto por nombre o ID..."
          density="compact"
          variant="outlined"
          hide-details
          clearable
          no-filter
          prepend-inner-icon="tabler-search"
          class="rounded-lg"
          @update:search="searchProducts"
        >
          <template #item="{ props: itemProps, item }">
            <VListItem v-bind="itemProps">
              <template #prepend>
                <VChip size="x-small" color="primary" label class="me-2 font-weight-bold">ID: {{ item.raw.id }}</VChip>
              </template>
              <template #subtitle>
                <span class="text-caption text-medium-emphasis">{{ item.raw.active_ingredient || 'Sin principio activo' }}</span>
              </template>
            </VListItem>
          </template>
        </VAutocomplete>
      </div>
    </div>

    <!-- Estado: cargando datos de SKU -->
    <VCardText v-if="loadingStats" class="pa-6">
      <VProgressLinear indeterminate color="primary" class="mb-6 rounded" />
      <VRow>
        <VCol cols="12" md="4">
          <VSkeleton type="card" height="260" />
        </VCol>
        <VCol cols="12" md="8">
          <VSkeleton type="card" height="260" />
        </VCol>
      </VRow>
    </VCardText>

    <!-- Estado: datos cargados -->
    <VCardText v-else-if="productStatsData" class="pa-6">
      <VRow>
        <!-- Market Share -->
        <VCol cols="12" md="4">
          <VCard variant="outlined" class="pa-4 rounded-lg d-flex flex-column align-center justify-center h-100 bg-surface">
            <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase mb-2">Dominancia del SKU</div>
            <VueApexCharts type="radialBar" height="220" :options="marketShareOptions" :series="marketShareSeries" />
            <div class="text-center mt-1">
              <div class="text-h4 font-weight-black text-primary">{{ productStatsData.market_share }}%</div>
              <div class="text-caption text-medium-emphasis">Participación en su categoría competitiva</div>
            </div>
          </VCard>
        </VCol>

        <!-- Tendencia histórica -->
        <VCol cols="12" md="8">
          <VCard variant="outlined" class="pa-4 rounded-lg h-100 bg-surface">
            <div class="d-flex align-center justify-space-between mb-3">
              <span class="text-caption font-weight-bold text-high-emphasis text-uppercase">Tendencia Histórica de Ventas</span>
              <div class="d-flex gap-4">
                <div class="text-right">
                  <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Ventas Totales</div>
                  <div class="text-subtitle-1 font-weight-black text-primary">{{ productStatsData.total_units_sold }} <span class="text-caption font-weight-normal text-medium-emphasis">Unds</span></div>
                </div>
                <div class="text-right">
                  <div class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Promedio Mensual</div>
                  <div class="text-subtitle-1 font-weight-black text-success">{{ productStatsData.monthly_average }} <span class="text-caption font-weight-normal text-medium-emphasis">/ mes</span></div>
                </div>
              </div>
            </div>
            <VueApexCharts type="area" height="220" :options="individualChartOptions" :series="individualSeries" />
          </VCard>
        </VCol>

        <!-- Última operación -->
        <VCol cols="12">
          <VCard variant="outlined" class="pa-4 rounded-lg d-flex align-center justify-space-between flex-wrap gap-3 bg-surface">
            <div class="d-flex align-center gap-2">
              <VAvatar size="30" color="primary" variant="tonal" rounded>
                <VIcon icon="tabler-history" size="18" />
              </VAvatar>
              <span class="text-subtitle-2 font-weight-bold">Detalle de la última transacción registrada:</span>
            </div>
            <div v-if="productStatsData.last_sale" class="d-flex align-center justify-space-between flex-grow-1 flex-sm-grow-0 gap-6">
              <div class="d-flex flex-column align-start align-sm-end">
                <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Fecha</span>
                <span class="text-subtitle-2 font-weight-bold">{{ formatDateSimple(productStatsData.last_sale.date) }}</span>
              </div>
              <div class="d-flex flex-column align-center align-sm-end">
                <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Precio Unitario</span>
                <span class="text-subtitle-2 font-weight-bold text-success">{{ formatPrice(productStatsData.last_sale.price) }}</span>
              </div>
              <div class="d-flex flex-column align-end">
                <span class="text-caption text-medium-emphasis text-uppercase font-weight-bold">Cantidad</span>
                <span class="text-subtitle-2 font-weight-bold">{{ productStatsData.last_sale.quantity }} Unds</span>
              </div>
            </div>
            <span v-else class="text-subtitle-2 italic text-medium-emphasis">Sin operaciones registradas</span>
          </VCard>
        </VCol>
      </VRow>
    </VCardText>

    <!-- Estado: sin selección (Guía interactiva) -->
    <VCardText v-else class="pa-10 text-center text-medium-emphasis bg-surface">
      <VIcon icon="tabler-scan" size="48" class="mb-3 text-primary opacity-60" />
      <div class="text-subtitle-1 font-weight-bold text-high-emphasis">Búsqueda Rápida de SKU Específico</div>
      <p class="text-caption text-medium-emphasis" style="max-width: 480px; margin: 0 auto;">
        Usa el buscador superior o haz clic en el ícono de lupa en cualquiera de los rankings TOP para inspeccionar un producto: obtendrás de inmediato su índice de dominancia en categoría, curva de ventas e historial de última salida.
      </p>
    </VCardText>
  </VCard>
</template>
