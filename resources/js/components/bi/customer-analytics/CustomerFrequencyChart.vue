<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  frequency: {
    type: Object,
    default: () => ({}),
  },
  totalCustomers: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const chartOptions = computed(() => ({
  labels: Object.keys(props.frequency || {}).map((f) => `${f} ${f === '1' ? 'Orden' : 'Órdenes'}`),
  colors: ['#E20074', '#7A0099', '#28C76F', '#FF9F43', '#EA5455'],
  plotOptions: {
    pie: {
      donut: {
        size: '75%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Clientes',
            fontSize: '12px',
            fontWeight: 800,
            formatter: () => props.totalCustomers || 0,
          },
        },
      },
    },
  },
  legend: { position: 'bottom', fontSize: '11px' },
}));

const series = computed(() => Object.values(props.frequency || {}));

const hasData = computed(() => series.value.length > 0 && series.value.some((val) => val > 0));
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 h-100">
    <VCardItem class="py-3 border-b">
      <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
        <VIcon icon="tabler-chart-donut" class="me-2 text-success" size="20" />
        Frecuencia de Compra
      </VCardTitle>
    </VCardItem>
    <VCardText class="pa-4">
      <VSkeletonLoader v-if="loading && !frequency" type="image" height="300" />
      <template v-else-if="hasData">
        <VueApexCharts height="300" type="donut" :options="chartOptions" :series="series" />
      </template>
      <VEmptyState
        v-else
        icon="tabler-chart-donut"
        title="Sin distribución de frecuencias"
        text="No existen compras registradas para calcular frecuencias en este rango."
      />
    </VCardText>
  </VCard>
</template>
