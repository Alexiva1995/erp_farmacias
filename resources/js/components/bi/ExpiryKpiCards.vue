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
    (acc, b) => ({
      units: acc.units + parseFloat(b.excedente_proyectado ?? 0),
      cost: acc.cost + parseFloat(b.costo_excedente ?? 0),
    }),
    { units: 0, cost: 0 }
  )
})

/** Definición declarativa de cada KPI card */
const kpiCards = computed(() => [
  {
    title: 'Vencido (Mes)',
    mainValue: `${formatNumber(props.dashboardData.kpis.total_units_expired_month)} U.`,
    subValue: formatMoney(props.dashboardData.kpis.total_cost_merma_month),
    icon: 'tabler-package-off',
    color: 'error',
    desc: 'Pérdida total registrada',
  },
  {
    title: 'Stock Riesgo (<6m)',
    mainValue: `${formatNumber(horizonTotals.value.units)} U.`,
    subValue: `= ${formatMoney(horizonTotals.value.value)}`,
    icon: 'tabler-alert-triangle',
    color: 'warning',
    desc: 'Vencimiento próximo',
  },
  {
    title: 'Excedente Unidades',
    mainValue: `${formatNumber(overstockTotals.value.units)} U.`,
    subValue: `= ${formatMoney(overstockTotals.value.cost)}`,
    icon: 'tabler-chart-bar-off',
    color: 'info',
    desc: 'Sobre existencia proyectada',
  },
  {
    title: 'Costo Excedente',
    mainValue: formatMoney(overstockTotals.value.cost),
    subValue: 'Impacto total estimado',
    icon: 'tabler-cash-off',
    color: 'secondary',
    desc: 'Capital estancado',
  },
])
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

          <!-- Skeleton loader mientras carga — VSkeletonLoader es el componente correcto en Vuetify 3 -->
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

            <div class="overflow-hidden">
              <p class="text-caption text-disabled mb-0 font-weight-bold kpi-title">
                {{ kpi.title }}
              </p>
              <h3 class="text-h5 font-weight-black mb-0 text-truncate">
                {{ kpi.mainValue }}
              </h3>
              <p class="text-xs font-weight-bold text-medium-emphasis mb-0 mt-0">
                {{ kpi.subValue }}
              </p>
              <p class="text-super-xs text-disabled mb-0">
                {{ kpi.desc }}
              </p>
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
