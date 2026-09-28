<script setup>
defineProps({
  cohorts: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const formatNumber = (value) => {
  return new Intl.NumberFormat('en-US').format(value || 0);
};

const getCohortStyle = (percentage) => {
  if (percentage === undefined || percentage === null) return {};
  const opacity = Math.min(1, Math.max(0.08, percentage / 100));
  return {
    backgroundColor: `rgba(226, 0, 116, ${opacity})`,
    color: percentage > 45 ? '#ffffff' : 'inherit',
    fontWeight: '600',
  };
};
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 overflow-hidden">
    <VCardItem class="py-3 border-b">
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
          <VIcon icon="tabler-table" class="me-2 text-primary" size="20" />
          Análisis de Cohortes (Retención Mensual %)
        </VCardTitle>

        <!-- Leyenda térmica -->
        <div class="d-flex align-center gap-2 text-caption">
          <span class="text-disabled">Retención:</span>
          <span class="d-inline-flex align-center gap-1">
            <span class="rounded" style="width: 12px; height: 12px; background: rgba(226, 0, 116, 0.15);" />
            <span class="text-disabled">&lt;20%</span>
          </span>
          <span class="d-inline-flex align-center gap-1">
            <span class="rounded" style="width: 12px; height: 12px; background: rgba(226, 0, 116, 0.5);" />
            <span class="text-disabled">20-50%</span>
          </span>
          <span class="d-inline-flex align-center gap-1">
            <span class="rounded" style="width: 12px; height: 12px; background: rgba(226, 0, 116, 0.95);" />
            <span class="text-disabled">&gt;50%</span>
          </span>
        </div>
      </div>
    </VCardItem>

    <VCardText v-if="loading && cohorts.length === 0" class="pa-4">
      <VSkeletonLoader type="table-heading, table-tbody" />
    </VCardText>

    <template v-else-if="cohorts.length > 0">
      <div class="overflow-x-auto">
        <VTable density="compact" class="text-caption">
          <thead>
            <tr>
              <th class="text-uppercase font-weight-bold">Cohorte (Mes)</th>
              <th class="text-center text-uppercase font-weight-bold">Clientes Iniciales</th>
              <th v-for="i in 12" :key="i" class="text-center text-uppercase font-weight-bold">
                Mes {{ i - 1 }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cohort in cohorts" :key="cohort.month">
              <td class="font-weight-bold text-primary">{{ cohort.month }}</td>
              <td class="text-center font-weight-bold">{{ formatNumber(cohort.initial) }}</td>
              <td
                v-for="i in 12"
                :key="i"
                class="text-center border-sm"
                :style="getCohortStyle(cohort.data[i - 1]?.percentage)"
              >
                {{ cohort.data[i - 1] ? `${cohort.data[i - 1].percentage}%` : '-' }}
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </template>

    <VCardText v-else class="pa-6">
      <VEmptyState
        icon="tabler-table-off"
        title="Sin cohortes generadas"
        text="No hay suficientes transacciones históricas para construir la matriz de cohortes."
      />
    </VCardText>
  </VCard>
</template>
