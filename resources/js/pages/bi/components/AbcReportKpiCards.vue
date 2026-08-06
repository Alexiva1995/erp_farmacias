<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

defineProps({
  loading: { type: Boolean, default: false },
  selectedAnalysisType: { type: String, default: 'all' },
  summaryStats: {
    type: Object,
    default: () => ({
      total_volume: 0,
      aax_products: 0,
      avg_margin: 0,
      frozen_capital: 0,
      count_a: 0,
      count_b: 0,
      count_c: 0,
      critical_stockouts: 0,
      total_products: 0,
    }),
  },
});
</script>

<template>
  <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
    <!-- Skeletons durante carga -->
    <template v-if="loading">
      <VCol v-for="n in 4" :key="'skeleton-kpi-' + n" cols="6" class="pa-1 abc-kpi-col">
        <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full pa-5">
          <VSkeletonLoader type="list-item-avatar-two-line" class="bg-transparent" />
        </VCard>
      </VCol>
      <VCol cols="12" class="pa-1 abc-kpi-col-dist">
        <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full pa-5">
          <VSkeletonLoader type="list-item-two-line" class="bg-transparent" />
        </VCard>
      </VCol>
    </template>

    <!-- KPIs reales -->
    <template v-else>
      <VCol
        v-for="(kpi, index) in [
          {
            title: selectedAnalysisType === 'dead_stock' ? 'Capital Inmovilizado' : 'Ventas Globales',
            value: formatCurrency(selectedAnalysisType === 'dead_stock' ? summaryStats.frozen_capital : summaryStats.total_volume),
            color: selectedAnalysisType === 'dead_stock' ? 'error' : 'primary',
            icon: selectedAnalysisType === 'dead_stock' ? 'tabler-lock-square' : 'tabler-coin',
            desc: selectedAnalysisType === 'dead_stock' ? 'Dinero atrapado en stock' : 'Total facturado en el periodo'
          },
          {
            title: 'Prod. Estrella',
            value: summaryStats.aax_products,
            color: 'success',
            icon: 'tabler-star',
            desc: 'Clasificación AAX/AAY'
          },
          {
            title: 'Margen Global',
            value: summaryStats.avg_margin.toFixed(2) + '%',
            color: summaryStats.avg_margin > 0 ? 'primary' : 'error',
            icon: 'tabler-percentage',
            desc: 'Rentabilidad promedio'
          },
          {
            title: 'Quiebre Crítico',
            value: summaryStats.critical_stockouts,
            color: summaryStats.critical_stockouts > 0 ? 'error' : 'success',
            icon: 'tabler-alert-triangle',
            desc: 'Productos A/B sin stock'
          }
        ]"
        :key="index"
        cols="6"
        class="pa-1 abc-kpi-col"
      >
        <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
          <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)` }"></div>
          <VCardText class="pa-5 relative-content">
            <div class="d-flex align-center justify-space-between mb-4">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon :icon="kpi.icon" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">{{ kpi.title }}</span>
                <h4 class="text-h4 font-weight-black mt-1">{{ kpi.value }}</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
            <div class="d-flex align-center justify-space-between">
              <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
              <VIcon icon="tabler-chart-pie" size="16" :color="kpi.color" class="opacity-50" />
            </div>
          </VCardText>
          <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"></div>
        </VCard>
      </VCol>

      <!-- KPI: Distribución A/B/C -->
      <VCol cols="12" class="pa-1 abc-kpi-col-dist">
        <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
          <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-secondary), 0.08), transparent)"></div>
          <VCardText class="pa-5 relative-content">
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="secondary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon icon="tabler-chart-bar" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Distribución</span>
                <h4 class="text-h4 font-weight-black mt-1">{{ summaryStats.total_products }}</h4>
              </div>
            </div>

            <!-- Barra de distribución A/B/C -->
            <div class="d-flex rounded overflow-hidden mb-2" style="height:8px;gap:2px">
              <div
                :style="{ width: summaryStats.total_products > 0 ? (summaryStats.count_a / summaryStats.total_products * 100) + '%' : '0%', background: '#4CAF50' }"
                class="rounded-s"
              />
              <div
                :style="{ width: summaryStats.total_products > 0 ? (summaryStats.count_b / summaryStats.total_products * 100) + '%' : '0%', background: '#FF9800' }"
              />
              <div
                :style="{ width: summaryStats.total_products > 0 ? (summaryStats.count_c / summaryStats.total_products * 100) + '%' : '0%', background: '#9E9E9E' }"
                class="rounded-e flex-grow-1"
              />
            </div>

            <div class="d-flex justify-space-between">
              <span class="text-caption d-flex align-center gap-1">
                <span style="width:8px;height:8px;background:#4CAF50;border-radius:50%;display:inline-block"></span>
                A: <b>{{ summaryStats.count_a }}</b>
              </span>
              <span class="text-caption d-flex align-center gap-1">
                <span style="width:8px;height:8px;background:#FF9800;border-radius:50%;display:inline-block"></span>
                B: <b>{{ summaryStats.count_b }}</b>
              </span>
              <span class="text-caption d-flex align-center gap-1">
                <span style="width:8px;height:8px;background:#9E9E9E;border-radius:50%;display:inline-block"></span>
                C: <b>{{ summaryStats.count_c }}</b>
              </span>
            </div>
          </VCardText>
          <div class="accent-border" style="background-color: rgb(var(--v-theme-secondary))"></div>
        </VCard>
      </VCol>
    </template>
  </VRow>
</template>

<style scoped>
.abc-kpi-col {
  flex: 0 0 50%;
  max-width: 50%;
}

.abc-kpi-col-dist {
  flex: 0 0 100%;
  max-width: 100%;
}

@media (min-width: 960px) {
  .abc-kpi-col {
    flex: 0 0 20% !important;
    max-width: 20% !important;
  }
  .abc-kpi-col-dist {
    flex: 0 0 20% !important;
    max-width: 20% !important;
  }
}

.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 80%) !important;
  transition: all 0.3s ease;
}

.stats-card:hover {
  box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 8%) !important;
  transform: translateY(-5px);
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 100px;
  filter: blur(40px);
  inline-size: 100px;
  inset-block-start: -20px;
  inset-inline-end: -20px;
  pointer-events: none;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 70%;
  border-end-end-radius: 4px;
  border-start-end-radius: 4px;
  inline-size: 4px;
  inset-block-start: 15%;
  inset-inline-start: 0;
  opacity: 0.8;
}

.text-h4 {
  color: rgb(var(--v-theme-on-surface));
  letter-spacing: -0.5px !important;
}

.gap-1 { gap: 4px !important; }
</style>
