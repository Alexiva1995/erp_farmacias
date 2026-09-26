<script setup>
// Componente: Análisis ABC por Inmovilización de Stock & Diagnóstico de Obsolescencia
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const { formatCurrency } = useCurrencyConverter();

const props = defineProps({
  abcData: { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false    },
});

// Total de SKUs calculado en computed
const totalSkus = computed(() => props.abcData.reduce((sum, a) => sum + (a?.count ?? 0), 0));
const totalCapital = computed(() => props.abcData.reduce((sum, a) => sum + Number(a?.revenue ?? 0), 0));

const formatPercent = (val) => Number(val ?? 0).toFixed(1) + '%';

const skuPercent = (count) => {
  if (!totalSkus.value) return formatPercent(0);
  return formatPercent((count / totalSkus.value) * 100);
};

const getClassBadgeColor = (type) => {
  if (type === 'A') return 'success';
  if (type === 'B') return 'info';
  return 'warning';
};

const chartOptions = computed(() => ({
  chart: { type: 'donut', toolbar: { show: false } },
  labels: props.abcData.map(a => `Clase ${a?.type ?? '?'}`),
  colors: ['#28C76F', '#0EA5E9', '#F59E0B'],
  legend: { position: 'bottom', fontSize: '12px' },
  dataLabels: { enabled: true, dropShadow: { enabled: false } },
  plotOptions: {
    pie: {
      donut: {
        size: '72%',
        labels: {
          show: true,
          total: {
            show: true,
            label: 'Total SKUs',
            formatter: () => totalSkus.value.toLocaleString(),
          },
        },
      },
    },
  },
  stroke: { width: 2, colors: ['var(--v-theme-surface)'] },
}));

const chartSeries = computed(() => props.abcData.map(a => Number(a?.count ?? 0)));

// Capital inmóvil en Clase C
const classC = computed(() => props.abcData.find(a => a?.type === 'C') ?? null);
</script>

<template>
  <VCard border class="rounded-lg h-100 overflow-hidden shadow-sm">
    <VCardTitle class="pa-4 border-b d-flex align-center justify-space-between bg-surface">
      <div class="d-flex align-center">
        <VAvatar size="32" color="primary" variant="tonal" class="me-2 rounded">
          <VIcon icon="tabler-chart-donut" size="18" />
        </VAvatar>
        <div>
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis">Matriz ABC de Inventario</div>
          <div class="text-super-xs text-medium-emphasis">Segmentación de capital inmovilizado en stock</div>
        </div>
      </div>
      <span class="text-caption font-weight-bold text-primary">
        {{ formatCurrency(totalCapital) }}
      </span>
    </VCardTitle>

    <VCardText class="px-0 py-4 text-center">
      <!-- Skeleton de carga -->
      <div v-if="loading" class="skeleton-chart-pulse mx-4" style="height: 260px; border-radius: 8px;" />

      <!-- Gráfico -->
      <div v-else-if="abcData.length" class="px-2">
        <VueApexCharts
          height="260"
          :options="chartOptions"
          :series="chartSeries"
        />
      </div>
      <div v-else class="text-center pa-8 text-medium-emphasis">
        <VIcon icon="tabler-chart-donut" size="36" class="mb-2 opacity-30" />
        <div class="text-sm font-weight-bold">Sin datos ABC</div>
        <div class="text-xs text-disabled">No hay existencias con valor en este período.</div>
      </div>

      <!-- Tabla de clases -->
      <div class="mt-3 text-left px-4">
        <div
          v-for="abc in abcData"
          :key="abc?.type"
          class="d-flex justify-space-between mb-2 border-b pa-2 align-center rounded"
          :style="abc?.type === 'C' && (abc?.obsolete_value > 0) ? 'background: rgba(245, 158, 11, 0.04);' : ''"
        >
          <div class="d-flex align-center gap-2">
            <VChip
              :color="getClassBadgeColor(abc?.type)"
              variant="flat"
              size="x-small"
              class="font-weight-black"
              label
            >
              Clase {{ abc?.type }}
            </VChip>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-bold">{{ formatCurrency(abc?.revenue ?? 0) }}</span>
              <span class="text-super-xs text-medium-emphasis">
                {{ abc?.type === 'A' ? '80% del valor total' : abc?.type === 'B' ? '15% del valor total' : '5% del valor total' }}
              </span>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xs font-weight-black">{{ abc?.count }} SKUs</div>
            <div class="text-super-xs text-medium-emphasis">{{ skuPercent(abc?.count) }} del catálogo</div>
          </div>
        </div>

        <!-- Alerta de capital inmóvil en Clase C -->
        <div
          v-if="classC && classC.obsolete_value > 0"
          class="pa-2 mt-2 rounded border border-warning bg-warning-light d-flex align-center gap-2"
          style="background: rgba(245, 158, 11, 0.08);"
        >
          <VIcon icon="tabler-alert-triangle" size="18" color="warning" />
          <div class="text-super-xs text-warning-dark">
            <strong>{{ formatCurrency(classC.obsolete_value) }}</strong> en Clase C tiene &gt;90 días sin rotación ({{ classC.obsolete_count }} SKUs).
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.7rem !important;
  line-height: 1.2;
}

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
