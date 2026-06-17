<script setup>
import { computed, ref, watch } from "vue";
import axios from "@/plugins/axios";
import { formatPrice, formatDateSimple } from "@/utils/formatters";
import VueApexCharts from "vue3-apexcharts";
import { useDisplay } from "vuetify";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  product: { type: Object, required: true },
});

const emit = defineEmits(["update:modelValue"]);

const { xs } = useDisplay();

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings?.business_type === 'restaurant');

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
  markers: {
    size: 5,
    hover: { size: 7 }
  },
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
  colors: ["#E20074", "#28c76f", "#ea5455", "#ff9f43", "#00cfe8", "#4b4b4b"],
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
  colors: ['#E20074'],
}));

const series = computed(() => {
  const rawSeries = stats.value?.trend_chart?.series || [];
  if (!isIngredient.value) return rawSeries;
  
  const factor = (props.product?.unit_of_measure === 'g' || props.product?.unit_of_measure === 'ml') ? 1000 : 1;
  if (factor === 1) return rawSeries;
  
  return rawSeries.map(s => ({
    ...s,
    data: s.data.map(val => Math.round(val * factor))
  }));
});

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

const isIngredient = computed(() => {
  return isRestaurant.value || props.product?.no_pvp === true || props.product?.no_pvp == 1 || props.product?.no_pvp === '1';
});

const ingredientStatusLabel = computed(() => {
  if (!props.product) return 'Inactivo';
  const stock = Number(props.product.stock_calculado ?? props.product.stock ?? 0);
  if (stock <= 0) return 'Agotado';
  return 'En Stock / Activo';
});

const ingredientStatusColor = computed(() => {
  if (!props.product) return 'grey';
  const stock = Number(props.product.stock_calculado ?? props.product.stock ?? 0);
  if (stock <= 0) return 'error';
  return 'success';
});

const formatIngredientStock = (item) => {
  if (!item) return '0 UNDS';
  const stock = Number(item.stock_calculado ?? item.stock ?? 0);
  if (!item.unit_of_measure) {
    return `${stock.toString().replace('.', ',')} UNDS`;
  }
  if (item.unit_of_measure === 'g') {
    return `${Math.round(stock * 1000)} g`;
  }
  if (item.unit_of_measure === 'ml') {
    return `${Math.round(stock * 1000)} ml`;
  }
  if (item.unit_of_measure === 'und') {
    return `${stock.toString().replace('.', ',')} unidades`;
  }
  return `${stock.toString().replace('.', ',')} UNDS`;
};

const formatIngredientExp = (item) => {
  if (!item) return 'N/A';
  let rawDate = 'N/A';
  if (!item.lots || !Array.isArray(item.lots) || item.lots.length === 0) {
    rawDate = item.next_expiration || "N/A";
  } else {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const validLots = item.lots.filter((lot) => {
      if (!lot.expiration_date) return false;
      const expirationDate = new Date(lot.expiration_date);
      return !isNaN(expirationDate.getTime()) && expirationDate >= today;
    });
    if (validLots.length === 0) {
      rawDate = item.next_expiration || "EXPIRADO";
    } else {
      validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
      rawDate = validLots[0].expiration_date;
    }
  }
  
  if (rawDate && rawDate !== 'N/A' && rawDate !== 'EXPIRADO') {
    if (rawDate.includes('T')) {
      return rawDate.split('T')[0];
    }
    if (rawDate.includes(' ')) {
      return rawDate.split(' ')[0];
    }
  }
  return rawDate;
};

