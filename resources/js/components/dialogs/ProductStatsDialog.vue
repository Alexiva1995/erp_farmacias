<script setup>
import { computed, ref, watch } from "vue";
import axios from "@/plugins/axios";
import { formatPrice, formatDateSimple } from "@/utils/formatters";
import VueApexCharts from "vue3-apexcharts";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue"]);

const { xs } = useDisplay();

const loading = ref(false);
const stats = ref(null);

const chartOptions = computed(() => ({
  chart: {
    type: "area",
    toolbar: { show: false },
    zoom: { enabled: false },
    sparkline: { enabled: false },
    animations: {
      enabled: true,
      easing: "easeinout",
      speed: 800,
    },
  },
  dataLabels: { enabled: false },
  stroke: { curve: "smooth", width: 3 },
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.7,
      opacityTo: 0.1,
      stops: [0, 90, 100],
    },
  },
  xaxis: {
    categories: stats.value?.trend_chart?.labels || [],
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: {
      style: {
        colors: "rgba(var(--v-theme-on-surface), 0.45)",
        fontSize: "12px",
        fontWeight: 600,
      },
    },
  },
  yaxis: {
    labels: {
      style: {
        colors: "rgba(var(--v-theme-on-surface), 0.45)",
        fontSize: "12px",
        fontWeight: 600,
      },
    },
  },
  grid: {
    borderColor: "rgba(var(--v-border-color), 0.1)",
    strokeDashArray: 5,
    xaxis: { lines: { show: true } },
  },
  colors: ["#7367f0"],
  tooltip: {
    theme: "dark",
    x: { show: true },
  },
}));

const series = computed(() => stats.value?.trend_chart?.series || [{ name: "Ventas", data: [] }]);

const fetchStats = async () => {
  if (!props.product?.id) return;
  loading.value = true;
  try {
    const response = await axios.get(`/products/${props.product.id}/stats`);
    stats.value = response.data.data;
  } catch (error) {
    console.error("Error al cargar estadísticas:", error);
  } finally {
    loading.value = false;
  }
};

