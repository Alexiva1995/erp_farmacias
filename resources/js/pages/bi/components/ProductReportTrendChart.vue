<script setup>
// Componente: Gráfico de Tendencias Semanales (Ventas vs Compras) con sincronización de tema
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useBiThemeColors } from '@/composables/useBiThemeColors';

const props = defineProps({
  trendData: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  groups: {
    type: Array,
    default: () => [],
  },
  selectedGroup: {
    type: [Number, String, null],
    default: null,
  },
});

const emit = defineEmits(['update:selectedGroup']);

const { colors } = useBiThemeColors();

const safeTrendData = computed(() => (Array.isArray(props.trendData) ? props.trendData : []));

// Métricas de Brecha Compras vs Ventas
const totalTrendSold = computed(() =>
  safeTrendData.value.reduce((sum, d) => sum + Number(d?.sold ?? 0), 0)
);

const totalTrendPurchased = computed(() =>
  safeTrendData.value.reduce((sum, d) => sum + Number(d?.purchased ?? 0), 0)
);

const trendGap = computed(() =>
  totalTrendPurchased.value - totalTrendSold.value
);

const trendChartOptions = computed(() => ({
  chart: {
    type: 'line',
    toolbar: { show: false },
    zoom: { enabled: false },
    fontFamily: 'inherit',
    background: 'transparent',
  },
  dataLabels: { enabled: false },
  stroke: { width: [3, 3], curve: 'smooth' },
  markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
  colors: [colors.value.primary, colors.value.warning],
  legend: {
    position: 'top',
    horizontalAlign: 'right',
    offsetY: -10,
    fontSize: '12px',
    labels: { colors: colors.value.onSurface },
  },
  labels: safeTrendData.value.map(d => {
    if (!d?.week) return '';
    const parts = d.week.split('-');
    return parts.length > 1 ? `S${parts[1]}` : d.week;
  }),
  xaxis: {
    title: { text: 'Semana del Año', style: { color: colors.value.onSurface, fontSize: '11px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: {
      hideOverlappingLabels: true,
      rotate: -45,
      rotateAlways: false,
      style: { fontSize: '11px', colors: colors.value.onSurface },
    },
  },
  yaxis: {
    title: { text: 'Cantidad de Unidades', style: { color: colors.value.onSurface, fontSize: '11px' } },
    labels: {
      formatter: (val) => Math.trunc(val).toLocaleString(),
      style: { fontSize: '11px', colors: colors.value.onSurface },
    },
  },
  grid: { strokeDashArray: 4, borderColor: 'rgba(var(--v-theme-on-surface), 0.12)' },
  tooltip: {
    theme: colors.value.isDark ? 'dark' : 'light',
  },
}));

const trendChartSeries = computed(() => [
  { name: 'Ventas (Und)', type: 'line', data: safeTrendData.value.map(d => Number(d?.sold ?? 0)) },
  { name: 'Compras (Und)', type: 'line', data: safeTrendData.value.map(d => Number(d?.purchased ?? 0)) },
]);
</script>

<template>
  <VCard border class="rounded-lg overflow-hidden shadow-sm h-100">
    <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between flex-wrap gap-3 bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar size="32" color="primary" variant="tonal" class="rounded">
          <VIcon icon="tabler-chart-line" size="18" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis">Tendencias: Ventas vs Compras</div>
          <div class="text-caption text-medium-emphasis">Balance comparativo de unidades transaccionadas por semana</div>
        </div>
      </div>

      <!-- Resumen de brecha de unidades y filtro de grupo -->
      <div class="d-flex align-center gap-3 flex-wrap">
        <div v-if="safeTrendData.length" class="d-flex align-center gap-2 px-3 py-1 bg-surface border rounded-lg">
          <div class="text-caption">
            Ventas: <strong class="text-primary">{{ totalTrendSold.toLocaleString() }}</strong> | Compras: <strong class="text-warning">{{ totalTrendPurchased.toLocaleString() }}</strong>
          </div>
          <VChip
            size="x-small"
            :color="trendGap >= 0 ? 'warning' : 'info'"
            variant="tonal"
            label
            class="font-weight-black"
          >
            {{ trendGap >= 0 ? `+${trendGap.toLocaleString()} Excedente` : `${trendGap.toLocaleString()} Brecha` }}
          </VChip>
        </div>

        <div style="width: 260px; max-width: 100%;">
          <AppAutocomplete
            :model-value="selectedGroup"
            :items="groups"
            item-title="name"
            item-value="id"
            placeholder="Filtrar por Grupo"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:selectedGroup', $event)"
          />
        </div>
      </div>
    </VCardTitle>

    <VCardText class="pa-4">
      <!-- Skeleton mientras carga -->
      <div v-if="loading" class="skeleton-chart-pulse" style="height: 280px;" />

      <!-- Gráfico con datos -->
      <VueApexCharts
        v-else-if="safeTrendData.length"
        height="280"
        :options="trendChartOptions"
        :series="trendChartSeries"
      />

      <!-- Estado vacío -->
      <div v-else class="text-center pa-10 text-medium-emphasis">
        <VIcon icon="tabler-chart-line" size="40" class="mb-2 opacity-30" />
        <div class="text-sm font-weight-bold">Sin datos de tendencia</div>
        <div class="text-xs text-disabled">No hay movimiento registrado en el período seleccionado.</div>
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
