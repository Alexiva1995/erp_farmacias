<script setup>
// Componente: Análisis ABC (Donut chart + tabla de clases)
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const { formatCurrency } = useCurrencyConverter();

const props = defineProps({
  abcData: { type: Array,   default: () => [] },
  loading:  { type: Boolean, default: false    },
});

// Total de SKUs calculado en computed, no en template
const totalSkus = computed(() => props.abcData.reduce((sum, a) => sum + (a?.count ?? 0), 0));

const formatPercent = (val) => Number(val ?? 0).toFixed(2) + '%';

const skuPercent = (count) => {
  if (!totalSkus.value) return formatPercent(0);
  return formatPercent((count / totalSkus.value) * 100);
};

const chartOptions = computed(() => ({
  chart: { type: 'donut', toolbar: { show: false } },
  labels: props.abcData.map(a => `Clase ${a?.type ?? '?'}`),
  colors: ['#28C76F', '#FF9F43', '#EA5455'],
  legend: { position: 'bottom' },
  dataLabels: { enabled: true },
  plotOptions: {
    pie: {
      donut: {
        size: '70%',
        labels: { show: true, total: { show: true, label: 'SKUs' } },
      },
    },
  },
}));

const chartSeries = computed(() => props.abcData.map(a => Number(a?.count ?? 0)));
</script>

<template>
  <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
    <VCardTitle class="pa-4 border-b">
      <span class="text-h6 font-weight-bold">Análisis ABC</span>
    </VCardTitle>

    <VCardText class="px-0 py-4 text-center">
      <!-- Skeleton de carga -->
      <div v-if="loading" class="skeleton-chart-pulse mx-4" style="height: 300px; border-radius: 8px;" />

      <!-- Gráfico -->
      <VueApexCharts
        v-if="abcData.length"
        height="300"
        :options="chartOptions"
        :series="chartSeries"
      />
      <div v-else class="text-center pa-10 text-medium-emphasis">
        <VIcon icon="tabler-chart-donut" size="40" class="mb-2 opacity-30" />
        <div class="text-sm">Sin datos ABC</div>
      </div>

      <!-- Tabla de clases -->
      <div class="mt-4 text-left px-4">
        <div
          v-for="abc in abcData"
          :key="abc?.type"
          class="d-flex justify-space-between mb-2 border-b pa-1 align-center"
        >
          <div class="d-flex flex-column">
            <span class="text-sm font-weight-bold">Clase {{ abc?.type }}</span>
            <span class="text-xs text-medium-emphasis">{{ formatCurrency(abc?.revenue ?? 0) }} acum.</span>
          </div>
          <div class="text-right">
            <div class="text-sm font-weight-black">{{ abc?.count }} SKUs</div>
            <!-- Cálculo fuera del template, en computed -->
            <div class="text-super-xs text-primary">{{ skuPercent(abc?.count) }} del total</div>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs { font-size: 0.65rem !important; line-height: 1; }
.text-xs      { font-size: 0.75rem !important; }

.skeleton-chart-pulse {
  width: 100%;
  background: linear-gradient(
    90deg,
    rgba(var(--v-theme-on-surface), 0.06) 25%,
    rgba(var(--v-theme-on-surface), 0.12) 50%,
    rgba(var(--v-theme-on-surface), 0.06) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}
@keyframes shimmer {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
