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
    toolbar: { show: true },
    zoom: { enabled: true },
    animations: {
      enabled: true,
      easing: "easeinout",
      speed: 800,
    },
  },
  dataLabels: { enabled: false },
  stroke: { curve: "smooth", width: 2 },
  fill: {
    type: "gradient",
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.5,
      opacityTo: 0.05,
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
        fontSize: "10px",
        fontWeight: 600,
      },
    },
  },
  yaxis: {
    labels: {
      style: {
        colors: "rgba(var(--v-theme-on-surface), 0.45)",
        fontSize: "10px",
        fontWeight: 600,
      },
    },
  },
  legend: {
    position: 'top',
    horizontalAlign: 'left',
    fontSize: '11px',
    fontWeight: 600,
    labels: {
      colors: "rgba(var(--v-theme-on-surface), 0.8)",
    },
    markers: { radius: 12 }
  },
  grid: {
    borderColor: "rgba(var(--v-border-color), 0.1)",
    strokeDashArray: 5,
  },
  colors: ["#7367f0", "#28c76f", "#ea5455", "#ff9f43", "#00cfe8", "#4b4b4b"],
  tooltip: {
    theme: "dark",
    shared: true,
    intersect: false,
  },
}));

const marketShareOptions = computed(() => ({
  chart: { type: 'radialBar' },
  plotOptions: {
    radialBar: {
      startAngle: -135,
      endAngle: 135,
      hollow: { size: '70%' },
      dataLabels: {
        name: {
          fontSize: '13px',
          color: 'rgba(var(--v-theme-on-surface), 0.6)',
          offsetY: -10,
          fontWeight: 600,
        },
        value: {
          offsetY: 5,
          fontSize: '22px',
          color: 'rgba(var(--v-theme-on-surface), 0.87)',
          fontWeight: 800,
          formatter: val => `${val}%`,
        },
      },
    },
  },
  fill: {
    type: 'gradient',
    gradient: {
      shade: 'dark',
      shadeIntensity: 0.15,
      inverseColors: false,
      opacityFrom: 1,
      opacityTo: 1,
      stops: [0, 50, 65, 91]
    },
  },
  stroke: { dashArray: 4 },
  labels: ['Mkt Share'],
  colors: ['#7367f0'],
}));

const series = computed(() => stats.value?.trend_chart?.series || []);
const marketShareSeries = computed(() => [stats.value?.market_share || 0]);

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
    stats.value = null; // Limpiar estadísticas previas para forzar el desmontaje/montaje de los gráficos
    if (val) {
      fetchStats();
    }
  }
);

