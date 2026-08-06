<script setup>
import { computed } from 'vue';

const props = defineProps({
  kpis: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0);
};

const cards = computed(() => {
  if (!props.kpis) return [];
  return [
    {
      title: 'Tasa de Retención (CRR)',
      value: `${(props.kpis.crr || 0).toFixed(1)}%`,
      icon: 'tabler-user-check',
      color: 'primary',
      desc: 'Fidelidad del periodo',
    },
    {
      title: 'Tasa de Recompra',
      value: `${(props.kpis.repurchase_rate || 0).toFixed(1)}%`,
      icon: 'tabler-repeat',
      color: 'success',
      desc: 'Clientes recurrentes',
    },
    {
      title: 'Tasa de Abandono (Churn)',
      value: `${(props.kpis.churn_rate || 0).toFixed(1)}%`,
      icon: 'tabler-user-minus',
      color: 'error',
      desc: 'Inactivos > 90 días',
    },
    {
      title: 'LTV Promedio',
      value: formatCurrency(props.kpis.avg_ltv),
      icon: 'tabler-coin',
      color: 'warning',
      desc: 'Valor de vida promedio del cliente',
    },
  ];
});
</script>

<template>
  <VRow class="mb-6" dense>
    <template v-if="loading && !kpis">
      <VCol v-for="i in 4" :key="i" cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg elevation-1 h-100">
          <VCardText class="pa-4">
            <VSkeletonLoader type="list-item-avatar-two-line" />
          </VCardText>
        </VCard>
      </VCol>
    </template>

    <template v-else-if="kpis">
      <VCol v-for="(kpi, idx) in cards" :key="idx" cols="12" sm="6" md="3">
        <VCard variant="outlined" class="rounded-lg elevation-1 h-100 kpi-hover-card">
          <VCardText class="pa-4 d-flex align-center">
            <VAvatar :color="kpi.color" variant="tonal" size="48" rounded="lg" class="me-4">
              <VIcon :icon="kpi.icon" size="24" />
            </VAvatar>
            <div>
              <div class="text-caption text-medium-emphasis font-weight-medium text-truncate">
                {{ kpi.title }}
              </div>
              <div class="text-h5 font-weight-black my-1">
                {{ kpi.value }}
              </div>
              <div class="text-caption text-disabled text-truncate">
                {{ kpi.desc }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </template>
  </VRow>
</template>

<style scoped>
.kpi-hover-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-hover-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08) !important;
}
</style>
