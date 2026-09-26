<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import { formatCurrency } from '@/utils/currencyFormatter';

const props = defineProps({
  paretoCurve: {
    type: Array,
    default: () => [],
  },
  paretoInflection: {
    type: Object,
    default: () => ({
      point_80: { count: 0, sku_pct: 0, sales_pct: 0 },
      point_95: { count: 0, sku_pct: 0, sales_pct: 0 },
    }),
  },
  totalProducts: {
    type: Number,
    default: 0,
  },
  totalSales: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  selectedClass: {
    type: String,
    default: null,
  },
});

const emit = defineEmits(['filter-class']);

const series = computed(() => {
  if (!props.paretoCurve || props.paretoCurve.length === 0) {
    return [];
  }

  const dataPoints = props.paretoCurve.map((point) => ({
    x: `${point.sku_pct}% (${point.sku_index} prods)`,
    y: point.sales_pct,
    sales_accum: point.sales_accum,
    sku_index: point.sku_index,
    sku_pct: point.sku_pct,
    class: point.class,
  }));

  return [
    {
      name: '% Ventas Acumuladas',
      data: dataPoints,
    },
  ];
});

const chartOptions = computed(() => {
  const inflection80 = props.paretoInflection?.point_80;

  return {
    chart: {
      type: 'area',
      height: 260,
      toolbar: { show: false },
      zoom: { enabled: false },
      sparkline: { enabled: false },
      parentHeightOffset: 0,
      background: 'transparent',
      events: {
        dataPointSelection: (event, chartContext, config) => {
          const point = props.paretoCurve[config.dataPointIndex];
          if (point && point.class) {
            emit('filter-class', point.class);
          }
        },
        markerClick: (event, chartContext, { dataPointIndex }) => {
          const point = props.paretoCurve[dataPointIndex];
          if (point && point.class) {
            emit('filter-class', point.class);
          }
        },
      },
    },
    colors: ['#0D9488'],
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.35,
        opacityTo: 0.05,
        stops: [0, 90, 100],
      },
    },
    stroke: {
      curve: 'smooth',
      width: 2.5,
    },
    markers: {
      size: 0,
      hover: {
        size: 5,
        sizeOffset: 2,
      },
    },
    grid: {
      borderColor: 'rgba(var(--v-border-color), 0.08)',
      strokeDashArray: 4,
      padding: {
        top: 10,
        right: 20,
        bottom: 0,
        left: 10,
      },
    },
    xaxis: {
      type: 'category',
      labels: {
        show: true,
        rotate: -30,
        rotateAlways: false,
        hideOverlappingLabels: true,
        style: {
          fontSize: '10px',
          colors: 'rgba(var(--v-theme-on-surface), 0.6)',
        },
      },
      axisBorder: { show: false },
      axisTicks: { show: false },
      title: {
        text: 'Acumulado de Productos (SKUs)',
        style: {
          fontSize: '11px',
          fontWeight: 600,
          color: 'rgba(var(--v-theme-on-surface), 0.5)',
        },
        offsetY: 75,
      },
    },
    yaxis: {
      min: 0,
      max: 100,
      tickAmount: 5,
      labels: {
        formatter: (val) => `${Math.round(val)}%`,
        style: {
          fontSize: '10px',
          colors: 'rgba(var(--v-theme-on-surface), 0.6)',
        },
      },
      title: {
        text: '% Ventas Acumuladas',
        style: {
          fontSize: '11px',
          fontWeight: 600,
          color: 'rgba(var(--v-theme-on-surface), 0.5)',
        },
      },
    },
    annotations: {
      yaxis: [
        {
          y: 80,
          borderColor: '#10B981',
          strokeDashArray: 3,
          borderWidth: 1.5,
          label: {
            borderColor: '#10B981',
            style: {
              color: '#fff',
              background: '#10B981',
              fontSize: '10px',
              fontWeight: 700,
            },
            text: 'Corte Pareto 80% (Zona A)',
            position: 'left',
          },
        },
        {
          y: 95,
          borderColor: '#F59E0B',
          strokeDashArray: 3,
          borderWidth: 1.5,
          label: {
            borderColor: '#F59E0B',
            style: {
              color: '#fff',
              background: '#F59E0B',
              fontSize: '10px',
              fontWeight: 700,
            },
            text: 'Corte Pareto 95% (Zona B)',
            position: 'left',
          },
        },
      ],
    },
    tooltip: {
      theme: 'dark',
      custom: ({ series, seriesIndex, dataPointIndex, w }) => {
        const point = props.paretoCurve[dataPointIndex];
        if (!point) return '';
        
        return `
          <div class="pa-2 text-caption" style="font-family: inherit;">
            <div class="font-weight-bold mb-1 d-flex align-center justify-space-between gap-2">
              <span>Productos: ${point.sku_index} (${point.sku_pct}%)</span>
              <span class="badge px-1 py-0.5 rounded text-uppercase" style="background: ${point.class === 'A' ? '#10B981' : (point.class === 'B' ? '#F59E0B' : '#9CA3AF')}; color: #fff; font-size: 9px; font-weight: 800;">Clase ${point.class}</span>
            </div>
            <div>Ventas Acumuladas: <strong>${point.sales_pct}%</strong></div>
            <div class="text-disabled">Monto: ${formatCurrency(point.sales_accum)}</div>
          </div>
        `;
      },
    },
  };
});
</script>

