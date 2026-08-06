<script setup>
const props = defineProps({
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
  if (!percentage) return {};
  const opacity = percentage / 100;
  return {
    backgroundColor: `rgba(226, 0, 116, ${opacity})`,
    color: percentage > 50 ? '#ffffff' : 'inherit',
    fontWeight: '700',
  };
};
</script>

<template>
  <VCard variant="outlined" class="rounded-lg elevation-1 overflow-hidden">
    <VCardItem class="py-3 border-b">
      <VCardTitle class="d-flex align-center text-subtitle-2 font-weight-bold text-uppercase">
        <VIcon icon="tabler-table" class="me-2 text-primary" size="20" />
        Análisis de Cohortes (Retención Mensual %)
      </VCardTitle>
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
              <th class="text-center text-uppercase font-weight-bold">N° Clientes</th>
              <th v-for="i in 12" :key="i" class="text-center text-uppercase font-weight-bold">
                Mes {{ i - 1 }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cohort in cohorts" :key="cohort.month">
              <td class="font-weight-bold text-primary">{{ cohort.month }}</td>
              <td class="text-center font-weight-medium">{{ formatNumber(cohort.initial) }}</td>
              <td
                v-for="i in 12"
                :key="i"
                class="text-center border-sm"
                :style="getCohortStyle(cohort.data[i - 1]?.percentage)"
              >
                {{ cohort.data[i - 1] ? cohort.data[i - 1].percentage + '%' : '-' }}
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
