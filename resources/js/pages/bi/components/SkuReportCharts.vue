<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  chartsData: {
    type: Object,
    default: () => ({
      waterfall: [],
      semaphore_distribution: { green: 0, yellow: 0, red: 0, black: 0 },
      summary: {}
    })
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const formatCurrency = (val) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val || 0);
};

// --- CONFIGURACIÓN DE GRÁFICO WATERFALL (CASCADA FINANCIERA) ---
const waterfallChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit'
  },
  plotOptions: {
    bar: {
      horizontal: false,
      columnWidth: '45%',
      borderRadius: 4,
      colors: {
        ranges: [
          { from: -999999999, to: -0.01, color: '#FF4C51' }, // Error / Deducción
          { from: 0, to: 999999999, color: '#28C76F' }       // Success / Ingreso
        ]
      }
    }
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => formatCurrency(val),
    style: {
      fontSize: '11px',
      fontWeight: 'bold'
    }
  },
  xaxis: {
    categories: (props.chartsData?.waterfall || []).map(item => item.name),
    labels: {
      rotate: -15,
      rotateAlways: false,
      style: {
        fontSize: '11px',
        fontWeight: 600
      }
    }
  },
  yaxis: {
    labels: {
      formatter: (val) => formatCurrency(val),
      style: {
        fontSize: '11px'
      }
    }
  },
  grid: {
    borderColor: 'rgba(var(--v-border-color), 0.1)',
    strokeDashArray: 4
  },
  tooltip: {
    y: {
      formatter: (val) => formatCurrency(val)
    }
  }
}));

const waterfallSeries = computed(() => [
  {
    name: 'Monto USD',
    data: (props.chartsData?.waterfall || []).map(item => item.value)
  }
]);

// --- CONFIGURACIÓN DE GRÁFICO DONUT (DISTRIBUCIÓN POR SEMÁFORO) ---
const donutChartOptions = computed(() => ({
  chart: {
    type: 'donut',
    fontFamily: 'inherit'
  },
  labels: ['Rentable (>25%)', 'Medio (10-25%)', 'Peligro (0-10%)', 'En Pérdida (<0%)'],
  colors: ['#28C76F', '#FF9F43', '#FF4C51', '#7A0099'],
  legend: {
    position: 'bottom',
    fontSize: '12px',
    fontWeight: 500
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${val.toFixed(1)}%`
  },
  plotOptions: {
    pie: {
      donut: {
        size: '68%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'TOTAL SKUs',
            formatter: (w) => {
              const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
              return total.toString();
            }
          }
        }
      }
    }
  }
}));

const donutSeries = computed(() => {
  const dist = props.chartsData?.semaphore_distribution || { green: 0, yellow: 0, red: 0, black: 0 };
  return [dist.green || 0, dist.yellow || 0, dist.red || 0, dist.black || 0];
});
</script>

<template>
  <VRow class="mb-5" dense>
    <!-- Cascada Financiera (Waterfall) -->
    <VCol cols="12" md="8">
      <VCard border class="rounded-lg shadow-sm h-100 bg-surface">
        <VCardItem class="py-3 border-b">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-2">
              <VAvatar color="primary" variant="tonal" size="32" rounded="sm">
                <VIcon icon="tabler-chart-bar" size="18" />
              </VAvatar>
              <span class="text-subtitle-1 font-weight-bold">Cascada Financiera de Margen Global</span>
            </div>
            <span class="text-caption text-medium-emphasis">Ventas vs Descuentos vs Mermas</span>
          </div>
        </VCardItem>
        <VCardText class="pa-4">
          <VSkeletonLoader v-if="loading" type="card" height="260" />
          <VueApexCharts
            v-else
            height="260"
            :options="waterfallChartOptions"
            :series="waterfallSeries"
          />
        </VCardText>
      </VCard>
    </VCol>

    <!-- Distribución de Portafolio por Semáforo -->
    <VCol cols="12" md="4">
      <VCard border class="rounded-lg shadow-sm h-100 bg-surface">
        <VCardItem class="py-3 border-b">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center gap-2">
              <VAvatar color="secondary" variant="tonal" size="32" rounded="sm">
                <VIcon icon="tabler-chart-pie" size="18" />
              </VAvatar>
              <span class="text-subtitle-1 font-weight-bold">Salud del Portafolio</span>
            </div>
            <span class="text-caption text-medium-emphasis">Semáforo</span>
          </div>
        </VCardItem>
        <VCardText class="pa-4">
          <VSkeletonLoader v-if="loading" type="card" height="260" />
          <VueApexCharts
            v-else
            height="260"
            :options="donutChartOptions"
            :series="donutSeries"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
