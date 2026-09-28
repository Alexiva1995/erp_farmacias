<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const props = defineProps({
  trends: {
    type: Array,
    default: () => []
  },
  rankingsByRevenue: {
    type: Array,
    default: () => []
  },
  profitability: {
    type: Array,
    default: () => []
  },
  stockOnHand: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const { formatCurrency } = useCurrencyConverter();

// Paleta corporativa
const chartColors = ['#E20074', '#7A0099', '#28C76F', '#00BAD1', '#FF9F43', '#FF4C51'];

// Configuración de Tendencia Temporal
const trendChartOptions = computed(() => {
  const months = [...new Set(props.trends.map(t => t.month))].sort();
  
  return {
    chart: { 
      type: 'line', 
      toolbar: { show: false },
      dropShadow: { enabled: true, top: 2, left: 1, blur: 4, opacity: 0.08 }
    },
    stroke: { curve: 'smooth', width: 3 },
    markers: { size: 4, hover: { size: 7 } },
    grid: { borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))', strokeDashArray: 4 },
    xaxis: { 
      categories: months,
      labels: { style: { fontSize: '11px', fontWeight: 600 } }
    },
    yaxis: {
      labels: {
        formatter: (val) => formatCurrency(val),
        style: { fontWeight: 600 }
      }
    },
    colors: chartColors,
    legend: { position: 'top', horizontalAlign: 'right', fontWeight: 600 },
    tooltip: {
      y: { formatter: (val) => formatCurrency(val) }
    }
  };
});

const trendSeries = computed(() => {
  const months = [...new Set(props.trends.map(t => t.month))].sort();
  const seriesNames = [...new Set(props.trends.map(t => t.lab_name))];

  return seriesNames.map(name => ({
    name,
    data: months.map(m => {
      const match = props.trends.find(t => t.lab_name === name && t.month === m);
      return match ? parseFloat(match.revenue) : 0;
    })
  }));
});

// Cuota de Mercado
const marketShareChartOptions = computed(() => ({
  chart: { type: 'donut' },
  labels: props.rankingsByRevenue.map(l => l.name),
  colors: ['#E20074', '#28C76F', '#00BAD1', '#FF9F43', '#7A0099', '#FF4C51', '#607D8B', '#9C27B0', '#3F51B5', '#009688'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
  plotOptions: { 
    pie: { 
      donut: { 
        labels: { 
          show: true, 
          total: { 
            show: true, 
            label: 'TOTAL USD', 
            formatter: () => formatCurrency(props.rankingsByRevenue.reduce((a, b) => a + parseFloat(b.total_revenue || 0), 0)) 
          } 
        } 
      } 
    } 
  }
}));

const marketShareSeries = computed(() => props.rankingsByRevenue.map(l => parseFloat(l.total_revenue || 0)));

// Rentabilidad y Margen
const profitabilityChartOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, stacked: false },
  stroke: { width: [0, 3], curve: 'smooth' },
  plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
  colors: ['#E20074', '#28C76F'],
  dataLabels: { 
    enabled: true, 
    enabledOnSeries: [0, 1],
    formatter: (val, opts) => opts.seriesIndex === 0 ? formatCurrency(val) : `${val.toFixed(1)}%`,
    style: { fontSize: '10px' }
  },
  labels: props.profitability.map(l => l.name),
  xaxis: { categories: props.profitability.map(l => l.name) },
  yaxis: [
    {
      title: { text: 'Venta Bruta', style: { color: '#E20074' } },
      labels: { formatter: (val) => formatCurrency(val), style: { colors: '#E20074' } }
    },
    {
      opposite: true,
      title: { text: 'Margen %', style: { color: '#28C76F' } },
      labels: { formatter: (val) => `${val.toFixed(0)}%`, style: { colors: '#28C76F' } }
    }
  ],
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: (val, opts) => opts.seriesIndex === 0 ? formatCurrency(val) : `${val.toFixed(2)}%`
    }
  },
  legend: { position: 'top', horizontalAlign: 'center' }
}));