const formatIngredientStockQuantity = (qty) => {
  const stock = Number(qty || 0);
  const item = props.product;
  if (!item || !item.unit_of_measure) {
    return `${stock.toString().replace('.', ',')} Unds`;
  }
  if (item.unit_of_measure === 'g') {
    return `${Math.round(stock * 1000)} g`;
  }
  if (item.unit_of_measure === 'ml') {
    return `${Math.round(stock * 1000)} ml`;
  }
  if (item.unit_of_measure === 'und') {
    return `${stock.toString().replace('.', ',')} unidades`;
  }
  return `${stock.toString().replace('.', ',')} Unds`;
};

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
                <template v-if="!isIngredient">
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
                </template>
                <template v-else>
                  <div class="text-xs font-weight-black text-disabled uppercase mb-4">Estado del Ingrediente</div>
                  <div class="d-flex flex-column align-center gap-2 py-4">
                    <VChip
                      :color="ingredientStatusColor"
                      size="large"
                      label
                      variant="tonal"
                      class="font-weight-black text-subtitle-2 px-4 py-2"
                    >
                      <VIcon start icon="tabler-info-circle" size="18" />
                      {{ ingredientStatusLabel }}
                    </VChip>
                    <div class="text-xs font-weight-bold text-medium-emphasis mt-2 text-center leading-relaxed">
                      <div>Stock Actual: <span class="font-weight-black text-high-emphasis">{{ formatIngredientStock(props.product) }}</span></div>
                      <div class="mt-1">Próximo Vencimiento: <span class="font-weight-black text-high-emphasis">{{ formatIngredientExp(props.product) }}</span></div>
                    </div>
                  </div>
                </template>
              </VCard>

              <div class="d-flex gap-2">
                <VCard variant="flat" class="pa-3 rounded-lg border shadow-sm bg-surface flex-grow-1">
                  <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">{{ isIngredient ? 'Gastado' : 'Total Vendido' }}</span>
                  <div class="d-flex align-center gap-1">
                    <span class="text-h6 font-weight-black text-primary">
                      {{ isIngredient ? formatIngredientStockQuantity(stats.total_units_sold) : stats.total_units_sold }}
                    </span>
                    <span v-if="!isIngredient" class="text-super-xs font-weight-bold text-disabled pt-1">UNDS</span>
                  </div>
                </VCard>
                <VCard variant="flat" class="pa-3 rounded-lg border shadow-sm bg-surface flex-grow-1">
                  <span class="text-super-xs font-weight-black text-disabled uppercase d-block mb-1">Promedio Mes</span>
                  <div class="d-flex align-center gap-1">
                    <span class="text-h6 font-weight-black text-success">
                      {{ isIngredient ? formatIngredientStockQuantity(stats.monthly_average) : stats.monthly_average }}
                    </span>
                    <span v-if="!isIngredient" class="text-super-xs font-weight-bold text-disabled pt-1">/MES</span>
                    <span v-else class="text-super-xs font-weight-bold text-disabled pt-1">/mes</span>
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
                      {{ isIngredient ? 'Tendencia Histórica de Consumo' : 'Tendencia Histórica vs Competidores' }}
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
                  <span class="text-xs font-weight-bold text-medium-emphasis">
                    {{ isIngredient ? 'Detalle del último consumo registrado:' : 'Detalle de la última operación registrada:' }}
                  </span>
                </div>
                <template v-if="stats.last_sale">
                  <div class="d-flex align-center gap-4">
                    <div class="d-flex flex-column align-end">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Fecha</span>
                      <span class="text-xs font-weight-black text-high-emphasis">{{ formatDateSimple(stats.last_sale.date) }}</span>
                    </div>
                    <div v-if="!isIngredient" class="d-flex flex-column align-end">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Precio</span>
                      <span class="text-xs font-weight-black text-primary">{{ formatPrice(stats.last_sale.price) }}</span>
                    </div>
                    <div class="d-flex flex-column align-end">
                      <span class="text-super-xs text-disabled uppercase font-weight-black">Cantidad</span>
                      <span class="text-xs font-weight-black text-info">{{ formatIngredientStockQuantity(stats.last_sale.quantity) }}</span>
                    </div>
                  </div>
                </template>
                <span v-else class="text-xs font-weight-bold text-disabled italic">
                  {{ isIngredient ? 'No hay consumos previos' : 'No hay ventas previas' }}
                </span>
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
  background: linear-gradient(135deg, #7A0099 0%, #E20074 100%);
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

.header-indicator.primary { background-color: #E20074; }

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

.card-glow.primary { background-color: #E20074; }
.card-glow.success { background-color: #28c76f; }
.card-glow.info { background-color: #00cfe8; }

.uppercase { text-transform: uppercase; }
.letter-spacing-1 { letter-spacing: 0.05em; }
.tracking-wide { letter-spacing: 0.1em; }
.tracking-tight { letter-spacing: -0.05em; }
.tracking-tighter { letter-spacing: -0.02em; }
</style>
