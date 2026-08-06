<script setup>
import { formatCurrency } from '@/utils/currencyFormatter';

defineProps({
  loading: { type: Boolean, default: false },
  summaryStats: {
    type: Object,
    default: () => ({
      frozen_capital: 0,
      total_products: 0,
      count_a: 0,
      count_b: 0,
      count_c: 0,
    }),
  },
});
</script>

<template>
  <VRow class="ma-0 mx-n1 mb-5 mt-2" dense>
    <!-- KPI 1: Capital Inmovilizado -->
    <VCol cols="12" sm="6" md="4" class="pa-1">
      <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
        <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-error), 0.1), transparent)"></div>
        <VCardText class="pa-5 relative-content">
          <template v-if="loading">
            <VSkeletonLoader type="avatar, heading, text" />
          </template>
          <template v-else>
            <div class="d-flex align-center justify-space-between mb-4">
              <VAvatar color="error" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon icon="tabler-lock-square" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Capital Inmovilizado</span>
                <h4 class="text-h4 font-weight-black mt-1 text-error">{{ formatCurrency(summaryStats.frozen_capital) }}</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
            <span class="text-caption font-weight-medium text-medium-emphasis">Total atrapado en Stock Muerto</span>
          </template>
        </VCardText>
        <div class="accent-border" style="background-color: rgb(var(--v-theme-error))"></div>
      </VCard>
    </VCol>

    <!-- KPI 2: Productos Afectados -->
    <VCol cols="12" sm="6" md="4" class="pa-1">
      <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
        <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-primary), 0.1), transparent)"></div>
        <VCardText class="pa-5 relative-content">
          <template v-if="loading">
            <VSkeletonLoader type="avatar, heading, text" />
          </template>
          <template v-else>
            <div class="d-flex align-center justify-space-between mb-4">
              <VAvatar color="primary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon icon="tabler-box" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Productos Afectados</span>
                <h4 class="text-h4 font-weight-black mt-1">{{ summaryStats.total_products }} SKUs</h4>
              </div>
            </div>
            <VDivider class="mb-3 opacity-20" />
            <span class="text-caption font-weight-medium text-medium-emphasis">Items sin ventas y con existencias</span>
          </template>
        </VCardText>
        <div class="accent-border" style="background-color: rgb(var(--v-theme-primary))"></div>
      </VCard>
    </VCol>

    <!-- KPI 3: Distribución A/B/C -->
    <VCol cols="12" sm="12" md="4" class="pa-1">
      <VCard class="stats-card rounded-lg border shadow-sm overflow-hidden h-full position-relative">
        <div class="card-bg-decoration" style="background: linear-gradient(45deg, rgba(var(--v-theme-secondary), 0.08), transparent)"></div>
        <VCardText class="pa-5 relative-content">
          <template v-if="loading">
            <VSkeletonLoader type="avatar, heading, text" />
          </template>
          <template v-else>
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar color="secondary" variant="tonal" size="48" rounded="lg" class="elevation-1">
                <VIcon icon="tabler-chart-bar" size="26" />
              </VAvatar>
              <div class="text-right">
                <span class="text-overline font-weight-bold text-disabled" style="letter-spacing: 1px !important">Distribución de Ventas</span>
                <h4 class="text-h4 font-weight-black mt-1">{{ summaryStats.total_products }}</h4>
              </div>
            </div>
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
          </template>
        </VCardText>
        <div class="accent-border" style="background-color: rgb(var(--v-theme-secondary))"></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
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
