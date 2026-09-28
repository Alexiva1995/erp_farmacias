<script setup>
// Componente: Gráfico de Rentabilidad por Laboratorio / Fabricante con sincronización de tema
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useBiThemeColors } from '@/composables/useBiThemeColors';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const props = defineProps({
  labData: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const { colors } = useBiThemeColors();
const { formatCurrency } = useCurrencyConverter();

const safeLabData = computed(() => (Array.isArray(props.labData) ? props.labData : []));

const labChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 4,
      barHeight: '60%',
      dataLabels: { position: 'top' },
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (val) => formatCurrency(Number(val ?? 0)),
    offsetX: 12,
    style: {
      fontSize: '11px',
      fontWeight: 'bold',
      colors: [colors.value.primary],
    },
  },
  xaxis: {
    categories: safeLabData.value.map(l => l?.name ?? 'Desconocido'),
    labels: {
      formatter: (val) => formatCurrency(Number(val ?? 0)),
      style: { fontSize: '11px', colors: colors.value.onSurface },
    },
  },
  yaxis: {
    labels: {
      style: { fontSize: '12px', fontWeight: 600, colors: colors.value.onSurface },
      maxWidth: 180,
    },
  },
  colors: [colors.value.primary],
  grid: { strokeDashArray: 4, borderColor: 'rgba(var(--v-theme-on-surface), 0.12)' },
  tooltip: {
    theme: colors.value.isDark ? 'dark' : 'light',
    y: { formatter: (val) => formatCurrency(Number(val ?? 0)) },
  },
}));

const labChartSeries = computed(() => [{
  name: 'Margen Total',
  data: safeLabData.value.map(l => Number(l?.total_margin ?? 0)),
}]);
</script>

<template>
  <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
    <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar size="32" color="primary" variant="tonal" class="rounded">
          <VIcon icon="tabler-flask" size="18" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis">Rentabilidad por Laboratorio / Fabricante</div>
          <div class="text-caption text-medium-emphasis">Top 10 marcas líderes por margen bruto total aportado</div>
        </div>
      </div>
      <VChip size="x-small" color="primary" variant="flat" label class="font-weight-bold">
        Margen Total
      </VChip>
    </VCardTitle>

    <VCardText class="pa-4">
      <!-- Skeleton -->
      <div v-if="loading" class="skeleton-chart-pulse" style="height: 280px;" />

      <!-- Gráfico -->
      <VueApexCharts
        v-else-if="safeLabData.length"
        height="300"
        :options="labChartOptions"
        :series="labChartSeries"
      />

      <!-- Estado vacío -->
      <div v-else class="text-center pa-10 text-medium-emphasis">
        <VIcon icon="tabler-flask-off" size="40" class="mb-2 opacity-30" />
        <div class="text-sm font-weight-bold">Sin datos de laboratorio</div>
        <div class="text-xs text-disabled">No se registraron ventas por laboratorio en este período.</div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.skeleton-chart-pulse {
  width: 100%;
  border-radius: 8px;
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
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
