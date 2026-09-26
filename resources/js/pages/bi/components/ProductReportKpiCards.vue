<script setup>
// Componente: KPIs de abastecimiento del reporte de productos con diseño corporativo
import { computed } from 'vue';

const props = defineProps({
  quadrant4: {
    type: Object,
    default: () => ({ out_of_stock: 0, critical_stock: 0, avg_inventory_days: 0, estimated_30d_demand: 0 }),
  },
  paretoPercent: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

// Días de inventario redondeados
const avgDays = computed(() => Math.round(props.quadrant4.avg_inventory_days ?? 0));
const reorderDemand = computed(() => Number(props.quadrant4.estimated_30d_demand ?? 0).toLocaleString());
</script>

<template>
  <VRow class="mb-4">
    <!-- Out of Stock -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100 kpi-card">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 86px;">
          <VProgressCircular indeterminate color="error" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center justify-space-between">
          <div>
            <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Ruptura de Stock</div>
            <div class="text-h4 font-weight-bold text-error mt-1">{{ quadrant4.out_of_stock ?? 0 }}</div>
            <div class="text-super-xs text-medium-emphasis mt-1">SKUs agotados activos</div>
          </div>
          <VAvatar color="error" variant="tonal" rounded="lg" size="44">
            <VIcon icon="tabler-package-off" size="24" />
          </VAvatar>
        </div>
      </VCard>
    </VCol>

    <!-- Suministro Crítico & Proyección de Reorden -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100 kpi-card">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 86px;">
          <VProgressCircular indeterminate color="warning" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center justify-space-between">
          <div>
            <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Suministro Crítico (&lt;7d)</div>
            <div class="text-h4 font-weight-bold text-warning mt-1">{{ quadrant4.critical_stock ?? 0 }}</div>
            <div class="text-super-xs text-medium-emphasis mt-1">
              Reorden est. 30d: <strong class="text-high-emphasis">{{ reorderDemand }} unds</strong>
            </div>
          </div>
          <VAvatar color="warning" variant="tonal" rounded="lg" size="44">
            <VIcon icon="tabler-alert-triangle" size="24" />
          </VAvatar>
        </div>
      </VCard>
    </VCol>

    <!-- Eficiencia Pareto -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100 kpi-card">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 86px;">
          <VProgressCircular indeterminate color="primary" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center justify-space-between">
          <div>
            <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Concentración Pareto</div>
            <div class="text-h4 font-weight-bold text-primary mt-1">{{ paretoPercent }}%</div>
            <div class="text-super-xs text-medium-emphasis mt-1">SKUs concentran 80% del margen</div>
          </div>
          <VAvatar color="primary" variant="tonal" rounded="lg" size="44">
            <VIcon icon="tabler-chart-pie" size="24" />
          </VAvatar>
        </div>
      </VCard>
    </VCol>

    <!-- Días Prom. de Inventario -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm h-100 kpi-card">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 86px;">
          <VProgressCircular indeterminate color="info" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center justify-space-between">
          <div>
            <div class="text-caption font-weight-bold text-medium-emphasis text-uppercase">Cobertura Promedio</div>
            <div class="text-h4 font-weight-bold text-info mt-1">{{ avgDays }} <span class="text-subtitle-2 font-weight-medium">días</span></div>
            <div class="text-super-xs text-medium-emphasis mt-1">Rotación global estimada</div>
          </div>
          <VAvatar color="info" variant="tonal" rounded="lg" size="44">
            <VIcon icon="tabler-calendar-time" size="24" />
          </VAvatar>
        </div>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.7rem !important;
  line-height: 1.2;
}
.kpi-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06) !important;
}
</style>
