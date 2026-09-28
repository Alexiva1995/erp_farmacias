<script setup>
import { computed } from 'vue'

const props = defineProps({
  /** Datos del dashboard — reactive object desde el store */
  dashboardData: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const formatMoney = val => `$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const formatNumber = val => Number(val).toLocaleString('en-US')

/**
 * Totales del horizonte de vencimientos — memoizado.
 * Antes vivía como .reduce() inline en el template (re-evaluaba en cada render).
 */
const horizonTotals = computed(() => {
  return props.dashboardData.horizon.reduce(
    (acc, b) => ({
      units: acc.units + parseFloat(b.total_units ?? 0),
      value: acc.value + parseFloat(b.total_value ?? 0),
    }),
    { units: 0, value: 0 }
  )
})

const overstockTotals = computed(() => {
  return props.dashboardData.overstock.reduce(
    (acc, b) => {
      const stock = parseFloat(b.stock_actual ?? 0)
      const sales = parseFloat(b.venta_mensual_promedio ?? 0)
      const dio = sales > 0 ? (stock / (sales / 30)) : 0

      return {
        units: acc.units + parseFloat(b.excedente_proyectado ?? 0),
        cost: acc.cost + parseFloat(b.costo_excedente ?? 0),
        totalDio: acc.totalDio + dio,
        countDio: sales > 0 ? acc.countDio + 1 : acc.countDio,
      }
    },
    { units: 0, cost: 0, totalDio: 0, countDio: 0 }
  )
})

const avgDio = computed(() => {
  if (!overstockTotals.value.countDio) return 0
  return Math.round(overstockTotals.value.totalDio / overstockTotals.value.countDio)
})

/** Definición declarativa de cada KPI card con micro-indicadores */
const kpiCards = computed(() => {
  const trend = props.dashboardData.kpis?.cost_trend_pct ?? 0
  const isTrendUp = trend > 0

  return [
    {
      title: 'Vencido en Mes Actual',
      mainValue: `${formatNumber(props.dashboardData.kpis?.total_units_expired_month ?? 0)} U.`,
      subValue: formatMoney(props.dashboardData.kpis?.total_cost_merma_month ?? 0),
      trendText: trend !== 0 ? `${isTrendUp ? '↑ +' : '↓ '}${trend}% vs mes ant.` : '— Estable vs mes ant.',
      trendColor: isTrendUp ? 'error' : 'success',
      trendIcon: isTrendUp ? 'tabler-trending-up' : 'tabler-trending-down',
      icon: 'tabler-package-off',
      color: 'error',
      desc: 'Pérdida registrada acumulada',
    },
    {
      title: 'Stock en Riesgo (<6m)',
      mainValue: `${formatNumber(horizonTotals.value.units)} U.`,
      subValue: `= ${formatMoney(horizonTotals.value.value)}`,
      trendText: `Cobertura prom: ${avgDio.value} días`,
      trendColor: avgDio.value > 60 ? 'warning' : 'info',
      trendIcon: 'tabler-calendar-time',
      icon: 'tabler-alert-triangle',
      color: 'warning',
      desc: 'Próximos 6 meses de caducidad',
    },
    {
      title: 'Excedente Proyectado',
      mainValue: `${formatNumber(overstockTotals.value.units)} U.`,
      subValue: `= ${formatMoney(overstockTotals.value.cost)}`,
      trendText: `${props.dashboardData.overstock.filter(i => i.has_overstock_risk).length} SKUs con sobrestock`,
      trendColor: 'warning',
      trendIcon: 'tabler-alert-circle',
      icon: 'tabler-chart-bar-off',
      color: 'info',
      desc: 'Unidades que superan la venta',
    },
    {
      title: 'Costo FEFO en Riesgo',
      mainValue: formatMoney(overstockTotals.value.cost),
      subValue: 'Pérdida directa proyectada',
      trendText: 'Requiere acción comercial inmediata',
      trendColor: 'error',
      trendIcon: 'tabler-flame',
      icon: 'tabler-cash-off',
      color: 'secondary',
      desc: 'Capital estancado en riesgo',
    },
  ]
})
</script>

<template>
  <VRow class="mb-4">
    <VCol
      v-for="(kpi, idx) in kpiCards"
      :key="idx"
      cols="12"
      sm="6"
      md="3"
    >
      <VCard class="rounded-lg border shadow-sm kpi-card h-100">
        <VCardText class="pa-4 d-flex align-center">

          <!-- Skeleton loader mientras carga -->
          <template v-if="loading">
            <VSkeletonLoader
              type="avatar"
              class="me-4 rounded-lg"
              width="48"
              height="48"
            />
            <div class="flex-grow-1">
              <VSkeletonLoader type="text" width="70%" class="mb-1" />
              <VSkeletonLoader type="heading" width="90%" class="mb-1" />
              <VSkeletonLoader type="text" width="55%" />
            </div>
          </template>

          <!-- Datos reales -->
          <template v-else>
            <VAvatar
              :color="kpi.color"
              variant="tonal"
              size="48"
              rounded="lg"
              class="me-4 flex-shrink-0"
            >
              <VIcon :icon="kpi.icon" size="24" />
            </VAvatar>

            <div class="overflow-hidden flex-grow-1">
              <p class="text-caption text-disabled mb-0 font-weight-bold kpi-title">
                {{ kpi.title }}
              </p>
              <h3 class="text-h5 font-weight-black mb-0 text-truncate">
                {{ kpi.mainValue }}
              </h3>
              <p class="text-xs font-weight-bold text-medium-emphasis mb-1 mt-0">
                {{ kpi.subValue }}
              </p>
              <!-- Microindicador de tendencia / DIO -->
              <div class="d-flex align-center gap-1">
                <VIcon :icon="kpi.trendIcon" size="14" :color="kpi.trendColor" />
                <span :class="`text-super-xs font-weight-bold text-${kpi.trendColor}`">
                  {{ kpi.trendText }}
                </span>
              </div>
            </div>
          </template>

        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.kpi-title {
  max-width: 160px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kpi-card {
  transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.kpi-card:hover {
  box-shadow: 0 4px 20px rgba(var(--v-theme-on-surface), 0.08) !important;
  transform: translateY(-2px);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}
</style>