const profitabilitySeries = computed(() => [
  {
    name: 'Venta Bruta',
    type: 'column',
    data: props.profitability.map(l => parseFloat(l.total_revenue || 0))
  },
  {
    name: 'Margen %',
    type: 'line',
    data: props.profitability.map(l => parseFloat(l.margin_percent || 0))
  }
]);

// Treemap de Stock
const stockTreemapOptions = computed(() => ({
  legend: { show: false },
  chart: { height: 350, type: 'treemap', toolbar: { show: false } },
  colors: chartColors,
  plotOptions: {
    treemap: {
      enableShades: true,
      shadeIntensity: 0.5,
      distributed: true
    }
  },
  tooltip: {
    y: { formatter: (val) => formatCurrency(val) }
  }
}));

const stockSeries = computed(() => ([{
  data: props.stockOnHand.map(item => ({
    x: item.name,
    y: parseFloat(item.inventory_value || 0)
  }))
}]));
</script>

<template>
  <div class="mb-4">
    <!-- TENDENCIAS Y CUOTA DE MERCADO -->
    <VRow class="match-height mb-4">
      <VCol cols="12" md="8">
        <VCard border class="rounded-lg h-100">
          <VCardTitle class="pa-4 border-b d-flex align-center">
            <VIcon icon="tabler-chart-line" class="me-2 text-primary" />
            <span class="text-subtitle-1 font-weight-bold">Tendencia de Venta Bruta (Top 5)</span>
          </VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="320" />
            <VueApexCharts v-else-if="trends.length" height="320" :options="trendChartOptions" :series="trendSeries" />
            <VEmptyState
              v-else
              icon="tabler-chart-dots"
              title="Sin tendencias"
              text="No se registran datos suficientes en el periodo para calcular la curva de tendencia"
              class="py-6"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard border class="rounded-lg h-100">
          <VCardTitle class="pa-4 border-b d-flex align-center">
            <VIcon icon="tabler-chart-donut-2" class="me-2 text-secondary" />
            <span class="text-subtitle-1 font-weight-bold">Cuota de Mercado (% Ventas)</span>
          </VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="320" />
            <VueApexCharts v-else-if="rankingsByRevenue.length" height="320" :options="marketShareChartOptions" :series="marketShareSeries" />
            <VEmptyState
              v-else
              icon="tabler-chart-pie"
              title="Sin cuota"
              text="No hay datos de facturación disponibles"
              class="py-6"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- RENTABILIDAD Y STOCK -->
    <VRow class="match-height">
      <VCol cols="12" md="6">
        <VCard border class="rounded-lg h-100">
          <VCardTitle class="pa-4 border-b d-flex align-center">
            <VIcon icon="tabler-trending-up" class="me-2 text-success" />
            <span class="text-subtitle-1 font-weight-bold">Eficiencia vs Volumen (Margen / Venta)</span>
          </VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="360" />
            <VueApexCharts v-else-if="profitability.length" height="360" :options="profitabilityChartOptions" :series="profitabilitySeries" />
            <VEmptyState
              v-else
              icon="tabler-chart-bar"
              title="Sin rentabilidad"
              text="No se registran márgenes para los laboratorios en este periodo"
              class="py-6"
            />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard border class="rounded-lg h-100">
          <VCardTitle class="pa-4 border-b d-flex align-center">
            <VIcon icon="tabler-building-warehouse" class="me-2 text-primary" />
            <span class="text-subtitle-1 font-weight-bold">Inversión en Stock (Por Laboratorio)</span>
          </VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="360" />
            <VueApexCharts v-else-if="stockOnHand.length" height="360" :options="stockTreemapOptions" :series="stockSeries" />
            <VEmptyState
              v-else
              icon="tabler-packages"
              title="Sin inventario"
              text="No hay existencias valorizadas para mostrar"
              class="py-6"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
