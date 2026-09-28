<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  charts: { type: Object, default: () => ({}) },
});

const dailyChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  plotOptions: {
    bar: {
      borderRadius: 4,
      columnWidth: '45%',
      distributed: true,
      dataLabels: { position: 'top' },
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `$${val}`,
    offsetY: -20,
    style: { fontSize: '11px', fontWeight: 600 },
  },
  xaxis: {
    categories: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: { style: { fontSize: '11px', fontWeight: 600 } },
  },
  yaxis: {
    labels: {
      formatter: (val) => `$${val}`,
    },
  },
  colors: ['#FF4C51', '#E20074', '#7A0099', '#28C76F', '#FF9F43', '#00BAD1', '#8C57FF'],
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    strokeDashArray: 4,
  },
  legend: { show: false },
  tooltip: { theme: 'dark' },
}));

const hourlyChartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    sparkline: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  stroke: { curve: 'smooth', width: 3 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.45,
      opacityTo: 0.05,
      stops: [0, 90, 100],
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `${val}%`,
    style: { fontSize: '10px', fontWeight: 700 },
  },
  xaxis: {
    labels: { style: { fontSize: '11px' } },
    axisBorder: { show: false },
  },
  yaxis: { show: false },
  colors: ['#E20074'],
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    strokeDashArray: 4,
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (val, { series, seriesIndex, dataPointIndex, w }) => {
        const revenue = w.config.series[seriesIndex].data[dataPointIndex].revenue;
        return `${val}% (Facturado: $${new Intl.NumberFormat('en-US').format(revenue)})`;
      },
    },
  },
}));
</script>

<template>
  <VRow class="mb-6" dense>
    <VCol cols="12" md="6">
      <VCard variant="outlined" class="rounded-lg shadow-sm h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-coin" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Ventas Totales por Día (Semanal)
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts height="300" :options="dailyChartOptions" :series="charts.daily_focus?.series || []" />
        </VCardText>
      </VCard>
    </VCol>
    
    <VCol cols="12" md="6">
      <VCard variant="outlined" class="rounded-lg shadow-sm h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="success" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-chart-area" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
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
