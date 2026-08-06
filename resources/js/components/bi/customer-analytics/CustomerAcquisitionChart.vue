<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  growthData: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    toolbar: { show: false },
    fontFamily: 'Inter, sans-serif',
    dropShadow: { enabled: true, top: 8, left: 0, blur: 4, color: '#E20074', opacity: 0.15 },
  },
  stroke: { curve: 'smooth', width: 3 },
  colors: ['#E20074'],
  fill: {
    type: 'gradient',
    gradient: {
      shade: 'dark',
      gradientToColors: ['#7A0099'],
      shadeIntensity: 1,
      type: 'horizontal',
      opacityFrom: 1,
      opacityTo: 1,
      stops: [0, 100],
    },
  },
  xaxis: {
    categories: props.growthData?.new_customers_daily?.map((d) => d.date) || [],
    labels: { style: { fontSize: '11px', colors: '#78909C' } },
    axisBorder: { show: false },
  },
  yaxis: { labels: { style: { colors: '#78909C' } } },
  grid: { borderColor: 'rgba(144, 164, 174, 0.08)' },
  tooltip: { theme: 'dark' },
  markers: { size: 4, colors: ['#E20074'], strokeColors: '#fff', strokeWidth: 2, hover: { size: 6 } },
}));

const series = computed(() => [
  {
    name: 'Nuevos Clientes',
    data: props.growthData?.new_customers_daily?.map((d) => d.count) || [],
  },
]);

const hasData = computed(() => {
  return props.growthData?.new_customers_daily && props.growthData.new_customers_daily.length > 0;
});
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 h-100">
    <VCardItem class="py-3 border-b">
      <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
        <VIcon icon="tabler-chart-line" class="me-2 text-primary" size="20" />
        Velocidad de Adquisición (Clientes Nuevos)
      </VCardTitle>
    </VCardItem>
    <VCardText class="pa-4">
      <VSkeletonLoader v-if="loading && !growthData" type="image" height="300" />
      <template v-else-if="hasData">
        <VueApexCharts height="300" :options="chartOptions" :series="series" />
      </template>
      <VEmptyState
        v-else
        icon="tabler-chart-line"
        title="Sin registros de adquisición"
        text="No se encontraron clientes nuevos registrados en el periodo seleccionado."
      />
    </VCardText>
  </VCard>
</template>