watch(
  () => props.modelValue,
  (val) => {
    if (val) fetchStats();
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="1100px"
    @update:model-value="closeDialog"
    persistent
    :fullscreen="xs"
  >
    <VCard v-if="props.product" class="stats-dialog-card rounded-xl border-0 overflow-hidden bg-surface">
      <!-- Cabecera Premium -->
      <div class="header-gradient pa-5 d-flex align-center shadow-lg">
        <VAvatar color="white" variant="flat" size="48" class="me-4 elevation-2">
          <VIcon icon="tabler-chart-bar" size="28" color="primary" />
        </VAvatar>
        <div class="d-flex flex-column">
          <h2 class="text-h5 font-weight-black text-white leading-tight mb-0 uppercase tracking-wide">
            Analítica de Producto
          </h2>
          <span class="text-white opacity-75 font-weight-bold text-xs uppercase tracking-tighter">
            {{ props.product.name }} • {{ props.product.laboratory?.name || 'SIN LABORATORIO' }}
          </span>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="white"
          size="small"
          class="rounded-lg"
          @click="closeDialog"
        />
      </div>

      <VCardText class="pa-6 bg-light">
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-6 rounded" height="6" />

        <template v-if="stats">
          <VRow>
            <!-- KPI Cards -->
            <VCol cols="12" md="4">
              <VCard variant="flat" class="kpi-card pa-5 rounded-xl border-dashed-2 h-100 position-relative overflow-hidden">
                <div class="d-flex align-center justify-space-between mb-4">
                  <span class="text-xs font-weight-black text-disabled uppercase letter-spacing-1">Unidades Totales</span>
                  <VIcon icon="tabler-box" size="20" color="primary" />
                </div>
                <div class="d-flex align-end gap-2">
                  <h3 class="text-h3 font-weight-black text-primary leading-none">{{ stats.total_units_sold }}</h3>
                  <span class="text-caption font-weight-bold text-disabled pb-1">UNDS</span>
                </div>
                <div class="mt-4 text-xs font-weight-bold text-medium-emphasis">
                  Acumulado histórico de ventas
                </div>
                <div class="card-glow primary" />
              </VCard>
            </VCol>

            <VCol cols="12" md="4">
              <VCard variant="flat" class="kpi-card pa-5 rounded-xl border-dashed-2 h-100 position-relative overflow-hidden">
                <div class="d-flex align-center justify-space-between mb-4">
                  <span class="text-xs font-weight-black text-disabled uppercase letter-spacing-1">Promedio Mensual</span>
                  <VIcon icon="tabler-trending-up" size="20" color="success" />
                </div>
                <div class="d-flex align-end gap-2">
                  <h3 class="text-h3 font-weight-black text-success leading-none">{{ stats.monthly_average }}</h3>
                  <span class="text-caption font-weight-bold text-disabled pb-1">/MES</span>
                </div>
                <div class="mt-4 text-xs font-weight-bold text-medium-emphasis">
                  Media de los últimos 12 meses
                </div>
                <div class="card-glow success" />
              </VCard>
            </VCol>

            <VCol cols="12" md="4">
              <VCard variant="flat" class="kpi-card pa-5 rounded-xl border-dashed-2 h-100 position-relative overflow-hidden">
                <div class="d-flex align-center justify-space-between mb-4">
                  <span class="text-xs font-weight-black text-disabled uppercase letter-spacing-1">Última Venta</span>
                  <VIcon icon="tabler-clock" size="20" color="info" />
                </div>
                <template v-if="stats.last_sale">
                  <div class="d-flex flex-column gap-1">
                    <h3 class="text-h5 font-weight-black text-info leading-tight">
                      {{ formatDateSimple(stats.last_sale.date) }}
                    </h3>
                    <div class="d-flex align-center gap-2 mt-1">
                      <VChip size="x-small" color="info" variant="flat" class="font-weight-black">
                        {{ formatPrice(stats.last_sale.price) }}
                      </VChip>
                      <span class="text-caption text-disabled font-weight-bold">
                        x {{ stats.last_sale.quantity }} Unid.
                      </span>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <span class="text-medium-emphasis font-weight-bold italic">Sin ventas registradas</span>
                </template>
                <div class="card-glow info" />
              </VCard>
            </VCol>

            <!-- Chart Section -->
            <VCol cols="12">
              <VCard variant="flat" class="pa-6 rounded-xl border shadow-sm bg-surface mt-4">
                <div class="d-flex align-center justify-space-between mb-6">
                  <div class="d-flex align-center gap-3">
                    <div class="header-indicator primary shadow-sm" />
                    <span class="text-subtitle-1 font-weight-black text-high-emphasis uppercase letter-spacing-1">
                      Tendencia de Ventas (Últimos 6 Meses)
                    </span>
                  </div>
                  <div class="d-flex gap-2">
                    <VChip size="x-small" label color="primary" variant="tonal" class="font-weight-black">UNIDADES</VChip>
                  </div>
                </div>
                
                <div class="mx-n4">
                  <VueApexCharts
                    type="area"
                    height="320"
                    :options="chartOptions"
                    :series="series"
                  />
                </div>
              </VCard>
            </VCol>
          </VRow>
        </template>
        
        <!-- Loading State Skeleton -->
        <VRow v-else>
          <VCol cols="12" md="4" v-for="i in 3" :key="i">
            <VSkeletonLoader type="article" class="rounded-xl border h-100" />
          </VCol>
          <VCol cols="12">
            <VSkeletonLoader type="image" class="rounded-xl border" height="300" />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.stats-dialog-card {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
}

.header-gradient {
  background: linear-gradient(135deg, #7367f0 0%, #3f2b96 100%);
}

.bg-light {
  background-color: #f8f9fa !important;
}

.kpi-card {
  background-color: white !important;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.kpi-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.05) !important;
}

.border-dashed-2 {
  border: 2px dashed rgba(var(--v-border-color), 0.2) !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 20px;
  border-radius: 4px;
}

.header-indicator.primary { background-color: #7367f0; }

.card-glow {
  position: absolute;
  inline-size: 150px;
  block-size: 150px;
  filter: blur(80px);
  opacity: 0.1;
  pointer-events: none;
  inset-block-end: -50px;
  inset-inline-end: -50px;
  border-radius: 50%;
}

.card-glow.primary { background-color: #7367f0; }
.card-glow.success { background-color: #28c76f; }
.card-glow.info { background-color: #00cfe8; }

.uppercase { text-transform: uppercase; }
.letter-spacing-1 { letter-spacing: 0.05em; }
.tracking-wide { letter-spacing: 0.1em; }
.tracking-tight { letter-spacing: -0.05em; }
.tracking-tighter { letter-spacing: -0.02em; }
</style>