const closeDialog = () => {
  emit("update:modelValue", false);
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="900px"
    @update:model-value="closeDialog"
    persistent
    :fullscreen="xs"
    scrollable
  >
    <VCard v-if="props.product" class="stats-dialog-card rounded-xl border-0 overflow-hidden bg-surface">
      <!-- Cabecera Premium más compacta -->
      <div class="header-gradient px-5 py-3 d-flex align-center shadow-sm">
        <VAvatar color="white" variant="flat" size="36" class="me-3 elevation-2">
          <VIcon icon="tabler-chart-bar" size="20" color="primary" />
        </VAvatar>
        <div class="d-flex flex-column">
          <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0 uppercase tracking-wide">
            Analítica de Producto
          </h2>
          <span class="text-white opacity-75 font-weight-bold text-super-xs uppercase tracking-tighter truncate" style="max-inline-size: 600px;">
            {{ props.product.name }}
          </span>
        </div>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="tonal"
          color="white"
          size="x-small"
          class="rounded-lg"
          @click="closeDialog"
        />
      </div>

      <VCardText class="pa-4 bg-light overflow-y-auto">
        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4 rounded" height="4" />

        <template v-if="stats">
          <VRow dense>
            <!-- Lado Izquierdo: KPIs y Market Share -->
            <VCol cols="12" md="4" class="d-flex flex-column gap-2">
              <VCard variant="flat" class="pa-4 rounded-lg border shadow-sm bg-surface flex-grow-1 d-flex flex-column align-center justify-center">
                <div class="text-xs font-weight-black text-disabled uppercase mb-2">Preferencia de Compra</div>
                <VueApexCharts
                  :key="props.product?.id"
                  type="radialBar"
                  height="220"
                  :options="marketShareOptions"
                  :series="marketShareSeries"
                />
                <div class="text-super-xs font-weight-bold text-center text-medium-emphasis px-4">
                  Participación del producto dentro de su grupo competitivo.
                </div>
              </VCard>

              <div class="d-flex gap-2">
                <VCard variant="flat" class="pa-3 rounded-lg border shadow-sm bg-surface flex-grow-1">
                  <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Total Vendido</span>
                  <div class="d-flex align-center gap-1">
                    <span class="text-h6 font-weight-black text-primary">{{ stats.total_units_sold }}</span>
                    <span class="text-super-xs font-weight-bold text-disabled pt-1">UNDS</span>
                  </div>
                </VCard>
                <VCard variant="flat" class="pa-3 rounded-lg border shadow-sm bg-surface flex-grow-1">
                  <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Promedio Mes</span>
                  <div class="d-flex align-center gap-1">
                    <span class="text-h6 font-weight-black text-success">{{ stats.monthly_average }}</span>
                    <span class="text-super-xs font-weight-bold text-disabled pt-1">/MES</span>
                  </div>
                </VCard>
              </div>
            </VCol>

            <!-- Lado Derecho: Tendencia Comparativa -->
            <VCol cols="12" md="8">
              <VCard variant="flat" class="pa-4 rounded-lg border shadow-sm bg-surface h-100">
                <div class="d-flex align-center justify-space-between mb-4">
                  <div class="d-flex align-center gap-2">
                    <div class="header-indicator primary" />
                    <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">
                      Tendencia Histórica vs Competidores
                    </span>
                  </div>
                </div>
                
                <div class="mx-n2">
                  <VueApexCharts
                    :key="props.product?.id"
                    type="area"
                    height="300"
                    :options="chartOptions"
                    :series="series"
                  />
                </div>
              </VCard>
            </VCol>

            <!-- Card de Última Venta (Footer del modal body) -->
            <VCol cols="12">
              <VCard variant="flat" class="pa-3 rounded-lg border shadow-sm bg-surface d-flex align-center justify-space-between">
                <div class="d-flex align-center gap-3">
                  <VIcon icon="tabler-history" size="18" color="info" />
                  <span class="text-xs font-weight-bold text-medium-emphasis">Detalle de la última operación registrada:</span>
                </div>
                <template v-if="stats.last_sale">
                  <div class="d-flex align-center gap-4">
                    <div class="d-flex flex-column align-end">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Fecha</span>
                      <span class="text-xs font-weight-black text-high-emphasis">{{ formatDateSimple(stats.last_sale.date) }}</span>
                    </div>
                    <div class="d-flex flex-column align-end">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Precio</span>
                      <span class="text-xs font-weight-black text-primary">{{ formatPrice(stats.last_sale.price) }}</span>
                    </div>
                    <div class="d-flex flex-column align-end">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Cantidad</span>
                      <span class="text-xs font-weight-black text-info">{{ stats.last_sale.quantity }} Unds</span>
                    </div>
                  </div>
                </template>
                <span v-else class="text-xs font-weight-bold text-disabled italic">No hay ventas previas</span>
              </VCard>
            </VCol>
          </VRow>
        </template>
        
        <!-- Loading -->
        <VRow v-else dense>
          <VCol cols="12" sm="4" v-for="i in 3" :key="i">
            <VSkeletonLoader type="list-item-two-line" class="rounded-lg border" />
          </VCol>
          <VCol cols="12">
            <VSkeletonLoader type="image" class="rounded-lg border" height="200" />
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