<template>
  <VCard class="mb-4 rounded-lg border shadow-sm bg-surface overflow-hidden">
    <VCardText class="pa-4">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="32" rounded="lg">
            <VIcon icon="tabler-chart-dots" size="18" />
          </VAvatar>
          <div>
            <h3 class="text-subtitle-2 font-weight-bold text-high-emphasis mb-0 d-flex align-center gap-1.5">
              Curva de Pareto 80/15/5 (Cobertura Acumulada de Ventas)
            </h3>
            <span class="text-caption text-medium-emphasis">
              Identifica la concentración de valor: el punto exacto donde pocos productos concentran la mayor facturación.
            </span>
          </div>
        </div>

        <!-- Badges de Zonas Pareto -->
        <div class="d-flex align-center gap-1.5">
          <VChip
            size="small"
            color="success"
            :variant="selectedClass === 'A' ? 'flat' : 'tonal'"
            class="font-weight-medium cursor-pointer transition-all"
            :class="{ 'elevation-2': selectedClass === 'A' }"
            @click="emit('filter-class', 'A')"
          >
            <VIcon v-if="selectedClass === 'A'" icon="tabler-check" size="14" class="me-1" />
            <span class="font-weight-bold me-1">Zona A (80%)</span>
            <span class="text-caption">0 - {{ paretoInflection?.point_80?.sku_pct || 0 }}% prods</span>
          </VChip>
          <VChip
            size="small"
            color="warning"
            :variant="selectedClass === 'B' ? 'flat' : 'tonal'"
            class="font-weight-medium cursor-pointer transition-all"
            :class="{ 'elevation-2': selectedClass === 'B' }"
            @click="emit('filter-class', 'B')"
          >
            <VIcon v-if="selectedClass === 'B'" icon="tabler-check" size="14" class="me-1" />
            <span class="font-weight-bold me-1">Zona B (15%)</span>
            <span class="text-caption">{{ paretoInflection?.point_80?.sku_pct || 0 }}% - {{ paretoInflection?.point_95?.sku_pct || 0 }}%</span>
          </VChip>
          <VChip
            size="small"
            color="secondary"
            :variant="selectedClass === 'C' ? 'flat' : 'tonal'"
            class="font-weight-medium cursor-pointer transition-all"
            :class="{ 'elevation-2': selectedClass === 'C' }"
            @click="emit('filter-class', 'C')"
          >
            <VIcon v-if="selectedClass === 'C'" icon="tabler-check" size="14" class="me-1" />
            <span class="font-weight-bold me-1">Zona C (5%)</span>
            <span class="text-caption">Resto ({{ 100 - (paretoInflection?.point_95?.sku_pct || 0) }}%)</span>
          </VChip>
        </div>
      </div>

      <!-- Banner de Hallazgo Clave (Punto de Inflexión) -->
      <div
        v-if="paretoInflection?.point_80?.count > 0"
        class="pa-2.5 px-3 rounded-lg mb-2 d-flex align-center justify-space-between flex-wrap gap-2 border"
        style="background: rgba(var(--v-theme-primary), 0.04); border-color: rgba(var(--v-theme-primary), 0.15) !important;"
      >
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-bulb" color="primary" size="18" />
          <span class="text-caption text-high-emphasis">
            <strong>Punto de Inflexión Pareto:</strong> El <strong>{{ paretoInflection.point_80.sku_pct }}%</strong> de los productos (<strong>{{ paretoInflection.point_80.count }} SKUs</strong> de {{ totalProducts }}) genera el <strong>{{ paretoInflection.point_80.sales_pct }}%</strong> del valor total facturado ({{ formatCurrency(totalSales * 0.8) }}).
          </span>
        </div>
        <span class="text-caption text-primary font-weight-bold">Regla 80/20 Aplicada</span>
      </div>

      <!-- Gráfico ApexCharts -->
      <div v-if="loading" class="d-flex align-center justify-center py-12">
        <VProgressCircular indeterminate color="primary" size="32" />
      </div>
      <div v-else-if="series.length > 0" class="pareto-chart-container">
        <VueApexCharts
          type="area"
          height="240"
          :options="chartOptions"
          :series="series"
        />
      </div>
      <div v-else class="text-center py-8 text-medium-emphasis">
        <span class="text-caption">No hay suficientes datos de ventas para graficar la curva de Pareto en el período seleccionado.</span>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.pareto-chart-container :deep(.apexcharts-canvas) {
  margin: 0 auto;
}
</style>
