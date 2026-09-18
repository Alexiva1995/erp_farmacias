<script setup>
defineProps({
  summary: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-VE", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
  }).format(amount || 0);
};
</script>

<template>
  <VRow class="ma-0 mx-n1 mb-5" dense>
    <!-- INGRESOS -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration bg-success-opacity-1"></div>
        <VCardText class="pa-4 relative-content h-100 d-flex flex-column justify-space-between">
          <div class="d-flex align-center gap-3 mb-2">
            <VAvatar color="success" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-trending-up" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-black text-medium-emphasis uppercase letter-spacing-1">
                Ingresos Brutos
              </span>
              <span class="text-super-xs text-disabled">Ventas totales (USD)</span>
            </div>
          </div>
          <div v-if="!loading" class="mt-2">
            <span class="text-h5 font-weight-black text-success leading-tight">
              {{ formatCurrency(summary.income?.amount) }}
            </span>
          </div>
          <VSkeletonLoader v-else type="text" class="mt-2" />
        </VCardText>
        <div class="accent-border bg-success"></div>
      </VCard>
    </VCol>

    <!-- COSTOS -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration bg-warning-opacity-1"></div>
        <VCardText class="pa-4 relative-content h-100 d-flex flex-column justify-space-between">
          <div class="d-flex align-center gap-3 mb-2">
            <VAvatar color="warning" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-package" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-black text-medium-emphasis uppercase letter-spacing-1">
                Costos de Venta
              </span>
              <span class="text-super-xs text-disabled">Costo mercancía vendida</span>
            </div>
          </div>
          <div v-if="!loading" class="mt-2">
            <span class="text-h5 font-weight-black text-warning leading-tight">
              -{{ formatCurrency(summary.costs?.amount) }}
            </span>
          </div>
          <VSkeletonLoader v-else type="text" class="mt-2" />
        </VCardText>
        <div class="accent-border bg-warning"></div>
      </VCard>
    </VCol>

    <!-- GASTOS -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration bg-error-opacity-1"></div>
        <VCardText class="pa-4 relative-content h-100 d-flex flex-column justify-space-between">
          <div class="d-flex align-center gap-3 mb-2">
            <VAvatar color="error" variant="tonal" size="44" class="rounded-lg">
              <VIcon icon="tabler-activity" size="24" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-black text-medium-emphasis uppercase letter-spacing-1">
                Gastos Operativos
              </span>
              <span class="text-super-xs text-disabled">Egresos operativos</span>
            </div>
          </div>
          <div v-if="!loading" class="mt-2">
            <span class="text-h5 font-weight-black text-error leading-tight">
              -{{ formatCurrency(summary.expenses?.amount) }}
            </span>
          </div>
          <VSkeletonLoader v-else type="text" class="mt-2" />
        </VCardText>
        <div class="accent-border bg-error"></div>
      </VCard>
    </VCol>

    <!-- UTILIDAD NETA -->
    <VCol cols="12" sm="6" md="3" class="pa-1">
      <VCard class="stats-card h-100 border overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration bg-info-opacity-1"></div>
        <VCardText class="pa-4 relative-content h-100 d-flex flex-column justify-space-between">
          <div class="d-flex align-center gap-3 mb-2">
            <VAvatar color="info" variant="tonal" size="44" class="rounded-lg">
              <VIcon
                :icon="summary.net_profit?.amount >= 0 ? 'tabler-pig-money' : 'tabler-chart-down'"
                color="info"
                size="24"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-xs font-weight-black text-medium-emphasis uppercase letter-spacing-1">
                Utilidad Neta
              </span>
              <span class="text-super-xs text-disabled">
                Margen {{ summary.margin_percentage != null ? `${summary.margin_percentage}%` : 'consolidado' }}
              </span>
            </div>
          </div>
          <div v-if="!loading" class="mt-2">
            <span
              class="text-h5 font-weight-black leading-tight"
              :class="summary.net_profit?.amount >= 0 ? 'text-info' : 'text-error'"
            >
              {{ formatCurrency(summary.net_profit?.amount) }}
            </span>
          </div>
          <VSkeletonLoader v-else type="text" class="mt-2" />
        </VCardText>
        <div class="accent-border bg-info"></div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.stats-card {
  border-radius: 8px !important;
  backdrop-filter: blur(8px);
  background: rgba(var(--v-theme-surface), 90%) !important;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.stats-card:hover {
  transform: translateY(-4px);
  background: rgba(var(--v-theme-surface), 98%) !important;
  box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.15) !important;
}

.card-bg-decoration {
  position: absolute;
  z-index: 0;
  border-radius: 50%;
  block-size: 60px;
  filter: blur(35px);
  inline-size: 60px;
  inset-block-start: -10px;
  inset-inline-end: -10px;
  pointer-events: none;
  opacity: 0.5;
}

.relative-content {
  position: relative;
  z-index: 1;
}

.accent-border {
  position: absolute;
  block-size: 100%;
  inline-size: 4px;
  inset-block-start: 0;
  inset-inline-start: 0;
  opacity: 0.7;
}

.bg-success-opacity-1 { background: rgba(var(--v-theme-success), 0.1); }
.bg-warning-opacity-1 { background: rgba(var(--v-theme-warning), 0.1); }
.bg-error-opacity-1 { background: rgba(var(--v-theme-error), 0.1); }
.bg-info-opacity-1 { background: rgba(var(--v-theme-info), 0.1); }

.text-super-xs { font-size: 0.65rem !important; }
.letter-spacing-widest { letter-spacing: 0.1em; }
</style>
