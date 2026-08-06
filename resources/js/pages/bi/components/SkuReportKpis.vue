<script setup>
import { computed } from 'vue';

const props = defineProps({
  summaryStats: {
    type: Object,
    default: () => ({
      global_margin_real: 0,
      total_discounts: 0,
      total_loss: 0,
      critical_skus: 0
    })
  },
  loading: Boolean
});

const formatPercent = (val) => Number(val || 0).toFixed(2) + '%';
const formatMoney = (val) => '$' + Number(val || 0).toFixed(2);

const kpis = computed(() => [
  {
    title: 'Margen Real Global',
    value: formatPercent(props.summaryStats.global_margin_real),
    color: props.summaryStats.global_margin_real > 0 ? 'success' : 'error',
    icon: 'tabler-percentage',
    desc: 'Neto - Mermas'
  },
  {
    title: 'Impacto Desc.',
    value: formatMoney(props.summaryStats.total_discounts),
    color: 'warning',
    icon: 'tabler-tag',
    desc: 'Dinero cedido en ofertas'
  },
  {
    title: 'Pérdida por Mermas',
    value: formatMoney(props.summaryStats.total_loss),
    color: props.summaryStats.total_loss > 0 ? 'error' : 'secondary',
    icon: 'tabler-trash',
    desc: 'Costo total vencidos'
  },
  {
    title: 'Alertas Críticas',
    value: props.summaryStats.critical_skus || 0,
    color: props.summaryStats.critical_skus > 0 ? 'error' : 'success',
    icon: 'tabler-alert-triangle',
    desc: 'SKUs en Pérdida'
  }
]);
</script>

<template>
  <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
    <VCol v-for="(kpi, index) in kpis" :key="index" cols="6" md="3" class="pa-1">
      <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
        <div class="card-bg-decoration" :style="{ background: `linear-gradient(45deg, rgba(var(--v-theme-${kpi.color}), 0.1), transparent)` }"></div>
        <VCardText class="pa-5 relative-content">
          <div v-if="loading" class="d-flex flex-column gap-2 py-2">
            <div class="d-flex justify-space-between align-center">
              <div class="w-25 bg-secondary-light animate-pulse rounded" style="height: 32px;"></div>
              <div class="w-50 bg-secondary-light animate-pulse rounded" style="height: 24px;"></div>
            </div>
            <div class="w-100 bg-secondary-light animate-pulse rounded mt-3" style="height: 10px;"></div>
          </div>
          <div v-else>
            <div class="d-flex align-center justify-space-between mb-4">
              <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon :icon="kpi.icon" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important; line-height: 1.2; display: block">{{ kpi.title }}</span>
                <h4 class="text-h4 font-weight-black mt-1">{{ kpi.value }}</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
          </div>
          <div class="d-flex align-center justify-space-between">
            <span class="text-caption font-weight-medium text-medium-emphasis">{{ kpi.desc }}</span>
            <VIcon icon="tabler-chart-pie" size="16" :color="kpi.color" class="opacity-50" />
          </div>
        </VCardText>
        <div class="accent-border" :style="{ backgroundColor: `rgb(var(--v-theme-${kpi.color}))` }"></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .5; }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
.bg-secondary-light {
  background-color: rgba(var(--v-theme-secondary), 0.15);
}
</style>
