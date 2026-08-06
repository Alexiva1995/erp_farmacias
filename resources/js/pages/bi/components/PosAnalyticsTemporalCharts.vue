<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  charts: { type: Object, default: () => ({}) }
});

const dailyChartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'Inter, sans-serif' },
  plotOptions: {
    bar: {
      borderRadius: 4,
      columnWidth: '45%',
      distributed: true,
      dataLabels: { position: 'top' }
    }
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `$${val}`,
    offsetY: -20,
    style: { fontSize: '10px', colors: ['#304758'] }
  },
  xaxis: {
    categories: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: { style: { fontSize: '11px', fontWeight: 600, colors: '#a3a3a3' } }
  },
  yaxis: { labels: { style: { colors: '#a3a3a3' } } },
  colors: ['#EA5455', '#E20074', '#7A0099', '#28c76f', '#ff9f43', '#00cfe8', '#607d8b'],
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)', show: false },
  legend: { show: false },
  tooltip: { theme: 'dark' }
}));

const hourlyChartOptions = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false }, fontFamily: 'Inter, sans-serif' },
  stroke: { curve: 'smooth', width: 3 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1, stops: [0, 90, 100] } },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${val}%`,
    style: { fontSize: '9px', fontWeight: 900 }
  },
  xaxis: {
    labels: { style: { fontSize: '10px', colors: '#a3a3a3' } },
    axisBorder: { show: false }
  },
  yaxis: { show: false },
  colors: ['#E20074'],
  grid: { borderColor: 'rgba(144, 164, 174, 0.1)', strokeDashArray: 4 },
  tooltip: { 
    theme: 'dark',
    y: {
      formatter: (val, { series, seriesIndex, dataPointIndex, w }) => {
        const revenue = w.config.series[seriesIndex].data[dataPointIndex].revenue;
        return `${val}% (Facturado: $${new Intl.NumberFormat('en-US').format(revenue)})`;
      }
    }
  }
}));
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol cols="12" md="6">
      <VCard class="rounded-lg border shadow-sm h-100">
        <VCardItem class="py-3 border-b bg-light-primary">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-coin" class="me-2 text-primary" size="20" />
            Ventas Totales por Día (Semanal)
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="300" :options="dailyChartOptions" :series="charts.daily_focus?.series || []" />
        </VCardText>
      </VCard>
    </VCol>
    
    <VCol cols="12" md="6">
      <VCard class="rounded-lg border shadow-sm h-100">
        <VCardItem class="py-3 border-b">
          <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-black text-uppercase">
            <VIcon icon="tabler-chart-area" class="me-2 text-success" size="20" />
            Distribución Horaria (% y USD)
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="300" :options="hourlyChartOptions" :series="charts.hourly_distribution?.series || []" />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.font-weight-black { font-weight: 900 !important; }
.bg-light-primary { background-color: #fff0f6; }
</style>
