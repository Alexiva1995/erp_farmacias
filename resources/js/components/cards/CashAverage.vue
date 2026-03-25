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
  <VRow>
    <!-- KPI 1: Promedio Actual -->
    <VCol cols="12" sm="6" lg="3">
      <VCard class="kpi-card" elevation="0">
        <VCardText class="pa-5">
          <div class="d-flex align-center justify-space-between mb-3">
            <VAvatar color="primary" variant="tonal" size="44" rounded-lg>
              <VIcon icon="tabler-chart-bar" size="22" />
            </VAvatar>
            <VChip
              :color="changeColor"
              size="small"
              variant="tonal"
              label
            >
              <VIcon :icon="changeIcon" start size="14" />
              {{ props.percentageChange }}%
            </VChip>
          </div>
          <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Promedio Diario (Mes Actual)</div>
          <div class="text-h4 font-weight-black text-primary">{{ props.averageAmount }} <span class="text-body-2 text-disabled">USD</span></div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- KPI 2: Promedio Mes Anterior -->
    <VCol cols="12" sm="6" lg="3">
      <VCard class="kpi-card" elevation="0">
        <VCardText class="pa-5">
          <div class="d-flex align-center mb-3">
            <VAvatar color="secondary" variant="tonal" size="44" rounded-lg>
              <VIcon icon="tabler-calendar-stats" size="22" />
            </VAvatar>
          </div>
          <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Promedio Diario (Mes Anterior)</div>
          <div class="text-h4 font-weight-black">{{ props.lastMonthAverage }} <span class="text-body-2 text-disabled">USD</span></div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- KPI 3: Variación -->
    <VCol cols="12" sm="6" lg="3">
      <VCard class="kpi-card" elevation="0">
        <VCardText class="pa-5">
          <div class="d-flex align-center mb-3">
            <VAvatar :color="changeColor" variant="tonal" size="44" rounded-lg>
              <VIcon :icon="changeIcon" size="22" />
            </VAvatar>
          </div>
          <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Variación vs Mes Anterior</div>
          <div class="text-h4 font-weight-black" :class="changeClass">
            {{ props.isPositive ? '+' : '' }}{{ props.percentageChange }}%
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- KPI 4: Estado de Tendencia -->
    <VCol cols="12" sm="6" lg="3">
      <VCard class="kpi-card" :color="changeColor" variant="tonal" elevation="0">
        <VCardText class="pa-5">
          <div class="d-flex align-center mb-3">
            <VAvatar :color="changeColor" variant="elevated" size="44" rounded-lg>
              <VIcon icon="tabler-activity" size="22" class="text-white" />
            </VAvatar>
          </div>
          <div class="text-caption text-disabled text-uppercase font-weight-bold mb-1">Tendencia General</div>
          <div class="text-h5 font-weight-black" :class="changeClass">
            {{ props.isPositive ? '🟢 Ventas al alza' : '🔴 Ventas a la baja' }}
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.kpi-card {
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 8px !important;
  transition: box-shadow 0.2s ease;
}

.kpi-card:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 8%) !important;
}
</style>
