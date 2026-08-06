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

// --- CONFIGURACIÓN DE GRÁFICOS ---
const trendChartOptions = computed(() => {
  const months = [...new Set(props.trends.map(t => t.month))].sort();
  
  return {
    chart: { 
      type: 'line', 
      toolbar: { show: false },
      dropShadow: { enabled: true, top: 3, left: 2, blur: 4, opacity: 0.1 }
    },
    stroke: { curve: 'smooth', width: 3 },
    markers: { size: 4, hover: { size: 7 } },
    grid: { borderColor: '#f1f1f1', strokeDashArray: 5 },
    xaxis: { 
      categories: months,
      labels: { style: { colors: '#616161', fontSize: '11px', fontWeight: 600 } }
    },
    yaxis: {
      labels: {
        formatter: (val) => formatCurrency(val),
        style: { colors: '#616161', fontWeight: 600 }
      }
    },
    colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8'],
    legend: { position: 'top', horizontalAlign: 'right', fontWeight: 600 },
    tooltip: {
      theme: 'dark',
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

const marketShareChartOptions = computed(() => ({
  chart: { type: 'donut' },
  labels: props.rankingsByRevenue.map(l => l.name),
  colors: ['#7367f0', '#28c76f', '#ea5455', '#ff9f43', '#00cfe8', '#00bbd4', '#607d8b', '#9c27b0', '#3f51b5', '#e91e63'],
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
            formatter: () => formatCurrency(props.rankingsByRevenue.reduce((a, b) => a + parseFloat(b.total_revenue), 0)) 
          } 
        } 
      } 
    } 
  }
}));

const marketShareSeries = computed(() => props.rankingsByRevenue.map(l => parseFloat(l.total_revenue)));

const profitabilityChartOptions = computed(() => ({
  chart: { type: 'line', toolbar: { show: false }, stacked: false },
  stroke: { width: [0, 4], curve: 'smooth' },
  plotOptions: { bar: { columnWidth: '50%', borderRadius: 4 } },
  colors: ['#7367f0', '#28c76f'],
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
      title: { text: 'Venta Bruta', style: { color: '#7367f0' } },
      labels: { formatter: (val) => formatCurrency(val), style: { colors: '#7367f0' } }
    },
    {
      opposite: true,
      title: { text: 'Margen %', style: { color: '#28c76f' } },
      labels: { formatter: (val) => `${val.toFixed(0)}%`, style: { colors: '#28c76f' } }
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
    data: props.profitability.map(l => parseFloat(l.total_revenue))
  },
  {
    name: 'Margen %',
    type: 'line',
    data: props.profitability.map(l => parseFloat(l.margin_percent))
  }
]);

const stockTreemapOptions = computed(() => ({
  legend: { show: false },
  chart: { height: 350, type: 'treemap', toolbar: { show: false } },
  colors: ['#7367f0'],
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
    y: parseFloat(item.inventory_value)
  }))
}]));
</script>

<template>
  <div class="mb-4">
    <!-- TENDENCIAS Y CUOTA DE MERCADO -->
    <VRow class="mb-4">
      <VCol cols="12" md="8">
        <VCard border class="rounded-lg shadow-sm h-100">
          <VCardTitle class="pa-4 border-b">Tendencia de Venta Bruta (Top 5)</VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="320" />
            <VueApexCharts v-else height="320" :options="trendChartOptions" :series="trendSeries" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard border class="rounded-lg shadow-sm h-100">
          <VCardTitle class="pa-4 border-b">Cuota de Mercado (% Ventas)</VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="320" />
            <VueApexCharts v-else height="320" :options="marketShareChartOptions" :series="marketShareSeries" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- RENTABILIDAD Y STOCK -->
    <VRow class="match-height">
      <VCol cols="12" md="6">
        <VCard border class="rounded-lg shadow-sm h-100">
          <VCardTitle class="pa-4 border-b d-flex align-center">
            <VIcon icon="tabler-trending-up" class="me-2 text-success" />
            <span>Eficiencia vs Volumen (Profit)</span>
          </VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="380" />
            <VueApexCharts v-else height="380" :options="profitabilityChartOptions" :series="profitabilitySeries" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard border class="rounded-lg shadow-sm h-100">
          <VCardTitle class="pa-4 border-b d-flex align-center">
            <VIcon icon="tabler-building-warehouse" class="me-2 text-primary" />
            <span>Inversión en Stock (Por Lab)</span>
          </VCardTitle>
          <VCardText class="pa-4">
            <VSkeletonLoader v-if="loading" type="card" height="380" />
            <VueApexCharts v-else height="380" :options="stockTreemapOptions" :series="stockSeries" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
