<script setup>
import { computed } from "vue";

const props = defineProps({
  averageAmount:    { type: [String, Number], required: true },
  lastMonthAverage: { type: [String, Number], required: true },
  percentageChange: { type: [String, Number], default: 0 },
  isPositive:       { type: Boolean, default: true },
});

const changeClass = computed(() => props.isPositive ? "text-success" : "text-error");
const changeColor = computed(() => props.isPositive ? "success" : "error");
const changeIcon  = computed(() => props.isPositive ? "tabler-trending-up" : "tabler-trending-down");
</script>

<template>
  <VRow class="ma-0 mx-n1">
    <!-- KPI 1: Promedio Actual -->
    <VCol cols="12" sm="6" lg="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration bg-primary-opacity-1"></div>
        <VCardText class="pa-5 relative-content h-100 d-flex flex-column">
          <div class="d-flex align-center justify-space-between mb-4">
            <VAvatar color="primary" variant="tonal" size="44" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-chart-bar" size="22" />
            </VAvatar>
            <VChip
              :color="changeColor"
              size="x-small"
              variant="flat"
              class="font-weight-black rounded-lg px-2"
            >
              <VIcon :icon="changeIcon" start size="12" />
              {{ props.percentageChange }}%
            </VChip>
          </div>
          <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Promedio Diario (Mes Actual)</div>
          <div class="text-h4 font-weight-black text-primary leading-tight">
            {{ props.averageAmount }} <span class="text-xs text-disabled font-weight-medium">USD</span>
          </div>
        </VCardText>
        <div class="accent-border bg-primary"></div>
      </VCard>
    </VCol>

    <!-- KPI 2: Promedio Mes Anterior -->
    <VCol cols="12" sm="6" lg="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration bg-secondary-opacity-1"></div>
        <VCardText class="pa-5 relative-content h-100 d-flex flex-column">
          <div class="d-flex align-center mb-4">
            <VAvatar color="secondary" variant="tonal" size="44" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-calendar-stats" size="22" />
            </VAvatar>
          </div>
          <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Promedio Diario (Mes Anterior)</div>
          <div class="text-h4 font-weight-black leading-tight">
            {{ props.lastMonthAverage }} <span class="text-xs text-disabled font-weight-medium">USD</span>
          </div>
        </VCardText>
        <div class="accent-border bg-secondary"></div>
      </VCard>
    </VCol>

    <!-- KPI 3: Variación -->
    <VCol cols="12" sm="6" lg="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative">
        <div class="card-bg-decoration" :class="`bg-${changeColor}-opacity-1`"></div>
        <VCardText class="pa-5 relative-content h-100 d-flex flex-column">
          <div class="d-flex align-center mb-4">
            <VAvatar :color="changeColor" variant="tonal" size="44" class="rounded-lg shadow-sm">
              <VIcon :icon="changeIcon" size="22" />
            </VAvatar>
          </div>
          <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Variación vs Mes Anterior</div>
          <div class="text-h4 font-weight-black leading-tight" :class="changeClass">
            {{ props.isPositive ? '+' : '' }}{{ props.percentageChange }}%
          </div>
        </VCardText>
        <div class="accent-border" :class="`bg-${changeColor}`"></div>
      </VCard>
    </VCol>

    <!-- KPI 4: Estado de Tendencia -->
    <VCol cols="12" sm="6" lg="3" class="pa-1">
      <VCard class="stats-card h-100 border-0 overflow-hidden shadow-sm position-relative" :class="`bg-${changeColor}-lighten-5`">
        <div class="card-bg-decoration" :class="`bg-${changeColor}-opacity-1`"></div>
        <VCardText class="pa-5 relative-content h-100 d-flex flex-column">
          <div class="d-flex align-center mb-4">
            <VAvatar :color="changeColor" variant="tonal" size="44" class="rounded-lg shadow-sm">
              <VIcon icon="tabler-activity" size="22" />
            </VAvatar>
          </div>
          <div class="text-super-xs text-disabled text-uppercase font-weight-black mb-1">Tendencia General</div>
          <div class="text-h5 font-weight-black" :class="changeClass">
            {{ props.isPositive ? 'VENTAS AL ALZA' : 'VENTAS A LA BAJA' }}
          </div>
        </VCardText>
        <div class="accent-border" :class="`bg-${changeColor}`"></div>
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
  block-size: 80px;
  filter: blur(40px);
  inline-size: 80px;
  inset-block-start: -15px;
  inset-inline-end: -15px;
  pointer-events: none;
  opacity: 0.6;
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

.bg-primary-opacity-1 { background: rgba(var(--v-theme-primary), 0.1); }
.bg-secondary-opacity-1 { background: rgba(var(--v-theme-secondary), 0.1); }
.bg-success-opacity-1 { background: rgba(var(--v-theme-success), 0.1); }
.bg-error-opacity-1 { background: rgba(var(--v-theme-error), 0.1); }

.text-super-xs {
  font-size: 0.625rem !important;
  letter-spacing: 0.05em !important;
  line-height: normal;
}

.leading-tight {
  line-height: 1.2 !important;
}
</style>
