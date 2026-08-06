<script setup>
// Componente: KPIs de abastecimiento del reporte de productos
import { computed } from 'vue';

const props = defineProps({
  quadrant4: {
    type: Object,
    default: () => ({ out_of_stock: 0, critical_stock: 0, avg_inventory_days: 0 }),
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
</script>

<template>
  <VRow class="mb-4">
    <!-- Out of Stock -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 80px;">
          <VProgressCircular indeterminate color="error" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center gap-4">
          <VAvatar color="error" variant="tonal" rounded="lg" size="48">
            <VIcon icon="tabler-package-off" size="26" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">{{ quadrant4.out_of_stock ?? 0 }}</div>
            <div class="text-caption text-medium-emphasis">Out of Stock (SKUs)</div>
          </div>
        </div>
      </VCard>
    </VCol>

    <!-- Suministro Crítico -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 80px;">
          <VProgressCircular indeterminate color="warning" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center gap-4">
          <VAvatar color="warning" variant="tonal" rounded="lg" size="48">
            <VIcon icon="tabler-hourglass-high" size="26" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">{{ quadrant4.critical_stock ?? 0 }}</div>
            <div class="text-caption text-medium-emphasis">Suministro Crítico (&lt;7d)</div>
          </div>
        </div>
      </VCard>
    </VCol>

    <!-- Eficiencia Pareto -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 80px;">
          <VProgressCircular indeterminate color="success" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center gap-4">
          <VAvatar color="success" variant="tonal" rounded="lg" size="48">
            <VIcon icon="tabler-chart-pie" size="26" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">{{ paretoPercent }}%</div>
            <div class="text-caption text-medium-emphasis">Eficiencia Pareto (Utilidad)</div>
          </div>
        </div>
      </VCard>
    </VCol>

    <!-- Días Prom. de Inventario -->
    <VCol cols="12" sm="6" md="3">
      <VCard border class="rounded-lg overflow-hidden shadow-sm">
        <div v-if="loading" class="pa-4 d-flex align-center justify-center" style="height: 80px;">
          <VProgressCircular indeterminate color="info" size="24" width="2" />
        </div>
        <div v-else class="pa-4 d-flex align-center gap-4">
          <VAvatar color="info" variant="tonal" rounded="lg" size="48">
            <VIcon icon="tabler-calendar-time" size="26" />
          </VAvatar>
          <div>
            <div class="text-h5 font-weight-bold">{{ avgDays }}</div>
            <div class="text-caption text-medium-emphasis">Días Prom. de Inventario</div>
          </div>
        </div>
      </VCard>
    </VCol>
  </VRow>
</template>
