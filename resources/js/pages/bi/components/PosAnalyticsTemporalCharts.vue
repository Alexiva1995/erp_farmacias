<script setup>
import { ref, computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  charts: { type: Object, default: () => ({}) },
});

const activeSalesView = ref('trend'); // 'trend' | 'weekday'

// 1. Gráfico de Evolución Diaria Continua (Timeline)
const trendChartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  stroke: { curve: 'smooth', width: [3, 2] },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: [0.4, 0.2],
      opacityTo: [0.05, 0.0],
      stops: [0, 90, 100],
    },
  },
  xaxis: {
    categories: props.charts.daily_trend?.categories || [],
    labels: {
      style: { fontSize: '11px' },
      rotate: -45,
      rotateAlways: (props.charts.daily_trend?.categories?.length || 0) > 10,
    },
    axisBorder: { show: false },
  },
  yaxis: [
    {
      title: { text: 'Facturación ($)', style: { fontSize: '11px', fontWeight: 600 } },
      labels: {
        formatter: (val) => `$${new Intl.NumberFormat('en-US').format(Math.round(val))}`,
      },
    },
    {
      opposite: true,
      title: { text: 'Tickets', style: { fontSize: '11px', fontWeight: 600 } },
      labels: {
        formatter: (val) => `${Math.round(val)}`,
      },
    },
  ],
  colors: ['#E20074', '#7A0099'],
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    strokeDashArray: 4,
  },
  legend: { position: 'top', fontSize: '12px', fontWeight: 600 },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (val, { seriesIndex }) => {
        return seriesIndex === 0 
          ? `$${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(val)}`
          : `${val} tickets`;
      },
    },
  },
}));

// 2. Gráfico de Ventas por Día de la Semana
const weekdayChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  plotOptions: {
    bar: {
      borderRadius: 6,
      columnWidth: '45%',
      distributed: true,
      dataLabels: { position: 'top' },
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => `$${new Intl.NumberFormat('en-US').format(Math.round(val))}`,
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
      formatter: (val) => `$${new Intl.NumberFormat('en-US').format(Math.round(val))}`,
    },
  },
  colors: ['#FF4C51', '#E20074', '#7A0099', '#28C76F', '#FF9F43', '#00BAD1', '#8C57FF'],
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    strokeDashArray: 4,
  },
  legend: { show: false },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (val) => `$${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(val)}`,
    },
  },
}));

// 3. Gráfico de Distribución Horaria
const hourlyChartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
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
  legend: { show: false },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (val, { series, seriesIndex, dataPointIndex, w }) => {
        const item = w.config.series[seriesIndex]?.data?.[dataPointIndex];
        const revenue = item?.revenue || 0;
        return `${val}% (Facturado: $${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(revenue)})`;
      },
    },
  },
}));
</script>

<template>
  <VRow class="mb-6" dense>
    <!-- Gráfico 1: Evolución y Estacionalidad -->
    <VCol cols="12" lg="6">
      <VCard variant="outlined" class="rounded-lg h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="primary" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-chart-line" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            {{ activeSalesView === 'trend' ? 'Evolución de Ventas en el Periodo' : 'Ventas Acumuladas por Día Semanal' }}
          </VCardTitle>
          <template #append>
            <VBtnToggle
              v-model="activeSalesView"
              mandatory
              density="compact"
              variant="outlined"
              color="primary"
            >
              <VBtn value="trend" size="x-small">Timeline</VBtn>
              <VBtn value="weekday" size="x-small">Semana</VBtn>
            </VBtnToggle>
          </template>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts
            v-if="activeSalesView === 'trend'"
            height="320"
            :options="trendChartOptions"
            :series="charts.daily_trend?.series || []"
          />
          <VueApexCharts
            v-else
            height="320"
            :options="weekdayChartOptions"
            :series="charts.daily_focus?.series || []"
          />
        </VCardText>
      </VCard>
    </VCol>

    <!-- Gráfico 2: Distribución Horaria -->
    <VCol cols="12" lg="6">
      <VCard variant="outlined" class="rounded-lg h-100">
        <VCardItem class="py-3 border-b">
          <template #prepend>
            <VAvatar color="success" variant="tonal" size="32" class="me-2 rounded">
              <VIcon icon="tabler-clock" size="18" />
            </VAvatar>
          </template>
          <VCardTitle class="text-subtitle-2 font-weight-bold text-uppercase">
            Curva de Tráfico y Concentración Horaria
          </VCardTitle>
        </VCardItem>
        <VCardText class="pa-4">
          <VueApexCharts
            height="320"
            :options="hourlyChartOptions"
            :series="charts.hourly_distribution?.series || []"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
