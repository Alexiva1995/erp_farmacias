<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  segmentation: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};

const translateSegment = (key) => {
  const translations = {
    platinum: 'Platino',
    gold: 'Oro',
    silver: 'Plata',
    bronze: 'Bronce',
  };
  return translations[key] || key;
};

const treemapOptions = computed(() => ({
  legend: { show: false },
  chart: { height: 300, type: 'treemap', toolbar: { show: false } },
  colors: ['#E20074', '#7A0099', '#FF9F43', '#EA5455'],
  plotOptions: {
    treemap: {
      distributed: true,
      enableShades: false,
    },
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (value) => formatCurrency(value),
    },
  },
}));

const treemapSeries = computed(() => [
  {
    data: [
      { x: 'Platino (5%)', y: Number((props.segmentation?.platinum?.revenue || 0).toFixed(2)) },
      { x: 'Oro (15%)', y: Number((props.segmentation?.gold?.revenue || 0).toFixed(2)) },
      { x: 'Plata (30%)', y: Number((props.segmentation?.silver?.revenue || 0).toFixed(2)) },
      { x: 'Bronce (50%)', y: Number((props.segmentation?.bronze?.revenue || 0).toFixed(2)) },
    ],
  },
]);

const segmentBadges = computed(() => {
  if (!props.segmentation) return [];
  const colorMap = {
    platinum: 'primary',
    gold: 'success',
    silver: 'warning',
    bronze: 'error',
  };
  return ['platinum', 'gold', 'silver', 'bronze'].map((key) => ({
    key,
    label: translateSegment(key),
    color: colorMap[key],
    data: props.segmentation[key] || { count: 0, revenue: 0, avg_per_client: 0 },
  }));
});

const hasData = computed(() => props.segmentation && (props.segmentation.total_revenue || 0) > 0);
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 h-100">
    <VCardItem class="py-3 border-b">
      <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
        <VIcon icon="tabler-pyramid" class="me-2 text-warning" size="20" />
        Pirámide de Valor (Aporte por Segmento)
      </VCardTitle>
    </VCardItem>

    <VCardText class="pa-4">
      <VSkeletonLoader v-if="loading && !segmentation" type="image" height="300" />
      <template v-else-if="hasData">
        <VueApexCharts height="280" type="treemap" :options="treemapOptions" :series="treemapSeries" />

        <VRow dense class="mt-2">
          <VCol v-for="seg in segmentBadges" :key="seg.key" cols="6">
            <VCard variant="tonal" :color="seg.color" class="pa-2 rounded-lg">
              <div class="text-caption font-weight-bold text-uppercase opacity-80">
                {{ seg.label }}
              </div>
              <div class="text-subtitle-2 font-weight-black">
                {{ formatCurrency(seg.data.revenue) }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ seg.data.count }} clientes | Prom: {{ formatCurrency(seg.data.avg_per_client) }}
              </div>
            </VCard>
          </VCol>
        </VRow>
      </template>

      <VEmptyState
        v-else
        icon="tabler-pyramid-off"
        title="Sin datos de segmentación"
        text="No existen montos acumulados para clasificar clientes en el rango actual."
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
:deep(.apexcharts-tooltip) {
  background: #1e1e1e !important;
  color: #ffffff !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important;
}
:deep(.apexcharts-tooltip-title) {
  background: #2a2a2a !important;
  color: #ffffff !important;
  font-weight: bold !important;
}
:deep(.apexcharts-tooltip-text),
:deep(.apexcharts-tooltip-text-y-value),
:deep(.apexcharts-tooltip-text-y-label) {
  color: #ffffff !important;
  font-weight: 600 !important;
}
:deep(.apexcharts-tooltip-series-group) {
  background: #1e1e1e !important;
  color: #ffffff !important;
}
</style>
